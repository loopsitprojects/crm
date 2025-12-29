<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Deal;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Setting;
use Illuminate\Support\Str;

class DealController extends Controller
{
    public function index()
    {
        $stages = [
            'Planned to Meet',
            'Introductory meeting',
            'Brief Stage',
            'Working on pitch',
            'Pitched',
            'Objection handling',
            'Finalizing terms',
            'Rejected',
            'Approved'
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

        $allDeals = Deal::with(['customer', 'owner'])->orderBy('updated_at', 'desc')->get();
        $deals = $allDeals->groupBy('stage');
        $customers = Customer::all();
        $users = \App\Models\User::all();
        $currencies = \App\Models\SystemCurrency::all();

        // Calculate metrics
        $openDeals = $allDeals->whereNotIn('stage', ['Rejected', 'Approved']);

        // Weighted Deal Amount: sum of (amount × probability) for open deals
        $weightedDealAmount = $openDeals->sum(function ($deal) use ($stageProbabilities) {
            return $deal->amount * ($stageProbabilities[$deal->stage] ?? 0);
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

        return view('deals.index', compact(
            'stages',
            'deals',
            'customers',
            'users',
            'currencies',
            'weightedDealAmount',
            'approvedDealAmount',
            'newDealAmount',
            'averageDealAge'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|max:3',
            'pipeline' => 'nullable|string|max:255',
            'stage' => 'required|string|max:255',
            'user_id' => 'nullable|exists:users,id', // Deal Owner
            'type' => 'nullable|string|max:255', // New/Existing Business
            'priority' => 'nullable|string|max:255', // Low, Medium, High
            'close_date' => 'nullable|date',
            'customer_id' => 'nullable|exists:customers,id',
            'customer_name' => 'nullable|required_without:customer_id|string',
            'customer_email' => 'nullable|email',
            'customer_phone' => 'nullable|string',
        ]);

        Deal::create($validated);

        return back()->with('success', 'Deal created successfully.');
    }

    public function updateStage(Request $request, Deal $deal)
    {
        $request->validate([
            'stage' => 'required|string'
        ]);

        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $deal->update(['stage' => $request->stage]);

            if ($request->stage === 'Approved') {
                $this->createEstimateFromDeal($deal);
                \Illuminate\Support\Facades\DB::commit();
                return response()->json(['message' => 'Deal approved! Estimate draft created.', 'redirect' => route('estimates.index')]);
            }

            \Illuminate\Support\Facades\DB::commit();
            return response()->json(['message' => 'Stage updated successfully.']);
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
                'name' => $deal->customer_name ?? 'Unknown Client',
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
