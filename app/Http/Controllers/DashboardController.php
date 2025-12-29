<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Invoice;

class DashboardController extends Controller
{
    public function index()
    {
        $customerCount = Customer::count();
        $estimateCount = Estimate::count();
        $invoiceCount = Invoice::count();

        return view('dashboard', compact('customerCount', 'estimateCount', 'invoiceCount'));
    }
}
