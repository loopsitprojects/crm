<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Deal;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Setting;
use App\Models\Invoice;
use Illuminate\Support\Str;
use App\Traits\LogsActivity;

class DealController extends Controller
{
    use LogsActivity;

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

        // Group all deals by stage for display
        $allDeals = Deal::with(['customer', 'owner', 'teamMembers', 'estimates'])->orderBy('updated_at', 'desc')->get();


        // Group all deals by stage for counts
        $dealsByStage = $allDeals->groupBy('stage');

        $customers = Customer::all();
        $users = \App\Models\User::all();
        $usersByDepartment = $users->groupBy('department');
        $currencies = \App\Models\SystemCurrency::all();
        $seniorManagers = \App\Models\User::whereIn('role', ['HOD', 'Management'])->get();

        // Calculate metrics
        $openDeals = $allDeals->whereNotIn('stage', ['Rejected', 'Closed Won']);

        // Weighted Deal Amount: sum of (amount × probability) for open deals
        $weightedDealAmount = $openDeals->sum(function ($deal) use ($stageProbabilities) {
            $probability = $stageProbabilities[$deal->stage] ?? 0;
            return $deal->revenue * $probability;
        });

        // Weighted Contribution Amount
        $weightedContributionAmount = $openDeals->sum(function ($deal) use ($stageProbabilities) {
            $probability = $stageProbabilities[$deal->stage] ?? 0;
            return ($deal->contribution ?? 0) * $probability;
        });

        // Approved Deal Revenue: sum of revenue for approved deals
        $approvedDealRevenue = $allDeals->where('stage', 'Closed Won')->sum('revenue');
        $approvedDealContribution = $allDeals->where('stage', 'Closed Won')->sum('contribution');

        // Total Project Contribution
        $totalProjectContribution = $allDeals->sum('contribution');

        // New Deal Revenue: sum of revenue for deals created in last 30 days
        $thirtyDaysAgo = now()->subDays(30);
        $newDeals = $allDeals->where('created_at', '>=', $thirtyDaysAgo);
        $newDealRevenue = $newDeals->sum('revenue');
        $newDealContribution = $newDeals->sum('contribution');

        // Average Deal Age: average days since creation for open deals
        $averageDealAge = $openDeals->count() > 0
            ? round($openDeals->avg(function ($deal) {
                return now()->diffInDays($deal->created_at);
            }))
            : 0;

        // Invoiced Amount: Sum of total_amount from invoices linked to deals
        $invoicedAmount = Invoice::whereHas('estimate', function ($q) {
            $q->whereNotNull('deal_id');
        })->where('is_proforma', false)->sum('total_amount');

        // Payment Collected: Sum of total_amount from paid invoices linked to deals
        $paymentCollected = Invoice::whereHas('estimate', function ($q) {
            $q->whereNotNull('deal_id');
        })->where('status', 'paid')->where('is_proforma', false)->sum('total_amount');

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
            'totalProjectContribution',
            'newDealRevenue',
            'newDealContribution',
            'averageDealAge',
            'invoicedAmount',
            'paymentCollected',
            'usersByDepartment',
            'seniorManagers'
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
            'close_date' => 'nullable|date',
            'customer_id' => 'nullable|string',
            'customer_name' => 'nullable|string',
            'customer_email' => 'nullable|email',
            'customer_phone' => 'nullable|string',
            'rejection_reason' => 'nullable|string',
            'senior_manager' => 'nullable|string|max:255',
        ]);

        if ($request->customer_id) {
            if (is_numeric($request->customer_id)) {
                $customer = \App\Models\Customer::find($request->customer_id);
                if ($customer) {
                    $validated['customer_id'] = $customer->id;
                    $validated['customer_name'] = $customer->name;
                } else {
                    $validated['customer_name'] = $request->customer_id;
                    $validated['customer_id'] = null;
                }
            } else {
                $validated['customer_name'] = $request->customer_id;
                $validated['customer_id'] = null;
            }
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

        $deal = Deal::create($validated);

        // Handle department allocations
        if ($request->has('department_allocations')) {
            $deal->department_split = json_encode($request->department_allocations);
            $deal->save();
        }

        $this->logAction("Created deal: {$deal->title}", $deal);

        return back()->with('success', 'Deal created successfully.');
    }

    public function update(Request $request, Deal $deal)
    {
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
            'close_date' => 'nullable|date',
            'customer_id' => 'nullable|string',
            'customer_name' => 'nullable|string',
            'customer_email' => 'nullable|email',
            'customer_phone' => 'nullable|string',
            'rejection_reason' => 'nullable|string',
            'senior_manager' => 'nullable|string|max:255',
        ]);

        if ($request->customer_id) {
            if (is_numeric($request->customer_id)) {
                $customer = \App\Models\Customer::find($request->customer_id);
                if ($customer) {
                    $validated['customer_id'] = $customer->id;
                    $validated['customer_name'] = $customer->name;
                } else {
                    $validated['customer_name'] = $request->customer_id;
                    $validated['customer_id'] = null;
                }
            } else {
                $validated['customer_name'] = $request->customer_id;
                $validated['customer_id'] = null;
            }
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

        $deal->update($validated);

        // Handle department allocations
        if ($request->has('department_allocations')) {
            $deal->department_split = json_encode($request->department_allocations);
            $deal->save();
        } elseif ($request->has('department_allocations_cleared')) {
            // Handle clearing if no allocations are sent but field was modified
            $deal->department_split = null;
            $deal->save();
        }

        if ($request->has('team_members')) {
            $teamData = [];
            foreach ($request->team_members as $userId) {
                $costAllocation = $request->input("cost_allocation.{$userId}", 0);
                $teamData[$userId] = ['cost_allocation' => $costAllocation];
            }
            $deal->teamMembers()->sync($teamData);
        }

        $this->logAction("Updated deal: {$deal->title}", $deal);

        return back()->with('success', 'Deal updated successfully.');
    }

    public function destroy(Deal $deal)
    {
        $title = $deal->title;
        $deal->delete();
        $this->logAction("Deleted deal: {$title}");
        return back()->with('success', 'Deal removed successfully.');
    }


    public function updateStage(Request $request, Deal $deal)
    {
        $validated = $request->validate([
            'stage' => 'required|string',
            'rejection_reason' => 'nullable|string'
        ]);

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

            $deal->update($validated);

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
        if ($deal->estimates()->exists()) {
            return response()->json([
                'message' => 'An estimate already exists for this deal.'
            ], 422);
        }

        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $this->createEstimateFromDeal($deal);
            \Illuminate\Support\Facades\DB::commit();

            return response()->json([
                'message' => 'Estimate draft created successfully.',
                'redirect' => route('estimates.index')
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
            'deal_id' => $deal->id,
            'reference_number' => Estimate::generateReferenceNumber(),
            'date' => now(),
            'status' => 'draft',
            'total_amount' => $deal->revenue,
            'currency' => 'LKR',
            'heading' => 'Estimate for ' . $deal->title,
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
    }

}

