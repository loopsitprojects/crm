<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\Customer;
use App\Models\Estimate;
use App\Traits\LogsActivity;

class InvoiceController extends Controller
{
    use LogsActivity;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Invoice::with('customer', 'estimate.deal')->where('is_proforma', false);

        // RBAC Access Control
        $user = auth()->user();
        if ($user->role === 'HOD' && $user->department) {
            $query->whereHas('estimate.deal', function ($q) use ($user) {
                $q->where('department_split', 'like', '%' . $user->department . '%');
            });
        } elseif ($user->role === 'Manager') {
            $query->whereHas('estimate.deal', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        } elseif (!in_array($user->role, ['Super Admin', 'Management'])) {
            $query->whereHas('estimate.deal', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'LIKE', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'LIKE', "%{$search}%");
                    });
            });
        }

        if ($request->filled('from_date')) {
            $query->whereDate('date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('date', '<=', $request->to_date);
        }

        $invoices = $query->latest()->get();
        return view('invoices.index', compact('invoices'));
    }

    public function ready(Request $request)
    {
        $query = Estimate::with('customer', 'deal')->whereIn('status', ['accepted', 'ready_to_invoice']);

        // RBAC Access Control
        $user = auth()->user();
        if ($user->role === 'HOD' && $user->department) {
            $query->whereHas('deal', function ($q) use ($user) {
                $q->where('department_split', 'like', '%' . $user->department . '%');
            });
        } elseif ($user->role === 'Manager') {
            $query->whereHas('deal', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        } elseif (!in_array($user->role, ['Super Admin', 'Management'])) {
            $query->whereHas('deal', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'LIKE', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'LIKE', "%{$search}%");
                    });
            });
        }

        if ($request->filled('from_date')) {
            $query->whereDate('date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('date', '<=', $request->to_date);
        }

        $estimates = $query->latest()->get();
        return view('invoices.ready', compact('estimates'));
    }

    public function invoiced(Request $request)
    {
        $query = Estimate::with('customer', 'deal')->whereIn('status', ['invoiced', 'approved']);

        // RBAC Access Control
        $user = auth()->user();
        if ($user->role === 'HOD' && $user->department) {
            $query->whereHas('deal', function ($q) use ($user) {
                $q->where('department_split', 'like', '%' . $user->department . '%');
            });
        } elseif ($user->role === 'Manager') {
            $query->whereHas('deal', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        } elseif (!in_array($user->role, ['Super Admin', 'Management'])) {
            $query->whereHas('deal', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'LIKE', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'LIKE', "%{$search}%");
                    });
            });
        }

        if ($request->filled('from_date')) {
            $query->whereDate('date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('date', '<=', $request->to_date);
        }

        $estimates = $query->latest()->get();
        return view('invoices.invoiced', compact('estimates'));
    }

    public function proforma(Request $request)
    {
        if (auth()->user()->role !== 'Super Admin') {
            return redirect()->route('invoices.index')->with('error', 'Unauthorized access.');
        }

        $query = Invoice::with('customer', 'estimate')->where('is_proforma', true);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'LIKE', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'LIKE', "%{$search}%");
                    });
            });
        }

        if ($request->filled('from_date')) {
            $query->whereDate('date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('date', '<=', $request->to_date);
        }

        $invoices = $query->latest()->get();
        return view('invoices.proforma', compact('invoices'));
    }

    public function rejected(Request $request)
    {
        $query = Estimate::with('customer', 'deal')->where('status', 'rejected');

        // RBAC Access Control
        $user = auth()->user();
        if ($user->role === 'HOD' && $user->department) {
            $query->whereHas('deal', function ($q) use ($user) {
                $q->where('department_split', 'like', '%' . $user->department . '%');
            });
        } elseif ($user->role === 'Manager') {
            $query->whereHas('deal', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        } elseif (!in_array($user->role, ['Super Admin', 'Management'])) {
            $query->whereHas('deal', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'LIKE', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'LIKE', "%{$search}%");
                    });
            });
        }

        if ($request->filled('from_date')) {
            $query->whereDate('date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('date', '<=', $request->to_date);
        }

        $estimates = $query->latest()->get();
        return view('invoices.rejected', compact('estimates'));
    }

    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Invoice $invoice)
    {
        $invoice->load(['customer', 'items']);
        return view('invoices.show', compact('invoice'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $invoice = Invoice::findOrFail($id);
        
        if ($invoice->quotation_id) {
            return redirect()->route('estimates.edit', $invoice->quotation_id);
        }

        return back()->with('error', 'This invoice cannot be edited directly.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function updateStatus(Request $request, Invoice $invoice)
    {
        if (auth()->user()->role !== 'Super Admin') {
            abort(403, 'Only Super Admin can change invoice status.');
        }

        $request->validate([
            'status' => 'required|in:unpaid,paid,overdue'
        ]);

        $invoice->update(['status' => $request->status]);
        $this->logAction("Updated status to {$request->status} for invoice: {$invoice->invoice_number}", $invoice);

        return back()->with('success', 'Invoice status updated successfully.');
    }

    public function duplicate(Invoice $invoice)
    {
        $invoice->load(['customer', 'items', 'estimate']);

        // Create new Estimate
        $newEstimate = Estimate::create([
            'customer_id' => $invoice->customer_id,
            'reference_number' => Estimate::generateReferenceNumber(),
            'date' => now(),
            'total_amount' => $invoice->total_amount,
            'status' => 'draft',
            'attention_to' => $invoice->estimate->attention_to ?? null,
            'address_line_1' => $invoice->estimate->address_line_1 ?? null,
            'address_line_2' => $invoice->estimate->address_line_2 ?? null,
            'address_line_3' => $invoice->estimate->address_line_3 ?? null,
            'designation' => $invoice->estimate->designation ?? null,
            'currency' => $invoice->estimate->currency ?? 'LKR',
            'heading' => $invoice->estimate->heading ?? null,
            'terms' => $invoice->estimate->terms ?? null,
            'special_terms' => $invoice->estimate->special_terms ?? null,
            'advance_payment' => $invoice->estimate->advance_payment ?? null,
            'advance_percentage' => $invoice->estimate->advance_percentage ?? null,
            'senior_manager' => $invoice->estimate->senior_manager ?? null,
            'additional_notes' => $invoice->estimate->additional_notes ?? null,
            'sscl_applicable' => $invoice->estimate->sscl_applicable ?? false,
            'vat_applicable' => $invoice->estimate->vat_applicable ?? false,
        ]);

        // Copy Items
        foreach ($invoice->items as $item) {
            $newEstimate->items()->create([
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'amount' => $item->amount,
                'sscl_amount' => $item->sscl_amount,
                'vat_amount' => $item->vat_amount,
                'total_with_vat' => $item->total_with_vat,
            ]);
        }

        $this->logAction("Duplicated invoice: {$invoice->invoice_number} to new estimate", $invoice);

        return redirect()->route('estimates.edit', $newEstimate->id)->with('success', 'Invoice duplicated to a new Estimate.');
    }

    public function destroy(string $id)
    {
        $invoice = Invoice::findOrFail($id);
        $num = $invoice->invoice_number;
        $invoice->items()->delete();
        $invoice->delete();
        $this->logAction("Deleted invoice: {$num}");

        return redirect()->route('invoices.index')->with('success', 'Invoice deleted successfully.');
    }
}
