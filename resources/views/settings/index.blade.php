@extends('layouts.app')

@section('header', 'System Settings')

@section('content')
    <div class="max-w-6xl mx-auto space-y-8 pb-12">
        @if(session('success'))
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                {{ session('success') }}
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Sidebar Navigation for Settings -->
            <div class="col-span-1 space-y-2">
                <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider px-3 mb-2">Management</h3>
                <button onclick="showSection('general')"
                    class="section-btn w-full text-left px-4 py-3 rounded-lg bg-white shadow-sm border border-gray-100 hover:border-brand-blue transition-all"
                    id="btn-general">
                    <i class="fas fa-building mr-2 text-brand-blue"></i> Company & Branding
                </button>
                <button onclick="showSection('tax')"
                    class="section-btn w-full text-left px-4 py-3 rounded-lg bg-white shadow-sm border border-gray-100 hover:border-brand-blue transition-all"
                    id="btn-tax">
                    <i class="fas fa-percentage mr-2 text-green-600"></i> Tax Rates (VAT/SSCL)
                </button>
                <button onclick="showSection('managers')"
                    class="section-btn w-full text-left px-4 py-3 rounded-lg bg-white shadow-sm border border-gray-100 hover:border-brand-blue transition-all"
                    id="btn-managers">
                    <i class="fas fa-user-tie mr-2 text-brand-purple"></i> Senior Managers
                </button>
                <button onclick="showSection('terms')"
                    class="section-btn w-full text-left px-4 py-3 rounded-lg bg-white shadow-sm border border-gray-100 hover:border-brand-blue transition-all"
                    id="btn-terms">
                    <i class="fas fa-file-contract mr-2 text-brand-pink"></i> Standard Terms
                </button>
                @if(auth()->user()->hasRole('super_admin'))
                    <button onclick="showSection('currencies')"
                        class="section-btn w-full text-left px-4 py-3 rounded-lg bg-white shadow-sm border border-gray-100 hover:border-brand-blue transition-all"
                        id="btn-currencies">
                        <i class="fas fa-coins mr-2 text-yellow-500"></i> Currencies
                    </button>
                @endif
            </div>

            <!-- Settings Content Area -->
            <div class="col-span-2">
                <!-- General Settings Section -->
                <section id="section-general" class="settings-section space-y-6">
                    <form action="{{ route('settings.updateGeneral') }}" method="POST">
                        @csrf
                        <div class="bg-white rounded-xl shadow-md overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
                                <h3 class="text-lg font-bold text-gray-800">Company Information</h3>
                                <button type="submit"
                                    class="px-4 py-2 bg-brand-pink text-white rounded-md hover:bg-brand-purple text-sm font-medium transition-all">
                                    Save Changes
                                </button>
                            </div>
                            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Company Name</label>
                                    <input type="text" name="company_name"
                                        value="{{ \App\Models\Setting::get('company_name') }}"
                                        class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Address Line 1</label>
                                    <input type="text" name="company_address_1"
                                        value="{{ \App\Models\Setting::get('company_address_1') }}"
                                        class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Address Line 2</label>
                                    <input type="text" name="company_address_2"
                                        value="{{ \App\Models\Setting::get('company_address_2') }}"
                                        class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                    <input type="text" name="company_phone"
                                        value="{{ \App\Models\Setting::get('company_phone') }}"
                                        class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Website</label>
                                    <input type="text" name="company_web"
                                        value="{{ \App\Models\Setting::get('company_web') }}"
                                        class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm">
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">VAT Number</label>
                                    <input type="text" name="company_vat"
                                        value="{{ \App\Models\Setting::get('company_vat') }}"
                                        class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm">
                                </div>
                            </div>
                        </div>

                        <div class="mt-8 bg-white rounded-xl shadow-md overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                                <h3 class="text-lg font-bold text-gray-800">Visual Identity (Brand Colors)</h3>
                            </div>
                            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Primary (Pink)</label>
                                    <div class="flex items-center space-x-2">
                                        <input type="color" name="brand_pink"
                                            value="{{ \App\Models\Setting::get('brand_pink') }}"
                                            class="h-10 w-10 p-0 border-none rounded">
                                        <input type="text" name="brand_pink_val" disabled
                                            value="{{ \App\Models\Setting::get('brand_pink') }}"
                                            class="bg-gray-50 text-gray-500 border-none text-sm rounded">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Secondary (Blue)</label>
                                    <div class="flex items-center space-x-2">
                                        <input type="color" name="brand_blue"
                                            value="{{ \App\Models\Setting::get('brand_blue') }}"
                                            class="h-10 w-10 p-0 border-none rounded">
                                        <input type="text" name="brand_blue_val" disabled
                                            value="{{ \App\Models\Setting::get('brand_blue') }}"
                                            class="bg-gray-50 text-gray-500 border-none text-sm rounded">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Accent (Purple)</label>
                                    <div class="flex items-center space-x-2">
                                        <input type="color" name="brand_purple"
                                            value="{{ \App\Models\Setting::get('brand_purple') }}"
                                            class="h-10 w-10 p-0 border-none rounded">
                                        <input type="text" name="brand_purple_val" disabled
                                            value="{{ \App\Models\Setting::get('brand_purple') }}"
                                            class="bg-gray-50 text-gray-500 border-none text-sm rounded">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Info (Teal)</label>
                                    <div class="flex items-center space-x-2">
                                        <input type="color" name="brand_teal"
                                            value="{{ \App\Models\Setting::get('brand_teal') }}"
                                            class="h-10 w-10 p-0 border-none rounded">
                                        <input type="text" name="brand_teal_val" disabled
                                            value="{{ \App\Models\Setting::get('brand_teal') }}"
                                            class="bg-gray-50 text-gray-500 border-none text-sm rounded">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </section>

                <!-- Tax Settings Section -->
                <section id="section-tax" class="settings-section hidden space-y-6">
                    <form action="{{ route('settings.updateTax') }}" method="POST">
                        @csrf
                        <div class="bg-white rounded-xl shadow-md overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
                                <h3 class="text-lg font-bold text-gray-800">Tax Rates</h3>
                                <button type="submit"
                                    class="px-4 py-2 bg-brand-pink text-white rounded-md hover:bg-brand-purple text-sm font-medium transition-all">
                                    Save Rates
                                </button>
                            </div>
                            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">SSCL Rate (%)</label>
                                    <div class="relative rounded-md shadow-sm">
                                        <input type="number" step="0.01" name="sscl_rate"
                                            value="{{ \App\Models\Setting::get('sscl_rate', 2.5) }}"
                                            class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm pr-8">
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 sm:text-sm">%</span>
                                        </div>
                                    </div>

                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">VAT Rate (%)</label>
                                    <div class="relative rounded-md shadow-sm">
                                        <input type="number" step="0.01" name="vat_rate"
                                            value="{{ \App\Models\Setting::get('vat_rate', 15) }}"
                                            class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm pr-8">
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 sm:text-sm">%</span>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </form>
                </section>

                <!-- Senior Managers Section -->
                <section id="section-managers" class="settings-section hidden space-y-6">
                    <div class="bg-white rounded-xl shadow-md overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                            <h3 class="text-lg font-bold text-gray-800">Senior Managers</h3>
                        </div>
                        <div class="p-6">
                            <form action="{{ route('settings.storeManager') }}" method="POST" class="mb-6 flex gap-4">
                                @csrf
                                <input type="text" name="name" placeholder="Manager Name" required
                                    class="flex-1 rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm">
                                <button type="submit"
                                    class="px-4 py-2 bg-brand-blue text-white rounded-md hover:bg-brand-purple text-sm font-medium transition-all">
                                    <i class="fas fa-plus mr-1"></i> Add
                                </button>
                            </form>

                            <div class="divide-y divide-gray-100">
                                @foreach($managers as $manager)
                                    <div class="py-3 flex items-center justify-between">
                                        <span class="font-medium text-gray-700">{{ $manager->name }}</span>
                                        <div class="flex items-center gap-2">
                                            <button
                                                onclick="editManager('{{ route('settings.updateManager', $manager) }}', '{{ addslashes($manager->name) }}', '{{ addslashes($manager->designation ?? '') }}')"
                                                class="text-blue-500 hover:text-blue-700 transition-colors">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="{{ route('settings.destroyManager.get', $manager) }}"
                                                onclick="return confirm('Are you sure you want to delete this manager?');"
                                                class="text-red-500 hover:text-red-700 transition-colors inline-flex items-center gap-1 ml-2">
                                                <i class="fas fa-trash-alt pointer-events-none"></i> Delete
                                            </a>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Edit Manager Modal -->
                        <div id="editManagerModal"
                            class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
                            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                                <div class="mt-3">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900 border-b pb-2">Edit Manager</h3>
                                    <form id="editManagerForm" method="POST" class="mt-4">
                                        @csrf
                                        @method('PUT')
                                        <div class="mb-4">
                                            <label class="block text-gray-700 text-sm font-bold mb-2">Name</label>
                                            <input type="text" name="name" id="edit_manager_name" required
                                                class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm">
                                        </div>
                                        <div class="mb-4">
                                            <label class="block text-gray-700 text-sm font-bold mb-2">Designation</label>
                                            <input type="text" name="designation" id="edit_manager_designation"
                                                class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm">
                                        </div>
                                        <div class="flex justify-end gap-2">
                                            <button type="button"
                                                onclick="document.getElementById('editManagerModal').classList.add('hidden')"
                                                class="px-4 py-2 bg-gray-500 text-white text-base font-medium rounded-md hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-300">
                                                Cancel
                                            </button>
                                            <button type="submit"
                                                class="px-4 py-2 bg-brand-blue text-white text-base font-medium rounded-md hover:bg-brand-purple focus:outline-none focus:ring-2 focus:ring-blue-300">
                                                Update
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Standard Terms Section -->
                <section id="section-terms" class="settings-section hidden space-y-6">
                    <div class="bg-white rounded-xl shadow-md overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                            <h3 class="text-lg font-bold text-gray-800">Standard Estimate/Invoice Terms</h3>
                        </div>
                        <div class="p-6">
                            <form action="{{ route('settings.storeTerm') }}" method="POST" class="mb-6 space-y-3">
                                @csrf
                                <textarea name="content" placeholder="New term content..." required
                                    class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm"
                                    rows="2"></textarea>
                                <div class="flex justify-end">
                                    <button type="submit"
                                        class="px-4 py-2 bg-brand-pink text-white rounded-md hover:bg-brand-purple text-sm font-medium transition-all">
                                        <i class="fas fa-plus mr-1"></i> Add Term
                                    </button>
                                </div>
                            </form>

                            <div class="space-y-4">
                                @foreach($terms as $term)
                                    <div class="p-4 bg-gray-50 rounded-lg border border-gray-100 relative group">
                                        <p class="text-sm text-gray-600 italic pr-16">"{{ $term->content }}"</p>
                                        <div
                                            class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity flex gap-2">
                                            <button
                                                onclick="editTerm('{{ route('settings.updateTerm', $term) }}', '{{ addslashes($term->content) }}')"
                                                class="text-blue-500 hover:text-blue-700">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="{{ route('settings.destroyTerm.get', $term) }}"
                                                onclick="return confirm('Are you sure you want to delete this term?');"
                                                class="text-red-500 hover:text-red-700 inline-flex items-center gap-1 ml-2">
                                                <i class="fas fa-times-circle pointer-events-none"></i> Delete
                                            </a>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Edit Term Modal -->
                        <div id="editTermModal"
                            class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
                            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                                <div class="mt-3">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900 border-b pb-2">Edit Term</h3>
                                    <form id="editTermForm" method="POST" class="mt-4">
                                        @csrf
                                        @method('PUT')
                                        <div class="mb-4">
                                            <label class="block text-gray-700 text-sm font-bold mb-2">Content</label>
                                            <textarea name="content" id="edit_term_content" required rows="4"
                                                class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm"></textarea>
                                        </div>
                                        <div class="flex justify-end gap-2">
                                            <button type="button"
                                                onclick="document.getElementById('editTermModal').classList.add('hidden')"
                                                class="px-4 py-2 bg-gray-500 text-white text-base font-medium rounded-md hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-300">
                                                Cancel
                                            </button>
                                            <button type="submit"
                                                class="px-4 py-2 bg-brand-blue text-white text-base font-medium rounded-md hover:bg-brand-purple focus:outline-none focus:ring-2 focus:ring-blue-300">
                                                Update
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                </section>

                <!-- Currency Management Section (Super Admin Only) -->
                @if(auth()->user()->hasRole('super_admin'))
                    <section id="section-currencies" class="settings-section hidden space-y-6">
                        <div class="bg-white rounded-xl shadow-md overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                                <h3 class="text-lg font-bold text-gray-800">Supported Currencies</h3>
                            </div>
                            <div class="p-6">
                                <!-- Add Currency Form -->
                                <form action="{{ route('settings.storeCurrency') }}" method="POST"
                                    class="mb-8 p-4 bg-gray-50 rounded-lg border border-gray-100">
                                    @csrf
                                    <h4 class="text-sm font-semibold text-gray-700 mb-3 uppercase tracking-wider">Add New
                                        Currency</h4>
                                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 mb-1">Currency Code (e.g.
                                                USD)</label>
                                            <input type="text" name="code" placeholder="USD" required maxlength="3"
                                                class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm uppercase">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 mb-1">Currency Name</label>
                                            <input type="text" name="name" placeholder="US Dollar"
                                                class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 mb-1">Symbol</label>
                                            <input type="text" name="symbol" placeholder="$"
                                                class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm">
                                        </div>
                                        <button type="submit"
                                            class="px-4 py-2 bg-brand-blue text-white rounded-md hover:bg-brand-purple text-sm font-medium transition-all w-full">
                                            <i class="fas fa-plus mr-1"></i> Add Currency
                                        </button>
                                    </div>
                                </form>

                                <!-- Currency List -->
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th
                                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Code</th>
                                                <th
                                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Name</th>
                                                <th
                                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Symbol</th>
                                                <th
                                                    class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            @foreach($currencies as $currency)
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        {{ $currency->code }}
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        {{ $currency->name }}
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        {{ $currency->symbol }}
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                        <button
                                                            onclick="editCurrency('{{ route('settings.updateCurrency', $currency) }}', '{{ $currency->code }}', '{{ addslashes($currency->name) }}', '{{ $currency->symbol }}')"
                                                            class="text-blue-500 hover:text-blue-700 transition-colors mr-2">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <a href="{{ route('settings.destroyCurrency.get', $currency) }}"
                                                            onclick="return confirm('Are you sure you want to delete this currency?');"
                                                            class="text-red-500 hover:text-red-700 transition-colors inline-flex items-center gap-1 ml-2">
                                                            <i class="fas fa-trash-alt pointer-events-none"></i> Delete
                                                        </a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Edit Currency Modal -->
                            <div id="editCurrencyModal"
                                class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
                                <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                                    <div class="mt-3">
                                        <h3 class="text-lg leading-6 font-medium text-gray-900 border-b pb-2">Edit Currency</h3>
                                        <form id="editCurrencyForm" method="POST" class="mt-4">
                                            @csrf
                                            @method('PUT')
                                            <div class="mb-4">
                                                <label class="block text-gray-700 text-sm font-bold mb-2">Code</label>
                                                <input type="text" name="code" id="edit_currency_code" required maxlength="3"
                                                    class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm uppercase">
                                            </div>
                                            <div class="mb-4">
                                                <label class="block text-gray-700 text-sm font-bold mb-2">Name</label>
                                                <input type="text" name="name" id="edit_currency_name"
                                                    class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm">
                                            </div>
                                            <div class="mb-4">
                                                <label class="block text-gray-700 text-sm font-bold mb-2">Symbol</label>
                                                <input type="text" name="symbol" id="edit_currency_symbol"
                                                    class="w-full rounded-md border-gray-300 focus:border-brand-blue focus:ring-brand-blue sm:text-sm">
                                            </div>
                                            <div class="flex justify-end gap-2">
                                                <button type="button"
                                                    onclick="document.getElementById('editCurrencyModal').classList.add('hidden')"
                                                    class="px-4 py-2 bg-gray-500 text-white text-base font-medium rounded-md hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-300">
                                                    Cancel
                                                </button>
                                                <button type="submit"
                                                    class="px-4 py-2 bg-brand-blue text-white text-base font-medium rounded-md hover:bg-brand-purple focus:outline-none focus:ring-2 focus:ring-blue-300">
                                                    Update
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                @endif
            </div>
        </div>
    </div>



    <script>
        function showSection(id) {
            // Hide all sections
            document.querySelectorAll('.settings-section').forEach(s => s.classList.add('hidden'));
            // Show selected section
            document.getElementById('section-' + id).classList.remove('hidden');

            // Update buttons
            document.querySelectorAll('.section-btn').forEach(btn => {
                btn.classList.remove('ring-2', 'ring-brand-blue', 'bg-blue-50');
            });
            document.getElementById('btn-' + id).classList.add('ring-2', 'ring-brand-blue', 'bg-blue-50');
        }

        function editManager(url, name, designation) {
            const form = document.getElementById('editManagerForm');
            form.action = url;
            document.getElementById('edit_manager_name').value = name;
            document.getElementById('edit_manager_designation').value = designation;
            document.getElementById('editManagerModal').classList.remove('hidden');
        }

        function editTerm(url, content) {
            const form = document.getElementById('editTermForm');
            form.action = url;
            document.getElementById('edit_term_content').value = content;
            document.getElementById('editTermModal').classList.remove('hidden');
        }

        function editCurrency(url, code, name, symbol) {
            const form = document.getElementById('editCurrencyForm');
            form.action = url;
            document.getElementById('edit_currency_code').value = code;
            document.getElementById('edit_currency_name').value = name;
            document.getElementById('edit_currency_symbol').value = symbol;
            document.getElementById('editCurrencyModal').classList.remove('hidden');
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            showSection('general');
        });

        // Update color displays on change
        document.querySelectorAll('input[type="color"]').forEach(input => {
            input.addEventListener('input', (e) => {
                const valInput = e.target.nextElementSibling;
                if (valInput) valInput.value = e.target.value;
            });
        });


    </script>
@endsection