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

        /* Override Quill header labels */
        .ql-snow .ql-picker.ql-header .ql-picker-label[data-value="1"]::before,
        .ql-snow .ql-picker.ql-header .ql-picker-item[data-value="1"]::before {
            content: 'Heading' !important;
        }
        .ql-snow .ql-picker.ql-header .ql-picker-label[data-value="2"]::before,
        .ql-snow .ql-picker.ql-header .ql-picker-item[data-value="2"]::before {
            content: 'Sub heading' !important;
        }
    </style>
@endpush

@section('header')
    <div class="flex items-center justify-between px-6 py-4 bg-white border-b border-gray-200">
        <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fas fa-file-invoice text-brand-pink"></i> Create New Estimate
        </h2>
        <a href="{{ route('estimates.index') }}" class="text-sm text-gray-500 hover:text-gray-700 transition-colors">
            Cancel
        </a>
    </div>
@endsection

@section('content')
    <div class="max-w-7xl mx-auto my-8 px-4 sm:px-6 lg:px-8">
        <form id="estimate-form" action="{{ route('estimates.store') }}" method="POST" enctype="multipart/form-data" novalidate>
            @csrf

            @if ($errors->any())
                <div class="mb-6 flex items-center bg-white border border-gray-200 p-3 shadow-sm rounded-md max-w-fit">
                    <div class="bg-orange-500 text-white w-8 h-8 flex items-center justify-center rounded mr-4 flex-shrink-0">
                        <i class="fas fa-exclamation text-lg font-bold"></i>
                    </div>
                    <div class="text-sm text-gray-800 font-medium">
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if(isset($deal))
                <input type="hidden" name="deal_id" value="{{ $deal->id }}">
            @endif



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
                                    <select name="customer_id" required data-required="true"
                                        class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm shadow-sm py-2">
                                        <option value="">-- Select Customer --</option>
                                        @foreach($customers as $customer)
                                            <option value="{{ $customer->id }}" {{ (isset($deal) && $deal->customer_id == $customer->id) ? 'selected' : '' }}>{{ $customer->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <!-- Brand Name -->
                            <div class="grid grid-cols-12 gap-4 items-center">
                                <label class="col-span-12 sm:col-span-3 text-right text-sm font-medium text-gray-600">Brand Name <span class="text-red-500">*</span></label>
                                <div class="col-span-12 sm:col-span-9 brand-name-tom-select">
                                    <select name="brand_name" id="brand_name_select" required data-required="true"
                                        class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm shadow-sm py-2">
                                        <option value="">-- No Brand / Select Brand --</option>
                                        @foreach($brands as $brand)
                                            <option value="{{ $brand }}">{{ $brand }}</option>
                                        @endforeach
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
                                    <input type="text" name="attention_to" placeholder="E.g. Mr. John Doe" required data-required="true"
                                        class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm shadow-sm py-2">
                                </div>
                            </div>

                            <!-- Designation -->
                            <div class="grid grid-cols-12 gap-4 items-center">
                                <label class="col-span-12 sm:col-span-3 text-right text-sm font-medium text-gray-600">Designation</label>
                                <div class="col-span-12 sm:col-span-9">
                                    <input type="text" name="designation" placeholder="E.g. Senior Manager"
                                        class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm shadow-sm py-2">
                                </div>
                            </div>

                            <!-- Address Group -->
                            <div class="grid grid-cols-12 gap-4 items-start">
                                <label class="col-span-12 sm:col-span-3 text-right text-sm font-medium text-gray-600 pt-2">Address</label>
                                <div class="col-span-12 sm:col-span-9 space-y-2">
                                    <input type="text" name="address_line_1" placeholder="Address Line 1"
                                        class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm shadow-sm py-2">
                                    <input type="text" name="address_line_2" placeholder="Address Line 2"
                                        class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm shadow-sm py-2">
                                    <input type="text" name="address_line_3" placeholder="Address Line 3"
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
                                    <input type="checkbox" name="sscl_applicable" id="sscl_applicable" value="1" onchange="calculateAllRows()"
                                        class="rounded border-gray-300 text-brand-purple shadow-sm focus:ring-brand-purple">
                                    <span class="ml-2 text-xs font-semibold text-gray-600">SSCL ({{ number_format($ssclRate, 4) }}%)</span>
                                </label>
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="vat_applicable" id="vat_applicable" value="1" onchange="calculateAllRows()"
                                        class="rounded border-gray-300 text-brand-purple shadow-sm focus:ring-brand-purple">
                                    <span class="ml-2 text-xs font-semibold text-gray-600">VAT ({{ number_format($vatRate, 2) }}%)</span>
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

                    <!-- Section: Summary (Totals) - REQUESTED IN MAIN AREA -->
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
                                    <input type="text" name="reference_number" value="{{ $nextReferenceNumber }}" readonly
                                        class="w-full pl-8 bg-gray-50 rounded-md border-gray-200 text-gray-500 text-sm py-2 font-mono font-bold"
                                        title="Auto-generated reference number">
                                </div>
                            </div>

                            <!-- Date -->
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Date <span class="text-red-500">*</span></label>
                                <input type="date" name="date" value="{{ date('Y-m-d') }}" required data-required="true"
                                    class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue text-sm py-2">
                            </div>

                            <!-- Currency -->
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Currency <span class="text-red-500">*</span></label>
                                <select name="currency" required data-required="true"
                                    class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue text-sm py-2">
                                    <option value="" selected>Select Currency</option>
                                    @if($currencies->isEmpty())
                                        <option value="LKR">LKR - Sri Lankan Rupee (Rs)</option>
                                    @else
                                        @foreach($currencies as $currency)
                                            <option value="{{ $currency->code }}">
                                                {{ $currency->code }} - {{ $currency->name }} ({{ $currency->symbol }})
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>

                            <!-- Heading -->
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Project Heading</label>
                                <input type="text" name="heading" value="{{ isset($deal) ? $deal->title : '' }}" placeholder="E.g. Web Development"
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
                                        <input type="radio" name="proforma_invoice" value="yes" checked onchange="toggleProformaFields(this.value)"
                                            class="w-4 h-4 text-brand-blue border-gray-300 focus:ring-brand-blue">
                                        <span class="text-sm font-medium text-gray-700">Yes</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="proforma_invoice" value="no" onchange="toggleProformaFields(this.value)"
                                            class="w-4 h-4 text-brand-blue border-gray-300 focus:ring-brand-blue">
                                        <span class="text-sm font-medium text-gray-700">No</span>
                                    </label>
                                </div>
                                <div id="proforma_details" class="space-y-3">
                                    <input type="number" step="1" name="proforma_percentage" placeholder="Percentage %"
                                        class="w-full rounded-md border-gray-200 text-sm py-1.5 px-3">
                                    <select name="proforma_tax" class="w-full rounded-md border-gray-200 text-xs py-1.5 px-3">
                                        <option value="with_tax">With Tax</option>
                                        <option value="without_tax">Without Tax</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Advance Received Amount -->
                            <div class="pt-4 border-t border-gray-50">
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Advance Received Amount</label>
                                <input type="number" step="0.01" name="advance_received_amount" placeholder="0.00"
                                    class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue text-sm py-2 shadow-sm">
                            </div>

                            <!-- Third Party Costs? -->
                            <div class="pt-4 border-t border-gray-50">
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-3">Third Party Costs? <span class="text-red-500">*</span></label>
                                <div class="flex items-center gap-6 mb-4">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="third_party_cost" value="yes" onchange="toggleThirdPartySection(this.value)"
                                            class="w-4 h-4 text-brand-blue border-gray-300 focus:ring-brand-blue">
                                        <span class="text-sm font-medium text-gray-700">Yes</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="third_party_cost" value="no" checked onchange="toggleThirdPartySection(this.value)"
                                            class="w-4 h-4 text-brand-blue border-gray-300 focus:ring-brand-blue">
                                        <span class="text-sm font-medium text-gray-700">No</span>
                                    </label>
                                </div>

                                <!-- Integrated Third Party Costs Table -->
                                <div id="third_party_costs_section" class="hidden mt-2 mb-4 border border-gray-100 rounded-lg overflow-hidden">
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

                            <!-- PO Applicable? -->
                            <div class="pt-4 border-t border-gray-50">
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-3">PO Applicable? <span class="text-red-500">*</span></label>
                                <div class="flex items-center gap-6 mb-4">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="po_applicable" value="yes" onchange="togglePoSection(this.value)"
                                            class="w-4 h-4 text-brand-blue border-gray-300 focus:ring-brand-blue">
                                        <span class="text-sm font-medium text-gray-700">Yes</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="po_applicable" value="no" checked onchange="togglePoSection(this.value)"
                                            class="w-4 h-4 text-brand-blue border-gray-300 focus:ring-brand-blue">
                                        <span class="text-sm font-medium text-gray-700">No</span>
                                    </label>
                                </div>

                                <div id="po_details" class="hidden space-y-3">
                                    <div>
                                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">PO Number <span class="text-red-500">*</span></label>
                                        <input type="text" name="po_number" id="po_number" placeholder="Enter PO Number"
                                            class="w-full rounded-md border-gray-200 text-sm py-1.5 px-3">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">PO Document <span class="text-red-500">*</span></label>
                                        <input type="file" name="po_document" id="po_document"
                                            class="w-full text-xs text-gray-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:bg-brand-blue file:bg-opacity-10 file:text-brand-blue">
                                    </div>
                                </div>
                            </div>

                            <!-- Special Terms -->
                            <div class="pt-4 border-t border-gray-50">
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Special Terms</label>
                                <textarea name="special_terms" rows="3" placeholder="Any custom conditions..."
                                    class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue text-sm py-2"></textarea>
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
                            <!-- Senior Manager -->
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Senior Manager <span class="text-red-500">*</span></label>
                                <select name="senior_manager" required data-required="true"
                                    class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue text-sm py-2">
                                    <option value="">-- Select Senior Manager --</option>
                                    @foreach($users as $user)
                                        @php
                                            $isSelected = old('senior_manager') == $user->name;
                                            if (!$isSelected && isset($deal)) {
                                                $dealOwner = $deal->senior_manager ?? ($deal->owner->name ?? null);
                                                if ($dealOwner == $user->name) {
                                                    $isSelected = true;
                                                }
                                            }
                                        @endphp
                                        <option value="{{ $user->name }}" {{ $isSelected ? 'selected' : '' }}>{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <!-- Note -->
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Internal Note</label>
                                <textarea name="additional_notes" rows="3" placeholder="Notes for the team..."
                                    class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue text-sm py-2"></textarea>
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
                    <i class="fas fa-save mr-2"></i> Save Estimate
                </button>
            </div>

        </form>
    </div>

    <!-- Scripts (restored to sidebar logic) -->
    <script>
        function initQuill(id, inputName) {
            const editorContainer = document.getElementById(id);
            const hiddenInput = document.querySelector(`input[name="${inputName}"]`);
            
            const quill = new Quill(editorContainer, {
                theme: 'snow',
                placeholder: 'Item description...',
                modules: {
                    toolbar: [
                        [{ 'header': [1, 2, false] }],
                        ['bold', 'italic', 'underline'],
                        [{ 'list': 'bullet' }]
                    ]
                }
            });

            // Set initial content if any
            if (hiddenInput.value) {
                quill.root.innerHTML = hiddenInput.value;
            }

            // Sync content to hidden input
            quill.on('text-change', function() {
                hiddenInput.value = quill.root.innerHTML;
                // Trigger auto-calculation if needed, though usually description doesn't affect price
            });

            return quill;
        }

        function addItem() {
            const tbody = document.getElementById('items-body');
            const rowCount = tbody.children.length;
            const row = document.createElement('tr');
            row.className = "group hover:bg-gray-50 transition-colors";

            const newIndex = new Date().getTime();
            const editorId = `editor-${newIndex}`;
            const inputName = `items[${rowCount}][description]`;

            row.innerHTML = `
                <td class="p-2 align-middle text-center drag-handle" title="Drag to reorder">
                    <i class="fas fa-grip-vertical text-gray-300"></i>
                </td>
                <td class="p-2 align-top">
                    <div class="quill-wrapper">
                        <div id="${editorId}" class="text-sm"></div>
                    </div>
                    <input type="hidden" name="${inputName}" required data-required="true">
                </td>
                <td class="p-2 align-top">
                    <select name="items[${rowCount}][department]" required data-required="true" class="w-full rounded-md border-gray-200 text-xs py-1 px-1">
                        <option value="">Select</option>
                        <option value="creative">Creative</option>
                        <option value="digital">Digital</option>
                        <option value="play">Play</option>
                        <option value="tech">Tech</option>
                        <option value="Corporate">Corporate</option>
                    </select>
                </td>
                <td class="p-2 align-top">
                    <select name="items[${rowCount}][revenue_category]" required data-required="true" class="w-full rounded-md border-gray-200 text-xs py-1 px-1">
                        <option value="">Select</option>
                        <option value="Retainer">Retainer</option>
                        <option value="Ads">Ads</option>
                        <option value="Campaigns/Projects">Campaigns/Projects</option>
                        <option value="CAG">CAG</option>
                        <option value="Corporate">Corporate</option>
                    </select>
                </td>
                <td class="p-2 align-top">
                    <input type="hidden" name="items[${rowCount}][position]" value="${rowCount}">
                    <input type="number" name="items[${rowCount}][quantity]" required data-required="true" value="" placeholder="1" oninput="calculateRow(this)" class="w-full rounded-md border-gray-200 text-sm py-1 px-1 text-right">
                </td>
                <td class="p-2 align-top">
                    <input type="number" step="0.01" name="items[${rowCount}][unit_price]" required data-required="true" value="" placeholder="0.00" oninput="calculateRow(this)" class="w-full rounded-md border-gray-200 text-sm py-1 px-1 text-right">
                </td>
                <td class="p-2 align-top">
                    <input type="number" step="0.01" name="items[${rowCount}][amount]" placeholder="0.00" readonly class="w-full border-none bg-transparent text-sm py-1 px-1 text-right font-medium text-gray-700">
                </td>
                <td class="p-2 align-top text-center">
                    <button type="button" onclick="this.closest('tr').remove(); calculateTotals();" class="text-gray-400 hover:text-red-500 transition-colors p-1 rounded-full hover:bg-red-50">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
            `;

            tbody.appendChild(row);
            initQuill(editorId, inputName);
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
            const inputs = section.querySelectorAll('input, select');
            
            if (value === 'yes') {
                section.classList.remove('hidden');
                inputs.forEach(input => {
                    // Make all fields mandatory except those that might be optional (none in this table)
                    if (input.name.includes('[supplier]') || 
                        input.name.includes('[cost]') || 
                        input.name.includes('[department]') || 
                        input.name.includes('[file]')) {
                        input.required = true;
                    }
                });
                if (document.getElementById('third-party-body').children.length === 0) {
                    addThirdPartyCost();
                }
            } else {
                section.classList.add('hidden');
                inputs.forEach(input => input.required = false);
            }
        }

        function togglePoSection(value) {
            const details = document.getElementById('po_details');
            const poNumber = document.getElementById('po_number');
            const poDocument = document.getElementById('po_document');
            if (value === 'yes') {
                details.classList.remove('hidden');
                poNumber.required = true;
                poDocument.required = true;
            } else {
                details.classList.add('hidden');
                poNumber.required = false;
                poDocument.required = false;
            }
        }

        function addThirdPartyCost() {
            const tbody = document.getElementById('third-party-body');
            const newIndex = 'new_' + new Date().getTime() + Math.random().toString(36).substr(2, 5);
            const row = document.createElement('tr');
            row.className = "group hover:bg-gray-50 transition-colors";

            const isRequired = document.querySelector('input[name="third_party_cost"]:checked')?.value === 'yes' ? 'required' : '';

            row.innerHTML = `
                <td class="p-2 align-top">
                    <input type="text" name="third_party_costs[${newIndex}][supplier]" ${isRequired} placeholder="Supplier Name" class="w-full rounded-md border-gray-200 text-[10px] py-1 px-1 font-mono">
                </td>
                <td class="p-2 align-top">
                    <input type="number" step="0.01" name="third_party_costs[${newIndex}][cost]" ${isRequired} placeholder="0.00" class="w-full rounded-md border-gray-200 text-[10px] py-1 px-1 text-right font-mono">
                </td>
                <td class="p-2 align-top">
                    <select name="third_party_costs[${newIndex}][department]" ${isRequired} class="w-full rounded-md border-gray-200 text-[10px] py-1 px-1 font-mono">
                        <option value="">Select</option>
                        <option value="creative">Creative</option>
                        <option value="digital">Digital</option>
                        <option value="play">Play</option>
                        <option value="tech">Tech</option>
                        <option value="Corporate">Corporate</option>
                    </select>
                </td>
                <td class="p-2 align-top">
                    <input type="file" name="third_party_costs[${newIndex}][file]" ${isRequired} class="w-full text-[8px] text-gray-500 file:mr-1 file:py-0.5 file:px-1 file:rounded file:border-0 file:text-[8px] file:bg-brand-blue file:bg-opacity-10 file:text-brand-blue">
                </td>
                <td class="p-2 align-top text-center">
                    <button type="button" onclick="this.closest('tr').remove()" class="text-gray-300 hover:text-red-500 transition-colors">
                        <i class="fas fa-trash-alt text-xs"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        }

        document.addEventListener('DOMContentLoaded', () => {
            addItem();
            calculateTotals();

            Sortable.create(document.getElementById('items-body'), {
                handle: '.drag-handle',
                animation: 150,
                ghostClass: 'sortable-ghost',
                onEnd: function () {
                    document.querySelectorAll('#items-body tr').forEach(function (tr, i) {
                        tr.querySelectorAll('[name]').forEach(function (el) {
                            el.name = el.name.replace(/items\[\d+\]/, 'items[' + i + ']');
                        });
                        const posInput = tr.querySelector('input[name*="[position]"]');
                        if (posInput) posInput.value = i;
                    });
                }
            });

            const brandSelectInstance = new TomSelect('#brand_name_select', {
                create: true
            });

            const checkedProforma = document.querySelector('input[name="proforma_invoice"]:checked');
            if (checkedProforma) toggleProformaFields(checkedProforma.value);

            const checkedThirdParty = document.querySelector('input[name="third_party_cost"]:checked');
            if (checkedThirdParty) toggleThirdPartySection(checkedThirdParty.value);

            // Customer Data for auto-populating fields
            @php
                $customersData = $customers->mapWithKeys(function($item) {
                     return [$item['id'] => [
                         'brand' => $item['brand'],
                         'attention' => $item['primary_contact_name'],
                         'designation' => $item['primary_contact_designation'],
                         'address' => $item['billing_address']
                     ]];
                 });
            @endphp
            const customersData = @json($customersData);

            const customerSelect = document.querySelector('select[name="customer_id"]');
            
            if (customerSelect) {
                customerSelect.addEventListener('change', function() {
                    const customerId = this.value;
                    const data = customersData[customerId];
                    
                    if (customerId && data) {
                        // Fill Brand
                        if (data.brand && brandSelectInstance) {
                            brandSelectInstance.addOption({value: data.brand, text: data.brand});
                            brandSelectInstance.setValue(data.brand);
                        } else if (brandSelectInstance) {
                            brandSelectInstance.clear();
                        }

                        // Fill Attention To
                        const attentionInput = document.querySelector('input[name="attention_to"]');
                        if (attentionInput) attentionInput.value = data.attention || '';

                        // Fill Designation
                        const designationInput = document.querySelector('input[name="designation"]');
                        if (designationInput) designationInput.value = data.designation || '';

                        // Fill Address
                        if (data.address) {
                            const lines = data.address.split(/\r?\n/);
                            document.querySelector('input[name="address_line_1"]').value = lines[0] || '';
                            document.querySelector('input[name="address_line_2"]').value = lines[1] || '';
                            document.querySelector('input[name="address_line_3"]').value = lines[2] || '';
                        } else {
                            document.querySelector('input[name="address_line_1"]').value = '';
                            document.querySelector('input[name="address_line_2"]').value = '';
                            document.querySelector('input[name="address_line_3"]').value = '';
                        }
                    } else {
                        if (brandSelectInstance) brandSelectInstance.clear();
                        document.querySelector('input[name="attention_to"]').value = '';
                        document.querySelector('input[name="designation"]').value = '';
                        document.querySelector('input[name="address_line_1"]').value = '';
                        document.querySelector('input[name="address_line_2"]').value = '';
                        document.querySelector('input[name="address_line_3"]').value = '';
                    }
                });
                
                // Trigger change immediately in case a customer is pre-selected
                if (customerSelect.value) {
                    customerSelect.dispatchEvent(new Event('change'));
                }

            }

            // Custom Form Validation
            function showError(input, message) {
                const parent = input.closest('td') || input.parentNode;
                if (!parent.classList.contains('relative')) parent.classList.add('relative');
                
                let errorDiv = parent.querySelector('.field-error');
                if (!errorDiv) {
                    errorDiv = document.createElement('div');
                    errorDiv.className = 'field-error text-[10px] text-red-500 mt-0.5 absolute left-2';
                    parent.appendChild(errorDiv);
                }
                errorDiv.textContent = message;

                // If it is a quill hidden input, put the border on the quill container itself
                if (input.classList.contains('quill-hidden-input')) {
                    const quillContainer = parent.querySelector('.quill-editor');
                    if (quillContainer) quillContainer.classList.add('border-red-500', 'ring-1', 'ring-red-500');
                } else {
                    input.classList.add('border-red-500', 'ring-1', 'ring-red-500');
                }
            }

            function clearErrors(form) {
                form.querySelectorAll('.field-error').forEach(e => e.remove());
                form.querySelectorAll('.border-red-500').forEach(el => el.classList.remove('border-red-500', 'ring-1', 'ring-red-500'));
            }

            const form = document.getElementById('estimate-form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    clearErrors(this);
                    let isValid = true;
                    let firstErrorField = null;

                    // Check Brand Select (TomSelect)
                    if (brandSelectInstance && !brandSelectInstance.getValue()) {
                        isValid = false;
                        const control = brandSelectInstance.control;
                        control.classList.add('border-red-500', 'ring-1', 'ring-red-500');
                        showError(control, 'Please select a brand');
                        if (!firstErrorField) firstErrorField = control;
                    }

                    // Check all data-required fields
                    this.querySelectorAll('[data-required="true"]').forEach(el => {
                        // Skip if it's the hidden select for brandSelectInstance handled above
                        if (el.id === 'brand_name_select') return;

                        let val = el.value;
                        // For Quill hidden fields, empty content might still have <p><br></p>
                        if (el.name.includes('[description]')) {
                            const tempDiv = document.createElement('div');
                            tempDiv.innerHTML = val;
                            val = tempDiv.textContent.trim();
                        }

                        if (!val || val.trim() === "" || (el.type === 'number' && parseFloat(val) <= 0)) {
                            isValid = false;
                            showError(el, 'Required');
                            if (!firstErrorField) {
                                // If hidden quill input, scroll to the parent td
                                firstErrorField = el.classList.contains('quill-hidden-input') ? (el.closest('td') || el.parentNode) : el;
                            }
                        }
                    });

                    if (!isValid) {
                        e.preventDefault();
                        if (firstErrorField) {
                            firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            if (typeof firstErrorField.focus === 'function') firstErrorField.focus();
                        }
                    }
                });
            }
        });
    </script>
@endsection