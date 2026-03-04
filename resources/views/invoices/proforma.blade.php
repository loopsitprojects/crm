@extends('layouts.app')

@section('header', 'Invoices')

@section('content')
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-700">Proforma Invoices</h3>
        </div>

        <!-- TABS -->
        <div class="bg-gray-50 px-6 py-2 border-b border-gray-200 flex space-x-4">
            <a href="{{ route('invoices.ready') }}"
                class="px-3 py-1 rounded-md {{ request()->routeIs('invoices.ready') ? 'bg-green-100 text-green-700 font-semibold' : 'text-gray-600 hover:bg-gray-200' }}">Ready
                to Invoice</a>
            <a href="{{ route('invoices.index') }}"
                class="px-3 py-1 rounded-md {{ request()->routeIs('invoices.index') ? 'bg-blue-100 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-200' }}">Invoices</a>
            <a href="{{ route('invoices.proforma') }}"
                class="px-3 py-1 rounded-md {{ request()->routeIs('invoices.proforma') ? 'bg-purple-100 text-purple-700 font-semibold' : 'text-gray-600 hover:bg-gray-200' }}">Proforma
                Invoices</a>
            <a href="{{ route('invoices.invoiced') }}"
                class="px-3 py-1 rounded-md {{ request()->routeIs('invoices.invoiced') ? 'bg-indigo-100 text-indigo-700 font-semibold' : 'text-gray-600 hover:bg-gray-200' }}">Invoiced
                Estimates</a>
            <a href="{{ route('invoices.rejected') }}"
                class="px-3 py-1 rounded-md {{ request()->routeIs('invoices.rejected') ? 'bg-red-100 text-red-700 font-semibold' : 'text-gray-600 hover:bg-gray-200' }}">Rejected
                Invoices</a>
        </div>

        <!-- Search and Filters -->
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
            <form action="{{ url()->current() }}" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div>
                    <label for="search" class="block text-xs font-medium text-gray-500 uppercase mb-1">Search</label>
                    <input type="text" name="search" id="search" value="{{ request('search') }}"
                        placeholder="Invoice # or Customer"
                        class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-brand-blue focus:border-brand-blue sm:text-sm h-10 px-3 border">
                </div>
                <div>
                    <label for="from_date" class="block text-xs font-medium text-gray-500 uppercase mb-1">From Date</label>
                    <input type="date" name="from_date" id="from_date" value="{{ request('from_date') }}"
                        class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-brand-blue focus:border-brand-blue sm:text-sm h-10 px-3 border">
                </div>
                <div>
                    <label for="to_date" class="block text-xs font-medium text-gray-500 uppercase mb-1">To Date</label>
                    <input type="date" name="to_date" id="to_date" value="{{ request('to_date') }}"
                        class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-brand-blue focus:border-brand-blue sm:text-sm h-10 px-3 border">
                </div>
                <div class="flex space-x-2">
                    <button type="submit"
                        class="flex-1 px-4 py-2 bg-brand-blue text-white rounded-md hover:bg-brand-purple text-sm font-medium transition-colors h-10">
                        <i class="fas fa-filter mr-2"></i>Filter
                    </button>
                    <a href="{{ url()->current() }}"
                        class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 text-sm font-medium transition-colors h-10 flex items-center">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice #
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($invoices as $invoice)
                        <tr class="hover:bg-purple-50 transition-colors">
                            <td class="px-6 py-4 white-space-nowrap text-sm font-medium text-gray-900">
                                <span class="inline-flex items-center">
                                    <i class="fas fa-file-invoice text-purple-600 mr-2"></i>
                                    {{ $invoice->invoice_number }}
                                </span>
                            </td>
                            <td class="px-6 py-4 white-space-nowrap text-sm text-gray-500">{{ $invoice->customer->name }}</td>
                            <td class="px-6 py-4 white-space-nowrap text-sm text-gray-500">{{ $invoice->date }}</td>
                            <td class="px-6 py-4 white-space-nowrap text-sm text-gray-500">{{ $invoice->due_date }}</td>
                            <td class="px-6 py-4 white-space-nowrap text-sm text-gray-900 font-bold">
                                LKR {{ number_format($invoice->total_amount, 2) }}</td>
                            <td class="px-6 py-4 white-space-nowrap">
                                <form action="{{ route('invoices.updateStatus', $invoice) }}" method="POST">
                                    @csrf
                                    <select name="status" onchange="this.form.submit()"
                                        class="text-xs font-semibold rounded-full px-2 py-1 border-none focus:ring-0 cursor-pointer
                                                                                                                                            @if($invoice->status == 'unpaid') bg-yellow-100 text-yellow-800
                                                                                                                                            @elseif($invoice->status == 'paid') bg-green-100 text-green-800
                                                                                                                                            @elseif($invoice->status == 'overdue') bg-red-100 text-red-800
                                                                                                                                            @endif">
                                        <option value="unpaid" {{ $invoice->status == 'unpaid' ? 'selected' : '' }}>Unpaid
                                        </option>
                                        <option value="paid" {{ $invoice->status == 'paid' ? 'selected' : '' }}>Paid</option>
                                        <option value="overdue" {{ $invoice->status == 'overdue' ? 'selected' : '' }}>Overdue
                                        </option>
                                    </select>
                                </form>
                            </td>
                            <td class="px-6 py-4 white-space-nowrap text-right text-sm font-medium flex justify-end space-x-2">
                                <a href="{{ route('invoices.show', $invoice) }}" class="text-brand-blue hover:text-brand-purple"
                                    title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                @if(auth()->user()->role === 'Super Admin')
                                    <a href="{{ route('invoices.edit', $invoice) }}" class="text-gray-600 hover:text-brand-blue mx-2" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                @endif
                                @if(auth()->user()->role === 'Super Admin')
                                    <form action="{{ route('invoices.duplicate', $invoice) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="text-brand-purple hover:text-brand-pink"
                                            title="Duplicate to Estimate">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-gray-500 text-sm">
                                <i class="fas fa-file-invoice text-4xl text-gray-300 mb-2"></i>
                                <p>No proforma invoices found.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection