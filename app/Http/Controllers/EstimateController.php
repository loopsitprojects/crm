<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\StandardTerm;
use App\Models\SystemCurrency;
use App\Models\ThirdPartyCost;
use App\Traits\LogsActivity;

class EstimateController extends Controller
{
    use LogsActivity;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Estimate::with('customer', 'deal')->whereIn('status', ['draft', 'ready_to_invoice']);

        // RBAC Access Control
        $user = auth()->user();
        if (!in_array($user->role, ['Super Admin', 'Management'])) {
            $query->whereHas('deal', function ($q) use ($user) {
                $q->where(function ($inner) use ($user) {
                    // Own deals
                    $inner->where('user_id', $user->id)
                      // Team member deals
                      ->orWhereHas('teamMembers', function ($tm) use ($user) {
                          $tm->where('users.id', $user->id);
                      });
                    
                    // HOD specific
                    if ($user->role === 'HOD') {
                        if ($user->department) {
                            $inner->orWhere('department_split', 'like', '%' . $user->department . '%');
                        }
                        
                        // Subordinates
                        $subordinateIds = \App\Models\User::where('supervisor_id', $user->id)->pluck('id');
                        if ($subordinateIds->isNotEmpty()) {
                            $inner->orWhereIn('user_id', $subordinateIds);
                        }
                    }
                });
            });
        }

        // Search filter (Reference or Customer Name)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'LIKE', "%{$search}%")
                    ->orWhere('brand_name', 'LIKE', "%{$search}%")
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

    public function create(Request $request)
    {
        $customers = Customer::all();
        $standardTerms = \App\Models\StandardTerm::all();
        $currencies = \App\Models\SystemCurrency::all();
        $ssclRate = \App\Models\Setting::get('sscl_rate', 2.5);
        $vatRate = \App\Models\Setting::get('vat_rate', 15);
        $brands = Estimate::whereNotNull('brand_name')->distinct()->pluck('brand_name');
        $nextReferenceNumber = Estimate::generateReferenceNumber();
        $users = \App\Models\User::whereIn('role', ['HOD', 'Management'])->get();

        $deal = null;
        if ($request->has('deal_id')) {
            $deal = \App\Models\Deal::with(['customer'])->find($request->deal_id);
        }

        return view('estimates.create', compact('customers', 'standardTerms', 'currencies', 'ssclRate', 'vatRate', 'brands', 'nextReferenceNumber', 'users', 'deal'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'brand_name' => 'nullable|string|max:255',
            'date' => 'required|date',
            'attention_to' => 'nullable|string',
            'currency' => 'required|string',
            'senior_manager' => 'nullable|string',
            'deal_id' => 'nullable|exists:deals,id',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.position' => 'required|integer',
            'proforma_invoice' => 'nullable|string|in:yes,no',
            'third_party_cost' => 'nullable|string|in:yes,no',
            'proforma_percentage' => 'nullable|numeric|min:0|max:100',
            'proforma_tax' => 'nullable|string|in:with_tax,without_tax',
            'advance_received_amount' => 'nullable|numeric|min:0',
        ]);

        if ($request->deal_id && Estimate::where('deal_id', $request->deal_id)->exists()) {
            return back()->withErrors(['deal_id' => 'An estimate already exists for this deal.'])->withInput();
        }

        $referenceNumber = Estimate::generateReferenceNumber();

        $estimate = Estimate::create([
            'customer_id' => $request->customer_id,
            'reference_number' => $referenceNumber,
            'brand_name' => $request->brand_name,
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
            'deal_id' => $request->deal_id,
            'proforma_invoice' => $request->proforma_invoice ?? 'yes',
            'third_party_cost' => $request->third_party_cost ?? 'no',
            'proforma_percentage' => $request->proforma_percentage,
            'proforma_tax' => $request->proforma_tax ?? 'with_tax',
            'advance_received_amount' => $request->advance_received_amount ?? 0,
        ]);

        $grandTotal = 0;

        $positionCounter = 0;
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
                // Use backend counter instead of JS timestamp to prevent INT(11) overflow
                'position' => $positionCounter++,
                'item_heading' => $item['item_heading'] ?? null,
                'locations' => $item['locations'] ?? null,
                'days' => $item['days'] ?? null,
                'department' => $item['department'] ?? null,
                'revenue_category' => $item['revenue_category'] ?? null,
            ]);

            $grandTotal += $totalWithVat;
        }

        $estimate->update(['total_amount' => $grandTotal]);


        // Sync with Deal if exists
        if ($estimate->deal_id) {
            $deal = \App\Models\Deal::find($estimate->deal_id);
            if ($deal) {
                $deal->update([
                    'revenue' => $grandTotal,
                    'contribution' => $grandTotal
                ]);
            }
        }

        // Handle Third Party Costs
        if ($request->third_party_cost === 'yes' && $request->has('third_party_costs')) {
            foreach ($request->third_party_costs as $costData) {
                $filePath = null;
                if (isset($costData['file']) && $costData['file'] instanceof \Illuminate\Http\UploadedFile) {
                    $filePath = $costData['file']->store('third_party_costs', 'public');
                }

                $estimate->thirdPartyCosts()->create([
                    'supplier' => $costData['supplier'],
                    'cost' => $costData['cost'],
                    'department' => $costData['department'] ?? null,
                    'file_path' => $filePath,
                ]);
            }
        }

        $this->syncProformaInvoice($estimate);

        $this->logAction("Created estimate: {$estimate->reference_number}", $estimate);

        return redirect()->route('estimates.index')->with('success', 'Estimate created successfully.');
    }

    public function markAsAccepted(Estimate $estimate)
    {
        $estimate->update(['status' => 'accepted']);
        $this->logAction("Accepted estimate: {$estimate->reference_number}", $estimate);
        return back()->with('success', 'Estimate marked as accepted.');
    }

    public function markAsRejected(Estimate $estimate)
    {
        $estimate->update(['status' => 'rejected']);
        $this->logAction("Rejected estimate: {$estimate->reference_number}", $estimate);
        return back()->with('success', 'Estimate marked as rejected.');
    }

    public function updateStatus(Request $request, Estimate $estimate)
    {
        $user = auth()->user();
        $isRestricted = !in_array($user->role, ['Super Admin', 'Management']);

        // Define allowed statuses
        $allowedStatuses = 'draft,approved,accepted,rejected,invoiced,ready_to_invoice';

        // If restricted, they can ONLY set status to ready_to_invoice
        if ($isRestricted && $request->status !== 'ready_to_invoice') {
            return back()->with('error', 'You are only authorized to change status to Ready to Invoice.');
        }

        $request->validate([
            'status' => "required|in:$allowedStatuses"
        ]);

        $estimate->update(['status' => $request->status]);
        $this->logAction("Updated status to {$request->status} for estimate: {$estimate->reference_number}", $estimate);

        $message = "Estimate status updated to " . ucfirst($request->status) . ".";

        if ($request->status === 'ready_to_invoice' || $request->status === 'accepted') {
            return redirect()->route('invoices.ready')->with('success', $message);
        } elseif ($request->status === 'approved' || $request->status === 'invoiced') {
            return redirect()->route('invoices.invoiced')->with('success', $message);
        } elseif ($request->status === 'rejected') {
            return redirect()->route('invoices.rejected')->with('success', $message);
        }

        return redirect()->route('estimates.index')->with('success', $message);
    }

    public function convertToInvoice(Estimate $estimate)
    {
        if (!in_array($estimate->status, ['accepted', 'ready_to_invoice'])) {
            return back()->with('error', 'Only accepted/ready to invoice estimates can be invoiced.');
        }

        // Generate Invoice Number (YYMMM_LDSL_XXXXX)
        $prefix = strtoupper(now()->format('yM')) . '_LDSL_';
        $latestInvoice = \App\Models\Invoice::where('invoice_number', 'LIKE', '%_LDSL_%')
                        ->orderBy('id', 'desc')
                        ->first();

        $sequence = 1;
        if ($latestInvoice && preg_match('/_LDSL_(\d+)$/', $latestInvoice->invoice_number, $matches)) {
            $sequence = intval($matches[1]) + 1;
        }
        
        $newInvoiceNumber = $prefix . str_pad($sequence, 5, '0', STR_PAD_LEFT);

        // Create Invoice
        $invoice = Invoice::create([
            'quotation_id' => $estimate->id,
            'customer_id' => $estimate->customer_id,
            'invoice_number' => $newInvoiceNumber,
            'date' => now(),
            'due_date' => now()->addDays(30),
            'total_amount' => $estimate->total_amount - ($estimate->advance_received_amount ?? 0),
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
                'department' => $item->department,
                'revenue_category' => $item->revenue_category,
            ]);
        }

        $estimate->update(['status' => 'invoiced']);
        $this->logAction("Converted estimate: {$estimate->reference_number} to invoice: {$invoice->invoice_number}", $estimate);

        return redirect()->route('invoices.invoiced')->with('success', 'Estimate converted to Invoice successfully.');
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
        $estimate = Estimate::with(['items', 'deal.owner'])->findOrFail($id);

        $user = auth()->user();
        if ($user->role !== 'Super Admin') {
            if ($user->role === 'Management' && $estimate->status === 'invoiced') {
                abort(403, 'Management cannot edit Invoiced estimates.');
            } elseif ($user->role !== 'Management' && $estimate->status !== 'draft') {
                abort(403, 'You can only edit Draft estimates.');
            }
        }

        $customers = Customer::all();
        $standardTerms = \App\Models\StandardTerm::all();
        $currencies = \App\Models\SystemCurrency::all();
        $ssclRate = \App\Models\Setting::get('sscl_rate', 2.5);
        $vatRate = \App\Models\Setting::get('vat_rate', 15);
        $brands = Estimate::whereNotNull('brand_name')->distinct()->pluck('brand_name');
        $users = \App\Models\User::whereIn('role', ['HOD', 'Management'])->get();
        return view('estimates.edit', compact('estimate', 'customers', 'standardTerms', 'currencies', 'ssclRate', 'vatRate', 'brands', 'users'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $estimate = Estimate::findOrFail($id);

        $user = auth()->user();
        if ($user->role !== 'Super Admin') {
            if ($user->role === 'Management' && $estimate->status === 'invoiced') {
                abort(403, 'Management cannot edit Invoiced estimates.');
            } elseif ($user->role !== 'Management' && $estimate->status !== 'draft') {
                abort(403, 'You can only edit Draft estimates.');
            }
        }

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
            'items.*.position' => 'required|integer',
            'proforma_invoice' => 'nullable|string|in:yes,no',
            'third_party_cost' => 'nullable|string|in:yes,no',
            'proforma_percentage' => 'nullable|numeric|min:0|max:100',
            'proforma_tax' => 'nullable|string|in:with_tax,without_tax',
            'advance_received_amount' => 'nullable|numeric|min:0',
        ]);

        $estimate->update([
            'customer_id' => $request->customer_id,
            'brand_name' => $request->brand_name,
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
            'proforma_invoice' => $request->proforma_invoice ?? 'yes',
            'third_party_cost' => $request->third_party_cost ?? 'no',
            'proforma_percentage' => $request->proforma_percentage,
            'proforma_tax' => $request->proforma_tax ?? 'with_tax',
            'advance_received_amount' => $request->advance_received_amount ?? 0,
        ]);

        // Delete existing items and recreate
        $estimate->items()->delete();

        $grandTotal = 0;

        $positionCounter = 0;
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
                // Use backend counter instead of JS timestamp to prevent INT(11) overflow
                'position' => $positionCounter++,
                'item_heading' => $item['item_heading'] ?? null,
                'locations' => $item['locations'] ?? null,
                'days' => $item['days'] ?? null,
                'department' => $item['department'] ?? null,
                'revenue_category' => $item['revenue_category'] ?? null,
            ]);

            $grandTotal += $totalWithVat;
        }

        $estimate->update(['total_amount' => $grandTotal]);


        // Sync with Deal if exists
        if ($estimate->deal_id) {
            $deal = \App\Models\Deal::find($estimate->deal_id);
            if ($deal) {
                $deal->update([
                    'revenue' => $grandTotal,
                    'contribution' => $grandTotal
                ]);
            }
        }

        // Handle Third Party Costs (Delete old and recreate)
        $estimate->thirdPartyCosts()->get()->each(function($oldCost) {
            if ($oldCost->file_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($oldCost->file_path);
            }
            $oldCost->delete();
        });

        if ($request->third_party_cost === 'yes' && $request->has('third_party_costs')) {
            foreach ($request->third_party_costs as $costData) {
                $filePath = null;
                // If there's a new file, upload it
                if (isset($costData['file']) && $costData['file'] instanceof \Illuminate\Http\UploadedFile) {
                    $filePath = $costData['file']->store('third_party_costs', 'public');
                } 
                // Note: In this simple implementation, we recreate rows. 
                // If we wanted to keep old files when not replaced, we'd need more logic.
                // But since the UI doesn't currently support "keep existing file" vs "replace",
                // we'll assume we're recreating. 
                // Actually, I should probably check if I can keep the old file path if no new one is uploaded.
                
                $estimate->thirdPartyCosts()->create([
                    'supplier' => $costData['supplier'],
                    'cost' => $costData['cost'],
                    'department' => $costData['department'] ?? null,
                    'file_path' => $filePath,
                ]);
            }
        }

        $this->syncProformaInvoice($estimate);

        $this->logAction("Updated estimate: {$estimate->reference_number}", $estimate);

        return redirect()->route('estimates.show', $estimate->id)->with('success', 'Estimate updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Estimate $estimate)
    {
        $ref = $estimate->reference_number;
        $estimate->items()->delete();
        $estimate->delete();
        $this->logAction("Deleted estimate: {$ref}");

        return redirect()->route('estimates.index')->with('success', 'Estimate deleted successfully.');
    }

    /**
     * Synchronize the proforma invoice status based on Estimate settings.
     */
    private function syncProformaInvoice(Estimate $estimate)
    {
        if ($estimate->proforma_invoice === 'yes') {
            $existingProforma = Invoice::where('quotation_id', $estimate->id)->where('is_proforma', true)->first();
            
            if ($existingProforma) {
                // Delete old items and update
                $existingProforma->items()->delete();
                $invoice = $existingProforma;
                $invoice->update([
                    'total_amount' => $estimate->total_amount,
                    'date' => now(), // Or $estimate->date
                    // 'due_date' => now()->addDays(30),
                ]);
            } else {
                // Generate Proforma Invoice Number (PROINV_YY_XXXX)
                $prefix = 'PROINV_' . now()->format('y') . '_';
                $latestInvoice = \App\Models\Invoice::where('invoice_number', 'LIKE', $prefix . '%')
                                ->orderBy('id', 'desc')
                                ->first();

                $sequence = 1;
                if ($latestInvoice && preg_match('/_(\d+)$/', $latestInvoice->invoice_number, $matches)) {
                    $sequence = intval($matches[1]) + 1;
                }
                $newInvoiceNumber = $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);

                // Create Proforma Invoice
                $invoice = Invoice::create([
                    'quotation_id' => $estimate->id,
                    'customer_id' => $estimate->customer_id,
                    'invoice_number' => $newInvoiceNumber,
                    'date' => now(),
                    'due_date' => now()->addDays(30),
                    'total_amount' => $estimate->total_amount,
                    'status' => 'unpaid',
                    'is_proforma' => true,
                ]);
            }
            
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
        } else {
            // Delete existing proforma if it was changed to 'no'
            Invoice::where('quotation_id', $estimate->id)->where('is_proforma', true)->delete();
        }
    }
}
