@extends('layouts.app')

@section('header', 'Estimates')

@section('content')
    <div class="bg-white rounded-lg shadow-md overflow-hidden" x-data="{ 
        columns: $persist(['reference', 'customer', 'brand', 'date', 'amount', 'status', 'actions']).as('estimates_columns'),
        showPicker: false,
        isColumnVisible(col) { return this.columns.includes(col); },
        toggleColumn(col) {
            if (this.isColumnVisible(col)) {
                this.columns = this.columns.filter(c => c !== col);
            } else {
                this.columns.push(col);
            }
        }
    }">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-700">Manage Estimates</h3>
            <div class="flex items-center space-x-3">
                <div class="relative" @click.away="showPicker = false">
                    <button @click="showPicker = !showPicker" 
                        class="px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-50 transition-colors flex items-center shadow-sm">
                        <i class="fas fa-columns mr-2"></i>Columns
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
                                @foreach([
                                    'reference' => 'Reference',
                                    'customer' => 'Customer',
                                    'brand' => 'Brand',
                                    'date' => 'Date',
                                    'amount' => 'Amount',
                                    'status' => 'Status',
                                    'actions' => 'Actions'
                                ] as $key => $label)
                                    <label class="flex items-center group cursor-pointer">
                                        <input type="checkbox" :checked="isColumnVisible('{{ $key }}')" @change="toggleColumn('{{ $key }}')"
                                            class="w-4 h-4 text-brand-blue border-gray-200 rounded focus:ring-brand-blue transition-colors">
                                        <span class="ml-3 text-xs font-bold text-slate-600 group-hover:text-brand-blue transition-colors">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                <a href="{{ route('estimates.create') }}"
                    class="px-4 py-2 bg-brand-pink text-white rounded-md hover:bg-brand-purple text-sm font-medium transition-colors">
                    <i class="fas fa-plus mr-2"></i>Create Estimate
                </a>
            </div>
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
                        <th x-show="isColumnVisible('reference')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference
                        </th>
                        <th x-show="isColumnVisible('customer')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer
                        </th>
                        <th x-show="isColumnVisible('brand')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Brand
                        </th>
                        <th x-show="isColumnVisible('date')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th x-show="isColumnVisible('amount')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount
                        </th>
                        <th x-show="isColumnVisible('status')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status
                        </th>
                        <th x-show="isColumnVisible('actions')" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions
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
                            <td x-show="isColumnVisible('reference')" class="px-6 py-4 white-space-nowrap text-sm font-medium text-gray-900">
                                {{ $estimate->reference_number }}
                            </td>
                            <td x-show="isColumnVisible('customer')" class="px-6 py-4 white-space-nowrap text-sm text-gray-500">{{ $estimate->customer->name }}</td>
                            <td x-show="isColumnVisible('brand')" class="px-6 py-4 white-space-nowrap text-sm text-gray-500">
                                @if($estimate->brand_name)
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                        {{ $estimate->brand_name }}
                                    </span>
                                @else
                                    <span class="text-gray-400 text-xs italic">No Brand</span>
                                @endif
                            </td>
                            <td x-show="isColumnVisible('date')" class="px-6 py-4 white-space-nowrap text-sm text-gray-500">{{ $estimate->date }}</td>
                            <td x-show="isColumnVisible('amount')" class="px-6 py-4 white-space-nowrap text-sm text-gray-900 font-bold">
                                ${{ number_format($estimate->total_amount, 2) }}</td>
                            <td x-show="isColumnVisible('status')" class="px-6 py-4 white-space-nowrap">
                                @if($isRestricted && $estimate->status != 'draft')
                                    <span class="text-xs font-semibold rounded-full px-2 py-1 inline-block
                                                                                        @if($estimate->status == 'draft') bg-gray-100 text-gray-800
                                                                                        @elseif($estimate->status == 'approved') bg-yellow-100 text-yellow-800
                                                                                        @elseif($estimate->status == 'accepted' || $estimate->status == 'ready_to_invoice') bg-green-100 text-green-800
                                                                                        @elseif($estimate->status == 'rejected') bg-red-100 text-red-800
                                                                                        @elseif($estimate->status == 'invoiced') bg-blue-100 text-blue-800
                                                                                        @endif">
                                        {{ $estimate->status == 'draft' ? 'Pending' : ($estimate->status == 'ready_to_invoice' ? 'Ready to Invoice' : ucfirst($estimate->status == 'accepted' ? 'Ready to Invoice (Old)' : $estimate->status)) }}
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
                                                <option value="draft" {{ $estimate->status == 'draft' ? 'selected' : '' }}>Pending</option>
                                                <option value="ready_to_invoice">Ready to Invoice</option>
                                            @else
                                                <!-- Admin Options -->
                                                <option value="draft" {{ $estimate->status == 'draft' ? 'selected' : '' }}>Pending</option>
                                                <option value="approved" {{ $estimate->status == 'approved' ? 'selected' : '' }}>Approved</option>
                                                <option value="rejected" {{ $estimate->status == 'rejected' ? 'selected' : '' }}>Rejected</option>
                                                <option value="ready_to_invoice" {{ $estimate->status == 'ready_to_invoice' ? 'selected' : '' }}>Ready to Invoice</option>
                                            @endif
                                        </select>
                                    </form>
                                @endif
                            </td>
                            <td x-show="isColumnVisible('actions')"
                                class="px-6 py-4 white-space-nowrap text-right text-sm font-medium flex justify-end gap-2 items-center">
                                <!-- View -->
                                <a href="{{ route('estimates.show', $estimate) }}"
                                    class="text-brand-blue hover:text-brand-purple" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>

                                @php
                                    $canEditOrDelete = false;
                                    if ($user->role === 'Super Admin') {
                                        $canEditOrDelete = true;
                                    } elseif ($user->role === 'Management') {
                                        $canEditOrDelete = !in_array($estimate->status, ['invoiced']);
                                    } else {
                                        $canEditOrDelete = $estimate->status === 'draft';
                                    }
                                @endphp

                                <!-- Edit -->
                                @if($canEditOrDelete)
                                    <a href="{{ route('estimates.edit', $estimate) }}" class="text-gray-600 hover:text-brand-blue"
                                        title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                @endif

                                <!-- Delete -->
                                @if($canEditOrDelete)
                                    <form action="{{ route('estimates.destroy', $estimate) }}" method="POST"
                                        onsubmit="return confirm('Are you sure you want to delete this estimate?');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-gray-400 hover:text-red-600" title="Delete">
                                            <i class="fas fa-trash-alt"></i>
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