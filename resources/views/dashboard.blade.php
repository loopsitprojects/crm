@extends('layouts.app')

@section('header', 'System Dashboard')

@section('content')
    <div class="flex flex-col space-y-8">
        <!-- Filtering Bar -->
        <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100">
            <form action="{{ route('dashboard') }}" method="GET" class="flex flex-wrap items-end gap-4">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Date Range</label>
                    <div class="flex items-center space-x-2">
                        <input type="date" name="start_date" value="{{ $startDate->format('Y-m-d') }}" 
                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none">
                        <span class="text-gray-400">to</span>
                        <input type="date" name="end_date" value="{{ $endDate->format('Y-m-d') }}" 
                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Type</label>
                    <select name="type" class="px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none bg-white min-w-[150px]">
                        <option value="all" {{ $type == 'all' ? 'selected' : '' }}>All Departments</option>
                        <option value="creative" {{ $type == 'creative' ? 'selected' : '' }}>Creative</option>
                        <option value="digital" {{ $type == 'digital' ? 'selected' : '' }}>Digital</option>
                        <option value="play" {{ $type == 'play' ? 'selected' : '' }}>Play</option>
                        <option value="tech" {{ $type == 'tech' ? 'selected' : '' }}>Tech</option>
                    </select>
                </div>

                <div class="flex space-x-2">
                    <button type="submit" class="px-6 py-2 bg-brand-blue text-white text-sm font-bold rounded-lg hover:bg-brand-purple transition-all shadow-md active:scale-95">
                        Apply Filters
                    </button>
                    <a href="{{ route('dashboard') }}" class="px-4 py-2 bg-gray-100 text-gray-600 text-sm font-bold rounded-lg hover:bg-gray-200 transition-all">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Primary Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Revenue Card -->
            <div class="bg-white rounded-2xl shadow-sm p-6 border border-gray-100 relative overflow-hidden group hover:shadow-md transition-shadow">
                <div class="absolute top-0 right-0 w-32 h-32 -mr-8 -mt-8 bg-green-50 rounded-full opacity-50 group-hover:scale-110 transition-transform"></div>
                <div class="relative">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-green-100 rounded-xl text-green-600">
                            <i class="fas fa-hand-holding-usd text-xl"></i>
                        </div>
                        <span class="text-[10px] font-bold text-green-600 bg-green-50 px-2 py-0.5 rounded-full uppercase">Revenue</span>
                    </div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Total Collected</p>
                    <h3 class="text-2xl font-black text-slate-800">LKR {{ number_format($revenue, 2) }}</h3>
                    <p class="text-[11px] text-gray-500 mt-2">Paid Invoices in selected period</p>
                </div>
            </div>

            <!-- Pipeline Card -->
            <div class="bg-white rounded-2xl shadow-sm p-6 border border-gray-100 relative overflow-hidden group hover:shadow-md transition-shadow">
                <div class="absolute top-0 right-0 w-32 h-32 -mr-8 -mt-8 bg-brand-purple bg-opacity-5 rounded-full opacity-50 group-hover:scale-110 transition-transform"></div>
                <div class="relative">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-brand-purple bg-opacity-10 rounded-xl text-brand-purple">
                            <i class="fas fa-project-diagram text-xl"></i>
                        </div>
                        <span class="text-[10px] font-bold text-brand-purple bg-brand-purple bg-opacity-5 px-2 py-0.5 rounded-full uppercase">Pipeline</span>
                    </div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Open Deals Revenue</p>
                    <h3 class="text-2xl font-black text-slate-800">LKR {{ number_format($pipelineRevenue, 2) }}</h3>
                    <p class="text-[11px] text-gray-500 mt-2">{{ $dealCount }} new deals created</p>
                </div>
            </div>

            <!-- Conversion Card -->
            <div class="bg-white rounded-2xl shadow-sm p-6 border border-gray-100 relative overflow-hidden group hover:shadow-md transition-shadow">
                <div class="absolute top-0 right-0 w-32 h-32 -mr-8 -mt-8 bg-blue-50 rounded-full opacity-50 group-hover:scale-110 transition-transform"></div>
                <div class="relative">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-blue-100 rounded-xl text-blue-600">
                            <i class="fas fa-percentage text-xl"></i>
                        </div>
                        <span class="text-[10px] font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full uppercase">Conversion</span>
                    </div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Estimate to Invoice</p>
                    <h3 class="text-2xl font-black text-slate-800">{{ $conversionRate }}%</h3>
                    <p class="text-[11px] text-gray-500 mt-2">{{ $estimateCount }} estimates issued</p>
                </div>
            </div>

            <!-- Pending Payments -->
            <div class="bg-white rounded-2xl shadow-sm p-6 border border-gray-100 relative overflow-hidden group hover:shadow-md transition-shadow">
                <div class="absolute top-0 right-0 w-32 h-32 -mr-8 -mt-8 bg-red-50 rounded-full opacity-50 group-hover:scale-110 transition-transform"></div>
                <div class="relative">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-red-100 rounded-xl text-red-600">
                            <i class="fas fa-exclamation-circle text-xl"></i>
                        </div>
                        <span class="text-[10px] font-bold text-red-600 bg-red-50 px-2 py-0.5 rounded-full uppercase">Receivables</span>
                    </div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Outstanding Amount</p>
                    <h3 class="text-2xl font-black text-slate-800">LKR {{ number_format($pendingPayments, 2) }}</h3>
                    <p class="text-[11px] text-gray-500 mt-2">Unpaid & Overdue Invoices</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Performance Chart -->
            <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm p-6 border border-gray-100">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-black text-slate-800 flex items-center">
                        <i class="fas fa-chart-line mr-3 text-brand-pink"></i> Performance Overview
                    </h3>
                    <div class="flex space-x-2">
                        <span class="flex items-center text-[10px] font-bold text-gray-500">
                            <span class="w-2 h-2 rounded-full bg-brand-pink mr-1.5"></span> Revenue
                        </span>
                        <span class="flex items-center text-[10px] font-bold text-gray-500">
                            <span class="w-2 h-2 rounded-full bg-brand-blue mr-1.5"></span> Deals
                        </span>
                    </div>
                </div>
                <div class="h-[300px] flex items-center justify-center bg-gray-50 rounded-xl border border-dashed border-gray-200">
                    <p class="text-gray-400 text-sm italic">Growth visualization will appear here as more data is collected</p>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="bg-white rounded-2xl shadow-sm p-6 border border-gray-100">
                <h3 class="text-lg font-black text-slate-800 mb-6 flex items-center">
                    <i class="fas fa-history mr-3 text-brand-teal"></i> Recent Updates
                </h3>
                <div class="space-y-6">
                    <div>
                        <h4 class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-3 border-b border-gray-50 pb-1">Latest Invoices</h4>
                        <div class="space-y-3">
                            @forelse($recentInvoices as $invoice)
                                <div class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 transition-colors">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-xs">
                                            <i class="fas fa-file-invoice"></i>
                                        </div>
                                        <div>
                                            <p class="text-xs font-bold text-slate-800">{{ $invoice->customer->name ?? 'N/A' }}</p>
                                            <p class="text-[10px] text-gray-500">{{ $invoice->created_at->format('M d, Y') }}</p>
                                        </div>
                                    </div>
                                    <p class="text-xs font-black text-slate-900">LKR {{ number_format($invoice->total_amount, 0) }}</p>
                                </div>
                            @empty
                                <p class="text-xs text-gray-400 italic">No recent invoices found</p>
                            @endforelse
                        </div>
                    </div>

                    <div>
                        <h4 class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-3 border-b border-gray-50 pb-1">Latest Deals</h4>
                        <div class="space-y-3">
                            @forelse($recentDeals as $deal)
                                <div class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 transition-colors">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center text-xs">
                                            <i class="fas fa-briefcase"></i>
                                        </div>
                                        <div>
                                            <p class="text-xs font-bold text-slate-800 truncate max-w-[120px]">{{ $deal->title }}</p>
                                            <p class="text-[10px] text-gray-500">{{ $deal->stage }}</p>
                                        </div>
                                    </div>
                                    <p class="text-xs font-black text-slate-900">LKR {{ number_format($deal->revenue, 0) }}</p>
                                </div>
                            @empty
                                <p class="text-xs text-gray-400 italic">No recent deals found</p>
                            @endforelse
                        </div>
                    </div>
                </div>
                
                <div class="mt-8 pt-6 border-t border-gray-100">
                    <a href="{{ route('activities.index') }}" class="block text-center text-xs font-bold text-brand-blue hover:text-brand-purple transition-colors">
                        View Full Activity Log <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
