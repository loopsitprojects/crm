@extends('layouts.app')

@section('header', 'Business Intelligence Reports')

@section('content')
<div class="flex flex-col space-y-8" x-data="{ 
    columns: $persist({
        deals: ['date', 'title', 'customer', 'owner', 'type', 'stage', 'revenue'],
        invoices: ['date', 'invoice_no', 'customer', 'deal', 'status', 'amount']
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
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
                <!-- Column 1: Period -->
                <div class="space-y-2 col-span-1 md:col-span-1">
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Analysis Period</label>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <span class="text-[9px] text-gray-400 block mb-1">From</span>
                            <input type="date" name="start_date" value="{{ $startDate->format('Y-m-d') }}" 
                                class="w-full px-3 py-2 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none bg-gray-50/30">
                        </div>
                        <div>
                            <span class="text-[9px] text-gray-400 block mb-1">To</span>
                            <input type="date" name="end_date" value="{{ $endDate->format('Y-m-d') }}" 
                                class="w-full px-3 py-2 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none bg-gray-50/30">
                        </div>
                    </div>
                </div>

                <!-- Column 2: Department -->
                <div class="space-y-2">
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Department / Category</label>
                    <select name="department" class="w-full px-3 py-2 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none bg-gray-50/30 h-[38px]">
                        <option value="">All Departments</option>
                        @foreach(['Creative', 'Digital', 'Tech'] as $d)
                            <option value="{{ $d }}" {{ $department == $d ? 'selected' : '' }}>{{ $d }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Column 3: Customer & Stage -->
                <div class="space-y-2">
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Stage / Customer</label>
                    <div class="grid grid-cols-2 gap-2">
                        <select name="stage" class="w-full px-3 py-2 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none bg-gray-50/30 h-[38px]">
                            <option value="">All Stages</option>
                            @foreach($stages as $s)
                                <option value="{{ $s }}" {{ $stageFilter == $s ? 'selected' : '' }}>{{ $s }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="customer_name" value="{{ $customerName }}" placeholder="Customer..."
                            class="w-full px-3 py-2 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none bg-gray-50/30 h-[38px]">
                    </div>
                </div>

                <!-- Column 4: Actions -->
                <div class="flex space-x-2">
                    <button type="submit" class="flex-1 px-4 py-2 bg-brand-blue text-white text-[10px] font-black uppercase tracking-widest rounded-lg hover:bg-brand-purple transition-all shadow-md active:scale-95 h-[38px]">
                        <i class="fas fa-sync-alt mr-1"></i> Update
                    </button>
                    <a href="{{ route('reports.index') }}" class="px-4 py-2 bg-gray-100 text-gray-500 text-[10px] font-black uppercase tracking-widest rounded-lg hover:bg-gray-200 transition-all text-center flex items-center h-[38px]">
                        Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Removed Summary Tabs section as requested -->


    <!-- Removed Detailed Report section as requested -->

    <!-- Detailed Report Section -->
    @include('reports.partials.detailed_report')

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

    <!-- Original Breakdown Table -->
</div>
@endsection
