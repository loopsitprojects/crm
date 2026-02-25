@extends('layouts.app')

@section('header', 'Jobs')

@section('content')
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-700">Jobs</h3>
        </div>

        <!-- Filter Bar -->
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
            <form action="{{ route('jobs.index') }}" method="GET" class="flex flex-wrap gap-4 items-end">
                <!-- Date Range -->
                <div class="flex flex-col space-y-1">
                    <label for="start_date" class="text-xs font-bold text-gray-700">From Date</label>
                    <input type="date" name="start_date" id="start_date" value="{{ request('start_date') }}"
                        class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-brand-purple text-sm">
                </div>
                <div class="flex flex-col space-y-1">
                    <label for="end_date" class="text-xs font-bold text-gray-700">To Date</label>
                    <input type="date" name="end_date" id="end_date" value="{{ request('end_date') }}"
                        class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-brand-purple text-sm">
                </div>

                <!-- Department Dropdown -->
                <div class="flex flex-col space-y-1">
                    <label for="department" class="text-xs font-bold text-gray-700">Department</label>
                    <select name="department" id="department"
                        class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-brand-purple text-sm min-w-[150px]">
                        <option value="">All Departments</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept }}" {{ request('department') == $dept ? 'selected' : '' }}>
                                {{ $dept }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Users Dropdown -->
                <div class="flex flex-col space-y-1">
                    <label for="user_id" class="text-xs font-bold text-gray-700">User</label>
                    <select name="user_id" id="user_id"
                        class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-brand-purple text-sm min-w-[150px]">
                        <option value="">All Users</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>
                                {{ $user->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Buttons -->
                <div class="flex gap-2 pb-0.5">
                    <button type="submit"
                        class="px-4 py-2 bg-brand-purple text-white text-sm font-medium rounded-md hover:opacity-90 transition-opacity">
                        Filter
                    </button>
                    <a href="{{ route('jobs.index') }}"
                        class="px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-300 transition-colors">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-slate-800 border-b border-slate-700">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-200 uppercase tracking-wider">Job ID
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-200 uppercase tracking-wider">Deal Name
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-200 uppercase tracking-wider">Customer
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-200 uppercase tracking-wider">Stage
                        </th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-slate-200 uppercase tracking-wider">Amount
                        </th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-slate-200 uppercase tracking-wider">Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($jobs as $job)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 white-space-nowrap">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-brand-purple text-white">
                                    {{ $job->job_number }}
                                </span>
                            </td>
                            <td class="px-6 py-4 white-space-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ $job->title }}</div>
                                <div class="text-xs text-gray-500">{{ $job->pipeline ?? 'Sales Pipeline' }}</div>
                            </td>
                            <td class="px-6 py-4 white-space-nowrap">
                                <div class="text-sm text-gray-900">{{ $job->customer->name ?? '-' }}</div>
                            </td>
                            <td class="px-6 py-4 white-space-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                            @if($job->stage == 'Won' || $job->stage == 'Approved') bg-green-100 text-green-800 
                                                            @elseif($job->stage == 'Lost' || $job->stage == 'Rejected') bg-red-100 text-red-800 
                                                            @elseif($job->stage == 'Pitched') bg-purple-100 text-purple-800
                                                            @else bg-blue-100 text-blue-800 @endif">
                                    {{ $job->stage }}
                                </span>
                            </td>
                            <td class="px-6 py-4 white-space-nowrap text-right">
                                <div class="text-sm font-medium text-gray-900">
                                    {{ $job->currency }} {{ number_format($job->amount, 2) }}
                                </div>
                            </td>
                            <td class="px-6 py-4 white-space-nowrap text-right text-sm font-medium">
                                <a href="{{ route('deals.index', ['deal_id' => $job->id]) }}"
                                    class="px-3 py-1 bg-brand-blue/10 text-brand-blue hover:bg-brand-blue hover:text-white rounded-lg text-xs font-bold transition-all inline-flex items-center">
                                    <i class="fas fa-eye mr-1.5"></i> View Details
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-gray-500 text-sm">
                                No jobs found. Jobs are created when a deal reaches the "Pitched" stage.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Reusing Edit Deal Modal logic if needed, but for now just viewing -->
    <!-- Ideally, clicking View Deal would open the deal modal or go to deals page highlighting it. 
                     For simplicity, we can link to the deals page or just show basic info. 
                     Given the user wants to see "Jobs", a simple list is what they asked for.
                     I'll leave the Edit/View link as a placeholder or remove the onclick if JS isn't available here.
                     To make editDeal work here, I'd need to include the modal and JS from deals.index. 
                     For now, I'll just link to deals index? Or maybe just text.
                     The prompt implies a separate tab for Jobs, so listing them is the priority.
                -->
@endsection