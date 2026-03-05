@extends('layouts.app')

@section('header', 'Business Intelligence Reports')

@section('content')
<div class="flex flex-col space-y-8" x-data="{ 
    columns: $persist({
        deals: ['date', 'title', 'customer', 'owner', 'type', 'stage', 'revenue'],
        invoices: ['date', 'invoice_no', 'customer', 'deal', 'status', 'amount'],
        contribution: ['month', 'net_revenue', 'sscl', 'vat', 'total_income']
    }).as('report_columns'),
    showPicker: false,
    isColumnVisible(type, col) {
        return this.columns[type].includes(col);
    },
    toggleColumn(type, col) {
        if (this.isColumnVisible(type, col)) {
            this.columns[type] = this.columns[type].filter(c => c !== col);
        } else {
            this.columns[type].push(col);
        }
    }
}">
    <!-- Professional Filter Grid (Matching Reference Style) -->
    <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100">
        <form action="{{ route('reports.index') }}" method="GET" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Column 1: Period -->
                <div class="space-y-4">
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400">Analysis Period</label>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <span class="text-[9px] text-gray-400 block mb-1">From</span>
                            <input type="date" name="start_date" value="{{ $startDate->format('Y-m-d') }}" 
                                class="w-full px-4 py-2.5 text-xs border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-purple outline-none bg-gray-50/30">
                        </div>
                        <div>
                            <span class="text-[9px] text-gray-400 block mb-1">To</span>
                            <input type="date" name="end_date" value="{{ $endDate->format('Y-m-d') }}" 
                                class="w-full px-4 py-2.5 text-xs border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-purple outline-none bg-gray-50/30">
                        </div>
                    </div>
                </div>

                <!-- Column 2: Customer & Department -->
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Customer Name</label>
                        <input type="text" name="customer_name" value="{{ $customerName }}" placeholder="Search by customer..."
                            class="w-full px-4 py-2.5 text-xs border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-purple outline-none bg-gray-50/30">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Department</label>
                        <select name="department" class="w-full px-4 py-2.5 text-xs border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-purple outline-none bg-gray-50/30 {{ $isRestricted ? 'opacity-50 cursor-not-allowed' : '' }}" {{ $isRestricted ? 'disabled' : '' }}>
                            <option value="">All Departments</option>
                            @foreach(['Creative', 'Digital', 'Play', 'Tech'] as $dept)
                                <option value="{{ $dept }}" {{ $department == $dept ? 'selected' : '' }}>{{ $dept }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Column 3: Stage & Actions -->
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Deal Stage</label>
                        <select name="stage" class="w-full px-4 py-2.5 text-xs border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-purple outline-none bg-gray-50/30">
                            <option value="">All Stages</option>
                            @foreach($stages as $stage)
                                <option value="{{ $stage }}" {{ $stageFilter == $stage ? 'selected' : '' }}>{{ $stage }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end space-x-2 pt-2">
                        <button type="submit" class="flex-1 px-6 py-2.5 bg-brand-blue text-white text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-brand-purple transition-all shadow-lg hover:shadow-brand-purple/20 active:scale-95">
                            <i class="fas fa-sync-alt mr-2"></i> Update Report
                        </button>
                        <a href="{{ route('reports.index') }}" class="px-6 py-2.5 bg-gray-100 text-gray-500 text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-gray-200 transition-all text-center">
                            Reset
                        </a>
                    </div>
                </div>
            </div>
            
            <input type="hidden" name="tab" value="{{ $activeTab }}">
        </form>
    </div>

    <!-- Enhanced Summary Stats (Interactive Tabs) -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Row 1 -->
        <a href="{{ request()->fullUrlWithQuery(['tab' => 'total_deals']) }}" class="group bg-white p-4 rounded-xl border {{ $activeTab == 'total_deals' ? 'border-brand-purple ring-2 ring-brand-purple/20' : 'border-gray-100' }} shadow-sm hover:shadow-md transition-all">
            <p class="text-[9px] font-black uppercase tracking-tighter text-gray-400 mb-1 group-hover:text-brand-purple transition-colors">Total Project Revenue</p>
            <h3 class="text-lg font-black {{ $activeTab == 'total_deals' ? 'text-brand-purple' : 'text-slate-700' }}">LKR {{ number_format($totalDealRevenue, 2) }}</h3>
        </a>
        <a href="{{ request()->fullUrlWithQuery(['tab' => 'open_deals']) }}" class="group bg-white p-4 rounded-xl border {{ $activeTab == 'open_deals' ? 'border-emerald-500 ring-2 ring-emerald-500/20' : 'border-gray-100' }} shadow-sm hover:shadow-md transition-all">
            <p class="text-[9px] font-black uppercase tracking-tighter text-gray-400 mb-1 group-hover:text-emerald-500 transition-colors">Open Deals</p>
            <h3 class="text-xl font-black {{ $activeTab == 'open_deals' ? 'text-emerald-500' : 'text-slate-700' }}">{{ $openDealsCount }}</h3>
        </a>
        <a href="{{ request()->fullUrlWithQuery(['tab' => 'weighted_amount']) }}" class="group bg-white p-4 rounded-xl border {{ $activeTab == 'weighted_amount' ? 'border-brand-blue ring-2 ring-brand-blue/20' : 'border-gray-100' }} shadow-sm hover:shadow-md transition-all">
            <p class="text-[9px] font-black uppercase tracking-tighter text-gray-400 mb-1 group-hover:text-brand-blue transition-colors">Weighted Revenue</p>
            <h3 class="text-lg font-black {{ $activeTab == 'weighted_amount' ? 'text-brand-blue' : 'text-slate-700' }}">LKR {{ number_format($weightedRevenue, 2) }}</h3>
        </a>
        <a href="{{ request()->fullUrlWithQuery(['tab' => 'approved_amount']) }}" class="group bg-white p-4 rounded-xl border {{ $activeTab == 'approved_amount' ? 'border-brand-pink ring-2 ring-brand-pink/20' : 'border-gray-100' }} shadow-sm hover:shadow-md transition-all">
            <p class="text-[9px] font-black uppercase tracking-tighter text-gray-400 mb-1 group-hover:text-brand-pink transition-colors">Approved Revenue</p>
            <h3 class="text-lg font-black {{ $activeTab == 'approved_amount' ? 'text-brand-pink' : 'text-slate-700' }}">LKR {{ number_format($approvedRevenue, 2) }}</h3>
        </a>
        
        <!-- Row 2 -->
        <a href="{{ request()->fullUrlWithQuery(['tab' => 'new_deals']) }}" class="group bg-white p-4 rounded-xl border {{ $activeTab == 'new_deals' ? 'border-cyan-500 ring-2 ring-cyan-500/20' : 'border-gray-100' }} shadow-sm hover:shadow-md transition-all">
            <p class="text-[9px] font-black uppercase tracking-tighter text-gray-400 mb-1 group-hover:text-cyan-500 transition-colors">New Revenue (30D)</p>
            <h3 class="text-lg font-black {{ $activeTab == 'new_deals' ? 'text-cyan-500' : 'text-slate-700' }}">LKR {{ number_format($newDeals30Revenue, 2) }}</h3>
        </a>
        <div class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm">
            <p class="text-[9px] font-black uppercase tracking-tighter text-gray-400 mb-1">Avg Deal Age</p>
            <h3 class="text-xl font-black text-slate-700">{{ round($avgDealAge) }} <span class="text-xs text-gray-400">days</span></h3>
        </div>
        <a href="{{ request()->fullUrlWithQuery(['tab' => 'invoiced']) }}" class="group bg-white p-4 rounded-xl border {{ $activeTab == 'invoiced' ? 'border-blue-600 ring-2 ring-blue-600/20' : 'border-gray-100' }} shadow-sm hover:shadow-md transition-all">
            <p class="text-[9px] font-black uppercase tracking-tighter text-gray-400 mb-1 group-hover:text-blue-600 transition-colors">Invoiced</p>
            <h3 class="text-lg font-black {{ $activeTab == 'invoiced' ? 'text-blue-600' : 'text-slate-700' }}">LKR {{ number_format($invoicedAmount, 2) }}</h3>
        </a>
        <a href="{{ request()->fullUrlWithQuery(['tab' => 'payment_collected']) }}" class="group bg-white p-4 rounded-xl border {{ $activeTab == 'payment_collected' ? 'border-green-600 ring-2 ring-green-600/20' : 'border-gray-100' }} shadow-sm hover:shadow-md transition-all">
            <p class="text-[9px] font-black uppercase tracking-tighter text-gray-400 mb-1 group-hover:text-green-600 transition-colors">Payment Collected</p>
            <h3 class="text-lg font-black {{ $activeTab == 'payment_collected' ? 'text-green-600' : 'text-slate-700' }}">LKR {{ number_format($paymentCollected, 2) }}</h3>
        </a>
        @if(!$isRestricted)
        <a href="{{ request()->fullUrlWithQuery(['tab' => 'contribution']) }}" class="group bg-brand-blue/5 p-4 rounded-xl border {{ $activeTab == 'contribution' ? 'border-brand-blue ring-2 ring-brand-blue/20' : 'border-gray-100' }} shadow-sm hover:shadow-md transition-all">
            <p class="text-[9px] font-black uppercase tracking-tighter text-brand-blue mb-1">Contribution</p>
            <h3 class="text-lg font-black {{ $activeTab == 'contribution' ? 'text-brand-blue' : 'text-slate-700' }}">LKR {{ number_format($paymentCollected, 2) }}</h3>
        </a>
        @endif
    </div>


    <!-- Detailed Tab Data (Premium Styling) -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-50 flex justify-between items-center bg-gray-50/30">
            <h4 class="text-sm font-black text-slate-800 uppercase tracking-wider flex items-center">
                <i class="fas fa-list-alt mr-2 text-blue-600"></i> 
                Detailed Report: <span class="ml-1 text-blue-600">{{ str_replace('_', ' ', ucwords($activeTab, '_')) }}</span>
            </h4>
            <div class="flex items-center space-x-3">
                <div class="relative" @click.away="showPicker = false">
                    <button @click="showPicker = !showPicker" 
                        class="px-4 py-2 bg-white border border-gray-200 text-gray-700 text-[10px] font-black uppercase tracking-widest rounded-lg hover:bg-gray-50 transition-all shadow-sm flex items-center">
                        <i class="fas fa-columns mr-2"></i> Columns
                    </button>
                    
                    <div x-show="showPicker" 
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="transform opacity-0 scale-95"
                        x-transition:enter-end="transform opacity-100 scale-100"
                        class="absolute right-0 mt-2 w-56 bg-white border border-gray-200 rounded-xl shadow-xl z-30 overflow-hidden"
                        style="display: none;">
                        <div class="p-4">
                            <h5 class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-4">Visible Columns</h5>
                            <div class="space-y-3">
                                @if(in_array($activeTab, ['total_deals', 'open_deals', 'weighted_amount', 'approved_amount', 'new_deals']))
                                    @foreach([
                                        'date' => 'Date',
                                        'title' => 'Title',
                                        'customer' => 'Customer',
                                        'owner' => 'Owner',
                                        'type' => 'Type',
                                        'stage' => 'Stage',
                                        'revenue' => 'Revenue'
                                    ] as $key => $label)
                                        <label class="flex items-center group cursor-pointer">
                                            <input type="checkbox" :checked="isColumnVisible('deals', '{{ $key }}')" @change="toggleColumn('deals', '{{ $key }}')"
                                                class="w-4 h-4 text-brand-blue border-gray-200 rounded focus:ring-brand-blue transition-colors">
                                            <span class="ml-3 text-xs font-bold text-slate-600 group-hover:text-brand-blue transition-colors">{{ $label }}</span>
                                        </label>
                                    @endforeach
                                @elseif(in_array($activeTab, ['invoiced', 'payment_collected']))
                                    @foreach([
                                        'date' => 'Date',
                                        'invoice_no' => 'Invoice #',
                                        'customer' => 'Customer',
                                        'deal' => 'Deal',
                                        'status' => 'Status',
                                        'amount' => 'Amount'
                                    ] as $key => $label)
                                        <label class="flex items-center group cursor-pointer">
                                            <input type="checkbox" :checked="isColumnVisible('invoices', '{{ $key }}')" @change="toggleColumn('invoices', '{{ $key }}')"
                                                class="w-4 h-4 text-brand-blue border-gray-200 rounded focus:ring-brand-blue transition-colors">
                                            <span class="ml-3 text-xs font-bold text-slate-600 group-hover:text-brand-blue transition-colors">{{ $label }}</span>
                                        </label>
                                    @endforeach
                                @elseif($activeTab == 'contribution')
                                    @foreach([
                                        'month' => 'Month',
                                        'net_revenue' => 'Net Revenue',
                                        'sscl' => 'SSCL (2.5%)',
                                        'vat' => 'VAT (18%)',
                                        'total_income' => 'Total Income'
                                    ] as $key => $label)
                                        <label class="flex items-center group cursor-pointer">
                                            <input type="checkbox" :checked="isColumnVisible('contribution', '{{ $key }}')" @change="toggleColumn('contribution', '{{ $key }}')"
                                                class="w-4 h-4 text-brand-blue border-gray-200 rounded focus:ring-brand-blue transition-colors">
                                            <span class="ml-3 text-xs font-bold text-slate-600 group-hover:text-brand-blue transition-colors">{{ $label }}</span>
                                        </label>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                        <div class="bg-gray-50 p-3 border-t border-gray-100 flex justify-end">
                            <button @click="showPicker = false" class="text-[10px] font-black uppercase tracking-widest text-brand-blue hover:text-brand-purple">Close</button>
                        </div>
                    </div>
                </div>

                <a href="{{ route('reports.export', array_merge(request()->all(), ['type' => in_array($activeTab, ['invoiced', 'payment_collected']) ? 'invoices' : 'deals'])) }}" 
                    class="px-4 py-2 bg-emerald-600 text-white text-[10px] font-black uppercase tracking-widest rounded-lg hover:bg-emerald-700 transition-all shadow-sm flex items-center">
                    <i class="fas fa-file-excel mr-2"></i> Generate Excel
                </a>
                <button onclick="window.print()" class="px-4 py-2 bg-blue-600 text-white text-[10px] font-black uppercase tracking-widest rounded-lg hover:bg-blue-700 transition-all shadow-sm flex items-center">
                    <i class="fas fa-file-pdf mr-2"></i> Generate PDF
                </button>
            </div>
        </div>
        <div class="overflow-x-auto">
            @if(in_array($activeTab, ['total_deals', 'open_deals', 'weighted_amount', 'approved_amount', 'new_deals']))
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-blue-600 text-white">
                            <th x-show="isColumnVisible('deals', 'date')" class="px-6 py-4 text-[10px] font-black uppercase tracking-widest border-b border-blue-700">Date</th>
                            <th x-show="isColumnVisible('deals', 'title')" class="px-6 py-4 text-[10px] font-black uppercase tracking-widest border-b border-blue-700">Title</th>
                            <th x-show="isColumnVisible('deals', 'customer')" class="px-6 py-4 text-[10px] font-black uppercase tracking-widest border-b border-blue-700">Customer</th>
                            <th x-show="isColumnVisible('deals', 'owner')" class="px-6 py-4 text-[10px] font-black uppercase tracking-widest border-b border-blue-700">Owner</th>
                            <th x-show="isColumnVisible('deals', 'type')" class="px-6 py-4 text-[10px] font-black uppercase tracking-widest border-b border-blue-700">Type</th>
                            <th x-show="isColumnVisible('deals', 'stage')" class="px-6 py-4 text-[10px] font-black uppercase tracking-widest border-b border-blue-700">Stage</th>
                            <th x-show="isColumnVisible('deals', 'revenue')" class="px-6 py-4 text-[10px] font-black uppercase tracking-widest border-b border-blue-700 text-right">Revenue (LKR)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse($detailedData as $deal)
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td x-show="isColumnVisible('deals', 'date')" class="px-6 py-4 text-xs font-medium text-slate-500">{{ $deal->created_at->format('Y-m-d') }}</td>
                                <td x-show="isColumnVisible('deals', 'title')" class="px-6 py-4 text-xs font-bold text-slate-800">{{ $deal->title }}</td>
                                <td x-show="isColumnVisible('deals', 'customer')" class="px-6 py-4 text-xs text-slate-600">{{ $deal->customer->name ?? 'N/A' }}</td>
                                <td x-show="isColumnVisible('deals', 'owner')" class="px-6 py-4 text-xs text-slate-600">{{ $deal->owner->name ?? 'N/A' }}</td>
                                <td x-show="isColumnVisible('deals', 'type')" class="px-6 py-4 text-[10px] uppercase font-black tracking-widest text-gray-400">{{ $deal->type }}</td>
                                <td x-show="isColumnVisible('deals', 'stage')" class="px-6 py-4">
                                    <span class="text-[10px] font-black py-1 px-2 uppercase rounded-full bg-blue-50 text-brand-blue">
                                        {{ $deal->stage }}
                                    </span>
                                </td>
                                <td x-show="isColumnVisible('deals', 'revenue')" class="px-6 py-4 text-right">
                                    <span class="text-xs font-black text-slate-900">{{ number_format($deal->revenue, 2) }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-gray-400 italic text-xs">No records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif(in_array($activeTab, ['invoiced', 'payment_collected']))
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-blue-600 text-white">
                            <th x-show="isColumnVisible('invoices', 'date')" class="px-6 py-4 text-[10px] font-black uppercase tracking-widest border-b border-blue-700">Date</th>
                            <th x-show="isColumnVisible('invoices', 'invoice_no')" class="px-6 py-4 text-[10px] font-black uppercase tracking-widest border-b border-blue-700">Invoice #</th>
                            <th x-show="isColumnVisible('invoices', 'customer')" class="px-6 py-4 text-[10px] font-black uppercase tracking-widest border-b border-blue-700">Customer</th>
                            <th x-show="isColumnVisible('invoices', 'deal')" class="px-6 py-4 text-[10px] font-black uppercase tracking-widest border-b border-blue-700">Deal</th>
                            <th x-show="isColumnVisible('invoices', 'status')" class="px-6 py-4 text-[10px] font-black uppercase tracking-widest border-b border-blue-700">Status</th>
                            <th x-show="isColumnVisible('invoices', 'amount')" class="px-6 py-4 text-[10px] font-black uppercase tracking-widest border-b border-blue-700 text-right">Amount (LKR)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse($detailedData as $invoice)
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td x-show="isColumnVisible('invoices', 'date')" class="px-6 py-4 text-xs font-medium text-slate-500">{{ $invoice->created_at->format('Y-m-d') }}</td>
                                <td x-show="isColumnVisible('invoices', 'invoice_no')" class="px-6 py-4 text-xs font-bold text-slate-800">{{ $invoice->invoice_number }}</td>
                                <td x-show="isColumnVisible('invoices', 'customer')" class="px-6 py-4 text-xs text-slate-600">{{ $invoice->customer->name ?? 'N/A' }}</td>
                                <td x-show="isColumnVisible('invoices', 'deal')" class="px-6 py-4 text-xs text-slate-600">{{ $invoice->estimate->deal->title ?? 'N/A' }}</td>
                                <td x-show="isColumnVisible('invoices', 'status')" class="px-6 py-4">
                                    <span class="text-[10px] font-black py-1 px-2 uppercase rounded-full {{ $invoice->status == 'paid' ? 'bg-green-50 text-green-600' : 'bg-yellow-50 text-yellow-600' }}">
                                        {{ $invoice->status }}
                                    </span>
                                </td>
                                <td x-show="isColumnVisible('invoices', 'amount')" class="px-6 py-4 text-right">
                                    <span class="text-xs font-black text-slate-900">{{ number_format($invoice->total_amount, 2) }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-gray-400 italic text-xs">No records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif($activeTab == 'contribution' && !$isRestricted)
                <div class="p-6">
                    <h5 class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-4">Consolidated Monthly Summary</h5>
                    <table class="w-full text-left mb-8 border border-gray-100 rounded-xl overflow-hidden">
                        <thead>
                            <tr class="bg-brand-blue text-white">
                                <th x-show="isColumnVisible('contribution', 'month')" class="px-6 py-3 text-[10px] font-black uppercase tracking-widest">Month</th>
                                <th x-show="isColumnVisible('contribution', 'net_revenue')" class="px-6 py-3 text-[10px] font-black uppercase tracking-widest text-right">Net Revenue</th>
                                <th x-show="isColumnVisible('contribution', 'sscl')" class="px-6 py-3 text-[10px] font-black uppercase tracking-widest text-right">SSCL (2.5%)</th>
                                <th x-show="isColumnVisible('contribution', 'vat')" class="px-6 py-3 text-[10px] font-black uppercase tracking-widest text-right">VAT (18%)</th>
                                <th x-show="isColumnVisible('contribution', 'total_income')" class="px-6 py-3 text-[10px] font-black uppercase tracking-widest text-right">Total Income</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($incomeBreakdown as $row)
                                <tr class="hover:bg-gray-50/50">
                                    <td x-show="isColumnVisible('contribution', 'month')" class="px-6 py-3 text-xs font-bold text-slate-800">{{ Carbon\Carbon::parse($row->month . '-01')->format('F Y') }}</td>
                                    <td x-show="isColumnVisible('contribution', 'net_revenue')" class="px-6 py-3 text-xs font-black text-slate-700 text-right">{{ number_format($row->net_revenue, 2) }}</td>
                                    <td x-show="isColumnVisible('contribution', 'sscl')" class="px-6 py-3 text-xs font-black text-brand-purple text-right">{{ number_format($row->sscl_total, 2) }}</td>
                                    <td x-show="isColumnVisible('contribution', 'vat')" class="px-6 py-3 text-xs font-black text-brand-pink text-right">{{ number_format($row->vat_total, 2) }}</td>
                                    <td x-show="isColumnVisible('contribution', 'total_income')" class="px-6 py-3 text-xs font-black text-emerald-600 text-right">{{ number_format($row->gross_revenue, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <h5 class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-4">Detailed Transaction Log</h5>
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-blue-600 text-white">
                                <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest border-b border-blue-700">Date</th>
                                <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest border-b border-blue-700">Invoice #</th>
                                <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest border-b border-blue-700">Customer</th>
                                <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest border-b border-blue-700">Total (Net + Taxes)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @forelse($detailedData as $invoice)
                                <tr class="hover:bg-gray-50/50 transition-colors">
                                    <td class="px-6 py-4 text-xs font-medium text-slate-500">{{ $invoice->created_at->format('Y-m-d') }}</td>
                                    <td class="px-6 py-4 text-xs font-bold text-slate-800">{{ $invoice->invoice_number }}</td>
                                    <td class="px-6 py-4 text-xs text-slate-600">{{ $invoice->customer->name ?? 'N/A' }}</td>
                                    <td class="px-6 py-4 text-right">
                                        <span class="text-xs font-black text-slate-900">{{ number_format($invoice->total_amount, 2) }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-8 text-center text-gray-400 italic text-xs">No records found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        @if($detailedData && $detailedData->hasPages())
            <div class="px-6 py-4 bg-gray-50/20 border-t border-gray-50">
                {{ $detailedData->appends(request()->all())->links() }}
            </div>
        @endif
    </div>

    <!-- Charts & Detailed Lists -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Revenue Trend -->
        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
            <h4 class="text-sm font-black text-slate-800 mb-6 uppercase tracking-wider flex items-center">
                <i class="fas fa-chart-line mr-2 text-brand-purple"></i> Revenue Trend
            </h4>
            <div class="h-[250px] flex items-center justify-center bg-gray-50/50 rounded-xl border border-dashed border-gray-200">
                @if($dailyRevenue->count() > 0)
                    <div class="text-center group p-8">
                        <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center shadow-sm mx-auto mb-4 group-hover:scale-110 transition-transform">
                            <i class="fas fa-check text-green-500"></i>
                        </div>
                        <p class="text-slate-800 text-xs font-bold">{{ $dailyRevenue->count() }} days of data available.</p>
                        <p class="text-gray-400 text-[10px] mt-1 italic">Visual charts will be rendered here.</p>
                    </div>
                @else
                    <div class="text-center group p-8">
                        <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center shadow-sm mx-auto mb-4 group-hover:scale-110 transition-transform">
                            <i class="fas fa-database text-gray-300"></i>
                        </div>
                        <p class="text-gray-400 text-xs italic">No daily revenue data for this range.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Deals by Stage -->
        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
            <h4 class="text-sm font-black text-slate-800 mb-6 uppercase tracking-wider flex items-center">
                <i class="fas fa-chart-pie mr-2 text-brand-blue"></i> Deals by Stage
            </h4>
            <div class="space-y-4">
                @forelse($dealsByStage as $stage)
                    <div class="relative pt-1">
                        <div class="flex items-center justify-between mb-2">
                            <div>
                                <span class="text-[10px] font-black py-1 px-2 uppercase rounded-full text-brand-blue bg-blue-50">
                                    {{ $stage->stage }}
                                </span>
                            </div>
                            <div class="text-right">
                                <span class="text-xs font-black text-slate-800">
                                    LKR {{ number_format($stage->total, 0) }}
                                </span>
                                <span class="text-[10px] text-gray-400 ml-1">({{ $stage->count }} deals)</span>
                            </div>
                        </div>
                        <div class="overflow-hidden h-2 text-xs flex rounded-full bg-gray-100">
                            @php 
                                $percentage = $totalDealRevenue > 0 ? ($stage->total / $totalDealRevenue) * 100 : 0;
                            @endphp
                            <div style="width:{{ $percentage }}%" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-brand-blue transition-all duration-1000"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-gray-400 text-xs italic text-center py-10">No deal data found for this period.</p>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Original Breakdown Table (Now Secondary) -->
    @if(!$isRestricted)
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-50 flex justify-between items-center bg-gray-50/20">
            <h4 class="text-sm font-black text-slate-800 uppercase tracking-wider flex items-center">
                <i class="fas fa-chart-pie mr-2 text-brand-teal"></i> Financial Contribution by Department
            </h4>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-gray-50/50">
                        <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-gray-400 border-b border-gray-100">Department / Type</th>
                        <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-gray-400 border-b border-gray-100 text-right">Revenue (LKR)</th>
                        <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-gray-400 border-b border-gray-100 text-right">Contribution</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($revenueByDept as $dept)
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-6 py-4">
                                <span class="text-xs font-bold text-slate-800">{{ $dept->type ?: 'Unspecified' }}</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <span class="text-xs font-black text-slate-900">{{ number_format($dept->total, 2) }}</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                @php 
                                    $contribution = $paymentCollected > 0 ? ($dept->total / $paymentCollected) * 100 : 0;
                                @endphp
                                <span class="text-[10px] font-black px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-600">
                                    {{ round($contribution, 1) }}%
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-6 py-8 text-center text-gray-400 italic text-xs">
                                No department-wise data available for the selected period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection
