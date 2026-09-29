@extends('layouts.app')

@section('header', 'User Management')

@section('content')
    @php
        $rolesList = [
            'all' => 'All Users',
            'Finance Admin' => 'Finance Admin',
            'IT Admin' => 'IT Admin',
            'Management' => 'Management',
            'HOD' => 'HOD',
            'Manager' => 'Manager',
            'Staff' => 'Staff',
        ];
    @endphp

    <div class="bg-white rounded-xl shadow-md overflow-hidden border border-gray-100"
        x-data="{
            activeTab: (new URLSearchParams(window.location.search)).get('role') || 'all',
            searchQuery: (new URLSearchParams(window.location.search)).get('search') || '',
            usersList: [
                @foreach($users as $user)
                    @php
                        $displayRole = in_array($user->role, ['Finance Admin', 'Super Admin']) ? 'Finance Admin' : $user->role;
                        $searchBlob = strtolower($user->name . ' ' . $user->email . ' ' . $displayRole . ' ' . ($user->department ?: '') . ' ' . ($user->supervisor ? $user->supervisor->name : ''));
                    @endphp
                    { role: {{ json_encode($displayRole) }}, search: {{ json_encode($searchBlob) }} },
                @endforeach
            ],
            init() {
                this.$watch('activeTab', () => this.syncUrl());
                this.$watch('searchQuery', () => this.syncUrl());
            },
            syncUrl() {
                const url = new URL(window.location);
                if (this.activeTab !== 'all') {
                    url.searchParams.set('role', this.activeTab);
                } else {
                    url.searchParams.delete('role');
                }
                if (this.searchQuery.trim()) {
                    url.searchParams.set('search', this.searchQuery.trim());
                } else {
                    url.searchParams.delete('search');
                }
                window.history.replaceState({}, '', url);
            },
            matches(role, searchBlob) {
                const tabMatch = this.activeTab === 'all' || this.activeTab === role;
                if (!tabMatch) return false;
                const q = this.searchQuery.toLowerCase().trim();
                if (!q) return true;
                return searchBlob.includes(q);
            },
            get visibleCount() {
                return this.usersList.filter(u => this.matches(u.role, u.search)).length;
            },
            setTab(tab) {
                this.activeTab = tab;
            },
            clearFilters() {
                this.activeTab = 'all';
                this.searchQuery = '';
            }
        }"
        @keydown.window="if ($event.key === '/' && $event.target.tagName !== 'INPUT' && $event.target.tagName !== 'TEXTAREA') { $event.preventDefault(); $refs.searchInput.focus(); }">

        <!-- Top Header & Actions -->
        <div class="px-4 sm:px-6 py-4 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white">
            <div>
                <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-users-cog text-brand-purple"></i>
                    <span>Manage Users</span>
                </h3>
                <p class="text-xs text-gray-500 mt-0.5">Filter by role, search, and manage system user credentials and department assignments.</p>
            </div>
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <button type="button" onclick="document.getElementById('importModal').classList.remove('hidden')"
                    class="flex-1 sm:flex-initial inline-flex items-center justify-center px-4 py-2 bg-purple-50 text-purple-700 hover:bg-purple-100 rounded-lg text-xs sm:text-sm font-semibold transition-colors border border-purple-200 shadow-2xs">
                    <i class="fas fa-file-import mr-2"></i>Import Users
                </button>
                <a href="{{ route('users.create') }}"
                    class="flex-1 sm:flex-initial inline-flex items-center justify-center px-4 py-2 bg-gradient-to-r from-brand-pink to-brand-purple text-white rounded-lg hover:opacity-90 text-xs sm:text-sm font-bold shadow-md transition-all">
                    <i class="fas fa-user-plus mr-2"></i>Add New User
                </a>
            </div>
        </div>

        @if(session('import_errors'))
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mx-4 sm:mx-6 mt-4 rounded-r-lg" role="alert">
                <p class="font-bold text-sm">Some rows failed to import:</p>
                <ul class="list-disc list-inside text-xs sm:text-sm mt-1">
                    @foreach(session('import_errors') as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Filter Toolbar: Role Tabs & Search Bar -->
        <div class="px-4 sm:px-6 py-3 bg-gray-50/80 border-b border-gray-200 flex flex-col lg:flex-row lg:items-center justify-between gap-3">
            <!-- Role Tabs -->
            <div class="flex items-center gap-1.5 overflow-x-auto pb-1.5 lg:pb-0 scrollbar-none -mx-1 px-1">
                @foreach($rolesList as $roleKey => $roleLabel)
                    @php
                        $roleCount = $roleKey === 'all' 
                            ? $users->count() 
                            : $users->filter(fn($u) => in_array($u->role, $roleKey === 'Finance Admin' ? ['Finance Admin', 'Super Admin'] : [$roleKey]))->count();
                    @endphp
                    <button type="button"
                        @click="setTab('{{ $roleKey }}')"
                        :class="activeTab === '{{ $roleKey }}'
                            ? 'bg-brand-purple text-white shadow-xs font-semibold'
                            : 'text-gray-600 hover:text-gray-900 hover:bg-gray-200/70 font-medium'"
                        class="px-3 py-1.5 rounded-lg whitespace-nowrap transition-all flex items-center gap-1.5 text-xs select-none">
                        <span>{{ $roleLabel }}</span>
                        <span :class="activeTab === '{{ $roleKey }}' ? 'bg-white/20 text-white' : 'bg-gray-200/80 text-gray-700'"
                            class="px-1.5 py-0.5 rounded-full text-[10px] font-bold">
                            {{ $roleCount }}
                        </span>
                    </button>
                @endforeach
            </div>

            <!-- Search Bar & Counter -->
            <div class="flex items-center gap-2.5 w-full lg:w-auto">
                <div class="relative w-full lg:w-80">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                        <i class="fas fa-search text-xs"></i>
                    </div>
                    <input type="text"
                        x-ref="searchInput"
                        x-model="searchQuery"
                        @keydown.escape="searchQuery = ''; $refs.searchInput.blur()"
                        placeholder="Search users... (Press '/' to focus)"
                        class="block w-full pl-8 pr-8 py-1.5 text-xs sm:text-sm bg-white border border-gray-300 rounded-lg text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand-purple/20 focus:border-brand-purple transition-all shadow-2xs">
                    <button type="button"
                        x-show="searchQuery.length > 0"
                        @click="searchQuery = ''; $refs.searchInput.focus()"
                        class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-gray-400 hover:text-gray-600"
                        title="Clear search"
                        style="display: none;">
                        <i class="fas fa-times-circle text-xs"></i>
                    </button>
                </div>
                <div class="hidden sm:inline-flex items-center px-2.5 py-1 rounded-md bg-gray-100 border border-gray-200 text-[11px] font-semibold text-gray-600 whitespace-nowrap">
                    <span x-text="`Showing ${visibleCount} of {{ $users->count() }}`"></span>
                </div>
            </div>
        </div>

        <!-- Active Filter Indicator Banner (when filtering) -->
        <div x-show="activeTab !== 'all' || searchQuery.trim() !== ''"
            x-transition
            class="px-4 sm:px-6 py-2 bg-purple-50/70 border-b border-purple-100 flex items-center justify-between text-xs text-purple-900"
            style="display: none;">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="font-medium text-gray-600">Filters:</span>
                <template x-if="activeTab !== 'all'">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-brand-purple/10 text-brand-purple font-semibold text-[11px]">
                        <span>Role:</span>
                        <strong x-text="activeTab"></strong>
                        <button type="button" @click="activeTab = 'all'" class="hover:text-red-600 ml-0.5">
                            <i class="fas fa-times text-[10px]"></i>
                        </button>
                    </span>
                </template>
                <template x-if="searchQuery.trim() !== ''">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-brand-purple/10 text-brand-purple font-semibold text-[11px]">
                        <span>Search:</span>
                        <strong x-text="searchQuery"></strong>
                        <button type="button" @click="searchQuery = ''" class="hover:text-red-600 ml-0.5">
                            <i class="fas fa-times text-[10px]"></i>
                        </button>
                    </span>
                </template>
                <span class="text-gray-500 text-[11px]" x-text="`(${visibleCount} match${visibleCount === 1 ? '' : 'es'})`"></span>
            </div>
            <button type="button"
                @click="clearFilters()"
                class="text-xs font-semibold text-brand-purple hover:text-purple-900 hover:underline">
                Clear all
            </button>
        </div>

        <!-- Users Table -->
        <div class="overflow-x-auto -mx-4 sm:mx-0">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50/80">
                    <tr>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Role</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Department</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">HOD / Supervisor</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Created At</th>
                        <th class="px-6 py-3.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($users as $user)
                        @php
                            $displayRole = in_array($user->role, ['Finance Admin', 'Super Admin']) ? 'Finance Admin' : $user->role;
                            $searchBlob = strtolower($user->name . ' ' . $user->email . ' ' . $displayRole . ' ' . ($user->department ?: '') . ' ' . ($user->supervisor ? $user->supervisor->name : ''));
                        @endphp
                        <tr x-show="matches('{{ $displayRole }}', {{ json_encode($searchBlob) }})"
                            class="hover:bg-gray-50/80 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                {{ $user->name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $user->email }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span
                                    class="px-2.5 py-1 text-xs font-semibold rounded-full 
                                        {{ in_array($user->role, ['Finance Admin', 'Super Admin']) ? 'bg-purple-100 text-purple-800' : '' }}
                                        {{ $user->role === 'IT Admin' ? 'bg-orange-100 text-orange-800' : '' }}
                                        {{ $user->role === 'Management' ? 'bg-blue-100 text-blue-800' : '' }}
                                        {{ $user->role === 'HOD' ? 'bg-green-100 text-green-800' : '' }}
                                        {{ $user->role === 'Manager' ? 'bg-gray-100 text-gray-800' : '' }}
                                        {{ $user->role === 'Staff' ? 'bg-amber-100 text-amber-800' : '' }}">
                                    {{ $displayRole }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <span class="capitalize text-gray-700 font-medium">{{ $user->department ?: '-' }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $user->supervisor ? $user->supervisor->name : '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $user->created_at ? $user->created_at->format('M d, Y') : '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="{{ route('users.edit', $user) }}"
                                    class="text-brand-blue hover:text-brand-purple mr-3 transition-colors">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                @if($user->id !== auth()->id())
                                    <form action="{{ route('users.destroy', $user) }}" method="POST" class="inline-block"
                                        onsubmit="return confirm('Are you sure you want to delete this user?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-900 transition-colors">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach

                    <!-- Empty State Row -->
                    <tr x-show="visibleCount === 0" style="display: none;">
                        <td colspan="7" class="px-6 py-12 text-center text-gray-500 bg-gray-50/40">
                            <div class="flex flex-col items-center justify-center max-w-sm mx-auto">
                                <div class="w-12 h-12 rounded-full bg-purple-50 text-brand-purple flex items-center justify-center text-lg mb-3 shadow-inner">
                                    <i class="fas fa-user-slash"></i>
                                </div>
                                <h4 class="text-sm font-bold text-gray-800">No users found</h4>
                                <p class="text-xs text-gray-500 mt-1"
                                    x-text="searchQuery.trim() ? `No users matching '${searchQuery}' in ${activeTab === 'all' ? 'any role' : activeTab}.` : `No users currently assigned to the '${activeTab}' role.`">
                                </p>
                                <button type="button"
                                    @click="clearFilters()"
                                    class="mt-3.5 inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-brand-purple bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors border border-purple-200">
                                    <i class="fas fa-undo text-[10px]"></i> Reset Filters
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Import Modal -->
    <div id="importModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-xl bg-white">
            <div class="mt-3 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-purple-100 text-brand-purple mb-3">
                    <i class="fas fa-file-csv text-xl"></i>
                </div>
                <h3 class="text-lg leading-6 font-bold text-gray-900">Import Users via CSV</h3>
                <div class="mt-2 text-left">
                    <p class="text-xs text-gray-500 mb-4">
                        Upload a CSV file with columns: <strong>Name, Email, Role, Supervisor Email, Department, Password</strong>.
                        <br class="mb-1">
                        <a href="{{ route('users.download-sample') }}" class="text-brand-purple hover:underline font-semibold inline-flex items-center gap-1 mt-1">
                            <i class="fas fa-download text-[10px]"></i> Download Sample CSV
                        </a>
                    </p>
                    <form action="{{ route('users.import') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-4">
                            <label class="block text-xs font-bold text-gray-700 mb-1.5" for="file">
                                Choose CSV File
                            </label>
                            <input
                                class="block w-full text-xs text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none p-2"
                                id="file" name="file" type="file" accept=".csv,.txt" required>
                        </div>
                        <div class="flex justify-end gap-2 mt-5">
                            <button type="button" onclick="document.getElementById('importModal').classList.add('hidden')"
                                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-xs font-semibold transition-colors">
                                Cancel
                            </button>
                            <button type="submit"
                                class="px-4 py-2 bg-brand-purple text-white rounded-lg hover:opacity-90 text-xs font-bold shadow-md transition-all">
                                Upload & Import
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection