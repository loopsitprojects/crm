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
    <!-- Top Text OUTSIDE the box -->
    <div class="max-w-4xl mx-auto flex justify-between items-center text-[10px] sm:text-[11px] font-bold text-black mb-1 px-1 print:hidden">
        <div>{{ \Carbon\Carbon::now()->format('n/j/y, g:i A') }}</div>
        <div>Invoice System</div>
    </div>

    <!-- MAIN BORDERED CONTAINER -->
    <div class="max-w-4xl mx-auto bg-white border border-black p-4 md:p-[24px] print:border print:m-4 text-black font-sans text-[13px] leading-tight mb-8" id="invoice-container">
        
        <!-- Center Title -->
        <div class="flex justify-center mb-[20px] mt-2">
            <div class="border border-black px-12 py-2 bg-white font-bold text-[17px] uppercase tracking-wide">
                {{ $invoice->is_proforma ? 'PROFORMA INVOICE' : 'TAX INVOICE' }}
            </div>
        </div>

        <!-- THE MAIN GRID -->
        <div class="flex flex-col border border-black bg-white">
            <!-- Top Header Boxes -->
            <div class="flex border-b border-black">
                <div class="w-1/2 border-r border-black p-3 flex items-center">
                    <span class="font-bold mr-2">Date of Invoice:</span> <span>{{ \Carbon\Carbon::parse($invoice->date)->format('d-m-Y') }}</span>
                </div>
                <div class="w-1/2 p-3 flex items-center">
                    <span class="font-bold mr-2">Tax Invoice No.:</span> <span>{{ $invoice->invoice_number }}</span>
                </div>
            </div>

            <!-- Supplier & Purchaser Boxes -->
            <div class="flex border-b border-black min-h-[140px] bg-white">
                <div class="w-1/2 border-r border-black p-3 flex flex-col justify-between">
                    <div class="space-y-1">
                        <div><span class="font-bold">Supplier's TIN:</span> 10246299 - 7000</div>
                        <div><span class="font-bold">Supplier's Name:</span> Loops Digital (Pvt) Ltd</div>
                        <div><span class="font-bold">Address:</span> 291, Soloman Terrace, Colombo 05, Sri Lanka</div>
                    </div>
                    <div class="mt-3"><span class="font-bold">Telephone No:</span> +94 112 581 689</div>
                </div>
                
                <div class="w-1/2 p-3 flex flex-col justify-between">
                    <div class="space-y-1">
                        <div><span class="font-bold">Purchaser's TIN:</span> {{ $invoice->customer->customer_vat_registration_number ?? 'N/A' }}</div>
                        <div><span class="font-bold">Purchaser's Name:</span> {{ $invoice->customer->name }}</div>
                        <div><span class="font-bold">Address:</span> {{ $invoice->customer->address }}</div>
                    </div>
                    <div class="mt-3"><span class="font-bold">Telephone No:</span> {{ $invoice->customer->phone }}</div>
                </div>
            </div>

            <!-- Delivery & Supply Boxes -->
            <div class="flex border-b border-black bg-white">
                <div class="w-1/2 border-r border-black p-3">
                    <span class="font-bold">Date of Delivery:</span> N/A
                </div>
                <div class="w-1/2 p-3">
                    <span class="font-bold">Place of Supply:</span> N/A
                </div>
            </div>

            <!-- Additional Info -->
            <div class="border-b border-black p-3 min-h-[60px] bg-white">
                <div class="font-bold mb-0.5">Additional Information if any:</div>
                <div>
                    @if($invoice->estimate && $invoice->estimate->additional_notes)
                        {{ $invoice->estimate->additional_notes }}
                    @else
                        N/A
                    @endif
                </div>
            </div>

            <!-- Main Items Table -->
            <div class="w-full">
                <!-- Table Header -->
                <div class="flex border-b border-black bg-white">
                    <div class="w-[8%] border-r border-black p-2 text-center font-bold flex items-center justify-center">Ref</div>
                    <div class="w-[42%] border-r border-black p-2 text-center font-bold flex items-center justify-center">Description of Goods or Services</div>
                    <div class="w-[12%] border-r border-black p-2 text-center font-bold flex items-center justify-center">Quantity</div>
                    <div class="w-[18%] border-r border-black p-2 text-center font-bold flex items-center justify-center">Unit Price</div>
                    <div class="w-[20%] p-2 text-center font-bold text-[12px] flex flex-col items-center justify-center leading-tight"><span>Amount Excluding</span><span>VAT (LKR)</span></div>
                </div>

                <!-- Table Rows -->
                @php
                    $totalExcludingVat = 0;
                    $rowCount = count($invoice->items);
                    $minRows = max(5, $rowCount); 
                @endphp
                
                @for($i = 0; $i < $minRows; $i++)
                    @if(isset($invoice->items[$i]))
                        @php
                            $item = $invoice->items[$i];
                            $itemAmountNoVat = $item->amount + $item->sscl_amount;
                            $totalExcludingVat += $itemAmountNoVat;
                        @endphp
                        <div class="flex border-b border-black last:border-b last:border-black min-h-[45px] bg-white">
                            <div class="w-[8%] border-r border-black p-2 text-center flex items-center justify-center">{{ $i + 1 }}</div>
                            <div class="w-[42%] border-r border-black p-2 flex items-center">{{ $item->description }}</div>
                            <div class="w-[12%] border-r border-black p-2 text-center flex items-center justify-center">{{ number_format($item->quantity, 0) }}</div>
                            <div class="w-[18%] border-r border-black p-2 text-right flex items-center justify-end pr-3">{{ number_format($item->unit_price, 2) }}</div>
                            <div class="w-[20%] p-2 text-right flex items-center justify-end pr-3">{{ number_format($itemAmountNoVat, 2) }}</div>
                        </div>
                    @else
                        <div class="flex border-b border-black last:border-b last:border-black min-h-[45px] bg-white">
                            <div class="w-[8%] border-r border-black p-2"></div>
                            <div class="w-[42%] border-r border-black p-2"></div>
                            <div class="w-[12%] border-r border-black p-2"></div>
                            <div class="w-[18%] border-r border-black p-2"></div>
                            <div class="w-[20%] p-2"></div>
                        </div>
                    @endif
                @endfor

                <!-- Totals Rows -->
                <div class="flex border-b border-black min-h-[35px] bg-white">
                    <div class="w-[80%] border-r border-black p-2 text-right font-bold pr-3 flex items-center justify-end">Total Value of Supply:</div>
                    <div class="w-[20%] p-2 text-right font-bold pr-3 flex items-center justify-end">{{ number_format($totalExcludingVat, 2) }}</div>
                </div>
                @php
                    $vatRate = \App\Models\Setting::get('vat_rate', 15);
                    $totalVat = $totalExcludingVat * ($vatRate / 100);
                @endphp
                <div class="flex border-b border-black min-h-[35px] bg-white">
                    <div class="w-[80%] border-r border-black p-2 text-right font-bold pr-3 flex items-center justify-end">VAT Amount (Total Value of Supply @ {{ \App\Models\Setting::get('vat_rate', 15) }}%):</div>
                    <div class="w-[20%] p-2 text-right font-bold pr-3 flex items-center justify-end">{{ number_format($totalVat, 2) }}</div>
                </div>
                <div class="flex border-b border-black min-h-[35px] bg-white">
                    <div class="w-[80%] border-r border-black p-2 text-right font-bold uppercase pr-3 flex items-center justify-end">TOTAL AMOUNT INCLUDING VAT:</div>
                    @php
                        $grandTotalIncludingVat = $totalExcludingVat + $totalVat;
                    @endphp
                    <div class="w-[20%] p-2 text-right font-bold pr-3 flex items-center justify-end">LKR {{ number_format($grandTotalIncludingVat, 2) }}</div>
                </div>
                <div class="flex border-b border-black min-h-[35px] bg-white">
                    <div class="w-[80%] border-r border-black p-2 text-right font-bold pr-3 flex items-center justify-end uppercase">Advance Received amount:</div>
                    <div class="w-[20%] p-2 text-right font-bold pr-3 flex items-center justify-end">LKR {{ number_format($invoice->estimate->advance_received_amount ?? 0, 2) }}</div>
                </div>
                <div class="flex border-b border-black min-h-[35px] bg-white">
                    <div class="w-[80%] border-r border-black p-2 text-right font-bold uppercase pr-3 flex items-center justify-end">Balance Payable:</div>
                    <div class="w-[20%] p-2 text-right font-bold pr-3 flex items-center justify-end">LKR {{ number_format($grandTotalIncludingVat - ($invoice->estimate->advance_received_amount ?? 0), 2) }}</div>
                </div>
            </div>

            <!-- Amount in Words / Advance Payable -->
            @if($invoice->is_proforma)
                @php
                    $percentage = $invoice->estimate->proforma_percentage ?? 50;
                    $isWithTax = ($invoice->estimate->proforma_tax ?? 'with_tax') === 'with_tax';
                    $baseForAdvance = $isWithTax ? $invoice->total_amount : $totalExcludingVat;
                    $advanceAmount = ($baseForAdvance * $percentage) / 100;
                @endphp
                <div class="flex border-b border-black min-h-[35px] bg-white">
                    <div class="w-[80%] border-r border-black p-2 text-right font-bold pr-3 flex items-center justify-end">
                        {{ (int)$percentage }}% Advance Payable {{ $isWithTax ? '(With Tax)' : '(Without Tax)' }}
                    </div>
                    <div class="w-[20%] p-2 text-right font-bold pr-3 flex items-center justify-end">LKR {{ number_format($advanceAmount, 2) }}</div>
                </div>
            @else
                <div class="border-b border-black p-3 min-h-[60px] bg-white">
                    <div class="font-bold mb-0.5">Total Amount in words:</div>
                    <div>
                        {{-- Leaving static/placeholder per the user image style, as Laravel needs a helper library to autogenerate --}}
                        Eighty-five thousand Rupees Only
                    </div>
                </div>
            @endif

            <!-- Mode of Payment - NO BOTTOM BORDER as the grid border handles it -->
            <div class="p-3 min-h-[45px] bg-white">
                <span class="font-bold">Mode of Payment:</span> <span>Cheque / Bank Transfer</span>
            </div>
        </div>
        <!-- END MAIN GRID -->
        
        <!-- Signature Area - Below grid, but inside padded outer box -->
        <div class="pt-[80px] pb-2 flex justify-between items-end bg-white border-none">
            <div class="italic text-gray-500 text-[11px]">
                (This is a computer generated invoice, No manual signature required)
            </div>
            <!-- Blank space for signature if needed, or just removed the authorized signature text/line -->
            <div class="w-48 text-center text-sm">
            </div>
        </div>
    </div>
    <style>
        @media print {
            body { background: white; }
            body * { visibility: hidden; }
            #invoice-container, #invoice-container * { visibility: visible; }
            #invoice-container { position: absolute; left: 0; top: 0; width: 100%; margin: 10px; padding: 20px !important; box-sizing: border-box; box-shadow: none; border: 1px solid black !important; border-width: 1px !important; }
            .no-print { display: none !important; }
            /* Keep borders visible in print */
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .border { border-width: 1px !important; border-style: solid !important; border-color: black !important; }
            .border-2 { border-width: 2px !important; border-style: solid !important; border-color: black !important; }
        }
    </style>
@endsection
