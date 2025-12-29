@extends('layouts.app')

@section('header', 'Deals Pipeline')

@section('content')
    <div class="h-full flex flex-col">
        <!-- Top Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Total Deal Amount</h4>
                <p class="text-xl font-bold text-brand-purple mt-1">LKR
                    {{ number_format($deals->flatten()->sum('amount'), 2) }}</p>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Open Deals</h4>
                <p class="text-xl font-bold text-green-600 mt-1">
                    {{ $deals->flatten()->whereNotIn('stage', ['Rejected', 'Approved'])->count() }}</p>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Weighted Amount</h4>
                <p class="text-xl font-bold text-brand-blue mt-1">LKR
                    {{ number_format($weightedDealAmount, 2) }}</p>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Approved Amount</h4>
                <p class="text-xl font-bold text-brand-pink mt-1">LKR
                    {{ number_format($approvedDealAmount, 2) }}</p>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider">New Deals (30d)</h4>
                <p class="text-xl font-bold text-brand-teal mt-1">LKR
                    {{ number_format($newDealAmount, 2) }}</p>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Avg Deal Age</h4>
                <p class="text-xl font-bold text-gray-700 mt-1">{{ $averageDealAge }}
                    <span class="text-sm font-normal">days</span></p>
            </div>
        </div>

        <!-- Action Button -->
        <div class="flex justify-end mb-4">
            <button onclick="document.getElementById('createDealModal').classList.remove('hidden')"
                class="bg-brand-pink hover:bg-brand-purple text-white font-bold py-2 px-4 rounded shadow transition-colors">
                <i class="fas fa-plus mr-2"></i> Create Deal
            </button>
        </div>

        <!-- Kanban Board -->
        <div class="flex-1 overflow-x-auto overflow-y-hidden">
            <div class="flex h-full space-x-4 pb-4" style="min-width: max-content;">
                @foreach($stages as $stage)
                    <div class="w-80 flex-shrink-0 flex flex-col bg-gray-100 rounded-lg">
                        <!-- Column Header -->
                        <div class="p-3 bg-gray-200 rounded-t-lg border-b border-gray-300 flex justify-between items-center">
                            <h3 class="font-bold text-gray-700 text-sm">{{ $stage }}</h3>
                            <span class="bg-gray-300 text-gray-600 text-xs font-semibold px-2 py-1 rounded-full">
                                {{ $deals->get($stage, collect())->count() }}
                            </span>
                        </div>

                        <div class="flex-1 p-2 overflow-y-auto kanban-col" data-stage="{{ $stage }}">
                            @foreach($deals->get($stage, collect()) as $deal)
                                <div class="bg-white p-3 rounded shadow-sm mb-3 cursor-move hover:shadow-md transition-shadow border-l-4 
                                    @if($stage === 'Rejected') border-red-500 
                                    @elseif($stage === 'Approved') border-green-500 
                                    @else border-brand-blue @endif"
                                    data-id="{{ $deal->id }}">
                                    <h4 class="font-bold text-gray-800 text-sm mb-1 line-clamp-1">{{ $deal->title }}</h4>
                                    <div class="flex flex-wrap gap-1 mb-2">
                                        @if($deal->priority)
                                            <span class="text-[10px] px-1.5 py-0.5 rounded-full font-bold uppercase
                                                @if($deal->priority == 'High') bg-red-100 text-red-600 
                                                @elseif($deal->priority == 'Medium') bg-yellow-100 text-yellow-600 
                                                @else bg-blue-100 text-blue-600 @endif">
                                                {{ $deal->priority }}
                                            </span>
                                        @endif
                                        @if($deal->type)
                                            <span class="text-[10px] px-1.5 py-0.5 rounded-full font-bold uppercase bg-gray-100 text-gray-600">
                                                {{ $deal->type == 'New Business' ? 'New' : 'Existing' }}
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-[11px] text-gray-500 mb-2 flex items-center">
                                        <i class="fas fa-building mr-1"></i>
                                        <span class="truncate">{{ $deal->customer_name ?? $deal->customer->name ?? 'Unknown' }}</span>
                                    </p>
                                    <div class="flex justify-between items-center mt-2 pt-2 border-t border-gray-50">
                                        <div class="flex items-center">
                                            @if($deal->owner)
                                                <div class="w-5 h-5 rounded-full bg-brand-purple flex items-center justify-center text-[10px] text-white font-bold mr-1" title="Owner: {{ $deal->owner->name }}">
                                                    {{ strtoupper(substr($deal->owner->name, 0, 1)) }}
                                                </div>
                                            @endif
                                            <span class="text-xs font-bold text-gray-900">{{ $deal->currency }} {{ number_format($deal->amount, 2) }}</span>
                                        </div>
                                        @if($deal->close_date)
                                            <span class="text-[10px] text-gray-400">
                                                {{ \Carbon\Carbon::parse($deal->close_date)->format('M d') }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Create Deal Modal -->
    <div id="createDealModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-10 mx-auto p-0 border w-full max-w-2xl shadow-xl rounded-xl bg-white overflow-hidden">
            <div class="bg-brand-purple px-6 py-4 flex justify-between items-center">
                <h3 class="text-xl font-bold text-white">Create New Deal</h3>
                <button onclick="document.getElementById('createDealModal').classList.add('hidden')" class="text-white hover:text-gray-200">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form action="{{ route('deals.store') }}" method="POST" class="p-6">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Left Column -->
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Deal Name <span class="text-red-500">*</span></label>
                            <input type="text" name="title" required placeholder="e.g. Q4 Marketing Campaign"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple focus:border-transparent outline-none transition-all">
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Pipeline <span class="text-red-500">*</span></label>
                            <select name="pipeline" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none">
                                <option value="Sales Pipeline">Sales Pipeline</option>
                                <option value="Marketing Pipeline">Marketing Pipeline</option>
                                <option value="Partnerships">Partnerships</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Deal Stage <span class="text-red-500">*</span></label>
                            <select name="stage" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none">
                                @foreach($stages as $stageOption)
                                    <option value="{{ $stageOption }}">{{ $stageOption }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">Amount <span class="text-red-500">*</span></label>
                                <input type="number" step="0.01" name="amount" required placeholder="0.00"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">Currency <span class="text-red-500">*</span></label>
                                <select name="currency" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none">
                                    @foreach($currencies as $currency)
                                        <option value="{{ $currency->code }}" {{ $currency->code == 'LKR' ? 'selected' : '' }}>{{ $currency->code }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Close Date</label>
                            <input type="date" name="close_date"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none">
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Deal Owner</label>
                            <select name="user_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none">
                                <option value="">-- Unassigned --</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Deal Type</label>
                            <select name="type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none">
                                <option value="New Business">New Business</option>
                                <option value="Existing Business">Existing Business</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Priority</label>
                            <select name="priority" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none">
                                <option value="Low">Low</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="High">High</option>
                            </select>
                        </div>

                        <div class="pt-4 border-t border-gray-100">
                            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Associate Deal with</h4>
                            
                            <div class="mb-4">
                                <label class="block text-xs font-bold text-gray-500 mb-1">Contact / Client</label>
                                <select name="customer_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none bg-gray-50">
                                    <option value="">-- Select Contact --</option>
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1">Company (If New)</label>
                                <input type="text" name="customer_name" placeholder="Enter Company Name"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none bg-gray-50">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex justify-end space-x-3 bg-gray-50 -mx-6 -mb-6 p-6">
                    <button type="button" onclick="document.getElementById('createDealModal').classList.add('hidden')"
                        class="px-6 py-2 bg-white border border-gray-300 text-gray-700 font-bold rounded-lg hover:bg-gray-50 transition-colors shadow-sm">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-8 py-2 bg-brand-pink text-white font-bold rounded-lg hover:bg-brand-purple transition-all shadow-md active:transform active:scale-95">
                        Create Deal
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- SortableJS -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const columns = document.querySelectorAll('.kanban-col');

            columns.forEach(col => {
                new Sortable(col, {
                    group: 'deals',
                    animation: 150,
                    ghostClass: 'bg-indigo-100',
                    onEnd: function (evt) {
                        const item = evt.item;
                        const newStage = evt.to.getAttribute('data-stage');
                        const dealId = item.getAttribute('data-id');

                        // Optimistic UI update could happen here

                        // API Call
                        fetch(`/deals/${dealId}/stage`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({ stage: newStage })
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (data.redirect) {
                                    window.location.href = data.redirect;
                                }
                            })
                            .catch(error => console.error('Error:', error));
                    }
                });
            });
        });
    </script>
@endsection