@extends('layouts.app')

@section('header', 'Deals Pipeline')

@section('content')
    <style>
        .ts-wrapper {
            width: 100% !important;
        }

        

        .ts-wrapper .ts-control {
            border: 1px solid #d1d5db !important;
            /* border-gray-300 */
            border-radius: 0.5rem !important;
            /* rounded-lg */
            padding: 0.5rem 1rem !important;
            /* px-4 py-2 */
            box-shadow: none !important;
            font-size: 0.875rem !important;
            /* text-sm */
            line-height: 1.25rem !important;
            min-height: 42px !important;
            display: flex !important;
            align-items: center !important;
            transition: all 0.2s !important;
            background-color: #fff !important;
        }

        .ts-wrapper.focus .ts-control {
            border-color: #8035ca !important;
            /* brand-purple */
            box-shadow: 0 0 0 2px rgba(128, 53, 202, 0.2) !important;
            outline: none !important;
        }

        .ts-wrapper.disabled .ts-control {
            background-color: #f3f4f6 !important;
            opacity: 1 !important;
        }

        .ts-dropdown {
            border-radius: 0.5rem !important;
            margin-top: 4px !important;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1) !important;
            border: 1px solid #e5e7eb !important;
        }

        .ts-dropdown .active {
            background-color: #8035ca !important;
            /* brand-purple */
            color: white !important;
        }

        .ts-control input {
            font-size: 0.875rem !important;
        }

        .cust-column {

            border-radius: 0.5rem !important;
        }
    </style>
    <div class="h-full flex flex-col">
        <!-- Top Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-8 gap-4 mb-6">
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Total Project</h4>
                <div class="mt-2 space-y-1">
                    <p class="text-xs font-bold text-gray-400">REVENUE: <span class="text-brand-purple">LKR {{ number_format($dealsByStage->flatten()->sum('revenue'), 2) }}</span></p>
                    <p class="text-xs font-bold text-gray-400">CONTRIBUTION: <span class="text-brand-purple">LKR {{ number_format($totalProjectContribution, 2) }}</span></p>
                </div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Open Deals</h4>
                <p class="text-xl font-bold text-green-600 mt-1">
                    {{ $dealsByStage->flatten()->whereNotIn('stage', ['Rejected', 'Closed Won'])->count() }}
                </p>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Weighted</h4>
                <div class="mt-2 space-y-1">
                    <p class="text-xs font-bold text-gray-400">REVENUE: <span class="text-brand-blue">LKR {{ number_format($weightedDealAmount, 2) }}</span></p>
                    <p class="text-xs font-bold text-gray-400">CONTRIBUTION: <span class="text-brand-blue">LKR {{ number_format($weightedContributionAmount, 2) }}</span></p>
                </div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Closed Won</h4>
                <div class="mt-2 space-y-1">
                    <p class="text-xs font-bold text-gray-400">REVENUE: <span class="text-brand-pink">LKR {{ number_format($approvedDealRevenue, 2) }}</span></p>
                    <p class="text-xs font-bold text-gray-400">CONTRIBUTION: <span class="text-brand-pink">LKR {{ number_format($approvedDealContribution, 2) }}</span></p>
                </div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider">New Revenue (30d)</h4>
                <p class="text-xl font-bold text-brand-teal mt-1">LKR
                    {{ number_format($newDealRevenue, 2) }}
                </p>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Avg Deal Age</h4>
                <p class="text-xl font-bold text-gray-700 mt-1">{{ $averageDealAge }}
                    <span class="text-sm font-normal">days</span>
                </p>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Invoiced</h4>
                <p class="text-xl font-bold text-brand-blue mt-1">LKR
                    {{ number_format($invoicedAmount, 2) }}
                </p>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Payment Collected</h4>
                <p class="text-xl font-bold text-green-600 mt-1">LKR
                    {{ number_format($paymentCollected, 2) }}
                </p>
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
                            <div class="p-3 bg-gray-200 rounded-t-lg border-b border-gray-300 flex justify-between items-center">
                                <h3 class="font-bold text-gray-700 text-sm">{{ $stage }}</h3>
                                <span class="bg-gray-300 text-gray-600 text-xs font-semibold px-2 py-1 rounded-full">
                                    {{ $dealsByStage->get($stage, collect())->count() }}
                                </span>
                            </div>
                            <div class="flex-1 p-2 overflow-y-auto kanban-col" data-stage="{{ $stage }}">
                                @foreach($dealsByStage->get($stage, collect()) as $deal)
                                    <div class="bg-white p-3 rounded shadow-sm mb-3 cursor-move hover:shadow-md transition-shadow border-l-4 @if($stage === 'Rejected') border-red-500 @elseif($stage === 'Closed Won') border-green-500 @else border-brand-blue @endif"
                                        data-id="{{ $deal->id }}">
                                        <div class="flex justify-between items-start mb-1">
                                            <h4 class="font-bold text-gray-800 text-sm line-clamp-1 flex-1">{{ $deal->title }}</h4>
                                            <div class="flex items-center gap-1 ml-2">
                                                @php $hasEstimate = $deal->estimates->isNotEmpty(); @endphp
                                                
                                                @if($stage === 'Objection handling')
                                                    @if(!$hasEstimate)
                                                    <button onclick="createEstimate({{ $deal->id }})"
                                                        class="text-green-500 hover:text-green-700 transition-colors" title="Create Estimate">
                                                        <i class="fas fa-file-invoice text-xs"></i>
                                                    </button>
                                                    @else
                                                    <a href="{{ route('estimates.edit', $deal->estimates->first()->id) }}"
                                                        class="text-purple-500 hover:text-purple-700 transition-colors" title="Edit Estimate">
                                                        <i class="fas fa-file-invoice text-xs"></i>
                                                    </a>
                                                    @endif
                                                @endif
                                                @if(in_array($stage, ['Finalizing terms', 'Closed Won']) && $hasEstimate)
                                                <a href="{{ route('estimates.edit', $deal->estimates->first()->id) }}"
                                                    class="text-purple-500 hover:text-purple-700 transition-colors" title="Edit Estimate">
                                                    <i class="fas fa-file-invoice text-xs"></i>
                                                </a>
                                                @endif
                                                @if(!in_array($stage, ['Objection handling', 'Finalizing terms', 'Closed Won']))
                                                <button onclick="editDeal({{ json_encode($deal) }})"
                                                    class="text-blue-400 hover:text-blue-600 transition-colors" title="Edit Deal">
                                                    <i class="fas fa-edit text-xs"></i>
                                                </button>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="flex flex-wrap gap-1 mb-2">
                                            @if($deal->job_number)
                                                <span
                                                    class="text-[10px] px-1.5 py-0.5 rounded-full font-bold uppercase bg-brand-purple text-white">{{ $deal->job_number }}</span>
                                            @endif
                                            @if($stage === 'Rejected' && $deal->rejection_reason)
                                                <div
                                                    class="w-full mt-2 p-2 bg-red-50 text-red-700 text-[10px] rounded border border-red-100 italic">
                                                    <strong>Reason:</strong> {{ $deal->rejection_reason }}</div>
                                            @endif
                                            @if($deal->priority)
                                                <span
                                                    class="text-[10px] px-1.5 py-0.5 rounded-full font-bold uppercase @if($deal->priority == 'High') bg-red-100 text-red-600 @elseif($deal->priority == 'Medium') bg-yellow-100 text-yellow-600 @else bg-blue-100 text-blue-600 @endif">{{ $deal->priority }}</span>
                                            @endif
                                            @if($deal->type)
                                                <span
                                                    class="text-[10px] px-1.5 py-0.5 rounded-full font-bold uppercase bg-gray-100 text-gray-600">{{ $deal->type == 'New Business' ? 'New' : 'Existing' }}</span>
                                            @endif
                                            @if($deal->winning_percentage)
                                                <span
                                                    class="text-[10px] px-1.5 py-0.5 rounded-full font-bold uppercase bg-emerald-100 text-emerald-600" title="Winning Probability">{{ $deal->winning_percentage }}%</span>
                                            @endif
                                        </div>
                                        <p class="text-[11px] text-gray-500 mb-2 flex items-center">
                                            <i class="fas fa-building mr-1"></i>
                                            <span
                                                class="truncate">{{ $deal->customer_name ?? $deal->customer->name ?? 'Unknown' }}</span>
                                        </p>
                                        <div class="flex justify-between items-center mt-2 pt-2 border-t border-gray-50">
                                            <div class="flex items-center">
                                                @if($deal->owner)
                                                    <div class="w-5 h-5 rounded-full bg-brand-purple flex items-center justify-center text-[10px] text-white font-bold mr-1"
                                                        title="Owner: {{ $deal->owner->name }}">
                                                        {{ strtoupper(substr($deal->owner->name, 0, 1)) }}
                                                    </div>
                                                @endif
                                                <span class="text-xs font-bold text-gray-900">{{ $deal->currency }}
                                                    {{ number_format($deal->contribution, 2) }}</span>
                                            </div>
                                            <div class="flex -space-x-1.5 overflow-hidden py-1">
                                                @foreach($deal->teamMembers->take(4) as $member)
                                                    <div class="inline-block h-4 w-4 rounded-full ring-2 ring-white bg-slate-200 flex items-center justify-center text-[8px] font-black text-slate-600 uppercase"
                                                        title="{{ $member->name }}">
                                                        {{ strtoupper(substr($member->name, 0, 1)) }}
                                                    </div>
                                                @endforeach
                                                @if($deal->teamMembers->count() > 4)
                                                    <div
                                                        class="inline-block h-4 w-4 rounded-full ring-2 ring-white bg-slate-100 flex items-center justify-center text-[8px] font-bold text-slate-400">
                                                        +{{ $deal->teamMembers->count() - 4 }}
                                                    </div>
                                                @endif
                                            </div>
                                            @if($deal->close_date)
                                                <span
                                                    class="text-[10px] text-gray-400">{{ \Carbon\Carbon::parse($deal->close_date)->format('M d') }}</span>
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
                <button onclick="document.getElementById('createDealModal').classList.add('hidden')"
                    class="text-white hover:text-gray-200">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form action="{{ route('deals.store') }}" method="POST" class="p-6">
                @csrf
                <div class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Left Column -->
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">Deal Name <span
                                        class="text-red-500">*</span></label>
                                <input type="text" name="title" required placeholder="e.g. Q4 Marketing Campaign"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple focus:border-transparent outline-none transition-all">
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1 flex items-center">
                                    Customer <span class="text-red-500">*</span> <i
                                        class="fas fa-info-circle ml-1 opacity-50 text-[10px]"></i>
                                </label>
                                <select name="customer_id" id="company_select" placeholder="Search Customer..."
                                    class="cust-column">
                                    <option value="">Search Customer...</option>
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1">Pipeline <span
                                            class="text-red-500">*</span></label>
                                    <select name="pipeline" required
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none">
                                        <option value="Sales Pipeline">Sales Pipeline</option>
                                        <option value="Marketing Pipeline">Marketing Pipeline</option>
                                        <option value="Partnerships">Partnerships</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1">Deal Stage <span
                                            class="text-red-500">*</span></label>
                                    <select name="stage" id="create_stage" required
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none">
                                        @foreach($stages as $stageOption)
                                            <option value="{{ $stageOption }}">{{ $stageOption }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div id="create_rejection_reason_container" class="hidden mt-4">
                                <label class="block text-sm font-bold text-gray-700 mb-1">Rejection Reason <span
                                        class="text-red-500">*</span></label>
                                <textarea name="rejection_reason" id="create_rejection_reason" rows="2"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none"
                                    placeholder="Enter reason for rejection..."></textarea>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1">Project Revenue <span
                                            class="text-red-500">*</span></label>
                                    <input type="number" step="0.01" name="revenue" required placeholder="0.00"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none">
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1">Currency <span
                                            class="text-red-500">*</span></label>
                                    <select name="currency" required
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none">
                                        @foreach($currencies as $currency)
                                            <option value="{{ $currency->code }}" {{ $currency->code == 'LKR' ? 'selected' : '' }}>
                                                {{ $currency->code }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">Contribution <span class="text-red-500">*</span></label>
                                <input type="number" step="0.01" min="0" name="contribution" placeholder="0.00" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none">
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">Deal Owner</label>
                                <select name="user_id"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none">
                                    <option value="">-- Unassigned --</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">Deal Type</label>
                                <select name="type"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none">
                                    <option value="New Business">New Business</option>
                                    <option value="Existing Business">Existing Business</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">Priority</label>
                                <select name="priority"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none">
                                    <option value="Low">Low</option>
                                    <option value="Medium" selected>Medium</option>
                                    <option value="High">High</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">Winning Percentage (%)</label>
                                <input type="number" name="winning_percentage" id="create_winning_percentage" readonly tabindex="-1"
                                    class="w-full px-4 py-2 border border-gray-200 rounded-lg bg-gray-50 text-gray-500 cursor-not-allowed outline-none select-none">
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">Close Date</label>
                                <input type="date" name="close_date"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none">
                            </div>
                        </div>
                    </div>

                    <!-- Project Split Section (Full Width) -->
                    <div class="bg-gray-50 -mx-6 p-6 border-y border-gray-100 mt-4">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-sm font-bold text-gray-700 flex items-center">
                                <i class="fas fa-users mr-2 text-brand-purple"></i> Project Split
                            </h4>
                            <button type="button" id="add-department-btn"
                                class="px-4 py-1.5 bg-brand-purple text-white text-xs font-bold rounded-lg hover:bg-brand-blue transition-all shadow-sm">
                                <i class="fas fa-plus mr-1"></i> Add Department
                            </button>
                        </div>

                        <div id="department-allocations" class="space-y-3">
                            <!-- Dynamic department allocation rows will be added here -->
                        </div>

                        <!-- Hidden template for department row -->
                        <template id="department-row-template">
                            <div class="department-row flex flex-wrap md:flex-nowrap items-center gap-2 p-3 bg-gray-50 rounded-lg border border-gray-200 w-full mb-2">
                                <div class="w-full md:w-3/12">
                                    <select class="department-select w-full px-2 py-2 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none" required>
                                        <option value="">Department</option>
                                        <option value="Corporate">Corporate</option>
                                        <option value="Creative">Creative</option>
                                        <option value="Digital">Digital</option>
                                        <option value="Play">Play</option>
                                        <option value="Tech">Tech</option>
                                    </select>
                                </div>
                                <div class="w-[48%] md:w-2/12 relative">
                                    <input type="number" step="0.01" min="0" max="100" placeholder="Rev %" class="department-rev-percentage w-full px-2 py-2 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none pr-6" required>
                                    <span class="absolute right-2 top-2 text-gray-500 text-xs">%</span>
                                </div>
                                <div class="w-[48%] md:w-2/12">
                                    <input type="number" step="0.01" min="0" placeholder="Revenue" class="department-rev-amount w-full px-2 py-2 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none" required>
                                </div>
                                <div class="w-[48%] md:w-2/12 relative mt-2 md:mt-0">
                                    <input type="number" step="0.01" min="0" max="100" placeholder="Con %" class="department-con-percentage w-full px-2 py-2 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none pr-6" required>
                                    <span class="absolute right-2 top-2 text-gray-500 text-xs">%</span>
                                </div>
                                <div class="w-[48%] md:w-2/12 mt-2 md:mt-0">
                                    <input type="number" step="0.01" min="0" placeholder="Contribution" class="department-con-amount w-full px-2 py-2 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none" required>
                                </div>
                                <button type="button" class="remove-department-btn px-2 py-2 bg-red-500 text-white text-xs rounded-lg hover:bg-red-600 transition-all flex-shrink-0 mt-2 md:mt-0">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </template>
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

    <!-- Edit Deal Modal -->
    <div id="editDealModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-10 mx-auto p-0 border w-full max-w-2xl shadow-xl rounded-xl bg-white overflow-hidden">
            <div class="bg-brand-purple px-6 py-4 flex justify-between items-center">
                <h3 class="text-xl font-bold text-white">Edit Deal</h3>
                <button onclick="document.getElementById('editDealModal').classList.add('hidden')"
                    class="text-white hover:text-gray-200">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form id="editDealForm" action="" method="POST" class="p-6">
                @csrf
                @method('PUT')
                <div class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Left Column -->
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">Deal Name <span
                                        class="text-red-500">*</span></label>
                                <input type="text" name="title" id="edit_title" required
                                    placeholder="e.g. Q4 Marketing Campaign"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple focus:border-transparent outline-none transition-all">
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1 flex items-center">
                                    Customer <span class="text-red-500">*</span> <i
                                        class="fas fa-info-circle ml-1 opacity-50 text-[10px]"></i>
                                </label>
                                <select name="customer_id" id="edit_company_select" placeholder="Search Customer..."
                                    class="cust-column">
                                    <option value="">Search Customer...</option>
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1">Pipeline <span
                                            class="text-red-500">*</span></label>
                                    <select name="pipeline" id="edit_pipeline" required
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none">
                                        <option value="Sales Pipeline">Sales Pipeline</option>
                                        <option value="Marketing Pipeline">Marketing Pipeline</option>
                                        <option value="Partnerships">Partnerships</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1">Deal Stage <span
                                            class="text-red-500">*</span></label>
                                    <select name="stage" id="edit_stage" required
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none">
                                        @foreach($stages as $stageOption)
                                            <option value="{{ $stageOption }}">{{ $stageOption }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div id="edit_rejection_reason_container" class="hidden mt-4">
                                <label class="block text-sm font-bold text-gray-700 mb-1">Rejection Reason <span
                                        class="text-red-500">*</span></label>
                                <textarea name="rejection_reason" id="edit_rejection_reason" rows="2"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none"
                                    placeholder="Enter reason for rejection..."></textarea>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1">Project Revenue <span
                                            class="text-red-500">*</span></label>
                                    <input type="number" step="0.01" name="revenue" id="edit_revenue" required
                                        placeholder="0.00"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none">
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1">Currency <span
                                            class="text-red-500">*</span></label>
                                    <select name="currency" id="edit_currency" required
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none">
                                        @foreach($currencies as $currency)
                                            <option value="{{ $currency->code }}">{{ $currency->code }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">Contribution <span class="text-red-500">*</span></label>
                                <input type="number" step="0.01" min="0" name="contribution" id="edit_contribution" placeholder="0.00" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none">
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">Deal Owner</label>
                                <select name="user_id" id="edit_user_id"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none">
                                    <option value="">-- Unassigned --</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">Deal Type</label>
                                <select name="type" id="edit_type"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none">
                                    <option value="New Business">New Business</option>
                                    <option value="Existing Business">Existing Business</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">Priority</label>
                                <select name="priority" id="edit_priority"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none">
                                    <option value="Low">Low</option>
                                    <option value="Medium">Medium</option>
                                    <option value="High">High</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">Winning Percentage (%)</label>
                                <input type="number" name="winning_percentage" id="edit_winning_percentage" readonly tabindex="-1"
                                    class="w-full px-4 py-2 border border-gray-200 rounded-lg bg-gray-50 text-gray-500 cursor-not-allowed outline-none select-none">
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">Close Date</label>
                                <input type="date" name="close_date" id="edit_close_date"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none">
                            </div>
                        </div>
                    </div>

                    <!-- Project Split Section (Full Width) -->
                    <div class="bg-gray-50 -mx-6 p-6 border-y border-gray-100 mt-4">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-sm font-bold text-gray-700 flex items-center">
                                <i class="fas fa-users mr-2 text-brand-purple"></i> Project Split
                            </h4>
                            <button type="button" id="edit-add-department-btn"
                                class="px-4 py-1.5 bg-brand-purple text-white text-xs font-bold rounded-lg hover:bg-brand-blue transition-all shadow-sm">
                                <i class="fas fa-plus mr-1"></i> Add Department
                            </button>
                        </div>

                        <div id="edit-department-allocations" class="space-y-3">
                            <!-- Dynamic department allocation rows will be added here -->
                        </div>

                        <!-- Hidden template for edit department row -->
                        <template id="edit-department-row-template">
                            <div class="department-row flex flex-wrap md:flex-nowrap items-center gap-2 p-3 bg-gray-50 rounded-lg border border-gray-200 w-full mb-2">
                                <div class="w-full md:w-3/12">
                                    <select class="department-select w-full px-2 py-2 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none" required>
                                        <option value="">Department</option>
                                        <option value="Corporate">Corporate</option>
                                        <option value="Creative">Creative</option>
                                        <option value="Digital">Digital</option>
                                        <option value="Play">Play</option>
                                        <option value="Tech">Tech</option>
                                    </select>
                                </div>
                                <div class="w-[48%] md:w-2/12 relative">
                                    <input type="number" step="0.01" min="0" max="100" placeholder="Rev %" class="department-rev-percentage w-full px-2 py-2 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none pr-6" required>
                                    <span class="absolute right-2 top-2 text-gray-500 text-xs">%</span>
                                </div>
                                <div class="w-[48%] md:w-2/12">
                                    <input type="number" step="0.01" min="0" placeholder="Revenue" class="department-rev-amount w-full px-2 py-2 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none" required>
                                </div>
                                <div class="w-[48%] md:w-2/12 relative mt-2 md:mt-0">
                                    <input type="number" step="0.01" min="0" max="100" placeholder="Con %" class="department-con-percentage w-full px-2 py-2 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none pr-6" required>
                                    <span class="absolute right-2 top-2 text-gray-500 text-xs">%</span>
                                </div>
                                <div class="w-[48%] md:w-2/12 mt-2 md:mt-0">
                                    <input type="number" step="0.01" min="0" placeholder="Contribution" class="department-con-amount w-full px-2 py-2 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-purple outline-none" required>
                                </div>
                                <button type="button" class="remove-department-btn px-2 py-2 bg-red-500 text-white text-xs rounded-lg hover:bg-red-600 transition-all flex-shrink-0 mt-2 md:mt-0">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </template>
                        <input type="hidden" name="department_allocations_cleared" id="edit_department_allocations_cleared"
                            value="0">
                    </div>
                </div>

                <div class="mt-8 flex justify-end space-x-3 bg-gray-50 -mx-6 -mb-6 p-6">
                    <button type="button" onclick="document.getElementById('editDealModal').classList.add('hidden')"
                        class="px-6 py-2 bg-white border border-gray-300 text-gray-700 font-bold rounded-lg hover:bg-gray-50 transition-colors shadow-sm">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-8 py-2 bg-brand-pink text-white font-bold rounded-lg hover:bg-brand-purple transition-all shadow-md active:transform active:scale-95">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- SortableJS -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <script>
        function editDeal(deal) {
            const form = document.getElementById('editDealForm');
            form.action = `/deals/${deal.id}`;

            document.getElementById('edit_title').value = deal.title;
            document.getElementById('edit_pipeline').value = deal.pipeline || 'Sales Pipeline';
            document.getElementById('edit_stage').value = deal.stage;
            document.getElementById('edit_revenue').value = deal.revenue;
            document.getElementById('edit_contribution').value = deal.contribution || '';
            document.getElementById('edit_currency').value = deal.currency;
            document.getElementById('edit_close_date').value = deal.close_date;
            document.getElementById('edit_user_id').value = deal.user_id || '';
            document.getElementById('edit_type').value = deal.type || 'New Business';
            document.getElementById('edit_priority').value = deal.priority || 'Medium';
            document.getElementById('edit_winning_percentage').value = deal.winning_percentage || '';
            document.getElementById('edit_company_select').tomselect.setValue(deal.customer_id || '');

            // Handle rejection reason visibility
            const rejectionReasonContainer = document.getElementById('edit_rejection_reason_container');
            const rejectionReasonInput = document.getElementById('edit_rejection_reason');
            if (deal.stage === 'Rejected') {
                rejectionReasonContainer.classList.remove('hidden');
                rejectionReasonInput.value = deal.rejection_reason || '';
                rejectionReasonInput.setAttribute('required', 'required');
            } else {
                rejectionReasonContainer.classList.add('hidden');
                rejectionReasonInput.value = '';
                rejectionReasonInput.removeAttribute('required');
            }

            // Populate Department Split
            const editDeptContainer = document.getElementById('edit-department-allocations');
            const template = document.getElementById('edit-department-row-template');
            editDeptContainer.innerHTML = ''; // Clear existing

            if (deal.department_split) {
                try {
                    const allocations = typeof deal.department_split === 'string' ? JSON.parse(deal.department_split) : deal.department_split;
                    const items = Array.isArray(allocations) ? allocations : Object.values(allocations);

                    items.forEach((allocation, index) => {
                        if (allocation.department) {
                            const clone = template.content.cloneNode(true);
                            const row = clone.querySelector('.department-row');
                            const select = row.querySelector('.department-select');
                            const revPercentInput = row.querySelector('.department-rev-percentage');
                            const revAmountInput = row.querySelector('.department-rev-amount');
                            const conPercentInput = row.querySelector('.department-con-percentage');
                            const conAmountInput = row.querySelector('.department-con-amount');
                            const removeBtn = row.querySelector('.remove-department-btn');

                            select.value = allocation.department;
                            revPercentInput.value = allocation.revenue_percentage || allocation.percentage || '';
                            revAmountInput.value = allocation.revenue_amount || allocation.cost || '';
                            conPercentInput.value = allocation.contribution_percentage || '';
                            conAmountInput.value = allocation.contribution_amount || '';

                            select.name = `department_allocations[${index}][department]`;
                            revPercentInput.name = `department_allocations[${index}][revenue_percentage]`;
                            revAmountInput.name = `department_allocations[${index}][revenue_amount]`;
                            conPercentInput.name = `department_allocations[${index}][contribution_percentage]`;
                            conAmountInput.name = `department_allocations[${index}][contribution_amount]`;

                            removeBtn.addEventListener('click', function () {
                                this.closest('.department-row').remove();
                            });
                            editDeptContainer.appendChild(clone);
                        }
                    });
                } catch (e) {
                    console.error('Error parsing department split:', e);
                }
            }

            document.getElementById('editDealModal').classList.remove('hidden');
        }

        document.addEventListener('DOMContentLoaded', function () {
            // ... existing sortable code ...
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

                        // If moving to Rejected, force open Edit Modal for reason
                        if (newStage === 'Rejected') {
                            // Revert visual move (simple append to 'from' container)
                            evt.from.appendChild(item);
                            
                            // Trigger edit modal
                            const editBtn = item.querySelector('button[onclick^="editDeal"]');
                            if (editBtn) {
                                editBtn.click();
                                // Set stage to Rejected and trigger change event after modal opens
                                setTimeout(() => {
                                    const stageSelect = document.getElementById('edit_stage');
                                    if (stageSelect) {
                                        stageSelect.value = 'Rejected';
                                        stageSelect.dispatchEvent(new Event('change'));
                                        // Focus the reason field
                                        document.getElementById('edit_rejection_reason').focus();
                                    }
                                }, 200);
                            }
                            return;
                        }

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
                                if (data.job_number) {
                                    // Find or create job number badge
                                    let badgeContainer = item.querySelector('.flex.flex-wrap.gap-1.mb-2');
                                    let badge = Array.from(badgeContainer.children).find(el => el.textContent.trim() === data.job_number);

                                    if (!badge) {
                                        const badgeHtml = `<span class="text-[10px] px-1.5 py-0.5 rounded-full font-bold uppercase bg-brand-purple text-white">${data.job_number}</span>`;
                                        badgeContainer.insertAdjacentHTML('afterbegin', badgeHtml);
                                    }
                                }
                            })
                            .catch(error => console.error('Error:', error));
                    }
                });
            });
        });

        // Department Allocation Functionality
        document.addEventListener('DOMContentLoaded', function () {
            const addDeptBtn = document.getElementById('add-department-btn');
            const deptContainer = document.getElementById('department-allocations');
            const template = document.getElementById('department-row-template');

            // Add department row
            addDeptBtn.addEventListener('click', function () {
                const clone = template.content.cloneNode(true);
                const row = clone.querySelector('.department-row');

                // Add remove functionality
                const removeBtn = clone.querySelector('.remove-department-btn');
                removeBtn.addEventListener('click', function () {
                    this.closest('.department-row').remove();
                });

                // Add name attributes for form submission
                const select = clone.querySelector('.department-select');
                const revPercentInput = clone.querySelector('.department-rev-percentage');
                const revAmountInput = clone.querySelector('.department-rev-amount');
                const conPercentInput = clone.querySelector('.department-con-percentage');
                const conAmountInput = clone.querySelector('.department-con-amount');
                const index = deptContainer.children.length;
                select.name = `department_allocations[${index}][department]`;
                revPercentInput.name = `department_allocations[${index}][revenue_percentage]`;
                revAmountInput.name = `department_allocations[${index}][revenue_amount]`;
                conPercentInput.name = `department_allocations[${index}][contribution_percentage]`;
                conAmountInput.name = `department_allocations[${index}][contribution_amount]`;

                deptContainer.appendChild(clone);
            });

            // Handle form submission for Create Deal
            const createForm = document.querySelector('#createDealModal form');
            if (createForm) {
                createForm.addEventListener('submit', function (e) {
                    const rows = deptContainer.querySelectorAll('.department-row');
                    rows.forEach((row, index) => {
                        const select = row.querySelector('.department-select');
                        const revPercentInput = row.querySelector('.department-rev-percentage');
                        const revAmountInput = row.querySelector('.department-rev-amount');
                        const conPercentInput = row.querySelector('.department-con-percentage');
                        const conAmountInput = row.querySelector('.department-con-amount');
                        select.name = `department_allocations[${index}][department]`;
                        revPercentInput.name = `department_allocations[${index}][revenue_percentage]`;
                        revAmountInput.name = `department_allocations[${index}][revenue_amount]`;
                        conPercentInput.name = `department_allocations[${index}][contribution_percentage]`;
                        conAmountInput.name = `department_allocations[${index}][contribution_amount]`;
                    });
                });
            }

            // Edit Deal Modal Functionality
            const editAddDeptBtn = document.getElementById('edit-add-department-btn');
            const editDeptContainer = document.getElementById('edit-department-allocations');
            const editTemplate = document.getElementById('edit-department-row-template');

            if (editAddDeptBtn) {
                editAddDeptBtn.addEventListener('click', function () {
                    const clone = editTemplate.content.cloneNode(true);

                    const removeBtn = clone.querySelector('.remove-department-btn');
                    removeBtn.addEventListener('click', function () {
                        this.closest('.department-row').remove();
                    });

                    const select = clone.querySelector('.department-select');
                    const revPercentInput = clone.querySelector('.department-rev-percentage');
                    const revAmountInput = clone.querySelector('.department-rev-amount');
                    const conPercentInput = clone.querySelector('.department-con-percentage');
                    const conAmountInput = clone.querySelector('.department-con-amount');
                    const index = editDeptContainer.children.length;
                    select.name = `department_allocations[${index}][department]`;
                    revPercentInput.name = `department_allocations[${index}][revenue_percentage]`;
                    revAmountInput.name = `department_allocations[${index}][revenue_amount]`;
                    conPercentInput.name = `department_allocations[${index}][contribution_percentage]`;
                    conAmountInput.name = `department_allocations[${index}][contribution_amount]`;

                    editDeptContainer.appendChild(clone);
                });
            }

            const editForm = document.getElementById('editDealForm');
            if (editForm) {
                editForm.addEventListener('submit', function (e) {
                    const rows = editDeptContainer.querySelectorAll('.department-row');
                    rows.forEach((row, index) => {
                        const select = row.querySelector('.department-select');
                        const revPercentInput = row.querySelector('.department-rev-percentage');
                        const revAmountInput = row.querySelector('.department-rev-amount');
                        const conPercentInput = row.querySelector('.department-con-percentage');
                        const conAmountInput = row.querySelector('.department-con-amount');
                        select.name = `department_allocations[${index}][department]`;
                        revPercentInput.name = `department_allocations[${index}][revenue_percentage]`;
                        revAmountInput.name = `department_allocations[${index}][revenue_amount]`;
                        conPercentInput.name = `department_allocations[${index}][contribution_percentage]`;
                        conAmountInput.name = `department_allocations[${index}][contribution_amount]`;
                    });

                    // Handle clearing
                    const clearedInput = document.getElementById('edit_department_allocations_cleared');
                    if (rows.length === 0) {
                        clearedInput.value = '1';
                    } else {
                        clearedInput.value = '0';
                    }
                });
            }

            function handleSplitCalculations(container, modalType) {
                container.addEventListener('input', function(e) {
                    const revInput = modalType === 'create' ? document.querySelector('#createDealModal input[name="revenue"]') : document.getElementById('edit_revenue');
                    const conInput = modalType === 'create' ? document.querySelector('#createDealModal input[name="contribution"]') : document.getElementById('edit_contribution');
                    
                    const totalRev = parseFloat(revInput.value) || 0;
                    const totalCon = parseFloat(conInput.value) || 0;

                    const row = e.target.closest('.department-row');
                    if (!row) return;

                    const revPercent = row.querySelector('.department-rev-percentage');
                    const revAmt = row.querySelector('.department-rev-amount');
                    const conPercent = row.querySelector('.department-con-percentage');
                    const conAmt = row.querySelector('.department-con-amount');

                    if (e.target.classList.contains('department-rev-percentage')) {
                        const p = parseFloat(e.target.value) || 0;
                        if (totalRev > 0) revAmt.value = ((p / 100) * totalRev).toFixed(2);
                    } else if (e.target.classList.contains('department-rev-amount')) {
                        const amt = parseFloat(e.target.value) || 0;
                        if (totalRev > 0) revPercent.value = ((amt / totalRev) * 100).toFixed(2);
                    } else if (e.target.classList.contains('department-con-percentage')) {
                        const p = parseFloat(e.target.value) || 0;
                        if (totalCon > 0) conAmt.value = ((p / 100) * totalCon).toFixed(2);
                    } else if (e.target.classList.contains('department-con-amount')) {
                        const amt = parseFloat(e.target.value) || 0;
                        if (totalCon > 0) conPercent.value = ((amt / totalCon) * 100).toFixed(2);
                    }
                });

                // Also recalculate when main revenue/contribution changes
                const revInput = modalType === 'create' ? document.querySelector('#createDealModal input[name="revenue"]') : document.getElementById('edit_revenue');
                const conInput = modalType === 'create' ? document.querySelector('#createDealModal input[name="contribution"]') : document.getElementById('edit_contribution');
                
                function recalculateAll() {
                    const totalRev = parseFloat(revInput.value) || 0;
                    const totalCon = parseFloat(conInput.value) || 0;
                    const rows = container.querySelectorAll('.department-row');
                    rows.forEach(row => {
                        const revPercent = row.querySelector('.department-rev-percentage');
                        const revAmt = row.querySelector('.department-rev-amount');
                        const conPercent = row.querySelector('.department-con-percentage');
                        const conAmt = row.querySelector('.department-con-amount');

                        if (revPercent.value) {
                            revAmt.value = ((parseFloat(revPercent.value) / 100) * totalRev).toFixed(2);
                        }
                        if (conPercent.value) {
                            conAmt.value = ((parseFloat(conPercent.value) / 100) * totalCon).toFixed(2);
                        }
                    });
                }
                
                if (revInput) revInput.addEventListener('input', recalculateAll);
                if (conInput) conInput.addEventListener('input', recalculateAll);
            }

            handleSplitCalculations(document.getElementById('department-allocations'), 'create');
            handleSplitCalculations(document.getElementById('edit-department-allocations'), 'edit');

        });
        // Initialize Tom Select
        document.addEventListener('DOMContentLoaded', function () {
            // Shared stage probabilities
            const stageProbs = {
                'Planned to Meet': 10,
                'Introductory meeting': 10,
                'Brief Stage': 20,
                'Working on pitch': 40,
                'Pitched': 50,
                'Objection handling': 80,
                'Finalizing terms': 90,
                'Rejected': 0,
                'Closed Won': 100
            };

            // Handle edit stage change for rejection reason
            const editStageSelect = document.getElementById('edit_stage');
            const editRejectionReasonContainer = document.getElementById('edit_rejection_reason_container');
            const editRejectionReasonInput = document.getElementById('edit_rejection_reason');
            const editWinningPercentageInput = document.getElementById('edit_winning_percentage');

            // Handle create stage change for rejection reason
            const createStageSelect = document.getElementById('create_stage');
            const createRejectionReasonContainer = document.getElementById('create_rejection_reason_container');
            const createRejectionReasonInput = document.getElementById('create_rejection_reason');
            const createWinningPercentageInput = document.getElementById('create_winning_percentage');

            if (createStageSelect) {
                // Initialize default
                if (createWinningPercentageInput && createStageSelect.value) {
                     createWinningPercentageInput.value = stageProbs[createStageSelect.value] || 0;
                }
                
                createStageSelect.addEventListener('change', function () {
                    
                    if (createWinningPercentageInput) {
                        createWinningPercentageInput.value = stageProbs[this.value] || 0;
                    }

                    if (this.value === 'Rejected') {
                        createRejectionReasonContainer.classList.remove('hidden');
                        createRejectionReasonInput.setAttribute('required', 'required');
                    } else {
                        createRejectionReasonContainer.classList.add('hidden');
                        createRejectionReasonInput.removeAttribute('required');
                        createRejectionReasonInput.value = '';
                    }
                });
            }

            if (editStageSelect) {
                editStageSelect.addEventListener('change', function () {
                    
                    if (editWinningPercentageInput) {
                        editWinningPercentageInput.value = stageProbs[this.value] || 0;
                    }

                    if (this.value === 'Rejected') {
                        editRejectionReasonContainer.classList.remove('hidden');
                        editRejectionReasonInput.setAttribute('required', 'required');
                    } else {
                        editRejectionReasonContainer.classList.add('hidden');
                        editRejectionReasonInput.removeAttribute('required');
                        editRejectionReasonInput.value = '';
                    }
                });
            }

            new TomSelect('#company_select', {
                create: true,
                sortField: {
                    field: "text",
                    direction: "asc"
                }
            });

            new TomSelect('#edit_company_select', {
                create: true,
                sortField: {
                    field: "text",
                    direction: "asc"
                }
            });
        });

        // Auto-open deal modal if deal_id is present in URL
        document.addEventListener('DOMContentLoaded', function () {
            const urlParams = new URLSearchParams(window.location.search);
            const dealId = urlParams.get('deal_id');
            if (dealId) {
                setTimeout(() => {
                    const dealCard = document.querySelector(`.kanban-col [data-id="${dealId}"]`);
                    if (dealCard) {
                        const editBtn = dealCard.querySelector('button[onclick^="editDeal"]');
                        if (editBtn) editBtn.click();
                    }
                }, 500);
            }
        });

        function createEstimate(dealId) {
            if (confirm('Create Estimate? This will generate a draft estimate from this deal.')) {
                fetch(`/deals/${dealId}/create-estimate`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    } else if (data.message) {
                        alert(data.message);
                        window.location.reload();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while creating the estimate.');
                });
            }
        }
    </script>
@endsection