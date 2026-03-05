@extends('layouts.app')

@section('header', 'Customers')

@section('content')
    <div class="bg-white rounded-lg shadow-md overflow-hidden" x-data="{ 
        columns: $persist(['name', 'email', 'phone', 'address', 'actions']).as('customers_columns'),
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
            <h3 class="text-lg font-semibold text-gray-700">All Customers</h3>
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
                                'name' => 'Name',
                                'email' => 'Email',
                                'phone' => 'Phone',
                                'address' => 'Address',
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
        </div>

        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
            <h4 class="text-md font-medium text-gray-600">Active Customers</h4>
            <a href="{{ route('customers.create') }}"
                class="px-4 py-2 bg-brand-pink text-white rounded-md hover:bg-brand-purple text-sm font-medium transition-colors">
                <i class="fas fa-plus mr-2"></i>Add Customer
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th x-show="isColumnVisible('name')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th x-show="isColumnVisible('email')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email
                        </th>
                        <th x-show="isColumnVisible('phone')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone
                        </th>
                        <th x-show="isColumnVisible('address')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Address
                        </th>
                        <th x-show="isColumnVisible('actions')" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($customers as $customer)
                        <tr>
                            <td x-show="isColumnVisible('name')" class="px-6 py-4 white-space-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ $customer->name }}</div>
                            </td>
                            <td x-show="isColumnVisible('email')" class="px-6 py-4 white-space-nowrap">
                                <div class="text-sm text-gray-500">{{ $customer->email }}</div>
                            </td>
                            <td x-show="isColumnVisible('phone')" class="px-6 py-4 white-space-nowrap">
                                <div class="text-sm text-gray-500">{{ $customer->phone ?? '-' }}</div>
                            </td>
                            <td x-show="isColumnVisible('address')" class="px-6 py-4">
                                <div class="text-sm text-gray-500">{{ Str::limit($customer->address, 30) ?? '-' }}</div>
                            </td>
                            <td x-show="isColumnVisible('actions')" class="px-6 py-4 white-space-nowrap text-right text-sm font-medium">
                                <a href="{{ route('customers.edit', $customer) }}"
                                    class="text-brand-blue hover:text-brand-purple mr-3">Edit</a>
                                <form action="{{ route('customers.destroy', $customer) }}" method="POST" class="inline-block"
                                    onsubmit="return confirm('Are you sure?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-gray-500 text-sm">
                                No customers found. Start by creating one!
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection