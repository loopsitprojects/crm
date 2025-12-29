@extends('layouts.app')

@section('header', 'Dashboard')

@section('content')
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- Card 1 -->
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-brand-pink">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Total Customers</p>
                    <p class="text-3xl font-bold text-gray-800">{{ $customerCount }}</p>
                </div>
                <div class="p-3 bg-brand-pink bg-opacity-10 rounded-full text-brand-pink">
                    <i class="fas fa-users fa-lg"></i>
                </div>
            </div>
        </div>

        <!-- Card 2 -->
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-brand-blue">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Total Estimates</p>
                    <p class="text-3xl font-bold text-gray-800">{{ $estimateCount }}</p>
                </div>
                <div class="p-3 bg-brand-blue bg-opacity-10 rounded-full text-brand-blue">
                    <i class="fas fa-file-signature fa-lg"></i>
                </div>
            </div>
        </div>

        <!-- Card 3 -->
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-brand-teal">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Total Invoices</p>
                    <p class="text-3xl font-bold text-gray-800">{{ $invoiceCount }}</p>
                </div>
                <div class="p-3 bg-brand-teal bg-opacity-10 rounded-full text-brand-teal">
                    <i class="fas fa-file-invoice-dollar fa-lg"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4 text-gray-700">Quick Actions</h3>
            <div class="space-y-4">
                <a href="{{ route('customers.create') }}"
                    class="block w-full text-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-brand-pink hover:bg-brand-purple transition-colors">
                    Create New Customer
                </a>
                <a href="{{ route('estimates.create') }}"
                    class="block w-full text-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-brand-blue hover:bg-brand-purple transition-colors">
                    Create New Estimate
                </a>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4 text-gray-700">Recent Activity</h3>
            <p class="text-gray-500 text-sm">No recent activity found.</p>
        </div>
    </div>
@endsection