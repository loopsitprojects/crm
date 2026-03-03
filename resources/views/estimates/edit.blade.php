@extends('layouts.app')

@push('head')
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <style>
        #items-body tr.sortable-ghost {
            opacity: 0.4;
            background: #f0f9ff;
        }

        .drag-handle {
            cursor: grab;
            color: #CBD5E1;
        }

        .drag-handle:hover {
            color: #94A3B8;
        }

        .drag-handle:active {
            cursor: grabbing;
        }
    </style>
@endpush

@section('header')
    <div class="flex items-center justify-between px-6 py-4 bg-white border-b border-gray-200">
        <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fas fa-edit text-brand-pink"></i> Edit Estimate <span
                class="text-gray-400 text-sm font-normal ml-2">#{{ $estimate->reference_number }}</span>
        </h2>
        <a href="{{ route('estimates.index') }}" class="text-sm text-gray-500 hover:text-gray-700 transition-colors">
            Cancel
        </a>
    </div>
@endsection

@section('content')
    <div class="max-w-7xl mx-auto my-8 px-4 sm:px-6 lg:px-8">
        <form action="{{ route('estimates.update', $estimate->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
                
                <!-- Main Area (2/3 Column) -->
                <div class="lg:col-span-2 space-y-8">
                    
                    <!-- Section: Client Details -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
                            <h3 class="text-base font-semibold text-gray-800 uppercase tracking-wider text-xs">Client Details</h3>
                            <i class="fas fa-user-tie text-gray-400"></i>
                        </div>
                        <div class="p-6 space-y-4">
                            <!-- Customer Select -->
                            <div class="grid grid-cols-12 gap-4 items-center">
                                <label class="col-span-12 sm:col-span-3 text-right text-sm font-medium text-gray-600">Customer <span class="text-red-500">*</span></label>
                                <div class="col-span-12 sm:col-span-9">
                                    <select name="customer_id" required
                                        class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm shadow-sm py-2">
                                        <option value="">-- Select Customer --</option>
                                        @foreach($customers as $customer)
                                            <option value="{{ $customer->id }}" {{ $estimate->customer_id == $customer->id ? 'selected' : '' }}>{{ $customer->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <!-- Brand Name -->
                            <div class="grid grid-cols-12 gap-4 items-center">
                                <label class="col-span-12 sm:col-span-3 text-right text-sm font-medium text-gray-600">Brand Name</label>
                                <div class="col-span-12 sm:col-span-9 brand-name-tom-select">
                                    <select name="brand_name" id="brand_name_select"
                                        class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm shadow-sm py-2">
                                        <option value="">-- No Brand / Select Brand --</option>
                                        @foreach($brands as $brand)
                                            <option value="{{ $brand }}" {{ $estimate->brand_name == $brand ? 'selected' : '' }}>{{ $brand }}</option>
                                        @endforeach
                                        @if($estimate->brand_name && !$brands->contains($estimate->brand_name))
                                            <option value="{{ $estimate->brand_name }}" selected>{{ $estimate->brand_name }}</option>
                                        @endif
                                    </select>
                                </div>
                            </div>

                            <style>
                                .brand-name-tom-select .ts-wrapper .ts-control {
                                    border: none !important;
                                    box-shadow: none !important;
                                }
                            </style>

                            <!-- Attention To -->
                            <div class="grid grid-cols-12 gap-4 items-center">
                                <label class="col-span-12 sm:col-span-3 text-right text-sm font-medium text-gray-600">Attention To <span class="text-red-500">*</span></label>
                                <div class="col-span-12 sm:col-span-9">
                                    <input type="text" name="attention_to" value="{{ old('attention_to', $estimate->attention_to) }}" placeholder="E.g. Mr. John Doe"
                                        class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm shadow-sm py-2">
                                </div>
                            </div>

                            <!-- Designation -->
                            <div class="grid grid-cols-12 gap-4 items-center">
                                <label class="col-span-12 sm:col-span-3 text-right text-sm font-medium text-gray-600">Designation</label>
                                <div class="col-span-12 sm:col-span-9">
                                    <input type="text" name="designation" value="{{ old('designation', $estimate->designation) }}" placeholder="E.g. Senior Manager"
                                        class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm shadow-sm py-2">
                                </div>
                            </div>

                            <!-- Address Group -->
                            <div class="grid grid-cols-12 gap-4 items-start">
                                <label class="col-span-12 sm:col-span-3 text-right text-sm font-medium text-gray-600 pt-2">Address</label>
                                <div class="col-span-12 sm:col-span-9 space-y-2">
                                    <input type="text" name="address_line_1" value="{{ old('address_line_1', $estimate->address_line_1) }}" placeholder="Address Line 1"
                                        class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm shadow-sm py-2">
                                    <input type="text" name="address_line_2" value="{{ old('address_line_2', $estimate->address_line_2) }}" placeholder="Address Line 2"
                                        class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm shadow-sm py-2">
                                    <input type="text" name="address_line_3" value="{{ old('address_line_3', $estimate->address_line_3) }}" placeholder="Address Line 3"
                                        class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm shadow-sm py-2">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Estimate Items -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
                            <h3 class="text-base font-semibold text-gray-800 uppercase tracking-wider text-xs">Estimate Items</h3>
                            <div class="flex items-center gap-4">
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="sscl_applicable" id="sscl_applicable" value="1" {{ $estimate->sscl_applicable ? 'checked' : '' }} onchange="calculateAllRows()"
                                        class="rounded border-gray-300 text-brand-purple shadow-sm focus:ring-brand-purple">
                                    <span class="ml-2 text-xs font-semibold text-gray-600">SSCL ({{ $ssclRate }}%)</span>
                                </label>
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="vat_applicable" id="vat_applicable" value="1" {{ $estimate->vat_applicable ? 'checked' : '' }} onchange="calculateAllRows()"
                                        class="rounded border-gray-300 text-brand-purple shadow-sm focus:ring-brand-purple">
                                    <span class="ml-2 text-xs font-semibold text-gray-600">VAT ({{ $vatRate }}%)</span>
                                </label>
                            </div>
                        </div>
                        <div class="p-0 overflow-x-auto min-h-[250px]">
                            <table class="w-full min-w-[700px]" id="items-table">
                                <thead class="bg-gray-50 border-b border-gray-100">
                                    <tr>
                                        <th class="px-2 py-3 w-8"></th>
                                        <th class="px-2 py-3 text-left text-xs font-bold text-gray-500 uppercase">Description</th>
                                        <th class="px-2 py-3 text-left text-xs font-bold text-gray-500 uppercase w-32">Department</th>
                                        <th class="px-2 py-3 text-left text-xs font-bold text-gray-500 uppercase w-32">Rev. Category</th>
                                        <th class="px-2 py-3 text-right text-xs font-bold text-gray-500 uppercase w-20">Qty</th>
                                        <th class="px-2 py-3 text-right text-xs font-bold text-gray-500 uppercase w-32">Unit Price</th>
                                        <th class="px-2 py-3 text-right text-xs font-bold text-gray-500 uppercase w-32">Amount</th>
                                        <th class="px-2 py-3 text-center text-xs font-bold text-gray-500 uppercase w-12"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50" id="items-body">
                                    @foreach($estimate->items as $index => $item)
                                        <tr class="group hover:bg-gray-50 transition-colors">
                                            <td class="p-2 align-middle text-center drag-handle" title="Drag to reorder">
                                                <i class="fas fa-grip-vertical text-gray-300"></i>
                                            </td>
                                            <td class="p-2 align-top">
                                                <textarea name="items[{{ $index }}][description]" rows="1" placeholder="Item..."
                                                    class="w-full border-none bg-transparent focus:ring-0 text-sm py-1 px-2 resize-none"
                                                    title="Description">{{ $item->description }}</textarea>
                                            </td>
                                            <td class="p-2 align-top">
                                                <select name="items[{{ $index }}][department]" class="w-full rounded-md border-gray-200 text-xs py-1 px-1">
                                                    <option value="">Select</option>
                                                    <option value="creative" {{ $item->department == 'creative' ? 'selected' : '' }}>Creative</option>
                                                    <option value="digital" {{ $item->department == 'digital' ? 'selected' : '' }}>Digital</option>
                                                    <option value="play" {{ $item->department == 'play' ? 'selected' : '' }}>Play</option>
                                                    <option value="tech" {{ $item->department == 'tech' ? 'selected' : '' }}>Tech</option>
                                                </select>
                                            </td>
                                            <td class="p-2 align-top">
                                                <select name="items[{{ $index }}][revenue_category]" class="w-full rounded-md border-gray-200 text-xs py-1 px-1">
                                                    <option value="">Select</option>
                                                    <option value="Retainer" {{ $item->revenue_category == 'Retainer' ? 'selected' : '' }}>Retainer</option>
                                                    <option value="Ads" {{ $item->revenue_category == 'Ads' ? 'selected' : '' }}>Ads</option>
                                                    <option value="Campaigns/Projects" {{ $item->revenue_category == 'Campaigns/Projects' ? 'selected' : '' }}>Campaigns/Projects</option>
                                                    <option value="CAG" {{ $item->revenue_category == 'CAG' ? 'selected' : '' }}>CAG</option>
                                                    <option value="Corporate" {{ $item->revenue_category == 'Corporate' ? 'selected' : '' }}>Corporate</option>
                                                </select>
                                            </td>
                                            <td class="p-2 align-top">
                                                <input type="hidden" name="items[{{ $index }}][position]" value="{{ $item->position ?? $index }}">
                                                <input type="number" name="items[{{ $index }}][quantity]" value="{{ $item->quantity }}" min="1" oninput="calculateRow(this)"
                                                    class="w-full rounded-md border-gray-200 text-sm py-1 px-1 text-right">
                                            </td>
                                            <td class="p-2 align-top">
                                                <input type="number" step="0.01" name="items[{{ $index }}][unit_price]" value="{{ $item->unit_price }}" min="0" oninput="calculateRow(this)"
                                                    class="w-full rounded-md border-gray-200 text-sm py-1 px-1 text-right">
                                            </td>
                                            <td class="p-2 align-top">
                                                <input type="number" step="0.01" name="items[{{ $index }}][amount]" value="{{ $item->total_with_vat }}" placeholder="0.00" readonly
                                                    class="w-full border-none bg-transparent text-sm py-1 px-1 text-right font-medium text-gray-700">
                                            </td>
                                            <td class="p-2 align-top text-center">
                                                <button type="button" onclick="this.closest('tr').remove(); calculateTotals();"
                                                    class="text-gray-400 hover:text-red-500 transition-colors p-1 rounded-full hover:bg-red-50">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <div class="p-4 bg-gray-50 border-t border-gray-100">
                                <button type="button" onclick="addItem()"
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-brand-purple bg-brand-purple bg-opacity-10 hover:bg-opacity-20 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-purple transition-colors">
                                    <i class="fas fa-plus mr-2"></i> Add Item
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Summary - REQUESTED IN MAIN AREA -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
                            <h3 class="text-base font-semibold text-gray-800 uppercase tracking-wider text-xs">Summary</h3>
                            <i class="fas fa-calculator text-gray-400"></i>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                <div class="space-y-3">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-500 font-medium">Subtotal</span>
                                        <span class="font-bold text-gray-700 font-mono" id="display_subtotal">0.00</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-500 font-medium">SSCL Amount</span>
                                        <span class="font-bold text-gray-700 font-mono" id="display_sscl">0.00</span>
                                    </div>
                                    <div class="flex justify-between text-sm border-b border-gray-100 pb-3">
                                        <span class="text-gray-500 font-medium">VAT Amount</span>
                                        <span class="font-bold text-gray-700 font-mono" id="display_vat">0.00</span>
                                    </div>
                                </div>
                                <div class="flex flex-col justify-center items-end border-l border-gray-50 pl-8">
                                    <span class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Grand Total</span>
                                    <span class="text-4xl font-black text-brand-pink font-mono" id="display_total">0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Area (1/3 Column) -->
                <div class="lg:col-span-1 space-y-8">
                    
                    <!-- Section: General Info -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
                            <h3 class="text-base font-semibold text-gray-800 uppercase tracking-wider text-xs">General Info</h3>
                            <i class="fas fa-info-circle text-gray-400"></i>
                        </div>
                        <div class="p-6 space-y-5">
                            <!-- Reference Number -->
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Estimate Number</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-hashtag text-gray-300 text-xs"></i>
                                    </div>
                                    <input type="text" value="{{ $estimate->reference_number }}" readonly
                                        class="w-full pl-8 bg-gray-50 rounded-md border-gray-200 text-gray-500 text-sm py-2 font-mono font-bold"
                                        title="Fixed reference number">
                                </div>
                            </div>

                            <!-- Date -->
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Date <span class="text-red-500">*</span></label>
                                <input type="date" name="date" value="{{ old('date', $estimate->date) }}" required
                                    class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue text-sm py-2">
                            </div>

                            <!-- Currency -->
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Currency <span class="text-red-500">*</span></label>
                                <select name="currency" required
                                    class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue text-sm py-2">
                                    <option value="">Select Currency</option>
                                    @if($currencies->isEmpty())
                                        <option value="LKR" {{ $estimate->currency == 'LKR' ? 'selected' : '' }}>LKR - Sri Lankan Rupee (Rs)</option>
                                    @else
                                        @foreach($currencies as $currency)
                                            <option value="{{ $currency->code }}" {{ $estimate->currency == $currency->code ? 'selected' : '' }}>
                                                {{ $currency->code }} - {{ $currency->name }} ({{ $currency->symbol }})
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>

                            <!-- Heading -->
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Project Heading</label>
                                <input type="text" name="heading" value="{{ old('heading', $estimate->heading) }}" placeholder="E.g. Web Development"
                                    class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue text-sm py-2">
                            </div>
                        </div>
                    </div>

                    <!-- Section: Terms & Payment - REQUESTED IN SIDEBAR -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
                            <h3 class="text-base font-semibold text-gray-800 uppercase tracking-wider text-xs">Terms & Payment</h3>
                            <i class="fas fa-file-contract text-gray-400"></i>
                        </div>
                        <div class="p-6 space-y-6">
                            <!-- Standard Terms -->
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Standard Terms</label>
                                <select id="term_selector" onchange="addTerm(this.value); this.value='';"
                                    class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue text-xs py-2 shadow-sm">
                                    <option value="">-- Add Standard Terms --</option>
                                    @foreach($standardTerms as $term)
                                        <option value="{{ $term->content }}">{{ Str::limit($term->content, 50) }}</option>
                                    @endforeach
                                </select>
                                <div id="selected_terms_container" class="mt-3 space-y-2">
                                    <!-- Selected terms will appear here -->
                                </div>
                            </div>

                            <!-- Proforma? -->
                            <div class="pt-4 border-t border-gray-50">
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-3">Proforma Invoice? <span class="text-red-500">*</span></label>
                                <div class="flex items-center gap-6 mb-4">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="proforma_invoice" value="yes" {{ ($estimate->proforma_invoice ?? 'yes') == 'yes' ? 'checked' : '' }} onchange="toggleProformaFields(this.value)"
                                            class="w-4 h-4 text-brand-blue border-gray-300 focus:ring-brand-blue">
                                        <span class="text-sm font-medium text-gray-700">Yes</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="proforma_invoice" value="no" {{ ($estimate->proforma_invoice ?? 'yes') == 'no' ? 'checked' : '' }} onchange="toggleProformaFields(this.value)"
                                            class="w-4 h-4 text-brand-blue border-gray-300 focus:ring-brand-blue">
                                        <span class="text-sm font-medium text-gray-700">No</span>
                                    </label>
                                </div>
                                <div id="proforma_details" class="{{ ($estimate->proforma_invoice ?? 'yes') == 'no' ? 'hidden' : '' }} space-y-3">
                                    <input type="number" step="0.01" name="proforma_percentage" value="{{ old('proforma_percentage', $estimate->proforma_percentage) }}" placeholder="Percentage %"
                                        class="w-full rounded-md border-gray-200 text-sm py-1.5 px-3">
                                    <select name="proforma_tax" class="w-full rounded-md border-gray-200 text-xs py-1.5 px-3">
                                        <option value="with_tax" {{ ($estimate->proforma_tax ?? 'with_tax') == 'with_tax' ? 'selected' : '' }}>With Tax</option>
                                        <option value="without_tax" {{ ($estimate->proforma_tax ?? 'with_tax') == 'without_tax' ? 'selected' : '' }}>Without Tax</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Third Party Costs? -->
                            <div class="pt-4 border-t border-gray-50">
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-3">Third Party Costs? <span class="text-red-500">*</span></label>
                                <div class="flex items-center gap-6 mb-4">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="third_party_cost" value="yes" {{ ($estimate->third_party_cost ?? 'no') == 'yes' ? 'checked' : '' }} onchange="toggleThirdPartySection(this.value)"
                                            class="w-4 h-4 text-brand-blue border-gray-300 focus:ring-brand-blue">
                                        <span class="text-sm font-medium text-gray-700">Yes</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="third_party_cost" value="no" {{ ($estimate->third_party_cost ?? 'no') == 'no' ? 'checked' : '' }} onchange="toggleThirdPartySection(this.value)"
                                            class="w-4 h-4 text-brand-blue border-gray-300 focus:ring-brand-blue">
                                        <span class="text-sm font-medium text-gray-700">No</span>
                                    </label>
                                </div>

                                <!-- Integrated Third Party Costs Table -->
                                <div id="third_party_costs_section" class="{{ ($estimate->third_party_cost ?? 'no') == 'no' ? 'hidden' : '' }} mt-2 mb-4 border border-gray-100 rounded-lg overflow-hidden">
                                    <div class="p-0 overflow-x-auto">
                                        <table class="w-full min-w-[400px] font-mono" id="third-party-table">
                                            <thead class="bg-gray-50 border-b border-gray-100">
                                                <tr>
                                                    <th class="px-2 py-2 text-left text-[10px] font-bold text-gray-500 uppercase">Supplier</th>
                                                    <th class="px-2 py-2 text-right text-[10px] font-bold text-gray-500 uppercase">Cost</th>
                                                    <th class="px-2 py-2 text-left text-[10px] font-bold text-gray-500 uppercase">Dept</th>
                                                    <th class="px-2 py-2 text-left text-[10px] font-bold text-gray-500 uppercase">File</th>
                                                    <th class="px-2 py-2 text-center text-[10px] font-bold text-gray-500 uppercase w-8"></th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-50" id="third-party-body">
                                                @foreach($estimate->thirdPartyCosts as $cIndex => $cost)
                                                    <tr class="group hover:bg-gray-50 transition-colors">
                                                        <td class="p-2 align-top">
                                                            <input type="text" name="third_party_costs[{{ $cIndex }}][supplier]" value="{{ $cost->supplier }}" placeholder="Supplier Name" class="w-full rounded-md border-gray-200 text-[10px] py-1 px-1 font-mono">
                                                        </td>
                                                        <td class="p-2 align-top">
                                                            <input type="number" step="0.01" name="third_party_costs[{{ $cIndex }}][cost]" value="{{ $cost->cost }}" placeholder="0.00" class="w-full rounded-md border-gray-200 text-[10px] py-1 px-1 text-right font-mono">
                                                        </td>
                                                        <td class="p-2 align-top">
                                                            <select name="third_party_costs[{{ $cIndex }}][department]" class="w-full rounded-md border-gray-200 text-[10px] py-1 px-1 font-mono">
                                                                <option value="">Select</option>
                                                                <option value="creative" {{ $cost->department == 'creative' ? 'selected' : '' }}>Creative</option>
                                                                <option value="digital" {{ $cost->department == 'digital' ? 'selected' : '' }}>Digital</option>
                                                                <option value="play" {{ $cost->department == 'play' ? 'selected' : '' }}>Play</option>
                                                                <option value="tech" {{ $cost->department == 'tech' ? 'selected' : '' }}>Tech</option>
                                                            </select>
                                                        </td>
                                                        <td class="p-2 align-top">
                                                            <input type="file" name="third_party_costs[{{ $cIndex }}][file]" class="w-full text-[8px] text-gray-500 file:mr-1 file:py-0.5 file:px-1 file:rounded file:border-0 file:text-[8px] file:bg-brand-blue file:bg-opacity-10 file:text-brand-blue">
                                                            @if($cost->file_path)
                                                                <a href="{{ Storage::url($cost->file_path) }}" target="_blank" class="text-[8px] text-brand-blue hover:underline mt-1 block">
                                                                    <i class="fas fa-paperclip mr-0.5"></i> View
                                                                </a>
                                                            @endif
                                                        </td>
                                                        <td class="p-2 align-top text-center">
                                                            <button type="button" onclick="this.closest('tr').remove()" class="text-gray-300 hover:text-red-500 transition-colors">
                                                                <i class="fas fa-trash-alt text-xs"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                        <div class="p-2 bg-gray-50 border-t border-gray-100">
                                            <button type="button" onclick="addThirdPartyCost()"
                                                class="w-full inline-flex justify-center items-center px-2 py-1.5 border border-transparent text-[10px] font-medium rounded-md text-brand-blue bg-brand-blue bg-opacity-10 hover:bg-opacity-20 transition-colors">
                                                <i class="fas fa-plus mr-1"></i> Add Cost
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                                <!-- Special Terms -->
                                <div class="pt-4 border-t border-gray-50">
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Special Terms</label>
                                    <textarea name="special_terms" rows="3" placeholder="Any custom conditions..."
                                        class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue text-sm py-2">{{ old('special_terms', $estimate->special_terms) }}</textarea>
                                </div>
                            </div>
                        </div>

                    <!-- Section: Approval - REQUESTED IN SIDEBAR -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
                            <h3 class="text-base font-semibold text-gray-800 uppercase tracking-wider text-xs">Approval</h3>
                            <i class="fas fa-signature text-gray-400"></i>
                        </div>
                        <div class="p-6 space-y-6">
                            <!-- Manager -->
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Senior Manager <span class="text-red-500">*</span></label>
                                <select name="senior_manager" required
                                    class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue text-sm py-2">
                                    <option value="">-- Select Manager --</option>
                                    @foreach(\App\Models\SeniorManager::all() as $manager)
                                        <option value="{{ $manager->name }}" {{ old('senior_manager', $estimate->senior_manager) == $manager->name ? 'selected' : '' }}>{{ $manager->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <!-- Note -->
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Internal Note</label>
                                <textarea name="additional_notes" rows="3" placeholder="Notes for the team..."
                                    class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue text-sm py-2">{{ old('additional_notes', $estimate->additional_notes) }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="mt-8 pt-6 border-t border-gray-200 flex items-center justify-end gap-4">
                <a href="{{ route('estimates.index') }}"
                    class="px-6 py-2.5 rounded-lg border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 text-sm font-medium shadow-sm transition-all">
                    Cancel
                </a>
                <button type="submit"
                    class="px-8 py-2.5 rounded-lg bg-brand-pink text-white hover:bg-brand-purple text-sm font-medium shadow-lg hover:shadow-xl transition-all transform hover:-translate-y-0.5">
                    <i class="fas fa-save mr-2"></i> Update Estimate
                </button>
            </div>

        </form>
    </div>

    <!-- Scripts -->
    <script>
        function addItem() {
            const tbody = document.getElementById('items-body');
            const newIndex = new Date().getTime();

            const row = document.createElement('tr');
            row.className = "group hover:bg-gray-50 transition-colors";

            row.innerHTML = `
                <td class="p-2 align-middle text-center drag-handle" title="Drag to reorder">
                    <i class="fas fa-grip-vertical text-gray-300"></i>
                </td>
                <td class="p-2 align-top">
                    <textarea name="items[${newIndex}][description]" rows="1" placeholder="Item..." class="w-full border-none bg-transparent focus:ring-0 text-sm py-1 px-2 resize-none" title="Description"></textarea>
                </td>
                <td class="p-2 align-top">
                    <select name="items[${newIndex}][department]" class="w-full rounded-md border-gray-200 text-xs py-1 px-1">
                        <option value="">Select</option>
                        <option value="creative">Creative</option>
                        <option value="digital">Digital</option>
                        <option value="play">Play</option>
                        <option value="tech">Tech</option>
                    </select>
                </td>
                <td class="p-2 align-top">
                    <select name="items[${newIndex}][revenue_category]" class="w-full rounded-md border-gray-200 text-xs py-1 px-1">
                        <option value="">Select</option>
                        <option value="Retainer">Retainer</option>
                        <option value="Ads">Ads</option>
                        <option value="Campaigns/Projects">Campaigns/Projects</option>
                        <option value="CAG">CAG</option>
                        <option value="Corporate">Corporate</option>
                    </select>
                </td>
                <td class="p-2 align-top">
                    <input type="hidden" name="items[${newIndex}][position]" value="${newIndex}">
                    <input type="number" name="items[${newIndex}][quantity]" value="1" min="1" oninput="calculateRow(this)" class="w-full rounded-md border-gray-200 text-sm py-1 px-1 text-right">
                </td>
                <td class="p-2 align-top">
                    <input type="number" step="0.01" name="items[${newIndex}][unit_price]" value="0" min="0" oninput="calculateRow(this)" class="w-full rounded-md border-gray-200 text-sm py-1 px-1 text-right">
                </td>
                <td class="p-2 align-top">
                    <input type="number" step="0.01" name="items[${newIndex}][amount]" placeholder="0.00" readonly class="w-full border-none bg-transparent text-sm py-1 px-1 text-right font-medium text-gray-700">
                </td>
                <td class="p-2 align-top text-center">
                    <button type="button" onclick="this.closest('tr').remove(); calculateTotals();" class="text-gray-400 hover:text-red-500 transition-colors p-1 rounded-full hover:bg-red-50">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
            `;

            tbody.appendChild(row);
            calculateRow(row.querySelector('input[name*="[quantity]"]'));
        }

        const ssclRate = {{ $ssclRate / 100 }};
        const vatRate = {{ $vatRate / 100 }};

        function calculateRow(input) {
            const row = input.closest('tr');
            const qty = parseFloat(row.querySelector('input[name*="[quantity]"]').value) || 0;
            const price = parseFloat(row.querySelector('input[name*="[unit_price]"]').value) || 0;
            const baseAmount = qty * price;

            const ssclApplicable = document.getElementById('sscl_applicable').checked;
            const vatApplicable = document.getElementById('vat_applicable').checked;

            let sscl = 0;
            let vat = 0;

            if (ssclApplicable) sscl = baseAmount * ssclRate;
            if (vatApplicable) vat = (baseAmount + sscl) * vatRate;

            const totalWithTaxes = baseAmount + sscl + vat;
            row.querySelector('input[name*="[amount]"]').value = totalWithTaxes.toFixed(2);

            calculateTotals();
        }

        function calculateTotals() {
            let subtotalBase = 0;
            let totalSSCL = 0;
            let totalVAT = 0;

            const ssclApplicable = document.getElementById('sscl_applicable').checked;
            const vatApplicable = document.getElementById('vat_applicable').checked;

            document.querySelectorAll('#items-body tr').forEach(row => {
                const qty = parseFloat(row.querySelector('input[name*="[quantity]"]').value) || 0;
                const price = parseFloat(row.querySelector('input[name*="[unit_price]"]').value) || 0;
                const baseAmount = qty * price;

                subtotalBase += baseAmount;
                if (ssclApplicable) totalSSCL += baseAmount * ssclRate;
                if (vatApplicable) totalVAT += (baseAmount + (ssclApplicable ? baseAmount * ssclRate : 0)) * vatRate;
            });

            const grandTotal = subtotalBase + totalSSCL + totalVAT;

            document.getElementById('display_subtotal').textContent = subtotalBase.toFixed(2);
            document.getElementById('display_sscl').textContent = totalSSCL.toFixed(2);
            document.getElementById('display_vat').textContent = totalVAT.toFixed(2);
            document.getElementById('display_total').textContent = grandTotal.toFixed(2);
        }

        function calculateAllRows() {
            document.querySelectorAll('#items-body tr').forEach(row => {
                const input = row.querySelector('input[name*="[quantity]"]');
                if (input) calculateRow(input);
            });
        }

        function addTerm(content) {
            if (!content) return;
            const container = document.getElementById('selected_terms_container');
            const id = 'term_' + new Date().getTime() + Math.random().toString(36).substr(2, 9);
            const div = document.createElement('div');
            div.className = "flex justify-between items-start bg-gray-50 p-2 rounded border border-gray-200 text-[11px]";
            div.id = id;
            div.innerHTML = `
                <span class="text-gray-700 leading-snug flex-1 mr-2">${content}</span>
                <input type="hidden" name="terms[]" value="${content}">
                <button type="button" onclick="document.getElementById('${id}').remove()" class="text-red-400 hover:text-red-600 focus:outline-none"><i class="fas fa-times"></i></button>
            `;
            container.appendChild(div);
        }

        function toggleProformaFields(value) {
            const details = document.getElementById('proforma_details');
            if (!details) return;
            if (value === 'yes') {
                details.classList.remove('hidden');
            } else {
                details.classList.add('hidden');
            }
        }

        function toggleThirdPartySection(value) {
            const section = document.getElementById('third_party_costs_section');
            if (value === 'yes') {
                section.classList.remove('hidden');
                if (document.getElementById('third-party-body').children.length === 0) {
                    addThirdPartyCost();
                }
            } else {
                section.classList.add('hidden');
            }
        }

        function addThirdPartyCost() {
            const tbody = document.getElementById('third-party-body');
            const rowCount = tbody.children.length;
            const row = document.createElement('tr');
            row.className = "group hover:bg-gray-50 transition-colors";

            row.innerHTML = `
                <td class="p-2 align-top">
                    <input type="text" name="third_party_costs[${rowCount}][supplier]" placeholder="Supplier Name" class="w-full rounded-md border-gray-200 text-[10px] py-1 px-1 font-mono">
                </td>
                <td class="p-2 align-top">
                    <input type="number" step="0.01" name="third_party_costs[${rowCount}][cost]" placeholder="0.00" class="w-full rounded-md border-gray-200 text-[10px] py-1 px-1 text-right font-mono">
                </td>
                <td class="p-2 align-top">
                    <select name="third_party_costs[${rowCount}][department]" class="w-full rounded-md border-gray-200 text-[10px] py-1 px-1 font-mono">
                        <option value="">Select</option>
                        <option value="creative">Creative</option>
                        <option value="digital">Digital</option>
                        <option value="play">Play</option>
                        <option value="tech">Tech</option>
                    </select>
                </td>
                <td class="p-2 align-top">
                    <input type="file" name="third_party_costs[${rowCount}][file]" class="w-full text-[8px] text-gray-500 file:mr-1 file:py-0.5 file:px-1 file:rounded file:border-0 file:text-[8px] file:bg-brand-blue file:bg-opacity-10 file:text-brand-blue">
                </td>
                <td class="p-2 align-top text-center">
                    <button type="button" onclick="this.closest('tr').remove()" class="text-gray-300 hover:text-red-500 transition-colors">
                        <i class="fas fa-trash-alt text-xs"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        }

        document.addEventListener('DOMContentLoaded', function () {
            @if($estimate->terms)
                @foreach(explode(', ', $estimate->terms) as $term)
                    addTerm("{!! addslashes($term) !!}");
                @endforeach
            @endif
            calculateTotals();

            const checkedThirdParty = document.querySelector('input[name="third_party_cost"]:checked');
            if (checkedThirdParty) toggleThirdPartySection(checkedThirdParty.value);

            Sortable.create(document.getElementById('items-body'), {
                handle: '.drag-handle',
                animation: 150,
                ghostClass: 'sortable-ghost',
                onEnd: function () {
                    document.querySelectorAll('#items-body tr').forEach(function (tr, i) {
                        tr.querySelectorAll('[name]').forEach(function (el) {
                            el.name = el.name.replace(/items\[[^\]]+\]/, 'items[' + i + ']');
                        });
                        const posInput = tr.querySelector('input[name*="[position]"]');
                        if (posInput) posInput.value = i;
                    });
                }
            });

            new TomSelect('#brand_name_select', {
                create: true
            });
        });

        function toggleProformaFields(value) {
            const details = document.getElementById('proforma_details');
            if (!details) return;
            if (value === 'yes') {
                details.classList.remove('hidden');
            } else {
                details.classList.add('hidden');
            }
        }
    </script>
@endsection