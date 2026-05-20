<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Dashboard Panel Admin ABBEV - Gérez vos films, séries et catégories.">

    <!-- Open Graph / Partage de lien -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="ABBEV - Admin">
    <meta property="og:description" content="Dashboard Panel Admin ABBEV - Gérez vos films, séries et catégories.">
    <meta property="og:image" content="{{ asset('logo/logo.jpeg') }}">

    <!-- Favicon -->
    <link rel="icon" type="image/jpeg" href="{{ asset('logo/logo.jpeg') }}">
    <link rel="apple-touch-icon" href="{{ asset('logo/logo.jpeg') }}">

    <title>@yield('title', 'Dashboard') - ABBEV Admin</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#ecfeff',
                            100: '#cffafe',
                            200: '#a5f3fc',
                            300: '#67e8f9',
                            400: '#22d3ee',
                            500: '#06b6d4',
                            600: '#0891b2',
                            700: '#0e7490',
                            800: '#155e75',
                            900: '#164e63',
                        },
                        dark: {
                            50: '#18181b',
                            100: '#09090b',
                            200: '#27272a',
                            300: '#3f3f46',
                            400: '#52525b',
                            500: '#71717a',
                            600: '#a1a1aa',
                            700: '#d4d4d8',
                            800: '#e4e4e7',
                            900: '#f4f4f5',
                        }
                    }
                }
            }
        }
    </script>

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        [x-cloak] { display: none !important; }
    </style>

    @stack('styles')
</head>
<body class="bg-dark-50" x-data="{ sidebarOpen: false }">
    <div class="min-h-screen">
        <!-- Mobile Menu Overlay -->
        <div x-show="sidebarOpen"
             x-cloak
             @click="sidebarOpen = false"
             class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden"
             x-transition:enter="transition-opacity ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"></div>

        <!-- Sidebar -->
        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
               class="fixed inset-y-0 left-0 z-50 w-64 bg-dark-100 transform transition-transform duration-300 ease-in-out lg:translate-x-0 flex flex-col h-screen">

            <!-- Logo -->
            <div class="flex items-center justify-between h-16 px-6 bg-dark-50 flex-shrink-0 border-b border-dark-200">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center shadow-lg overflow-hidden p-0.5">
                        <img src="{{ asset('logo/logo.jpeg') }}" alt="ABBEV Logo" class="w-full h-full object-cover rounded-full">
                    </div>
                    <span class="text-white font-bold text-xl">ABBEV</span>
                </a>
                <button @click="sidebarOpen = false" class="lg:hidden text-gray-400 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 mt-6 px-3 overflow-y-auto">
                <!-- Dashboard -->
                <div class="space-y-1">
                    <a href="{{ route('admin.dashboard') }}"
                       class="flex items-center px-4 py-3 text-sm rounded-lg transition-all {{ request()->routeIs('admin.dashboard') ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-md' : 'text-gray-300 hover:bg-dark-200 hover:text-white' }}">
                        <i class="fas fa-chart-pie w-5 mr-3"></i>
                        Dashboard
                    </a>
                </div>

                <!-- Section Contenu -->
                <div class="mt-8 pt-6 border-t border-dark-200">
                    <p class="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Contenu</p>

                    <a href="{{ route('films.index') }}"
                       class="flex items-center px-4 py-3 text-sm rounded-lg transition-all {{ request()->routeIs('films.*') ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-md' : 'text-gray-300 hover:bg-dark-200 hover:text-white' }}">
                        <i class="fas fa-video w-5 mr-3"></i>
                        Films
                    </a>

                    <a href="{{ route('series.index') }}"
                       class="flex items-center px-4 py-3 text-sm rounded-lg transition-all {{ request()->routeIs('series.*') ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-md' : 'text-gray-300 hover:bg-dark-200 hover:text-white' }}">
                        <i class="fas fa-tv w-5 mr-3"></i>
                        Séries
                    </a>

                    <a href="{{ route('categories.index') }}"
                       class="flex items-center px-4 py-3 text-sm rounded-lg transition-all {{ request()->routeIs('categories.*') ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-md' : 'text-gray-300 hover:bg-dark-200 hover:text-white' }}">
                        <i class="fas fa-th-large w-5 mr-3"></i>
                        Catégories
                    </a>

                    <a href="{{ route('admin.bunny.library') }}"
                       class="flex items-center px-4 py-3 text-sm rounded-lg transition-all {{ request()->routeIs('admin.bunny.*') ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-md' : 'text-gray-300 hover:bg-dark-200 hover:text-white' }}">
                        <i class="fas fa-cloud w-5 mr-3"></i>
                        Bunny Library
                    </a>
                </div>

                <!-- Section Utilisateurs -->
                <div class="mt-8 pt-6 border-t border-dark-200">
                    <p class="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Utilisateurs</p>

                    <a href="{{ route('users.index') }}"
                       class="flex items-center px-4 py-3 text-sm rounded-lg transition-all {{ request()->routeIs('users.*') ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-md' : 'text-gray-300 hover:bg-dark-200 hover:text-white' }}">
                        <i class="fas fa-users w-5 mr-3"></i>
                        Utilisateurs
                    </a>

                    <a href="{{ route('administrators.index') }}"
                       class="flex items-center px-4 py-3 text-sm rounded-lg transition-all {{ request()->routeIs('administrators.*') ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-md' : 'text-gray-300 hover:bg-dark-200 hover:text-white' }}">
                        <i class="fas fa-user-shield w-5 mr-3"></i>
                        Administrateurs
                    </a>
                </div>

                <!-- Section Abonnements -->
                <div class="mt-8 pt-6 border-t border-dark-200">
                    <p class="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Abonnements & Paiements</p>

                    <a href="{{ route('subscription-plans.index') }}"
                       class="flex items-center px-4 py-3 text-sm rounded-lg transition-all {{ request()->routeIs('subscription-plans.*') ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-md' : 'text-gray-300 hover:bg-dark-200 hover:text-white' }}">
                        <i class="fas fa-tags w-5 mr-3"></i>
                        Packs d'abonnement
                    </a>

                    <a href="{{ route('transactions.index') }}"
                       class="flex items-center px-4 py-3 text-sm rounded-lg transition-all {{ request()->routeIs('transactions.*') ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-md' : 'text-gray-300 hover:bg-dark-200 hover:text-white' }}">
                        <i class="fas fa-receipt w-5 mr-3"></i>
                        Transactions
                    </a>
                </div>

                <!-- Section Paramètres -->
                <div class="mt-8 pt-6 border-t border-dark-200">
                    <p class="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Paramètres</p>

                    <a href="{{ route('configuration.index') }}"
                       class="flex items-center px-4 py-3 text-sm rounded-lg transition-all {{ request()->routeIs('configuration.*') ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-md' : 'text-gray-300 hover:bg-dark-200 hover:text-white' }}">
                        <i class="fas fa-cog w-5 mr-3"></i>
                        Configuration
                    </a>
                </div>
            </nav>

            <!-- User Info at bottom -->
            <div class="p-4 border-t border-dark-200 flex-shrink-0">
                <div class="flex items-center">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center overflow-hidden flex-shrink-0 bg-gradient-to-br from-primary-500 to-primary-600 shadow-md">
                        <span class="text-white font-bold">{{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}</span>
                    </div>
                    <div class="ml-3 flex-1">
                        <p class="text-sm font-medium text-white">{{ auth()->user()->name ?? 'Admin' }}</p>
                        <p class="text-xs text-gray-400">Administrateur</p>
                    </div>
                    <form action="{{ route('admin.logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="text-gray-400 hover:text-primary-500 transition-colors" title="Déconnexion">
                            <i class="fas fa-sign-out-alt"></i>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex flex-col min-h-screen lg:ml-64">
            <!-- Top Header -->
            <header class="bg-dark-100 shadow-sm border-b border-dark-200 h-16 flex items-center justify-between px-6 flex-shrink-0 sticky top-0 z-10">
                <div class="flex items-center">
                    <button @click="sidebarOpen = true" class="lg:hidden text-gray-300 hover:text-white mr-4">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h1 class="text-xl font-semibold text-white">@yield('header', 'Dashboard')</h1>
                </div>

                <div class="flex items-center space-x-4">
                    <!-- Notifications -->
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="relative text-gray-300 hover:text-primary-500 transition-colors">
                            <i class="fas fa-bell text-xl"></i>
                            <span class="absolute -top-1 -right-1 w-5 h-5 bg-primary-500 text-white text-xs rounded-full flex items-center justify-center">0</span>
                        </button>

                        <div x-show="open"
                             x-cloak
                             @click.away="open = false"
                             class="absolute right-0 mt-2 w-80 bg-dark-100 rounded-lg shadow-lg border border-dark-200 py-2 z-50">
                            <div class="px-4 py-2 border-b border-dark-200">
                                <h3 class="font-semibold text-white">Notifications</h3>
                            </div>
                            <div class="max-h-64 overflow-y-auto">
                                <p class="px-4 py-3 text-sm text-gray-400">Aucune nouvelle notification</p>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto p-6">
                <!-- Flash Messages -->
                @if(session('success'))
                    <div class="mb-6 bg-green-50 border-l-4 border-green-500 text-green-700 px-4 py-3 rounded-lg flex items-center justify-between shadow-sm" role="alert">
                        <div class="flex items-center w-full">
                            <i class="fas fa-check-circle mr-2 text-green-500 flex-shrink-0"></i>
                            <div class="flex-1">{!! session('success') !!}</div>
                        </div>
                        <button onclick="this.parentElement.remove()" class="text-green-700 hover:text-green-900 ml-4 flex-shrink-0">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="mb-6 bg-red-900/20 border-l-4 border-red-500 text-red-400 px-4 py-3 rounded-lg flex items-center justify-between shadow-sm" role="alert">
                        <div class="flex items-center w-full">
                            <i class="fas fa-exclamation-circle mr-2 text-red-500 flex-shrink-0"></i>
                            <div class="flex-1">{!! session('error') !!}</div>
                        </div>
                        <button onclick="this.parentElement.remove()" class="text-red-400 hover:text-red-500 ml-4 flex-shrink-0">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                @endif

                @yield('content')
            </main>

            <!-- Footer -->
            <footer class="border-t border-dark-200 bg-dark-100 px-6 py-4 flex-shrink-0">
                <p class="text-sm text-gray-400 text-center">
                    &copy; {{ date('Y') }} ABBEV. Tous droits réservés.
                </p>
            </footer>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
