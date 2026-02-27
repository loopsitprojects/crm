@extends('layouts.app')

@section('header', 'Estimates')

@section('content')
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-700">Manage Estimates</h3>
            <a href="{{ route('estimates.create') }}"
                class="px-4 py-2 bg-brand-pink text-white rounded-md hover:bg-brand-purple text-sm font-medium transition-colors">
                <i class="fas fa-plus mr-2"></i>Create Estimate
            </a>
        </div>


        <!-- Search and Filters -->
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
            <form action="{{ route('estimates.index') }}" method="GET"
                class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div>
                    <label for="search" class="block text-xs font-medium text-gray-500 uppercase mb-1">Search</label>
                    <input type="text" name="search" id="search" value="{{ request('search') }}"
                        placeholder="Reference or Customer"
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
                    <a href="{{ route('estimates.index') }}"
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
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Brand
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($estimates as $estimate)
                        @php
                            $user = auth()->user();
                            $isRestricted = !in_array($user->role, ['Super Admin', 'Management']);
                        @endphp
                        <tr>
                            <td class="px-6 py-4 white-space-nowrap text-sm font-medium text-gray-900">
                                {{ $estimate->reference_number }}
                            </td>
                            <td class="px-6 py-4 white-space-nowrap text-sm text-gray-500">{{ $estimate->customer->name }}</td>
                            <td class="px-6 py-4 white-space-nowrap text-sm text-gray-500">
                                @if($estimate->brand_name)
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                        {{ $estimate->brand_name }}
                                    </span>
                                @else
                                    <span class="text-gray-400 text-xs italic">No Brand</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 white-space-nowrap text-sm text-gray-500">{{ $estimate->date }}</td>
                            <td class="px-6 py-4 white-space-nowrap text-sm text-gray-900 font-bold">
                                ${{ number_format($estimate->total_amount, 2) }}</td>
                            <td class="px-6 py-4 white-space-nowrap">
                                @if($isRestricted && $estimate->status != 'draft')
                                    <span class="text-xs font-semibold rounded-full px-2 py-1 inline-block
                                                                                        @if($estimate->status == 'approved') bg-yellow-100 text-yellow-800
                                                                                        @elseif($estimate->status == 'accepted' || $estimate->status == 'ready_to_invoice') bg-green-100 text-green-800
                                                                                        @elseif($estimate->status == 'rejected') bg-red-100 text-red-800
                                                                                        @elseif($estimate->status == 'invoiced') bg-blue-100 text-blue-800
                                                                                        @endif">
                                        {{ $estimate->status == 'ready_to_invoice' ? 'Ready to Invoice' : ucfirst($estimate->status == 'accepted' ? 'Ready to Invoice (Old)' : $estimate->status) }}
                                    </span>
                                @else
                                    <form action="{{ route('estimates.updateStatus', $estimate) }}" method="POST">
                                        @csrf
                                        <select name="status" onchange="this.form.submit()" class="text-xs font-semibold rounded-full px-2 py-1 border-none focus:ring-0 cursor-pointer w-full
                                                                                                    @if($estimate->status == 'draft') bg-gray-100 text-gray-800
                                                                                                    @elseif($estimate->status == 'approved') bg-yellow-100 text-yellow-800
                                                                                                    @elseif($estimate->status == 'accepted' || $estimate->status == 'ready_to_invoice') bg-green-100 text-green-800
                                                                                                    @elseif($estimate->status == 'rejected') bg-red-100 text-red-800
                                                                                                    @elseif($estimate->status == 'invoiced') bg-blue-100 text-blue-800
                                                                                                    @endif">
                                            @if($isRestricted)
                                                <!-- Restricted User Options (Draft -> Ready to Invoice only) -->
                                                <option value="draft" {{ $estimate->status == 'draft' ? 'selected' : '' }}>Pending (Draft)
                                                </option>
                                                <option value="ready_to_invoice">Ready to Invoice</option>
                                            @else
                                                <!-- Admin Options -->
                                                <option value="draft" {{ $estimate->status == 'draft' ? 'selected' : '' }}>Pending (Draft)
                                                </option>
                                                <option value="approved" {{ $estimate->status == 'approved' ? 'selected' : '' }}>Approved
                                                </option>
                                                <option value="rejected" {{ $estimate->status == 'rejected' ? 'selected' : '' }}>Rejected
                                                </option>
                                                <option value="ready_to_invoice" {{ $estimate->status == 'ready_to_invoice' ? 'selected' : '' }}>Ready to Invoice</option>
                                                <option value="accepted" {{ $estimate->status == 'accepted' ? 'selected' : '' }}>Accepted
                                                    (Legacy)</option>
                                                <option value="invoiced" {{ $estimate->status == 'invoiced' ? 'selected' : '' }} disabled>
                                                    Invoiced</option>
                                            @endif
                                        </select>
                                    </form>
                                @endif
                            </td>
                            <td
                                class="px-6 py-4 white-space-nowrap text-right text-sm font-medium flex justify-end gap-2 items-center">
                                <!-- View -->
                                <a href="{{ route('estimates.show', $estimate) }}"
                                    class="text-brand-blue hover:text-brand-purple" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>

                                <!-- Edit (Restricted: Only if Draft) -->
                                @if(!$isRestricted || $estimate->status == 'draft')
                                    <a href="{{ route('estimates.edit', $estimate) }}" class="text-gray-600 hover:text-brand-blue"
                                        title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                @endif

                                <!-- Delete (Restricted: Only if Draft) -->
                                @if(!$isRestricted || $estimate->status == 'draft')
                                    <form action="{{ route('estimates.destroy', $estimate) }}" method="POST"
                                        onsubmit="return confirm('Are you sure you want to delete this estimate?');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-gray-400 hover:text-red-600" title="Delete">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                @endif

                                <!-- Convert Shortcut if Ready -->
                                @if(in_array($estimate->status, ['accepted', 'ready_to_invoice']) && !$isRestricted)
                                    <div class="h-4 w-px bg-gray-300 mx-1"></div>
                                    <form action="{{ route('estimates.convert', $estimate) }}" method="POST" class="inline-block"
                                        onsubmit="return confirm('Convert to Invoice?');">
                                        @csrf
                                        <button type="submit" class="text-blue-600 hover:text-blue-900" title="Convert to Invoice">
                                            <i class="fas fa-file-invoice-dollar"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-gray-500 text-sm">No estimates found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection