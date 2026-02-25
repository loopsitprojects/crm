<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Invoice;

use App\Models\Deal;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : now()->startOfMonth();
        $endDate = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : now()->endOfMonth();
        $type = $request->input('type', 'all');

        // Customer Growth
        $customerCount = Customer::whereBetween('created_at', [$startDate, $endDate])->count();
        $totalCustomers = Customer::count();

        // Estimates
        $estimateQuery = Estimate::whereBetween('created_at', [$startDate, $endDate]);
        $estimateCount = (clone $estimateQuery)->count();
        $estimateTotal = (clone $estimateQuery)->sum('total_amount');

        // Invoices & Revenue
        $invoiceQuery = Invoice::whereBetween('created_at', [$startDate, $endDate])->where('is_proforma', false);
        $invoiceCount = (clone $invoiceQuery)->count();
        $revenue = (clone $invoiceQuery)->where('status', 'paid')->sum('total_amount');
        $pendingPayments = (clone $invoiceQuery)->where('status', '!=', 'paid')->sum('total_amount');

        // Deals & Pipeline
        $dealQuery = Deal::whereBetween('created_at', [$startDate, $endDate]);
        $dealCount = (clone $dealQuery)->count();
        $pipelineValue = (clone $dealQuery)->whereNotIn('stage', ['Rejected', 'Approved'])->sum('amount');
        $closedDealsValue = (clone $dealQuery)->where('stage', 'Approved')->sum('amount');

        // Conversions
        $conversionsCount = Invoice::whereHas('estimate', function ($q) use ($startDate, $endDate) {
            $q->whereBetween('created_at', [$startDate, $endDate]);
        })->count();

        $conversionRate = $estimateCount > 0 ? round(($conversionsCount / $estimateCount) * 100, 1) : 0;

        // Recent Activity
        $recentInvoices = Invoice::with('customer')->where('is_proforma', false)->latest()->take(5)->get();
        $recentDeals = Deal::with('customer')->latest()->take(5)->get();

        return view('dashboard', compact(
            'customerCount',
            'totalCustomers',
            'estimateCount',
            'estimateTotal',
            'invoiceCount',
            'revenue',
            'pendingPayments',
            'dealCount',
            'pipelineValue',
            'closedDealsValue',
            'conversionRate',
            'startDate',
            'endDate',
            'type',
            'recentInvoices',
            'recentDeals'
        ));
    }
}
