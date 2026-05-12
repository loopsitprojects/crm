@extends('layouts.app')

@push('head')
<style>
    .quill-content h1 {
        font-size: 1.125rem; /* text-lg */
        font-weight: 700; /* font-bold */
        text-transform: uppercase;
        margin-bottom: 0.5rem;
        margin-top: 0.5rem;
    }
    .quill-content h2 {
        font-size: 1rem; /* text-base */
        font-weight: 600; /* font-semibold */
        font-style: italic;
        margin-bottom: 0.25rem;
        margin-top: 0.5rem;
    }
    .quill-content ul {
        list-style-type: disc;
        padding-left: 1.5rem;
        margin-top: 0.25rem;
        margin-bottom: 0.25rem;
    }
    .quill-content p {
        margin-bottom: 0.25rem;
    }
    /* Reset margins for first and last children to keep table cells neat */
    .quill-content > *:first-child { margin-top: 0; }
    .quill-content > *:last-child { margin-bottom: 0; }
</style>
@endpush

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

    <!-- MAIN BORDERED CONTAINER -->
    <div class="max-w-4xl mx-auto bg-white border border-black p-4 md:p-[24px] print:border print:m-4 text-black font-sans text-[13px] leading-tight mb-8" id="invoice-container">
        
        <!-- Header with Logo for Proforma -->
        <div class="flex justify-between items-center mb-[20px] mt-2">
            <div class="w-1/3">
                <img src="{{ asset('images/logo_loops.png') }}" alt="Loops Logo" class="h-12 w-auto">
            </div>
            <div class="w-1/3 flex justify-center">
                <div class="border border-black px-12 py-2 bg-white font-bold text-[17px] uppercase tracking-wide whitespace-nowrap">
                    {{ $invoice->is_proforma ? 'PROFORMA INVOICE' : 'TAX INVOICE' }}
                </div>
            </div>
            <div class="w-1/3"></div>
        </div>

        <!-- THE MAIN GRID USING EXPLICIT BORDERS FOR BULLETPROOF PRINTING -->
        <div class="border-t border-l border-black">
            <!-- Top Section: 2 Columns -->
            <div class="flex">
                <div class="w-1/2 p-3 border-r border-b border-black flex items-center">
                    <span class="font-bold mr-2 text-[13px]">Date of Invoice:</span> <span class="text-[13px]">{{ \Carbon\Carbon::parse($invoice->date)->format('d-m-Y') }}</span>
                </div>
                <div class="w-1/2 p-3 border-r border-b border-black flex items-center">
                    <span class="font-bold mr-2 text-[13px]">{{ $invoice->is_proforma ? 'Proforma Invoice No.:' : 'Tax Invoice No.:' }}</span> <span class="text-[13px]">{{ $invoice->invoice_number }}</span>
                </div>
            </div>

            <!-- Supplier & Purchaser Section -->
            <div class="flex">
                <div class="w-1/2 p-3 border-r border-b border-black min-h-[140px] flex flex-col justify-between">
                    <div class="space-y-1">
                        <div><span class="font-bold text-[13px]">Supplier's TIN:</span> 10246299 - 7000</div>
                        <div><span class="font-bold text-[13px]">Supplier's Name:</span> Loops Digital (Pvt) Ltd</div>
                        <div class="flex"><span class="font-bold text-[13px] whitespace-nowrap mr-1">Address:</span> <span class="text-[13px]">291, Soloman Terrace, Colombo 05, Sri Lanka</span></div>
                    </div>
                    <div class="mt-3"><span class="font-bold text-[13px]">Telephone No:</span> +94 112 581 689</div>
                </div>
                <div class="w-1/2 p-3 border-r border-b border-black min-h-[140px] flex flex-col justify-between">
                    <div class="space-y-1">
                        <div><span class="font-bold text-[13px]">Purchaser's TIN:</span> {{ $invoice->customer->customer_vat_registration_number ?? 'N/A' }}</div>
                        <div><span class="font-bold text-[13px]">Purchaser's Name:</span> {{ $invoice->customer->name }}</div>
                        <div class="flex"><span class="font-bold text-[13px] whitespace-nowrap mr-1">Address:</span> <span class="text-[13px]">{{ $invoice->customer->billing_address ?: $invoice->customer->address }}</span></div>
                    </div>
                    <div class="mt-3"><span class="font-bold text-[13px]">Telephone No:</span> {{ $invoice->customer->telephone ?: $invoice->customer->phone }}</div>
                </div>
            </div>

            <!-- Delivery & Supply Section -->
            <div class="flex">
                <div class="w-1/2 p-3 border-r border-b border-black">
                    <span class="font-bold text-[13px]">Date of Delivery:</span> N/A
                </div>
                <div class="w-1/2 p-3 border-r border-b border-black">
                    <span class="font-bold text-[13px]">Place of Supply:</span> N/A
                </div>
            </div>

            <!-- Additional Info Section -->
            <div class="p-3 border-r border-b border-black min-h-[60px]">
                <div class="font-bold mb-1 text-[13px]">Additional Information if any:</div>
                <div class="text-[13px]">
                    @if($invoice->estimate && $invoice->estimate->additional_notes)
                        {{ $invoice->estimate->additional_notes }}
                    @else
                        N/A
                    @endif
                </div>
            </div>

            <!-- Items Table Headers -->
            <div class="flex font-bold text-[13px] text-center">
                <div class="p-2 w-[8%] border-r border-b border-black">Ref</div>
                <div class="p-2 w-[42%] border-r border-b border-black text-left pl-4">Description of Goods or Services</div>
                <div class="p-2 w-[12%] border-r border-b border-black">Quantity</div>
                <div class="p-2 w-[18%] border-r border-b border-black">Unit Price</div>
                <div class="p-2 w-[20%] border-r border-b border-black leading-tight flex items-center justify-center">Amount Excluding<br>VAT ({{ $invoice->estimate->deal->currency ?? 'LKR' }})</div>
            </div>

            <!-- Item Rows - Dynamic -->
            @php $totalExcludingVat = 0; @endphp
            @foreach($invoice->items as $i => $item)
                @php
                    $itemAmountNoVat = $item->amount + $item->sscl_amount;
                    if($item->type === 'item') {
                        $totalExcludingVat += $itemAmountNoVat;
                    }
                @endphp

                <div class="flex text-[13px] min-h-[45px]">
                    <div class="p-2 w-[8%] border-r border-b border-black text-center flex items-center justify-center">{{ $i + 1 }}</div>
                    <div class="p-2 w-[42%] border-r border-b border-black text-left flex items-start pl-4 py-2">
                        <div class="quill-content w-full">
                            {!! $item->description !!}
                        </div>
                    </div>
                    <div class="p-2 w-[12%] border-r border-b border-black text-center flex items-center justify-center">{{ number_format($item->quantity, 0) }}</div>
                    <div class="p-2 w-[18%] border-r border-b border-black text-right pr-3 flex items-center justify-end font-mono">{{ number_format($item->unit_price, 2) }}</div>
                    <div class="p-2 w-[20%] border-r border-b border-black text-right pr-3 flex items-center justify-end font-mono">{{ number_format($itemAmountNoVat, 2) }}</div>
                </div>
            @endforeach

            <!-- Totals Section -->
            <div class="flex text-[13px] font-bold min-h-[35px]">
                <div class="p-2 w-[80%] border-r border-b border-black text-right pr-3 flex items-center justify-end">Total Value of Supply:</div>
                <div class="p-2 w-[20%] border-r border-b border-black text-right pr-3 flex items-center justify-end">{{ number_format($totalExcludingVat, 2) }}</div>
            </div>
            @php
                $vatRate = \App\Models\Setting::get('vat_rate', 15);
                $totalVat = $totalExcludingVat * ($vatRate / 100);
                $grandTotalIncludingVat = $totalExcludingVat + $totalVat;
            @endphp
            <div class="flex text-[13px] font-bold min-h-[35px]">
                <div class="p-2 w-[80%] border-r border-b border-black text-right pr-3 flex items-center justify-end">VAT Amount (Total Value of Supply @ {{ number_format($vatRate, 2) }}%):</div>
                <div class="p-2 w-[20%] border-r border-b border-black text-right pr-3 flex items-center justify-end">{{ number_format($totalVat, 2) }}</div>
            </div>
            <div class="flex text-[13px] font-bold min-h-[35px]">
                <div class="p-2 w-[80%] border-r border-b border-black text-right pr-3 uppercase flex items-center justify-end">TOTAL AMOUNT INCLUDING VAT:</div>
                <div class="p-2 w-[20%] border-r border-b border-black text-right pr-3 flex items-center justify-end">{{ $invoice->estimate->deal->currency ?? 'LKR' }} {{ number_format($grandTotalIncludingVat, 2) }}</div>
            </div>
            @if(!$invoice->is_proforma)
            <div class="flex text-[13px] font-bold min-h-[35px]">
                <div class="p-2 w-[80%] border-r border-b border-black text-right pr-3 uppercase flex items-center justify-end">Advance Received amount:</div>
                <div class="p-2 w-[20%] border-r border-b border-black text-right pr-3 flex items-center justify-end">{{ $invoice->estimate->deal->currency ?? 'LKR' }} {{ number_format($invoice->estimate->advance_received_amount ?? 0, 2) }}</div>
            </div>
            <div class="flex text-[13px] font-bold min-h-[35px]">
                <div class="p-2 w-[80%] border-r border-b border-black text-right pr-3 uppercase flex items-center justify-end">Balance Payable:</div>
                <div class="p-2 w-[20%] border-r border-b border-black text-right pr-3 flex items-center justify-end">{{ $invoice->estimate->deal->currency ?? 'LKR' }} {{ number_format($grandTotalIncludingVat - ($invoice->estimate->advance_received_amount ?? 0), 2) }}</div>
            </div>
            @endif

            @if($invoice->is_proforma)
                @php
                    $percentage = $invoice->estimate->proforma_percentage ?? 50;
                    $isWithTax = ($invoice->estimate->proforma_tax ?? 'with_tax') === 'with_tax';
                    $baseForAdvance = $isWithTax ? $invoice->total_amount : $totalExcludingVat;
                    $advanceAmount = ($baseForAdvance * $percentage) / 100;
                @endphp
                <div class="flex text-[13px] font-bold min-h-[35px]">
                    <div class="p-2 w-[80%] border-r border-b border-black text-right pr-3 flex items-center justify-end">
                        {{ (int)$percentage }}% Advance Payable
                    </div>
                    <div class="p-2 w-[20%] border-r border-b border-black text-right pr-3 flex items-center justify-end">{{ $invoice->estimate->deal->currency ?? 'LKR' }} {{ number_format($advanceAmount, 2) }}</div>
                </div>
            @else
                <div class="p-3 border-r border-b border-black align-top min-h-[60px]">
                    <div class="font-bold mb-1 text-[13px]">Total Amount in words:</div>
                        {{ \App\Helpers\NumberToWordsHelper::translate($grandTotalIncludingVat) }} Rupees Only
                </div>
            @endif

            <div class="p-3 border-r border-b border-black align-top">
                <span class="font-bold text-[13px]">Mode of Payment:</span> <span class="text-[13px]">Cheque / Bank Transfer</span>
            </div>
        </div>
        <!-- END MAIN GRID -->
        

    </div>
    </div>
    <style>
        /* Base styles for BOTH screen and print to ensure perfect match */
        #invoice-container { 
            background-color: #fff !important;
            border: 1px solid #000 !important; 
            -webkit-print-color-adjust: exact !important; 
            print-color-adjust: exact !important; 
        }
        .bg-black { background-color: #000 !important; }
        .bg-white { background-color: #fff !important; }

        @media screen {
            body { background: #f3f4f6; padding: 20px 0; }
            #invoice-container { 
                box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
                margin-bottom: 30px;
            }
        }

        @page {
            size: A4;
            margin: 0mm;
        }

        @media print {
            body { background: white !important; padding: 0 !important; margin: 0 !important; }
            body * { visibility: hidden; }
            #invoice-container, #invoice-container * { 
                visibility: visible; 
            }
            #invoice-container { 
                position: absolute; 
                left: 0; 
                top: 0; 
                width: 210mm !important; 
                margin: 0 !important; 
                padding: 24px !important; 
                box-sizing: border-box; 
                box-shadow: none !important; 
                min-height: 297mm !important;
                height: auto !important;
            }
            .no-print { display: none !important; }
        }
    </style>
@endsection
