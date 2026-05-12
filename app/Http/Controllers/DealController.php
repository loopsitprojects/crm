<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Deal;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Setting;
use App\Models\Invoice;
use Illuminate\Support\Str;
use App\Notifications\DealStageChangedNotification;
use App\Notifications\EstimateCreatedNotification;
use App\Traits\LogsActivity;
use App\Traits\NotifiesStakeholders;

class DealController extends Controller
{
    use LogsActivity, NotifiesStakeholders;

    public function index(Request $request)
    {
        $stages = [
            'Planned to Meet',
            'Introductory meeting',
            'Brief Stage',
            'Working on pitch',
            'Pitched',
            'Objection handling',
            'Finalizing terms',
            'Closed Won',
            'Rejected'
        ];

        // Stage probability weights for weighted deal calculation
        $stageProbabilities = [
            'Planned to Meet' => 0.10,
            'Introductory meeting' => 0.10,
            'Brief Stage' => 0.20,
            'Working on pitch' => 0.40,
            'Pitched' => 0.50,
            'Objection handling' => 0.80,
            'Finalizing terms' => 0.90,
            'Rejected' => 0.00,
            'Closed Won' => 1.00
        ];

        $user = auth()->user();
        $userRole = $user->role;
        $userDept = $user->department;
        $currentSupervisor = $user->supervisor ? $user->supervisor->name : null;

        // Group all deals by stage for display
        $query = Deal::with(['customer', 'owner', 'teamMembers', 'estimates.invoices', 'estimates.items'])->orderBy('updated_at', 'desc');

        // RBAC Filtering
        if (!in_array($userRole, ['Super Admin', 'Management'])) {
            $query->where(function ($q) use ($user, $userDept) {
                // Own deals
                $q->where('user_id', $user->id)
                  // Team member deals
                  ->orWhereHas('teamMembers', function ($tm) use ($user) {
                      $tm->where('users.id', $user->id);
                  });
                
                // Department split check (all users in department)
                if ($userDept) {
                    $q->orWhereJsonContains('department_split', [['department' => $userDept]]);
                }
                
                // HOD specific: subordinates
                if ($user->role === 'HOD') {
                    // Deals owned by subordinates
                    $subordinateIds = \App\Models\User::where('supervisor_id', $user->id)->pluck('id');
                    if ($subordinateIds->isNotEmpty()) {
                        $q->orWhereIn('user_id', $subordinateIds);
                    }
                }
            });
        }

        $allDeals = $query->get();

        // Adjust revenue/contribution metrics to exclude VAT and SSCL, and deduct third party costs
        $allDeals->each(function($deal) {
            $estimate = $deal->estimates->first();
            if ($estimate) {
                // Calculate total excluding VAT and SSCL: sum of amount from items
                $preTaxTotal = $estimate->items->sum(function($item) {
                    return (float)$item->amount;
                });
                
                // Calculate third party costs
                $thirdPartyTotal = $estimate->thirdPartyCosts->sum('cost');
                
                if ($preTaxTotal > 0) {
                    $deal->revenue = $preTaxTotal;
                    $deal->contribution = $preTaxTotal - $thirdPartyTotal;
                }
            }
        });

        // 3. Apply restricted visibility and split logic
        $activeDeptForMetrics = $request->input('department') ?: (in_array($userRole, ['HOD', 'Manager']) ? $userDept : null);

        $allDeals->each(function($deal) use ($user, $activeDeptForMetrics) {
            $deptRevenue = 0;
            $deptContribution = 0;
            $deptInvoiced = 0;
            $deptPaid = 0;

            if ($activeDeptForMetrics) {
                $splits = is_string($deal->department_split) ? json_decode($deal->department_split, true) : $deal->department_split;
                if (is_array($splits) && !empty($splits)) {
                    foreach ($splits as $split) {
                        $splitDept = trim(strtolower($split['department'] ?? ''));
                        $targetDept = trim(strtolower($activeDeptForMetrics));
                        
                        if ($splitDept === $targetDept) {
                            $revPercent = (float)($split['revenue_percentage'] ?? 0);
                            $conPercent = (float)($split['contribution_percentage'] ?? 0);
                            
                            if ($revPercent > 0) {
                                $deptRevenue += ($deal->revenue * ($revPercent / 100));
                            } else {
                                $deptRevenue += (float)($split['revenue_amount'] ?? 0);
                            }
                            
                            if ($conPercent > 0) {
                                $deptContribution += ($deal->contribution * ($conPercent / 100));
                            } else {
                                $deptContribution += (float)($split['contribution_amount'] ?? 0);
                            }

                            // Factor invoiced/paid amounts by the revenue share ratio
                            $totalInvoiced = 0;
                            $totalPaid = 0;
                            foreach ($deal->estimates as $estimate) {
                                foreach ($estimate->invoices as $invoice) {
                                    if (!$invoice->is_proforma) {
                                        $totalInvoiced += $invoice->total_amount;
                                        if ($invoice->status === 'paid') {
                                            $totalPaid += $invoice->total_amount;
                                        }
                                    }
                                }
                            }
                            
                            if ($deal->revenue > 0) {
                                $ratio = $deptRevenue / $deal->revenue;
                                $deptInvoiced += ($totalInvoiced * $ratio);
                                $deptPaid += ($totalPaid * $ratio);
                            }
                        }
                    }
                }
            }

            // Apply visibility: Owners and their HODs/Supervisors always see 100%. 
            // Others (including Admin viewing a filtered dept) see the share.
            $isOwnerCircle = ($deal->user_id === $user->id) || 
                             ($deal->owner && $deal->owner->supervisor_id === $user->id) ||
                             ($user->role === 'HOD' && $deal->owner && $deal->owner->department === $user->department);

            if (!$isOwnerCircle) {
                // For those outside the owner's immediate team, show the department's share if a filter is active
                if ($activeDeptForMetrics || !in_array($user->role, ['Super Admin', 'Management'])) {
                    $deal->dept_share_revenue = $deptRevenue;
                    $deal->dept_share_contribution = $deptContribution;
                    $deal->dept_share_invoiced = $deptInvoiced;
                    $deal->dept_share_paid = $deptPaid;
                }
            }
        });

        // Group all deals by stage for counts
        $dealsByStage = $allDeals->groupBy('stage');

        $customers = Customer::all();
        $users = \App\Models\User::all();
        $usersByDepartment = $users->groupBy('department');
        $currencies = \App\Models\SystemCurrency::all();
        $seniorManagers = \App\Models\User::whereIn('role', ['HOD', 'Management'])
            ->where('id', '!=', $user->id)
            ->get();

        // Helpers to get contribution/revenue/invoiced/paid for metrics (respects departmental share for HODs)
        $getMetricRevenue = function($deal) {
            return isset($deal->dept_share_revenue) ? $deal->dept_share_revenue : ($deal->revenue ?? 0);
        };
        $getMetricContribution = function($deal) {
            return isset($deal->dept_share_contribution) ? $deal->dept_share_contribution : ($deal->contribution ?? 0);
        };
        $getMetricInvoiced = function($deal) {
            if (isset($deal->dept_share_invoiced)) {
                return $deal->dept_share_invoiced;
            }
            // If not HOD, calculate total invoiced for this deal
            $total = 0;
            foreach ($deal->estimates as $estimate) {
                foreach ($estimate->invoices as $invoice) {
                    if (!$invoice->is_proforma) $total += $invoice->total_amount;
                }
            }
            return $total;
        };
        $getMetricPaid = function($deal) {
            if (isset($deal->dept_share_paid)) {
                return $deal->dept_share_paid;
            }
            // If not HOD, calculate total paid for this deal
            $total = 0;
            foreach ($deal->estimates as $estimate) {
                foreach ($estimate->invoices as $invoice) {
                    if (!$invoice->is_proforma && $invoice->status === 'paid') $total += $invoice->total_amount;
                }
            }
            return $total;
        };

        // Calculate metrics
        $openDeals = $allDeals->whereNotIn('stage', ['Rejected', 'Closed Won']);

        // Weighted Deal Amount: sum of (amount × probability) for open deals
        $weightedDealAmount = $openDeals->sum(function ($deal) use ($stageProbabilities, $getMetricRevenue) {
            $probability = $stageProbabilities[$deal->stage] ?? 0;
            return $getMetricRevenue($deal) * $probability;
        });

        // Weighted Contribution Amount
        $weightedContributionAmount = $openDeals->sum(function ($deal) use ($stageProbabilities, $getMetricContribution) {
            $probability = $stageProbabilities[$deal->stage] ?? 0;
            return $getMetricContribution($deal) * $probability;
        });

        // Approved Deal Revenue: sum of revenue for approved deals
        $approvedDealRevenue = $allDeals->where('stage', 'Closed Won')->sum($getMetricRevenue);
        $approvedDealContribution = $allDeals->where('stage', 'Closed Won')->sum($getMetricContribution);

        // Total Project Revenue & Contribution
        $totalProjectRevenue = $allDeals->sum($getMetricRevenue);
        $totalProjectContribution = $allDeals->sum($getMetricContribution);

        // New Deal Revenue: sum of revenue for deals created in last 30 days
        $thirtyDaysAgo = now()->subDays(30);
        $newDeals = $allDeals->where('created_at', '>=', $thirtyDaysAgo);
        $newDealRevenue = $newDeals->sum($getMetricRevenue);
        $newDealContribution = $newDeals->sum($getMetricContribution);

        // Average Deal Age: average days since creation for open deals
        $averageDealAge = $openDeals->count() > 0
            ? round($openDeals->avg(function ($deal) {
                return now()->diffInDays($deal->created_at);
            }))
            : 0;

        // Invoiced Amount & Payment Collected
        $invoicedAmount = $allDeals->sum($getMetricInvoiced);
        $paymentCollected = $allDeals->sum($getMetricPaid);

        return view('deals.index', compact(
            'stages',
            'dealsByStage',
            'customers',
            'users',
            'currencies',
            'weightedDealAmount',
            'weightedContributionAmount',
            'approvedDealRevenue',
            'approvedDealContribution',
            'totalProjectRevenue',
            'totalProjectContribution',
            'newDealRevenue',
            'newDealContribution',
            'averageDealAge',
            'invoicedAmount',
            'paymentCollected',
            'usersByDepartment',
            'seniorManagers',
            'currentSupervisor',
            'allDeals'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'revenue' => 'required|numeric|min:0',
            'contribution' => 'required|numeric|min:0',
            'project_cost' => 'nullable|numeric|min:0',
            'currency' => 'required|string|max:3',
            'pipeline' => 'nullable|string|max:255',
            'stage' => 'required|string|max:255',
            'user_id' => 'nullable|exists:users,id', // Deal Owner
            'type' => 'nullable|string|max:255', // New/Existing Business
            'priority' => 'nullable|string|max:255', // Low, Medium, High
            'close_date' => 'required|date',
            'customer_id' => 'required|exists:customers,id',
            'customer_name' => 'nullable|string',
            'customer_phone' => 'nullable|string',
            'rejection_reason' => 'nullable|string',
            'senior_manager' => 'nullable|string|max:255',
        ]);

        $customer = \App\Models\Customer::find($validated['customer_id']);
        $validated['customer_name'] = $customer->name;

        $stageProbs = [
            'Planned to Meet' => 10,
            'Introductory meeting' => 10,
            'Brief Stage' => 20,
            'Working on pitch' => 40,
            'Pitched' => 50,
            'Objection handling' => 80,
            'Finalizing terms' => 90,
            'Rejected' => 0,
            'Closed Won' => 100
        ];
        $validated['winning_percentage'] = $stageProbs[$validated['stage']] ?? 0;
        $validated['user_id'] = auth()->id();

        $deal = Deal::create($validated);

        // Handle department allocations
        if ($request->has('department_allocations')) {
            $allocations = collect($request->department_allocations)->map(function($alloc) {
                if (isset($alloc['department'])) {
                    $alloc['department'] = trim($alloc['department']);
                }
                return $alloc;
            })->toArray();
            $deal->department_split = json_encode($allocations);
            $deal->save();

            // Notify users in these departments
            foreach ($allocations as $alloc) {
                $dept = $alloc['department'] ?? null;
                if ($dept) {
                    $deptUsers = \App\Models\User::where('department', $dept)->get();
                    foreach ($deptUsers as $deptUser) {
                        if ($deptUser->id !== auth()->id()) {
                            $deptUser->notify(new \App\Notifications\DealAssignedNotification($deal, 'Project Split (Department: ' . $dept . ')', auth()->user()));
                        }
                    }
                }
            }
        }

        if ($request->has('team_members')) {
            $deal->teamMembers()->sync($request->team_members);
            
            $usersToNotify = \App\Models\User::whereIn('id', $request->team_members)->get();
            foreach ($usersToNotify as $userToNotify) {
                if ($userToNotify->id !== auth()->id()) {
                    $userToNotify->notify(new \App\Notifications\DealAssignedNotification($deal, 'Team Member', auth()->user()));
                }
            }
        }

        $this->logAction("Created deal: {$deal->title}", $deal);

        if ($deal->senior_manager) {
            $seniorManagerUser = \App\Models\User::where('name', $deal->senior_manager)->first();
            if ($seniorManagerUser && $seniorManagerUser->id !== auth()->id()) {
                $seniorManagerUser->notify(new \App\Notifications\DealAssignedNotification($deal, 'Deal Owner', auth()->user()));
            }
        }

        return back()->with('success', 'Deal created successfully.');
    }

    public function update(Request $request, Deal $deal)
    {
        if (!$this->checkDealAccess($deal)) {
            abort(403, 'Unauthorized action.');
        }
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'revenue' => 'required|numeric|min:0',
            'contribution' => 'required|numeric|min:0',
            'project_cost' => 'nullable|numeric|min:0',
            'currency' => 'required|string|max:3',
            'pipeline' => 'nullable|string|max:255',
            'stage' => 'required|string|max:255',
            'user_id' => 'nullable|exists:users,id',
            'type' => 'nullable|string|max:255',
            'priority' => 'nullable|string|max:255',
            'close_date' => 'required|date',
            'customer_id' => 'required|exists:customers,id',
            'customer_name' => 'nullable|string',
            'customer_phone' => 'nullable|string',
            'rejection_reason' => 'nullable|string',
            'senior_manager' => 'nullable|string|max:255',
        ]);

        $customer = \App\Models\Customer::find($validated['customer_id']);
        $validated['customer_name'] = $customer->name;

        // Restriction: can't go to 'Finalizing terms' or 'Closed Won' without an estimate
        $postObjectionStages = ['Finalizing terms', 'Closed Won'];
        if (in_array($validated['stage'], $postObjectionStages) && !$deal->estimates()->exists()) {
            return back()->with('error', 'An estimate must be created before moving to ' . $validated['stage'] . '.');
        }

        // Auto-generate Job Number if stage is 'Working on pitch' or subsequent stages and job_number is not set
        $jobIdStages = ['Working on pitch', 'Pitched', 'Objection handling', 'Finalizing terms', 'Closed Won'];
        if (in_array($validated['stage'], $jobIdStages) && is_null($deal->job_number)) {
            $year = date('Y');
            $idPad = str_pad($deal->id, 4, '0', STR_PAD_LEFT);
            $validated['job_number'] = "LOOPS/{$year}/{$idPad}";
        }

        $stageProbs = [
            'Planned to Meet' => 10,
            'Introductory meeting' => 10,
            'Brief Stage' => 20,
            'Working on pitch' => 40,
            'Pitched' => 50,
            'Objection handling' => 80,
            'Finalizing terms' => 90,
            'Rejected' => 0,
            'Closed Won' => 100
        ];
        $validated['winning_percentage'] = $stageProbs[$validated['stage']] ?? 0;

        $oldStage = $deal->stage;
        $oldSeniorManager = $deal->senior_manager;
        $deal->update($validated);

        // Handle department allocations
        $oldDepartments = [];
        if ($deal->department_split) {
            $oldSplits = is_string($deal->department_split) ? json_decode($deal->department_split, true) : $deal->department_split;
            $oldDepartments = collect($oldSplits)->pluck('department')->filter()->toArray();
        }

        if ($request->has('department_allocations')) {
            $allocations = collect($request->department_allocations)->map(function($alloc) {
                if (isset($alloc['department'])) {
                    $alloc['department'] = trim($alloc['department']);
                }
                return $alloc;
            })->toArray();

            $newDepartments = collect($allocations)->pluck('department')->filter()->diff($oldDepartments)->toArray();

            $deal->department_split = json_encode($allocations);
            $deal->save();

            // Notify only newly added departments
            foreach ($newDepartments as $dept) {
                $deptUsers = \App\Models\User::where('department', $dept)->get();
                foreach ($deptUsers as $deptUser) {
                    if ($deptUser->id !== auth()->id()) {
                        $deptUser->notify(new \App\Notifications\DealAssignedNotification($deal, 'Project Split (Department: ' . $dept . ')', auth()->user()));
                    }
                }
            }
        } elseif ($request->has('department_allocations_cleared')) {
            // Handle clearing if no allocations are sent but field was modified
            $deal->department_split = null;
            $deal->save();
        }

        if ($request->has('team_members')) {
            $existingTeamMembers = $deal->teamMembers->pluck('id')->toArray();
            $newTeamMembers = array_diff($request->team_members, $existingTeamMembers);

            $teamData = [];
            foreach ($request->team_members as $userId) {
                $costAllocation = $request->input("cost_allocation.{$userId}", 0);
                $teamData[$userId] = ['cost_allocation' => $costAllocation];
            }
            $deal->teamMembers()->sync($teamData);

            if (!empty($newTeamMembers)) {
                $usersToNotify = \App\Models\User::whereIn('id', $newTeamMembers)->get();
                foreach ($usersToNotify as $userToNotify) {
                    if ($userToNotify->id !== auth()->id()) {
                        $userToNotify->notify(new \App\Notifications\DealAssignedNotification($deal, 'Team Member', auth()->user()));
                    }
                }
            }
        }

        if ($deal->senior_manager && $deal->senior_manager !== $oldSeniorManager) {
            $seniorManagerUser = \App\Models\User::where('name', $deal->senior_manager)->first();
            if ($seniorManagerUser && $seniorManagerUser->id !== auth()->id()) {
                $seniorManagerUser->notify(new \App\Notifications\DealAssignedNotification($deal, 'Deal Owner', auth()->user()));
            }
        }

        $this->logAction("Updated deal: {$deal->title}", $deal);
        
        if ($deal->stage !== $oldStage) {
            $this->notifyStakeholders($deal, new \App\Notifications\DealStageChangedNotification($deal, $oldStage, $deal->stage, auth()->user()));
        } else {
            $this->notifyStakeholders($deal);
        }

        return back()->with('success', 'Deal updated successfully.');
    }

    public function destroy(Deal $deal)
    {
        if (!$this->checkDealAccess($deal)) {
            abort(403, 'Unauthorized action.');
        }
        $title = $deal->title;
        $deal->delete();
        $this->logAction("Deleted deal: {$title}");
        return back()->with('success', 'Deal removed successfully.');
    }


    public function updateStage(Request $request, Deal $deal)
    {
        if (!$this->checkDealAccess($deal)) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }
        $validated = $request->validate([
            'stage' => 'required|string',
            'rejection_reason' => 'nullable|string'
        ]);

        // Restriction: can't go to 'Finalizing terms' or 'Closed Won' without an estimate
        $postObjectionStages = ['Finalizing terms', 'Closed Won'];
        if (in_array($validated['stage'], $postObjectionStages) && !$deal->estimates()->exists()) {
            return response()->json(['message' => 'An estimate must be created before moving to this stage.'], 422);
        }

        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            // Auto-generate Job Number if stage is 'Working on pitch' or subsequent stages
            $jobIdStages = ['Working on pitch', 'Pitched', 'Objection handling', 'Finalizing terms', 'Closed Won'];
            if (in_array($validated['stage'], $jobIdStages) && is_null($deal->job_number)) {
                $year = date('Y');
                $idPad = str_pad($deal->id, 4, '0', STR_PAD_LEFT);
                $validated['job_number'] = "LOOPS/{$year}/{$idPad}";
            }

            $stageProbs = [
                'Planned to Meet' => 10,
                'Introductory meeting' => 10,
                'Brief Stage' => 20,
                'Working on pitch' => 40,
                'Pitched' => 50,
                'Objection handling' => 80,
                'Finalizing terms' => 90,
                'Rejected' => 0,
                'Closed Won' => 100
            ];
            $validated['winning_percentage'] = $stageProbs[$validated['stage']] ?? 0;

            // Restriction: can't move away from 'Closed Won' if an estimate is already ready to invoice or invoiced
            if ($deal->stage === 'Closed Won' && $validated['stage'] !== 'Closed Won') {
                $hasLockedEstimate = $deal->estimates()->whereIn('status', ['ready_to_invoice', 'invoiced'])->exists();
                if ($hasLockedEstimate) {
                    return response()->json(['message' => 'Closed Won deals cannot be moved back once an estimate is ready to invoice.'], 422);
                }
            }

            $oldStage = $deal->stage;
            $deal->update($validated);
            $this->notifyStakeholders($deal, new DealStageChangedNotification($deal, $oldStage, $validated['stage'], auth()->user()));

            // Sync revenue and contribution if moving to 'Closed Won'
            if ($validated['stage'] === 'Closed Won') {
                $estimate = $deal->estimates->first();
                if ($estimate) {
                    $preTaxTotal = $estimate->items->sum('amount');
                    $thirdPartyTotal = $estimate->thirdPartyCosts->sum('cost');
                    
                    $deal->update([
                        'revenue' => $preTaxTotal,
                        'contribution' => $preTaxTotal - $thirdPartyTotal
                    ]);
                }
            }

            if ($request->has('team_members')) {
                $deal->teamMembers()->sync($request->team_members);
            }

            \Illuminate\Support\Facades\DB::commit();
            // Refresh to get any db defaults or changes if needed, mainly for job_number
            return response()->json(['message' => 'Stage updated successfully.', 'job_number' => $deal->job_number]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Deal update failed: ' . $e->getMessage());
            return response()->json(['message' => 'Update failed: ' . $e->getMessage()], 500);
        }
    }

    public function createEstimate(Request $request, Deal $deal)
    {
        if (!$this->checkDealAccess($deal)) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }
        if ($deal->estimates()->exists()) {
            return response()->json([
                'message' => 'An estimate already exists for this deal.'
            ], 422);
        }

        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $estimate = $this->createEstimateFromDeal($deal);
            \Illuminate\Support\Facades\DB::commit();

            $this->notifyStakeholders($deal, new EstimateCreatedNotification($estimate, $deal, auth()->user()));

            return response()->json([
                'message' => 'Estimate draft created successfully.',
                'redirect' => route('estimates.edit', $estimate->id)
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Failed to create estimate from deal: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to create estimate: ' . $e->getMessage()], 500);
        }
    }

    protected function createEstimateFromDeal(Deal $deal)
    {
        $customerId = $deal->customer_id;

        // Create Customer if not exists
        if (!$customerId) {
            $email = $deal->customer_email ?? ('client_' . Str::random(8) . '@example.com');
            $customer = Customer::create([
                'name' => $deal->customer_name ?? 'Unknown Customer',
                'email' => $email,
                'phone' => $deal->customer_phone,
                'address' => 'TBD'
            ]);
            $customerId = $customer->id;
            $deal->update(['customer_id' => $customerId]);
        }

        $estimate = Estimate::create([
            'customer_id' => $customerId,
            'user_id' => $deal->user_id,
            'deal_id' => $deal->id,
            'reference_number' => Estimate::generateReferenceNumber(),
            'date' => now(),
            'status' => 'draft',
            'total_amount' => $deal->revenue,
            'currency' => 'LKR',
            'heading' => $deal->title,
            'senior_manager' => $deal->senior_manager ?? ($deal->owner->name ?? null),
            'terms' => Setting::get('standard_terms', 'Standard business terms apply.')
        ]);

        // Add Deal Item
        $estimate->items()->create([
            'description' => $deal->title,
            'quantity' => 1,
            'unit_price' => $deal->revenue,
            'amount' => $deal->revenue,
            'vat_amount' => 0,
            'total_with_vat' => $deal->revenue,
            'sscl_amount' => 0
        ]);

        return $estimate;
    }
    private function checkDealAccess(Deal $deal)
    {
        return $deal->canEdit();
    }

}

