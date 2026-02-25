@extends('layouts.app')

@section('header', 'Customers')

@section('content')
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-700">All Customers</h3>
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
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Address
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($customers as $customer)
                        <tr>
                            <td class="px-6 py-4 white-space-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ $customer->name }}</div>
                            </td>
                            <td class="px-6 py-4 white-space-nowrap">
                                <div class="text-sm text-gray-500">{{ $customer->email }}</div>
                            </td>
                            <td class="px-6 py-4 white-space-nowrap">
                                <div class="text-sm text-gray-500">{{ $customer->phone ?? '-' }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-500">{{ Str::limit($customer->address, 30) ?? '-' }}</div>
                            </td>
                            <td class="px-6 py-4 white-space-nowrap text-right text-sm font-medium">
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