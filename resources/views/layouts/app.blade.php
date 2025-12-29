<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Invoice System</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            pink: '{{ \App\Models\Setting::get("brand_pink", "#ff0878") }}',
                            purple: '{{ \App\Models\Setting::get("brand_purple", "#8035ca") }}',
                            blue: '{{ \App\Models\Setting::get("brand_blue", "#0057be") }}',
                            teal: '{{ \App\Models\Setting::get("brand_teal", "#2fc9c3") }}',
                        },
                        primary: '{{ \App\Models\Setting::get("brand_pink", "#ff0878") }}',
                        secondary: '#0057be', // Using blue as secondary
                        dark: '#1f2937',
                    }
                }
            }
        }
    </script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-gray-100 text-gray-800">

    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <aside class="w-64 bg-dark text-white flex-shrink-0 hidden md:flex flex-col">
            <div class="p-4 flex items-center justify-center h-20 border-b border-gray-700">
                <img src="{{ asset('images/logo_loops.png') }}" alt="Loops Integrated" class="h-12 w-auto">
            </div>
            <nav class="flex-1 px-2 py-4 space-y-2 overflow-y-auto">
                <a href="{{ route('dashboard') }}"
                    class="flex items-center px-4 py-3 rounded-md hover:bg-gray-700 transition {{ request()->routeIs('dashboard') ? 'bg-gray-700 text-brand-pink' : '' }}">
                    <i class="fas fa-home w-6"></i>
                    <span>Dashboard</span>
                </a>
                <a href="{{ route('customers.index') }}"
                    class="flex items-center px-4 py-3 rounded-md hover:bg-gray-700 transition {{ request()->routeIs('customers.*') ? 'bg-gray-700 text-brand-pink' : '' }}">
                    <i class="fas fa-users w-6"></i>
                    <span>Customers</span>
                </a>
                <a href="{{ route('deals.index') }}"
                    class="flex items-center px-4 py-3 rounded-md hover:bg-gray-700 transition {{ request()->routeIs('deals.*') ? 'bg-gray-700 text-brand-pink' : '' }}">
                    <i class="fas fa-funnel-dollar w-6"></i>
                    <span>Deals</span>
                </a>
                <a href="{{ route('estimates.index') }}"
                    class="flex items-center px-4 py-3 rounded-md hover:bg-gray-700 transition {{ request()->routeIs('estimates.*') ? 'bg-gray-700 text-brand-pink' : '' }}">
                    <i class="fas fa-file-invoice w-6"></i>
                    <span>Estimates</span>
                </a>
                <a href="{{ route('invoices.index') }}"
                    class="flex items-center px-4 py-3 rounded-md hover:bg-gray-700 transition {{ request()->is('invoices*') ? 'bg-gray-700 text-brand-pink' : '' }}">
                    <i class="fas fa-file-invoice-dollar mr-3 w-5"></i> Invoices
                </a>

                @if(auth()->user()->role === 'Super Admin')
                    <a href="{{ route('users.index') }}"
                        class="flex items-center px-4 py-3 rounded-md hover:bg-gray-700 transition {{ request()->is('users*') ? 'bg-gray-700 text-brand-pink' : '' }}">
                        <i class="fas fa-users-cog mr-3 w-5"></i> Users
                    </a>

                    <a href="{{ route('settings.index') }}"
                        class="flex items-center px-4 py-3 rounded-md hover:bg-gray-700 transition {{ request()->is('settings*') ? 'bg-gray-700 text-brand-pink' : '' }}">
                        <i class="fas fa-cog mr-3 w-5"></i> Settings
                    </a>
                @endif
            </nav>
            <div class="p-4 border-t border-gray-700">
                <div class="flex items-center space-x-2">
                    <img src="https://ui-avatars.com/api/?name={{ Auth::user()->name ?? 'Admin' }}&background=random"
                        alt="User" class="w-8 h-8 rounded-full">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium truncate">{{ Auth::user()->name ?? 'Admin User' }}</p>
                        <p class="text-xs text-gray-400 truncate">{{ Auth::user()->email ?? 'admin@example.com' }}</p>
                    </div>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="text-gray-400 hover:text-white" title="Logout">
                            <i class="fas fa-sign-out-alt"></i>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <header class="bg-white shadow-sm z-10">
                <div class="flex items-center justify-between px-6 py-4">
                    <div class="flex items-center">
                        <button class="md:hidden text-gray-500 focus:outline-none">
                            <i class="fas fa-bars"></i>
                        </button>
                        <h2 class="text-xl font-semibold text-gray-700 ml-4">@yield('header')</h2>
                    </div>
                    <div>
                        <button class="relative p-2 text-gray-400 hover:text-gray-500">
                            <i class="fas fa-bell"></i>
                            <span
                                class="absolute top-0 right-0 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-red-100 transform translate-x-1/2 -translate-y-1/2 bg-red-600 rounded-full">3</span>
                        </button>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
                @if(session('success'))
                    <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative"
                        role="alert">
                        <strong class="font-bold">Success!</strong>
                        <span class="block sm:inline">{{ session('success') }}</span>
                    </div>
                @endif

                @if(session('error'))
                    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                        <strong class="font-bold">Error!</strong>
                        <span class="block sm:inline">{{ session('error') }}</span>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>
</body>

</html>