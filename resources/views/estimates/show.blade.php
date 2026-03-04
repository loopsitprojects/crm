@extends('layouts.app')

@section('header')
    <div class="flex justify-between items-center no-print px-4 py-2 bg-gray-100 border-b border-gray-200">
        <span class="font-semibold text-gray-700">Estimate Preview</span>
        <div class="space-x-2">
            <button onclick="window.print()"
                class="bg-gray-700 hover:bg-gray-800 text-white px-4 py-2 rounded shadow-sm text-sm transition-colors">
                <i class="fas fa-print mr-2"></i> Print / Save as PDF
            </button>
            <a href="{{ route('estimates.index') }}"
                class="bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded shadow-sm text-sm transition-colors">
                Back to List
            </a>
        </div>
    </div>
@endsection

@section('content')
    <div class="max-w-[210mm] mx-auto bg-white shadow-2xl my-10 min-h-[297mm] relative print:shadow-none print:w-full print:m-0 print:border-none"
        id="invoice-container">
        <!-- Top Colored Bar -->
        <div class="h-2 bg-brand-pink w-full print:bg-brand-pink"></div>

        <div class="p-12">
            <!-- Header Section -->
            <div class="flex justify-between items-start mb-12">
                <!-- Company Details (Left) -->
                <div class="w-1/2">
                    <img src="{{ asset('images/logo_loops.png') }}" alt="Company Logo" class="h-12 w-auto mb-6">
                    <div class="text-sm text-gray-600 leading-relaxed">
                        <p class="font-bold text-gray-800 text-base">{{ \App\Models\Setting::get('company_name') }}</p>
                        <p>{{ \App\Models\Setting::get('company_address_1') }}</p>
                        <p>{{ \App\Models\Setting::get('company_address_2') }}</p>
                        <p>Tel: {{ \App\Models\Setting::get('company_phone') }}</p>
                        <p>Web: {{ \App\Models\Setting::get('company_web') }}</p>
                        <p class="mt-2 text-xs text-gray-500">VAT REG No: {{ \App\Models\Setting::get('company_vat') }}</p>
                    </div>
                </div>

                <!-- Estimate Details (Right) -->
                <div class="w-1/2 text-right">
                    <h1 class="text-4xl font-light text-brand-blue tracking-tight mb-2 uppercase">Estimate</h1>
                    <div class="text-sm text-gray-600 space-y-1">
                        <p><span class="font-semibold text-gray-800">Date:</span>
                            {{ \Carbon\Carbon::parse($estimate->date)->format('M d, Y') }}</p>
                        <p><span class="font-semibold text-gray-800">Estimate No:</span> {{ $estimate->reference_number }}</p>
                        @if($estimate->currency)
                            <p><span class="font-semibold text-gray-800">Currency:</span> {{ $estimate->currency }}</p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Client Info & Heading -->
            <div class="mb-12 border-t border-b border-gray-100 py-6">
                <div class="flex justify-between items-start">
                    <div class="w-2/3">
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Estimate For</h3>
                        <div class="text-gray-800">
                            <p class="font-bold text-lg">{{ $estimate->customer->name }}</p>
                            @if($estimate->attention_to)
                                <p class="text-sm font-medium mt-1">Attn: {{ $estimate->attention_to }}</p>
                            @endif
                            @if($estimate->designation)
                                <p class="text-sm text-gray-500">{{ $estimate->designation }}</p>
                            @endif

                            <div class="mt-3 text-sm text-gray-600">
                                @if($estimate->address_line_1)
                                <p>{{ $estimate->address_line_1 }}</p> @endif
                                @if($estimate->address_line_2)
                                <p>{{ $estimate->address_line_2 }}</p> @endif
                                @if($estimate->address_line_3)
                                <p>{{ $estimate->address_line_3 }}</p> @endif
                                @if(!$estimate->address_line_1)
                                <p>{{ $estimate->customer->address }}</p> @endif
                            </div>
                        </div>
                    </div>
                    <div class="w-1/3 text-right">
                        @if($estimate->heading)
                            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Subject</h3>
                            <p class="text-gray-800 font-medium text-lg leading-tight">{{ $estimate->heading }}</p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <table class="w-full mb-8">
                <thead>
                    <tr class="text-xs font-bold text-gray-500 uppercase tracking-wider border-b-2 border-brand-teal">
                        <th class="py-3 text-left w-[52%]">Description</th>
                        <th class="py-3 text-right w-24">Line Amount</th>
                        <th class="py-3 text-right w-24">VAT</th>
                        <th class="py-3 text-right w-28">Amount</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @foreach($estimate->items as $item)
                        <tr class="border-b border-gray-50">
                            <td class="py-4 pr-4">
                                <p class="font-medium text-gray-800">{{ $item->description }}</p>
                                @if($item->locations)
                                    <p class="text-xs text-gray-500 mt-0.5"><b>Loc:</b> {{ $item->locations }}</p>
                                @endif
                            </td>
                            <td class="py-4 text-right text-gray-600">{{ number_format($item->unit_price + $item->sscl_amount, 2) }}</td>
                            <td class="py-4 text-right text-gray-600">{{ number_format($item->vat_amount, 2) }}</td>
                            <td class="py-4 text-right font-medium text-gray-800">{{ number_format($item->total_with_vat, 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <!-- Totals & Notes -->
            <div class="flex justify-between items-start mt-8">
                <!-- Left: Terms & Signature -->
                <div class="w-1/2 pr-8">
                    @if($estimate->special_terms || $estimate->additional_notes || $estimate->terms)
                        <div class="mb-8">
                            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Terms & Conditions</h4>
                            <div class="text-xs text-gray-600 space-y-2">
                                @if($estimate->special_terms)
                                    <p class="font-medium text-red-500">* {{ $estimate->special_terms }}</p>
                                @endif
                                @if($estimate->terms)
                                    <ul class="list-disc list-inside space-y-1 mt-2 text-gray-500">
                                        @foreach(explode(', ', $estimate->terms) as $term)
                                            <li>{{ $term }}</li>
                                        @endforeach
                                        @if($estimate->advance_percentage)
                                            <li>Advance Of {{ $estimate->advance_percentage }}% is required.</li>
                                        @endif
                                    </ul>
                                @else
                                    <ul class="list-disc list-inside space-y-1 mt-2 text-gray-500">
                                        @if($estimate->advance_percentage)
                                            <li>Advance Of {{ $estimate->advance_percentage }}% is required.</li>
                                        @endif
                                    </ul>
                                @endif
                            </div>
                        </div>
                    @endif

                </div>

                <!-- Right: Summary -->
                <div class="w-2/5">
                    <div class="bg-gray-50 rounded-lg p-6">
                        <div class="flex justify-between mb-3 text-sm text-gray-600">
                            <span>Subtotal</span>
                            <span class="font-medium">{{ number_format($estimate->items->sum('amount') + $estimate->items->sum('sscl_amount'), 2) }}</span>
                        </div>

                        @php
                            $totalVAT = $estimate->items->sum('vat_amount');
                        @endphp

                        @if($totalVAT > 0)
                            <div class="flex justify-between mb-3 text-sm text-gray-600">
                                <span>VAT ({{ \App\Models\Setting::get('vat_rate', 15) }}%)</span>
                                <span class="font-medium">{{ number_format($totalVAT, 2) }}</span>
                            </div>
                        @endif

                        <div class="border-t border-gray-200 my-3"></div>

                        <div class="flex justify-between items-center text-brand-purple">
                            <span class="font-bold text-lg">Total</span>
                            <span class="font-bold text-2xl">{{ number_format($estimate->total_amount, 2) }}</span>
                        </div>
                        <div class="text-right text-xs text-gray-500 mt-1">
                            {{ $estimate->currency ?? 'LKR' }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Colored Bar -->
            <div class="absolute bottom-0 left-0 w-full h-2 bg-gray-100 print:bg-gray-100"></div>
        </div>
    </div>

    <style>
        @media print {
            body {
                background: white;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            #invoice-container {
                box-shadow: none;
                margin: 0;
                width: 100%;
                max-width: none;
            }

            .no-print {
                display: none;
            }
        }
    </style>
@endsection