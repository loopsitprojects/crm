@extends('layouts.app')

@section('header', 'Activity Log')

@section('content')
    <div class="space-y-4">
        <!-- Search & Filter Card -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <form method="GET" action="{{ route('activities.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div>
                    <label for="search" class="block text-xs font-semibold text-gray-600 uppercase mb-1">Search</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                            <i class="fas fa-search text-xs"></i>
                        </div>
                        <input type="text" name="search" id="search" value="{{ request('search') }}"
                            placeholder="Estimate ref, Invoice #, user, description..."
                            class="w-full pl-8 pr-3 py-2 text-sm rounded-md border-gray-300 shadow-sm focus:border-brand-blue focus:ring focus:ring-brand-blue focus:ring-opacity-50">
                    </div>
                </div>

                <div>
                    <label for="subject_type" class="block text-xs font-semibold text-gray-600 uppercase mb-1">Type</label>
                    <select name="subject_type" id="subject_type"
                        class="w-full py-2 px-3 text-sm rounded-md border-gray-300 shadow-sm focus:border-brand-blue focus:ring focus:ring-brand-blue focus:ring-opacity-50">
                        <option value="">All Types</option>
                        <option value="estimate" {{ request('subject_type') === 'estimate' ? 'selected' : '' }}>Estimates</option>
                        <option value="invoice" {{ request('subject_type') === 'invoice' ? 'selected' : '' }}>Invoices</option>
                        <option value="temp_invoice" {{ request('subject_type') === 'temp_invoice' ? 'selected' : '' }}>Temporary Invoices</option>
                        <option value="deal" {{ request('subject_type') === 'deal' ? 'selected' : '' }}>Deals</option>
                        <option value="user" {{ request('subject_type') === 'user' ? 'selected' : '' }}>Users</option>
                        <option value="customer" {{ request('subject_type') === 'customer' ? 'selected' : '' }}>Customers</option>
                    </select>
                </div>

                <div>
                    <label for="from_date" class="block text-xs font-semibold text-gray-600 uppercase mb-1">From Date</label>
                    <input type="date" name="from_date" id="from_date" value="{{ request('from_date') }}"
                        class="w-full py-2 px-3 text-sm rounded-md border-gray-300 shadow-sm focus:border-brand-blue focus:ring focus:ring-brand-blue focus:ring-opacity-50">
                </div>

                <div class="flex items-center space-x-2">
                    <div class="flex-1">
                        <label for="to_date" class="block text-xs font-semibold text-gray-600 uppercase mb-1">To Date</label>
                        <input type="date" name="to_date" id="to_date" value="{{ request('to_date') }}"
                            class="w-full py-2 px-3 text-sm rounded-md border-gray-300 shadow-sm focus:border-brand-blue focus:ring focus:ring-brand-blue focus:ring-opacity-50">
                    </div>
                    <div class="flex space-x-1 pt-5">
                        <button type="submit"
                            class="px-4 py-2 bg-brand-blue hover:bg-brand-purple text-white text-sm font-medium rounded-md shadow-sm transition">
                            Filter
                        </button>
                        @if(request()->anyFilled(['search', 'subject_type', 'from_date', 'to_date']))
                            <a href="{{ route('activities.index') }}"
                                class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm font-medium rounded-md transition"
                                title="Reset filters">
                                <i class="fas fa-undo"></i>
                            </a>
                        @endif
                    </div>
                </div>
            </form>
        </div>

        <!-- Table Card -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                <h3 class="text-base font-semibold text-gray-800">System Activity Logs</h3>
                <span class="text-xs text-gray-500 font-medium">Total: {{ $activities->total() }} events</span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action & Details</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($activities as $activity)
                            <tr class="hover:bg-gray-50 transition-colors" x-data="{ showDetails: false }">
                                <td class="px-6 py-4 whitespace-nowrap align-top">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-8 w-8">
                                            <img class="h-8 w-8 rounded-full"
                                                src="https://ui-avatars.com/api/?name={{ urlencode($activity->user->name ?? 'System') }}&background=random"
                                                alt="">
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-sm font-medium text-gray-900">{{ $activity->user->name ?? 'System' }}</div>
                                            <div class="text-xs text-gray-500">{{ $activity->user->email ?? '' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700 max-w-xl align-top">
                                    <div class="font-medium text-gray-900 leading-snug">
                                        {{ $activity->description }}
                                    </div>

                                    @if(!empty($activity->properties) && is_array($activity->properties))
                                        <div class="mt-1">
                                            @php
                                                $fieldCount = count($activity->properties['changes'] ?? []);
                                                $itemsDiff = $activity->properties['items_diff'] ?? null;
                                                $itemChangesCount = ($itemsDiff['added'] ?? 0) + ($itemsDiff['removed'] ?? 0) + ($itemsDiff['modified'] ?? 0);
                                                $costsDiff = $activity->properties['costs_diff'] ?? null;
                                                $costChangesCount = ($costsDiff['added'] ?? 0) + ($costsDiff['removed'] ?? 0) + ($costsDiff['modified'] ?? 0);
                                            @endphp
                                            <button type="button" @click="showDetails = !showDetails"
                                                class="inline-flex items-center text-xs font-semibold text-brand-blue hover:text-brand-purple space-x-1 focus:outline-none">
                                                <i class="fas" :class="showDetails ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                                                <span x-text="showDetails ? 'Hide Details' : 'View Changes & Properties'"></span>
                                                @if($fieldCount > 0)
                                                    <span class="ml-1 inline-flex items-center px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800">
                                                        {{ $fieldCount }} field{{ $fieldCount > 1 ? 's' : '' }}
                                                    </span>
                                                @endif
                                                @if($itemChangesCount > 0)
                                                    <span class="ml-1 inline-flex items-center px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">
                                                        {{ $itemChangesCount }} item{{ $itemChangesCount > 1 ? 's' : '' }}
                                                    </span>
                                                @endif
                                                @if($costChangesCount > 0)
                                                    <span class="ml-1 inline-flex items-center px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-purple-100 text-purple-800">
                                                        {{ $costChangesCount }} cost{{ $costChangesCount > 1 ? 's' : '' }}
                                                    </span>
                                                @endif
                                            </button>

                                            <!-- Expanded Details Container -->
                                            <div x-show="showDetails" x-cloak class="mt-2 p-3 bg-gray-50 rounded-lg border border-gray-200 text-xs space-y-3">
                                                @if($fieldCount > 0)
                                                    <div>
                                                        <span class="font-bold text-gray-700 uppercase tracking-wider block mb-1">Field Level Changes:</span>
                                                        <div class="overflow-x-auto">
                                                            <table class="w-full text-left border-collapse border border-gray-200 bg-white rounded">
                                                                <thead>
                                                                    <tr class="bg-gray-100 text-gray-600">
                                                                        <th class="p-1.5 border border-gray-200">Field</th>
                                                                        <th class="p-1.5 border border-gray-200 text-red-600">Previous Value</th>
                                                                        <th class="p-1.5 border border-gray-200 text-green-600">New Value</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @foreach($activity->properties['changes'] as $field => $change)
                                                                        <tr class="border-t border-gray-100">
                                                                            <td class="p-1.5 border border-gray-200 font-semibold text-gray-700 font-mono">{{ ucwords(str_replace('_', ' ', $field)) }}</td>
                                                                            <td class="p-1.5 border border-gray-200 text-red-700 bg-red-50/50">
                                                                                {{ is_null($change['from'] ?? null) || ($change['from'] ?? '') === '' ? '(empty)' : (is_bool($change['from'] ?? null) ? (($change['from'] ?? false) ? 'Yes' : 'No') : (is_array($change['from'] ?? null) ? json_encode($change['from']) : ($change['from'] ?? ''))) }}
                                                                            </td>
                                                                            <td class="p-1.5 border border-gray-200 text-green-700 bg-green-50/50">
                                                                                {{ is_null($change['to'] ?? null) || ($change['to'] ?? '') === '' ? '(empty)' : (is_bool($change['to'] ?? null) ? (($change['to'] ?? false) ? 'Yes' : 'No') : (is_array($change['to'] ?? null) ? json_encode($change['to']) : ($change['to'] ?? ''))) }}
                                                                            </td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                @endif

                                                <!-- Modified Line Items Detailed Table -->
                                                @if(!empty($itemsDiff['details']['modified']))
                                                    <div>
                                                        <span class="font-bold text-amber-800 uppercase tracking-wider block mb-1">
                                                            <i class="fas fa-edit mr-1 text-amber-600"></i> Modified Line Items:
                                                        </span>
                                                        <div class="overflow-x-auto">
                                                            <table class="w-full text-left border-collapse border border-gray-200 bg-white rounded">
                                                                <thead>
                                                                    <tr class="bg-amber-50 text-amber-900 border-b border-gray-200">
                                                                        <th class="p-1.5 border border-gray-200 w-16">Item #</th>
                                                                        <th class="p-1.5 border border-gray-200">Description</th>
                                                                        <th class="p-1.5 border border-gray-200">Field</th>
                                                                        <th class="p-1.5 border border-gray-200 text-red-600">Previous Value</th>
                                                                        <th class="p-1.5 border border-gray-200 text-green-600">New Value</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @foreach($itemsDiff['details']['modified'] as $modItem)
                                                                        @php
                                                                            $changesList = $modItem['changes'] ?? [];
                                                                            $first = true;
                                                                            $rowCount = count($changesList);
                                                                        @endphp
                                                                        @foreach($changesList as $fieldKey => $fieldChange)
                                                                            <tr class="border-t border-gray-100 hover:bg-gray-50">
                                                                                @if($first)
                                                                                    <td class="p-1.5 border border-gray-200 font-bold text-gray-800 align-top" rowspan="{{ $rowCount }}">
                                                                                        #{{ $modItem['item_number'] ?? '-' }}
                                                                                    </td>
                                                                                    <td class="p-1.5 border border-gray-200 text-gray-700 align-top max-w-xs" rowspan="{{ $rowCount }}">
                                                                                        {{ $modItem['description'] ?? 'Line Item' }}
                                                                                    </td>
                                                                                    @php $first = false; @endphp
                                                                                @endif
                                                                                <td class="p-1.5 border border-gray-200 font-semibold text-gray-600 font-mono">
                                                                                    {{ ucwords(str_replace('_', ' ', $fieldKey)) }}
                                                                                </td>
                                                                                <td class="p-1.5 border border-gray-200 text-red-700 bg-red-50/40">
                                                                                    {{ $fieldChange['from'] ?? '(empty)' }}
                                                                                </td>
                                                                                <td class="p-1.5 border border-gray-200 text-green-700 bg-green-50/40">
                                                                                    {{ $fieldChange['to'] ?? '(empty)' }}
                                                                                </td>
                                                                            </tr>
                                                                        @endforeach
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                @endif

                                                <!-- Added Line Items -->
                                                @if(!empty($itemsDiff['details']['added']))
                                                    <div>
                                                        <span class="font-bold text-green-800 uppercase tracking-wider block mb-1">
                                                            <i class="fas fa-plus-circle mr-1 text-green-600"></i> Added Line Items:
                                                        </span>
                                                        <div class="overflow-x-auto">
                                                            <table class="w-full text-left border-collapse border border-gray-200 bg-white rounded">
                                                                <thead>
                                                                    <tr class="bg-green-50 text-green-900 border-b border-gray-200">
                                                                        <th class="p-1.5 border border-gray-200 w-16">Item #</th>
                                                                        <th class="p-1.5 border border-gray-200">Description</th>
                                                                        <th class="p-1.5 border border-gray-200 text-right">Qty</th>
                                                                        <th class="p-1.5 border border-gray-200 text-right">Unit Price</th>
                                                                        <th class="p-1.5 border border-gray-200 text-right">Amount</th>
                                                                        <th class="p-1.5 border border-gray-200">Department</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @foreach($itemsDiff['details']['added'] as $addItem)
                                                                        <tr class="border-t border-gray-100 bg-green-50/30">
                                                                            <td class="p-1.5 border border-gray-200 font-bold text-green-800">#{{ $addItem['item_number'] ?? '-' }}</td>
                                                                            <td class="p-1.5 border border-gray-200 text-gray-800">{{ $addItem['description'] ?? 'Line Item' }}</td>
                                                                            <td class="p-1.5 border border-gray-200 text-right font-mono">{{ $addItem['quantity'] ?? 1 }}</td>
                                                                            <td class="p-1.5 border border-gray-200 text-right font-mono">{{ $addItem['unit_price'] ?? '0.00' }}</td>
                                                                            <td class="p-1.5 border border-gray-200 text-right font-mono font-bold">{{ $addItem['amount'] ?? '0.00' }}</td>
                                                                            <td class="p-1.5 border border-gray-200 text-gray-600">{{ $addItem['department'] ?? '-' }}</td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                @endif

                                                <!-- Removed Line Items -->
                                                @if(!empty($itemsDiff['details']['removed']))
                                                    <div>
                                                        <span class="font-bold text-red-800 uppercase tracking-wider block mb-1">
                                                            <i class="fas fa-minus-circle mr-1 text-red-600"></i> Removed Line Items:
                                                        </span>
                                                        <div class="overflow-x-auto">
                                                            <table class="w-full text-left border-collapse border border-gray-200 bg-white rounded">
                                                                <thead>
                                                                    <tr class="bg-red-50 text-red-900 border-b border-gray-200">
                                                                        <th class="p-1.5 border border-gray-200 w-16">Item #</th>
                                                                        <th class="p-1.5 border border-gray-200">Description</th>
                                                                        <th class="p-1.5 border border-gray-200 text-right">Qty</th>
                                                                        <th class="p-1.5 border border-gray-200 text-right">Unit Price</th>
                                                                        <th class="p-1.5 border border-gray-200 text-right">Amount</th>
                                                                        <th class="p-1.5 border border-gray-200">Department</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @foreach($itemsDiff['details']['removed'] as $remItem)
                                                                        <tr class="border-t border-gray-100 bg-red-50/30">
                                                                            <td class="p-1.5 border border-gray-200 font-bold text-red-800">#{{ $remItem['item_number'] ?? '-' }}</td>
                                                                            <td class="p-1.5 border border-gray-200 text-gray-800">{{ $remItem['description'] ?? 'Line Item' }}</td>
                                                                            <td class="p-1.5 border border-gray-200 text-right font-mono">{{ $remItem['quantity'] ?? 1 }}</td>
                                                                            <td class="p-1.5 border border-gray-200 text-right font-mono">{{ $remItem['unit_price'] ?? '0.00' }}</td>
                                                                            <td class="p-1.5 border border-gray-200 text-right font-mono font-bold">{{ $remItem['amount'] ?? '0.00' }}</td>
                                                                            <td class="p-1.5 border border-gray-200 text-gray-600">{{ $remItem['department'] ?? '-' }}</td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                @endif

                                                <!-- Legacy / Fallback Items Delta -->
                                                @if(empty($itemsDiff['details']['modified']) && empty($itemsDiff['details']['added']) && empty($itemsDiff['details']['removed']) && !empty($itemsDiff['summary']))
                                                    <div>
                                                        <span class="font-bold text-gray-700 uppercase tracking-wider block mb-1">Items Delta:</span>
                                                        <span class="inline-block px-2 py-0.5 rounded bg-gray-200 text-gray-800 font-mono">
                                                            {{ $itemsDiff['summary'] }}
                                                        </span>
                                                    </div>
                                                @endif

                                                <!-- Third Party Costs Changes (Estimates) -->
                                                @if(!empty($costsDiff['details']['modified']))
                                                    <div>
                                                        <span class="font-bold text-purple-800 uppercase tracking-wider block mb-1">
                                                            <i class="fas fa-coins mr-1 text-purple-600"></i> Modified Third Party Costs:
                                                        </span>
                                                        <div class="overflow-x-auto">
                                                            <table class="w-full text-left border-collapse border border-gray-200 bg-white rounded">
                                                                <thead>
                                                                    <tr class="bg-purple-50 text-purple-900 border-b border-gray-200">
                                                                        <th class="p-1.5 border border-gray-200 w-16">Cost #</th>
                                                                        <th class="p-1.5 border border-gray-200">Supplier</th>
                                                                        <th class="p-1.5 border border-gray-200">Field</th>
                                                                        <th class="p-1.5 border border-gray-200 text-red-600">Previous Value</th>
                                                                        <th class="p-1.5 border border-gray-200 text-green-600">New Value</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @foreach($costsDiff['details']['modified'] as $modCost)
                                                                        @php
                                                                            $costChanges = $modCost['changes'] ?? [];
                                                                            $firstCost = true;
                                                                            $costRows = count($costChanges);
                                                                        @endphp
                                                                        @foreach($costChanges as $cField => $cChange)
                                                                            <tr class="border-t border-gray-100 hover:bg-gray-50">
                                                                                @if($firstCost)
                                                                                    <td class="p-1.5 border border-gray-200 font-bold text-gray-800 align-top" rowspan="{{ $costRows }}">
                                                                                        #{{ $modCost['cost_number'] ?? '-' }}
                                                                                    </td>
                                                                                    <td class="p-1.5 border border-gray-200 text-gray-700 align-top" rowspan="{{ $costRows }}">
                                                                                        {{ $modCost['supplier'] ?? 'Third Party' }}
                                                                                    </td>
                                                                                    @php $firstCost = false; @endphp
                                                                                @endif
                                                                                <td class="p-1.5 border border-gray-200 font-semibold text-gray-600 font-mono">
                                                                                    {{ ucwords(str_replace('_', ' ', $cField)) }}
                                                                                </td>
                                                                                <td class="p-1.5 border border-gray-200 text-red-700 bg-red-50/40 font-mono">
                                                                                    {{ $cChange['from'] ?? '(empty)' }}
                                                                                </td>
                                                                                <td class="p-1.5 border border-gray-200 text-green-700 bg-green-50/40 font-mono">
                                                                                    {{ $cChange['to'] ?? '(empty)' }}
                                                                                </td>
                                                                            </tr>
                                                                        @endforeach
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                @endif

                                                <!-- Legacy / Fallback Costs Delta -->
                                                @if(empty($costsDiff['details']['modified']) && empty($costsDiff['details']['added']) && empty($costsDiff['details']['removed']) && !empty($costsDiff['summary']))
                                                    <div>
                                                        <span class="font-bold text-gray-700 uppercase tracking-wider block mb-1">Third Party Costs Delta:</span>
                                                        <span class="inline-block px-2 py-0.5 rounded bg-purple-100 text-purple-800 font-mono">
                                                            {{ $costsDiff['summary'] }}
                                                        </span>
                                                    </div>
                                                @endif

                                                @if($fieldCount === 0 && $itemChangesCount === 0 && $costChangesCount === 0)
                                                    <div class="p-2 bg-gray-100 rounded text-gray-500 italic text-[11px]">
                                                        <i class="fas fa-info-circle mr-1"></i> No field-level or line-item differences were detected (saved with identical values).
                                                    </div>
                                                @endif

                                                <!-- Other Metadata Badges -->
                                                <div>
                                                    <span class="font-bold text-gray-700 uppercase tracking-wider block mb-1">Details & Context:</span>
                                                    <div class="flex flex-wrap gap-1.5">
                                                        @foreach($activity->properties as $propKey => $propVal)
                                                            @if(!in_array($propKey, ['changes', 'items_diff', 'costs_diff', 'items_summary']))
                                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-white border border-gray-300 text-gray-700">
                                                                    <strong class="mr-1 text-gray-500">{{ ucwords(str_replace('_', ' ', $propKey)) }}:</strong>
                                                                    {{ is_array($propVal) ? json_encode($propVal) : ($propVal ?? 'N/A') }}
                                                                </span>
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 align-top">
                                    @if($activity->subject_type)
                                        @php
                                            $base = class_basename($activity->subject_type);
                                            $badgeClass = match($base) {
                                                'Estimate' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                                                'Invoice' => 'bg-blue-50 text-blue-700 border-blue-200',
                                                'TempInvoice' => 'bg-amber-50 text-amber-700 border-amber-200',
                                                'Deal' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                                'User' => 'bg-orange-50 text-orange-700 border-orange-200',
                                                'Customer' => 'bg-purple-50 text-purple-700 border-purple-200',
                                                default => 'bg-gray-50 text-gray-700 border-gray-200',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold border {{ $badgeClass }}">
                                            {{ $base }}
                                            @if($activity->subject_id)
                                                <span class="ml-1 opacity-75">#{{ $activity->subject_id }}</span>
                                            @endif
                                        </span>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono align-top">
                                    {{ $activity->ip_address ?: '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 align-top">
                                    {{ $activity->created_at->format('M d, Y H:i:s') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-gray-500">
                                    <i class="fas fa-history text-4xl text-gray-200 mb-3"></i>
                                    <p class="font-medium text-gray-600">No activity logs found.</p>
                                    @if(request()->anyFilled(['search', 'subject_type', 'from_date', 'to_date']))
                                        <p class="text-xs text-gray-400 mt-1">Try clearing your search or filter options.</p>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($activities->hasPages())
                <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                    {{ $activities->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
