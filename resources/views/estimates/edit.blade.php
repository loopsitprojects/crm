@extends('layouts.app')

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
        <form action="{{ route('estimates.update', $estimate->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Left Column: Client & Basic Info -->
                <div class="lg:col-span-2 space-y-8">

                    <!-- Section: Client Details -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
                            <h3 class="text-base font-semibold text-gray-800 uppercase tracking-wider text-xs">Client
                                Details</h3>
                            <i class="fas fa-user-tie text-gray-400"></i>
                        </div>
                        <div class="p-6 space-y-4">
                            <!-- Customer Select -->
                            <div class="grid grid-cols-12 gap-4 items-center">
                                <label
                                    class="col-span-12 sm:col-span-3 text-right text-sm font-medium text-gray-600">Customer
                                    <span class="text-red-500">*</span></label>
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

                            <!-- Attention To -->
                            <div class="grid grid-cols-12 gap-4 items-center">
                                <label
                                    class="col-span-12 sm:col-span-3 text-right text-sm font-medium text-gray-600">Attention
                                    To <span class="text-red-500">*</span></label>
                                <div class="col-span-12 sm:col-span-9">
                                    <input type="text" name="attention_to"
                                        value="{{ old('attention_to', $estimate->attention_to) }}"
                                        placeholder="E.g. Mr. John Doe"
                                        class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm shadow-sm py-2">
                                </div>
                            </div>

                            <!-- Designation -->
                            <div class="grid grid-cols-12 gap-4 items-center">
                                <label
                                    class="col-span-12 sm:col-span-3 text-right text-sm font-medium text-gray-600">Designation</label>
                                <div class="col-span-12 sm:col-span-9">
                                    <input type="text" name="designation"
                                        value="{{ old('designation', $estimate->designation) }}"
                                        placeholder="E.g. Senior Manager"
                                        class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm shadow-sm py-2">
                                </div>
                            </div>

                            <!-- Address Group -->
                            <div class="grid grid-cols-12 gap-4 items-start">
                                <label
                                    class="col-span-12 sm:col-span-3 text-right text-sm font-medium text-gray-600 pt-2">Address</label>
                                <div class="col-span-12 sm:col-span-9 space-y-2">
                                    <input type="text" name="address_line_1"
                                        value="{{ old('address_line_1', $estimate->address_line_1) }}"
                                        placeholder="Address Line 1"
                                        class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm shadow-sm py-2">
                                    <input type="text" name="address_line_2"
                                        value="{{ old('address_line_2', $estimate->address_line_2) }}"
                                        placeholder="Address Line 2"
                                        class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm shadow-sm py-2">
                                    <input type="text" name="address_line_3"
                                        value="{{ old('address_line_3', $estimate->address_line_3) }}"
                                        placeholder="Address Line 3"
                                        class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm shadow-sm py-2">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Quote Items -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
                            <h3 class="text-base font-semibold text-gray-800 uppercase tracking-wider text-xs">Estimate
                                Items</h3>
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
                        <div class="p-0 overflow-x-auto">
                            <table class="w-full min-w-[800px]" id="items-table">
                                <thead class="bg-gray-50 border-b border-gray-100">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">
                                            Description</th>
                                        <th class="px-2 py-3 text-right text-xs font-bold text-gray-500 uppercase w-20">Qty
                                        </th>
                                        <th class="px-2 py-3 text-right text-xs font-bold text-gray-500 uppercase w-32">Unit
                                            Price</th>
                                        <th class="px-2 py-3 text-right text-xs font-bold text-gray-500 uppercase w-32">
                                            Amount</th>
                                        <th class="px-2 py-3 text-right text-xs font-bold text-gray-500 uppercase w-24">Tax
                                        </th>
                                        <th class="px-2 py-3 text-left text-xs font-bold text-gray-500 uppercase w-32">Meta
                                        </th>
                                        <th class="px-2 py-3 text-center text-xs font-bold text-gray-500 uppercase w-12">
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50" id="items-body">
                                    @foreach($estimate->items as $index => $item)
                                        <tr class="group hover:bg-gray-50 transition-colors">
                                            <td class="p-2 align-top">
                                                <textarea name="items[{{ $index }}][description]" rows="2"
                                                    placeholder="Item Description"
                                                    class="w-full rounded-md border-gray-200 focus:border-brand-blue focus:ring-brand-blue text-sm py-2 px-3 resize-none bg-transparent">{{ $item->description }}</textarea>
                                            </td>
                                            <td class="p-2 align-top">
                                                <input type="number" name="items[{{ $index }}][quantity]"
                                                    value="{{ $item->quantity }}" min="1" oninput="calculateRow(this)"
                                                    class="w-full rounded-md border-gray-200 focus:border-brand-blue focus:ring-brand-blue text-sm py-1.5 px-2 text-right">
                                            </td>
                                            <td class="p-2 align-top">
                                                <input type="number" step="0.01" name="items[{{ $index }}][unit_price]"
                                                    value="{{ $item->unit_price }}" min="0" oninput="calculateRow(this)"
                                                    class="w-full rounded-md border-gray-200 focus:border-brand-blue focus:ring-brand-blue text-sm py-1.5 px-2 text-right">
                                            </td>
                                            <td class="p-2 align-top">
                                                <input type="number" step="0.01" name="items[{{ $index }}][amount]"
                                                    value="{{ $item->amount }}" placeholder="0.00" readonly
                                                    class="w-full border-none bg-transparent text-sm py-1.5 px-2 text-right font-medium text-gray-700">
                                            </td>
                                            <td class="p-2 align-top">
                                                <div class="space-y-1">
                                                    <input type="text" readonly name="items[{{ $index }}][sscl_amount]"
                                                        value="{{ $item->sscl_amount }}" placeholder="SSCL"
                                                        class="w-full text-xs text-right border-none bg-transparent text-gray-500 py-0"
                                                        title="SSCL">
                                                    <input type="text" readonly name="items[{{ $index }}][vat_amount]"
                                                        value="{{ $item->vat_amount }}" placeholder="VAT"
                                                        class="w-full text-xs text-right border-none bg-transparent text-gray-500 py-0"
                                                        title="VAT">
                                                </div>
                                            </td>
                                            <td class="p-2 align-top space-y-2">
                                                <select name="items[{{ $index }}][item_heading]"
                                                    class="w-full rounded-md border-gray-200 text-xs py-1 px-2">
                                                    <option value="">Head</option>
                                                    <option value="General" {{ $item->item_heading == 'General' ? 'selected' : '' }}>General</option>
                                                </select>
                                                <input type="text" name="items[{{ $index }}][locations]"
                                                    value="{{ $item->locations }}" placeholder="Loc"
                                                    class="w-full rounded-md border-gray-200 text-xs py-1 px-2">
                                                <select name="items[{{ $index }}][days]"
                                                    class="w-full rounded-md border-gray-200 text-xs py-1 px-2">
                                                    <option value="">Day</option>
                                                    @for($i = 1; $i <= 31; $i++)
                                                        <option value="{{ $i }}" {{ $item->days == $i ? 'selected' : '' }}>{{ $i }}
                                                        </option>
                                                    @endfor
                                                </select>
                                            </td>
                                            <td class="p-2 align-top text-center">
                                                <button type="button" onclick="this.closest('tr').remove();"
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
                </div>

                <!-- Right Column: Settings & Terms -->
                <div class="lg:col-span-1 space-y-8">
                    <!-- Section: Quote Info -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
                            <h3 class="text-base font-semibold text-gray-800 uppercase tracking-wider text-xs">General Info
                            </h3>
                            <i class="fas fa-info-circle text-gray-400"></i>
                        </div>
                        <div class="p-6 space-y-4">
                            <!-- Date -->
                            <div>
                                <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Date <span
                                        class="text-red-500">*</span></label>
                                <input type="date" name="date" value="{{ old('date', $estimate->date) }}" required
                                    class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm py-2">
                            </div>
                            <!-- Currency -->
                            <div>
                                <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Currency <span
                                        class="text-red-500">*</span></label>
                                <select name="currency"
                                    class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm py-2">
                                    @if($currencies->isEmpty())
                                        <option value="LKR" {{ $estimate->currency == 'LKR' ? 'selected' : '' }}>LKR (Rs)
                                            (Default)</option>
                                    @else
                                        @foreach($currencies as $currency)
                                            <option value="{{ $currency->code }}" {{ $estimate->currency == $currency->code ? 'selected' : '' }}>
                                                {{ $currency->code }} ({{ $currency->symbol }})
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                            <!-- Heading -->
                            <div>
                                <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Project
                                    Heading</label>
                                <input type="text" name="heading" value="{{ old('heading', $estimate->heading) }}"
                                    placeholder="E.g. Web Development"
                                    class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm py-2">
                            </div>
                        </div>
                    </div>

                    <!-- Section: Terms & Payment -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
                            <h3 class="text-base font-semibold text-gray-800 uppercase tracking-wider text-xs">Terms &
                                Payment</h3>
                            <i class="fas fa-file-contract text-gray-400"></i>
                        </div>
                        <div class="p-6 space-y-4">
                            <!-- Terms -->
                            <div>
                                <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Standard Terms</label>
                                <div class="relative">
                                    <select id="term_selector" onchange="addTerm(this.value); this.value='';"
                                        class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm py-2">
                                        <option value="">-- Add Standard Terms --</option>
                                        @foreach($standardTerms as $term)
                                            <option value="{{ $term->content }}">{{ Str::limit($term->content, 50) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div id="selected_terms_container" class="mt-2 space-y-2">
                                    <!-- Selected terms will appear here -->
                                </div>
                            </div>
                            <!-- Advance -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Advance
                                        Req?</label>
                                    <select name="advance_payment"
                                        class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm py-2">
                                        <option value="yes" {{ $estimate->advance_payment == 'yes' ? 'selected' : '' }}>Yes
                                        </option>
                                        <option value="no" {{ $estimate->advance_payment == 'no' ? 'selected' : '' }}>No
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Percentage
                                        %</label>
                                    <input type="number" step="0.01" name="advance_percentage"
                                        value="{{ old('advance_percentage', $estimate->advance_percentage) }}"
                                        placeholder="50"
                                        class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2">
                                </div>
                            </div>
                            <!-- Special Terms -->
                            <div>
                                <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Special Terms</label>
                                <textarea name="special_terms" rows="2" placeholder="Any custom conditions..."
                                    class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm py-2">{{ old('special_terms', $estimate->special_terms) }}</textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Approval -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
                            <h3 class="text-base font-semibold text-gray-800 uppercase tracking-wider text-xs">Approval</h3>
                            <i class="fas fa-signature text-gray-400"></i>
                        </div>
                        <div class="p-6 space-y-4">
                            <!-- Manager -->
                            <div>
                                <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Senior Manager <span
                                        class="text-red-500">*</span></label>
                                <select name="senior_manager"
                                    class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm py-2">
                                    <option value="">-- Select Manager --</option>
                                    @foreach(\App\Models\SeniorManager::all() as $manager)
                                        <option value="{{ $manager->name }}" {{ old('senior_manager', $estimate->senior_manager) == $manager->name ? 'selected' : '' }}>{{ $manager->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <!-- Note -->
                            <div>
                                <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Internal Note</label>
                                <textarea name="additional_notes" rows="2" placeholder="Notes for the team..."
                                    class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm py-2">{{ old('additional_notes', $estimate->additional_notes) }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hidden Ref Number (Generated) -->
            <input type="hidden" name="reference_number" value="{{ $estimate->reference_number }}">

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
        // Same scripts as create, but handles dynamic indexing if needed.
        // For simplicity, we use DateTime to unique-ify new items, but standard array index is okay too
        function addItem() {
            const tbody = document.getElementById('items-body');
            const newIndex = new Date().getTime(); // Unique ID to avoid collisions

            const row = document.createElement('tr');
            row.className = "group hover:bg-gray-50 transition-colors";

            row.innerHTML = `
                                                            <td class="p-2 align-top">
                                                                <textarea name="items[${newIndex}][description]" rows="2" placeholder="Item Description" class="w-full rounded-md border-gray-200 focus:border-brand-blue focus:ring-brand-blue text-sm py-2 px-3 resize-none bg-transparent"></textarea>
                                                            </td>
                                                            <td class="p-2 align-top">
                                                                <input type="number" name="items[${newIndex}][quantity]" value="1" min="1" oninput="calculateRow(this)" class="w-full rounded-md border-gray-200 focus:border-brand-blue focus:ring-brand-blue text-sm py-1.5 px-2 text-right">
                                                            </td>
                                                            <td class="p-2 align-top">
                                                                <input type="number" step="0.01" name="items[${newIndex}][unit_price]" value="0" min="0" oninput="calculateRow(this)" class="w-full rounded-md border-gray-200 focus:border-brand-blue focus:ring-brand-blue text-sm py-1.5 px-2 text-right">
                                                            </td>
                                                            <td class="p-2 align-top">
                                                                <input type="number" step="0.01" name="items[${newIndex}][amount]" placeholder="0.00" readonly class="w-full border-none bg-transparent text-sm py-1.5 px-2 text-right font-medium text-gray-700">
                                                            </td>
                                                             <td class="p-2 align-top">
                                                                 <div class="space-y-1">
                                                                    <input type="text" readonly name="items[${newIndex}][sscl_amount]" placeholder="SSCL" class="w-full text-xs text-right border-none bg-transparent text-gray-500 py-0" title="SSCL">
                                                                    <input type="text" readonly name="items[${newIndex}][vat_amount]" placeholder="VAT" class="w-full text-xs text-right border-none bg-transparent text-gray-500 py-0" title="VAT">
                                                                 </div>
                                                            </td>
                                                            <td class="p-2 align-top space-y-2">
                                                                <select name="items[${newIndex}][item_heading]" class="w-full rounded-md border-gray-200 text-xs py-1 px-2">
                                                                    <option value="">Head</option>
                                                                    <option value="General">General</option>
                                                                </select>
                                                                <input type="text" name="items[${newIndex}][locations]" placeholder="Loc" class="w-full rounded-md border-gray-200 text-xs py-1 px-2">
                                                                 <select name="items[${newIndex}][days]" class="w-full rounded-md border-gray-200 text-xs py-1 px-2">
                                                                        <option value="">Day</option>
                                                                         ${Array.from({ length: 31 }, (_, i) => `<option value="${i + 1}">${i + 1}</option>`).join('')}
                                                                </select>
                                                            </td>
                                                            <td class="p-2 align-top text-center">
                                                                <button type="button" onclick="this.closest('tr').remove();" class="text-gray-400 hover:text-red-500 transition-colors p-1 rounded-full hover:bg-red-50">
                                                                    <i class="fas fa-trash-alt"></i>
                                                                </button>
                                                            </td>
                                                        `;

            tbody.appendChild(row);
            // Trigger calc for initial state
            calculateRow(row.querySelector('input[name*="[quantity]"]'));
        }


        const ssclRate = {{ $ssclRate / 100 }};
        const vatRate = {{ $vatRate / 100 }};

        function calculateRow(input) {
            const row = input.closest('tr');
            const qty = parseFloat(row.querySelector('input[name*="[quantity]"]').value) || 0;
            const price = parseFloat(row.querySelector('input[name*="[unit_price]"]').value) || 0;

            const amount = qty * price;

            // Update input value
            row.querySelector('input[name*="[amount]"]').value = amount.toFixed(2);

            const ssclApplicable = document.getElementById('sscl_applicable').checked;
            const vatApplicable = document.getElementById('vat_applicable').checked;

            let sscl = 0;
            let vat = 0;

            if (ssclApplicable) sscl = amount * ssclRate;
            if (vatApplicable) vat = (amount + sscl) * vatRate;

            const ssclInput = row.querySelector('input[name*="[sscl_amount]"]');
            const vatInput = row.querySelector('input[name*="[vat_amount]"]');

            if (ssclInput) {
                ssclInput.value = sscl > 0 ? sscl.toFixed(2) : '';
                ssclInput.style.display = ssclApplicable ? 'block' : 'none';
            }

            if (vatInput) {
                vatInput.value = vat > 0 ? vat.toFixed(2) : '';
                vatInput.style.display = vatApplicable ? 'block' : 'none';
            }
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
            div.className = "flex justify-between items-start bg-gray-50 p-2 rounded border border-gray-200 text-sm";
            div.id = id;
            div.innerHTML = `
                                            <span class="text-gray-700 leading-snug flex-1 mr-2">${content}</span>
                                            <input type="hidden" name="terms[]" value="${content}">
                                            <button type="button" onclick="document.getElementById('${id}').remove()" class="text-red-400 hover:text-red-600 focus:outline-none"><i class="fas fa-times"></i></button>
                                        `;
            container.appendChild(div);
        }

        document.addEventListener('DOMContentLoaded', function () {
            @if($estimate->terms)
                @foreach(explode(', ', $estimate->terms) as $term)
                    addTerm("{!! addslashes($term) !!}");
                @endforeach
            @endif
            calculateAllRows();
        });
    </script>
@endsection