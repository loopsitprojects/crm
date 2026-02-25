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
            'Approved',
            'Rejected'
        ];

        // Stage probability weights for weighted deal calculation
        $stageProbabilities = [
            'Planned to Meet' => 0.10,
            'Introductory meeting' => 0.20,
            'Brief Stage' => 0.30,
            'Working on pitch' => 0.40,
            'Pitched' => 0.50,
            'Objection handling' => 0.60,
            'Finalizing terms' => 0.80,
            'Rejected' => 0.00,
            'Approved' => 1.00
        ];

        // Group all deals by stage for display
        $allDeals = Deal::with(['customer', 'owner', 'teamMembers'])->orderBy('updated_at', 'desc')->get();


        // Group all deals by stage for counts
        $dealsByStage = $allDeals->groupBy('stage');

        $customers = Customer::all();
        $users = \App\Models\User::all();
        $usersByDepartment = $users->groupBy('department');
        $currencies = \App\Models\SystemCurrency::all();

        // Calculate metrics
        $openDeals = $allDeals->whereNotIn('stage', ['Rejected', 'Approved']);

        // Weighted Deal Amount: sum of (amount × probability) for open deals
        $weightedDealAmount = $openDeals->sum(function ($deal) use ($stageProbabilities) {
            $probability = $deal->winning_percentage !== null
                ? $deal->winning_percentage / 100
                : ($stageProbabilities[$deal->stage] ?? 0);
            return $deal->amount * $probability;
        });

        // Approved Deal Amount: sum of amounts for approved deals
        $approvedDealAmount = $allDeals->where('stage', 'Approved')->sum('amount');

        // New Deal Amount: sum of amounts for deals created in last 30 days
        $thirtyDaysAgo = now()->subDays(30);
        $newDealAmount = $allDeals->where('created_at', '>=', $thirtyDaysAgo)->sum('amount');

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
            'approvedDealAmount',
            'newDealAmount',
            'averageDealAge',
            'invoicedAmount',
            'paymentCollected',
            'usersByDepartment'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'project_cost' => 'nullable|numeric|min:0',
            'currency' => 'required|string|max:3',
            'pipeline' => 'nullable|string|max:255',
            'stage' => 'required|string|max:255',
            'user_id' => 'nullable|exists:users,id', // Deal Owner
            'type' => 'nullable|string|max:255', // New/Existing Business
            'priority' => 'nullable|string|max:255', // Low, Medium, High
            'winning_percentage' => 'required|integer|min:0|max:100',
            'close_date' => 'nullable|date',
            'customer_id' => 'nullable|string',
            'customer_name' => 'nullable|string',
            'customer_email' => 'nullable|email',
            'customer_phone' => 'nullable|string',
            'rejection_reason' => 'nullable|string',
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
            'amount' => 'required|numeric|min:0',
            'project_cost' => 'nullable|numeric|min:0',
            'currency' => 'required|string|max:3',
            'pipeline' => 'nullable|string|max:255',
            'stage' => 'required|string|max:255',
            'user_id' => 'nullable|exists:users,id',
            'type' => 'nullable|string|max:255',
            'priority' => 'nullable|string|max:255',
            'winning_percentage' => 'required|integer|min:0|max:100',
            'close_date' => 'nullable|date',
            'customer_id' => 'nullable|string',
            'customer_name' => 'nullable|string',
            'customer_email' => 'nullable|email',
            'customer_phone' => 'nullable|string',
            'rejection_reason' => 'nullable|string',
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

        // Auto-generate Job Number if stage is 'Pitched' or subsequent stages and job_number is not set
        $jobIdStages = ['Pitched', 'Objection handling', 'Finalizing terms', 'Approved'];
        if (in_array($validated['stage'], $jobIdStages) && is_null($deal->job_number)) {
            $year = date('y');
            $idPad = str_pad($deal->id, 4, '0', STR_PAD_LEFT);
            $validated['job_number'] = "JOB-{$year}-{$idPad}";
        }

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
            // Auto-generate Job Number if stage is 'Pitched' or subsequent stages
            $jobIdStages = ['Pitched', 'Objection handling', 'Finalizing terms', 'Approved'];
            if (in_array($validated['stage'], $jobIdStages) && is_null($deal->job_number)) {
                $year = date('y');
                $idPad = str_pad($deal->id, 4, '0', STR_PAD_LEFT);
                $validated['job_number'] = "JOB-{$year}-{$idPad}";
            }

            $deal->update($validated);

            if ($request->has('team_members')) {
                $deal->teamMembers()->sync($request->team_members);
            }

            if ($request->stage === 'Approved') {
                $this->createEstimateFromDeal($deal);
                \Illuminate\Support\Facades\DB::commit();
                return response()->json(['message' => 'Deal approved! Estimate draft created.', 'redirect' => route('estimates.index')]);
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

        // Create Draft Estimate
        $estimate = Estimate::create([
            'customer_id' => $customerId,
            'deal_id' => $deal->id,
            'reference_number' => 'EST-' . strtoupper(Str::random(6)),
            'date' => now(),
            'status' => 'draft',
            'total_amount' => $deal->amount,
            'currency' => 'LKR',
            'heading' => 'Estimate for ' . $deal->title,
            'terms' => Setting::get('standard_terms', 'Standard business terms apply.')
        ]);

        // Add Deal Item
        $estimate->items()->create([
            'description' => $deal->title,
            'quantity' => 1,
            'unit_price' => $deal->amount,
            'amount' => $deal->amount,
            'vat_amount' => 0,
            'total_with_vat' => $deal->amount,
            'sscl_amount' => 0
        ]);
    }

}

