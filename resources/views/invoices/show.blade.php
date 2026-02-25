@extends('layouts.app')

@section('header')
    <div class="flex justify-between items-center no-print">
        <span>Invoice Details</span>
        <div>
            <form action="{{ route('invoices.duplicate', $invoice) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="bg-brand-purple text-white px-4 py-2 rounded-md hover:bg-brand-pink text-sm mr-2">
                    <i class="fas fa-copy mr-1"></i> Duplicate to Estimate
                </button>
            </form>
            <button onclick="window.print()" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700 text-sm mr-2">
                <i class="fas fa-print mr-1"></i> Print
            </button>
            <a href="{{ route('invoices.index') }}" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 text-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back
            </a>
        </div>
    </div>
@endsection

@section('content')
    <div class="max-w-4xl mx-auto bg-white shadow-lg p-10 print:shadow-none print:w-full print:max-w-none" id="invoice-container">
        <!-- Header -->
        <div class="flex justify-between items-start mb-10">
            <div class="w-1/2">
                <!-- Empty or Logo Left -->
            </div>

            <div class="w-1/2 text-right">
                <img src="{{ asset('images/logo_loops.png') }}" alt="Loops Integrated" class="h-16 w-auto ml-auto mb-4">
                <div class="text-sm text-gray-600 space-y-1">
                    <p>291, Soloman Terrace</p>
                    <p>Colombo 05, Sri Lanka</p>
                    <p>Tel: +94 112 581 689</p>
                    <p>Website: www.loops.lk</p>
                    <p class="font-semibold">Loops Digital</p>
                    <p>VAT REG No: 10246299 - 7000</p>
                </div>
            </div>
        </div>

        <div class="flex justify-between items-start mb-8">
            <div class="w-1/2 text-sm text-gray-700">
                <p class="font-bold mb-1">Bill To: {{ $invoice->customer->name }}</p>
                <p>{{ $invoice->customer->address }}</p>
                <p>{{ $invoice->customer->phone }}</p>
                <p>{{ $invoice->customer->email }}</p>
            </div>
            
            <div class="w-1/2 text-right text-sm">
                <p class="font-bold">DATE: {{ \Carbon\Carbon::parse($invoice->date)->format('Y-m-d') }}</p>
                <p class="font-bold">INVOICE NO: {{ $invoice->invoice_number }}</p>
                <p class="font-bold">DUE DATE: {{ \Carbon\Carbon::parse($invoice->due_date)->format('Y-m-d') }}</p>
            </div>
        </div>

        <div class="text-center mb-8">
            <h2 class="text-xl font-bold uppercase underline tracking-wider">INVOICE</h2>
        </div>

        <!-- Items Table -->
        <table class="w-full mb-8 border-collapse">
            <thead>
                <tr class="border-b border-t border-gray-300">
                    <th class="py-3 px-4 text-left text-sm font-semibold text-gray-700">Description</th>
                    <th class="py-3 px-4 text-right text-sm font-semibold text-gray-700">Line Amount</th>
                    <th class="py-3 px-4 text-right text-sm font-semibold text-gray-700">SSCL</th>
                    <th class="py-3 px-4 text-right text-sm font-semibold text-gray-700">VAT</th>
                    <th class="py-3 px-4 text-right text-sm font-semibold text-gray-700">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $item)
                    <tr class="border-b border-gray-100">
                        <td class="py-4 px-4 text-sm text-gray-700">{{ $item->description }}</td>
                        <td class="py-4 px-4 text-right text-sm text-gray-700">LKR {{ number_format($item->amount, 2) }}</td>
                        <td class="py-4 px-4 text-right text-sm text-gray-700">LKR {{ number_format($item->sscl_amount, 2) }}</td>
                        <td class="py-4 px-4 text-right text-sm text-gray-700">LKR {{ number_format($item->vat_amount, 2) }}</td>
                        <td class="py-4 px-4 text-right text-sm text-gray-700 font-medium">LKR {{ number_format($item->total_with_vat, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Totals -->
        <div class="flex justify-end mb-10">
            <div class="w-1/2">
                <table class="w-full">
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <td class="py-3 px-4 text-sm font-medium text-gray-600">Total Line Amount</td>
                        <td class="py-3 px-4 text-right text-sm font-bold text-gray-800">LKR {{ number_format($invoice->items->sum('amount'), 2) }}</td>
                    </tr>
                    @php
                        $totalSSCL = $invoice->items->sum('sscl_amount');
                        $totalVAT = $invoice->items->sum('vat_amount');
                    @endphp
                    @if($totalSSCL > 0)
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <td class="py-3 px-4 text-sm font-medium text-gray-600">Total SSCL ({{ \App\Models\Setting::get('sscl_rate', 2.5) }}%)</td>
                            <td class="py-3 px-4 text-right text-sm font-bold text-gray-800">LKR {{ number_format($totalSSCL, 2) }}</td>
                        </tr>
                    @endif
                    @if($totalVAT > 0)
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <td class="py-3 px-4 text-sm font-medium text-gray-600">Total VAT ({{ \App\Models\Setting::get('vat_rate', 15) }}%)</td>
                            <td class="py-3 px-4 text-right text-sm font-bold text-gray-800">LKR {{ number_format($totalVAT, 2) }}</td>
                        </tr>
                    @endif
                    <tr class="bg-gray-100 border-b border-gray-200">
                        <td class="py-3 px-4 text-base font-bold text-gray-800">Total Due</td>
                        <td class="py-3 px-4 text-right text-base font-bold text-gray-800">LKR {{ number_format($invoice->total_amount, 2) }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Terms -->
        <div class="mb-10 text-xs text-gray-600">
            <h4 class="font-bold text-gray-700 mb-2">Payment Terms</h4>
            <ul class="list-disc list-inside space-y-1">
                <li>Cheques to be drawn in favour of "Loops Digital Private Limited"</li>
                <li>All relevant Government taxes will be applicable</li>
                <li>Account Name: Loops Digital (Pvt) Ltd, Account Number: 039010231847, Bank & Branch: Hatton National Bank PLC, Bambalapitiya Branch, Swift Code: HBLILLKX</li>
            </ul>
        </div>

        <!-- Signature -->
        <div class="mt-16">
            <p class="text-sm text-gray-700">Yours Sincerely,</p>
            <div class="h-16 my-2"></div>
            <div class="w-48 border-t border-gray-400 pt-2">
                <p class="text-xs font-bold text-gray-600">Authorized Signature</p>
                <p class="text-[10px] text-gray-500 mt-1">(This is a computer generated invoice, No manual signature required)</p>
            </div>
        </div>
    </div>
    <style>
        @media print {
            body * { visibility: hidden; }
            #invoice-container, #invoice-container * { visibility: visible; }
            #invoice-container { position: absolute; left: 0; top: 0; width: 100%; margin: 0; padding: 20px; box-shadow: none; }
            .no-print { display: none !important; }
        }
    </style>
@endsection
