<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\StandardTerm;
use App\Models\SystemCurrency;

class EstimateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Estimate::with('customer');

        // Search filter (Reference or Customer Name)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'LIKE', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'LIKE', "%{$search}%");
                    });
            });
        }

        // Date Range filter
        if ($request->filled('from_date')) {
            $query->whereDate('date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('date', '<=', $request->to_date);
        }

        $estimates = $query->latest()->get();
        return view('estimates.index', compact('estimates'));
    }

    public function create()
    {
        $customers = Customer::all();
        $standardTerms = \App\Models\StandardTerm::all();
        $currencies = \App\Models\SystemCurrency::all();
        $ssclRate = \App\Models\Setting::get('sscl_rate', 2.5);
        $vatRate = \App\Models\Setting::get('vat_rate', 15);
        return view('estimates.create', compact('customers', 'standardTerms', 'currencies', 'ssclRate', 'vatRate'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'reference_number' => 'required|string|max:255',
            'date' => 'required|date',
            'attention_to' => 'nullable|string',
            'currency' => 'required|string',
            'senior_manager' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        $estimate = Estimate::create([
            'customer_id' => $request->customer_id,
            'reference_number' => $request->reference_number,
            'date' => $request->date,
            'status' => 'draft',
            'total_amount' => 0,
            'attention_to' => $request->attention_to,
            'address_line_1' => $request->address_line_1,
            'address_line_2' => $request->address_line_2,
            'address_line_3' => $request->address_line_3,
            'designation' => $request->designation,
            'currency' => $request->currency,
            'heading' => $request->heading,
            'terms' => is_array($request->terms) ? implode(', ', $request->terms) : $request->terms,
            'special_terms' => $request->special_terms,
            'advance_payment' => $request->advance_payment,
            'advance_percentage' => $request->advance_percentage,
            'senior_manager' => $request->senior_manager,
            'additional_notes' => $request->additional_notes,
            'sscl_applicable' => $request->has('sscl_applicable'),
            'vat_applicable' => $request->has('vat_applicable'),
        ]);

        $grandTotal = 0;

        foreach ($request->items as $item) {
            $quantity = $item['quantity'];
            $unitPrice = $item['unit_price'];
            $amount = $quantity * $unitPrice;

            // Tax Calculation (Backend Fallback/Verification)
            $sscl = 0;
            $vat = 0;

            if ($request->has('sscl_applicable')) {
                $sscl = $amount * (\App\Models\Setting::get('sscl_rate', 2.5) / 100);
            }

            if ($request->has('vat_applicable')) {
                $vat = ($amount + $sscl) * (\App\Models\Setting::get('vat_rate', 15) / 100);
            }

            $totalWithVat = $amount + $vat + $sscl;

            $estimate->items()->create([
                'description' => $item['description'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'amount' => $amount,
                'vat_amount' => $vat,
                'sscl_amount' => $sscl,
                'total_with_vat' => $totalWithVat,
                'item_heading' => $item['item_heading'] ?? null,
                'locations' => $item['locations'] ?? null,
                'days' => $item['days'] ?? null,
            ]);

            $grandTotal += $totalWithVat;
        }

        $estimate->update(['total_amount' => $grandTotal]);

        return redirect()->route('estimates.index')->with('success', 'Estimate created successfully.');
    }

    public function markAsAccepted(Estimate $estimate)
    {
        $estimate->update(['status' => 'accepted']);
        return back()->with('success', 'Estimate marked as accepted.');
    }

    public function markAsRejected(Estimate $estimate)
    {
        $estimate->update(['status' => 'rejected']);
        return back()->with('success', 'Estimate marked as rejected.');
    }

    public function updateStatus(Request $request, Estimate $estimate)
    {
        $request->validate([
            'status' => 'required|in:draft,approved,accepted,rejected,invoiced'
        ]);

        $estimate->update(['status' => $request->status]);

        $message = "Estimate status updated to " . ucfirst($request->status) . ".";
        return back()->with('success', $message);
    }

    public function convertToInvoice(Estimate $estimate)
    {
        if ($estimate->status !== 'accepted') {
            return back()->with('error', 'Only accepted estimates can be invoiced.');
        }

        // Create Invoice
        $invoice = Invoice::create([
            'quotation_id' => $estimate->id,
            'customer_id' => $estimate->customer_id,
            'invoice_number' => 'INV-' . $estimate->reference_number,
            'date' => now(),
            'due_date' => now()->addDays(30),
            'total_amount' => $estimate->total_amount,
            'status' => 'unpaid',
        ]);

        // Copy Items
        foreach ($estimate->items as $item) {
            $invoice->items()->create([
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'amount' => $item->amount,
                'sscl_amount' => $item->sscl_amount,
                'vat_amount' => $item->vat_amount,
                'total_with_vat' => $item->total_with_vat,
            ]);
        }

        $estimate->update(['status' => 'invoiced']);

        return redirect()->route('invoices.index')->with('success', 'Estimate converted to Invoice successfully.');
    }




    /**
     * Display the specified resource.
     */
    public function show(Estimate $estimate)
    {
        $estimate->load(['customer', 'items']);
        return view('estimates.show', compact('estimate'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $estimate = Estimate::with('items')->findOrFail($id);
        $customers = Customer::all();
        $standardTerms = \App\Models\StandardTerm::all();
        $currencies = \App\Models\SystemCurrency::all();
        $ssclRate = \App\Models\Setting::get('sscl_rate', 2.5);
        $vatRate = \App\Models\Setting::get('vat_rate', 15);
        return view('estimates.edit', compact('estimate', 'customers', 'standardTerms', 'currencies', 'ssclRate', 'vatRate'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $estimate = Estimate::findOrFail($id);

        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'date' => 'required|date',
            'attention_to' => 'nullable|string',
            'currency' => 'required|string',
            'senior_manager' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        $estimate->update([
            'customer_id' => $request->customer_id,
            'date' => $request->date,
            'attention_to' => $request->attention_to,
            'address_line_1' => $request->address_line_1,
            'address_line_2' => $request->address_line_2,
            'address_line_3' => $request->address_line_3,
            'designation' => $request->designation,
            'currency' => $request->currency,
            'heading' => $request->heading,
            'terms' => is_array($request->terms) ? implode(', ', $request->terms) : $request->terms,
            'special_terms' => $request->special_terms,
            'advance_payment' => $request->advance_payment,
            'advance_percentage' => $request->advance_percentage,
            'senior_manager' => $request->senior_manager,
            'additional_notes' => $request->additional_notes,
            'sscl_applicable' => $request->has('sscl_applicable'),
            'vat_applicable' => $request->has('vat_applicable'),
        ]);

        // Delete existing items and recreate
        $estimate->items()->delete();

        $grandTotal = 0;

        foreach ($request->items as $item) {
            $quantity = $item['quantity'];
            $unitPrice = $item['unit_price'];
            $amount = $quantity * $unitPrice;

            $sscl = 0;
            $vat = 0;

            if ($request->has('sscl_applicable')) {
                $sscl = $amount * (\App\Models\Setting::get('sscl_rate', 2.5) / 100);
            }

            if ($request->has('vat_applicable')) {
                $vat = ($amount + $sscl) * (\App\Models\Setting::get('vat_rate', 15) / 100);
            }

            $totalWithVat = $amount + $vat + $sscl;

            $estimate->items()->create([
                'description' => $item['description'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'amount' => $amount,
                'vat_amount' => $vat,
                'sscl_amount' => $sscl,
                'total_with_vat' => $totalWithVat,
                'item_heading' => $item['item_heading'] ?? null,
                'locations' => $item['locations'] ?? null,
                'days' => $item['days'] ?? null,
            ]);

            $grandTotal += $totalWithVat;
        }

        $estimate->update(['total_amount' => $grandTotal]);

        return redirect()->route('estimates.show', $estimate->id)->with('success', 'Estimate updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Estimate $estimate)
    {
        $estimate->items()->delete();
        $estimate->delete();

        return redirect()->route('estimates.index')->with('success', 'Estimate deleted successfully.');
    }
}
