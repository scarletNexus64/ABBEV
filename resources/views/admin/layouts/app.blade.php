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

    <!-- Resumable.js (persistent across PJAX navigations) -->
    <script src="https://cdn.jsdelivr.net/npm/resumablejs@1.1.0/resumable.min.js"></script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        [x-cloak] { display: none !important; }

        /* ====== Loader de navigation ABBEV (PJAX) ====== */
        /* Barre de progression fine en haut (dégradé cyan ABBEV + halo). */
        #abbev-nav-progress {
            position: fixed; top: 0; left: 0; right: 0; height: 3px;
            z-index: 9999; pointer-events: none; opacity: 0;
            transition: opacity .3s ease;
        }
        #abbev-nav-progress.is-active { opacity: 1; }
        #abbev-nav-progress .bar {
            height: 100%; width: 0;
            background: linear-gradient(90deg, #22d3ee, #06b6d4, #0891b2);
            box-shadow: 0 0 12px rgba(34,211,238,.7), 0 0 4px rgba(34,211,238,.5);
            transition: width .25s cubic-bezier(.2,.6,.3,1);
        }
        /* Overlay centré sur la zone de contenu (la sidebar reste visible). */
        #abbev-nav-overlay {
            position: fixed; inset: 0; z-index: 9990;
            display: flex; align-items: center; justify-content: center;
            background: rgba(9,9,11,.55); backdrop-filter: blur(3px);
            opacity: 0; visibility: hidden;
            transition: opacity .25s ease, visibility .25s ease;
        }
        @media (min-width: 1024px) { #abbev-nav-overlay { left: 16rem; } }
        #abbev-nav-overlay.is-visible { opacity: 1; visibility: visible; }
        #abbev-nav-overlay .box {
            display: flex; flex-direction: column; align-items: center; gap: 16px;
        }
        #abbev-nav-overlay .ring { position: relative; width: 78px; height: 78px; }
        #abbev-nav-overlay .ring::before {
            content: ""; position: absolute; inset: 0; border-radius: 50%;
            border: 3px solid rgba(34,211,238,.15);
        }
        #abbev-nav-overlay .ring::after {
            content: ""; position: absolute; inset: 0; border-radius: 50%;
            border: 3px solid transparent;
            border-top-color: #22d3ee; border-right-color: #06b6d4;
            animation: abbev-spin .8s linear infinite;
        }
        /* Conteneur circulaire qui CLIPPE le logo (overflow:hidden) → jamais
           de carré, quel que soit le rendu du border-radius sur <img>. */
        #abbev-nav-overlay .logo {
            position: absolute; inset: 13px; border-radius: 50%;
            overflow: hidden;
        }
        #abbev-nav-overlay .logo img {
            width: 100%; height: 100%; object-fit: cover; display: block;
        }
        #abbev-nav-overlay .label {
            color: #d4d4d8; font-size: 13px; letter-spacing: 2px;
            text-transform: uppercase; animation: abbev-pulse 1.4s ease-in-out infinite;
        }
        @keyframes abbev-spin { to { transform: rotate(360deg); } }
        @keyframes abbev-pulse { 0%,100% { opacity: .45; } 50% { opacity: 1; } }
        /* Fondu doux du nouveau contenu injecté par PJAX. */
        main.abbev-nav-fade { animation: abbev-fade .35s ease; }
        @keyframes abbev-fade {
            from { opacity: .35; transform: translateY(6px); }
            to { opacity: 1; transform: none; }
        }
        @media (prefers-reduced-motion: reduce) {
            #abbev-nav-overlay .ring::after, #abbev-nav-overlay .label,
            main.abbev-nav-fade { animation: none; }
        }

        /* ====== Modal de confirmation ABBEV (remplace window.confirm) ====== */
        #abbev-confirm {
            position: fixed; inset: 0; z-index: 10000;
            display: flex; align-items: center; justify-content: center;
            padding: 20px; opacity: 0; visibility: hidden;
            background: rgba(0,0,0,.65); backdrop-filter: blur(3px);
            transition: opacity .2s ease, visibility .2s ease;
        }
        #abbev-confirm.is-open { opacity: 1; visibility: visible; }
        #abbev-confirm .cm-box {
            width: 100%; max-width: 420px;
            background: #18181b; border: 1px solid #27272a; border-radius: 18px;
            box-shadow: 0 24px 60px rgba(0,0,0,.5);
            padding: 28px 26px; text-align: center;
            transform: translateY(10px) scale(.97);
            transition: transform .2s ease;
        }
        #abbev-confirm.is-open .cm-box { transform: none; }
        #abbev-confirm .cm-ring {
            width: 64px; height: 64px; margin: 0 auto 18px;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 26px;
        }
        .cm-ring-danger  { background: rgba(239,68,68,.12);  border: 1px solid rgba(239,68,68,.35);  color: #f87171; }
        .cm-ring-warning { background: rgba(245,158,11,.12); border: 1px solid rgba(245,158,11,.35); color: #fbbf24; }
        .cm-ring-primary { background: rgba(6,182,212,.12);  border: 1px solid rgba(6,182,212,.35);  color: #22d3ee; }
        #abbev-confirm .cm-title { font-size: 1.2rem; font-weight: 700; color: #fff; margin-bottom: 8px; }
        #abbev-confirm .cm-message { font-size: .92rem; color: #a1a1aa; line-height: 1.55; margin-bottom: 24px; white-space: pre-line; }
        #abbev-confirm .cm-actions { display: flex; gap: 12px; }
        #abbev-confirm .cm-btn {
            flex: 1; padding: 12px 18px; border: none; border-radius: 11px;
            font-size: .92rem; font-weight: 600; cursor: pointer; font-family: inherit;
            transition: all .2s ease; color: #fff;
        }
        .cm-btn-cancel  { background: #27272a; color: #d4d4d8 !important; }
        .cm-btn-cancel:hover  { background: #3f3f46; }
        .cm-btn-danger  { background: #ef4444; }
        .cm-btn-danger:hover  { background: #dc2626; }
        .cm-btn-warning { background: #f59e0b; }
        .cm-btn-warning:hover { background: #d97706; }
        .cm-btn-primary { background: #06b6d4; }
        .cm-btn-primary:hover { background: #0891b2; }

        /* ====== Scrollbar custom ABBEV (listes défilables) ====== */
        .abbev-scroll { scrollbar-width: thin; scrollbar-color: #0891b2 transparent; }
        .abbev-scroll::-webkit-scrollbar { width: 9px; height: 9px; }
        .abbev-scroll::-webkit-scrollbar-track { background: transparent; }
        .abbev-scroll::-webkit-scrollbar-thumb {
            background: linear-gradient(#06b6d4, #0891b2);
            border-radius: 999px; border: 2px solid #18181b;
        }
        .abbev-scroll::-webkit-scrollbar-thumb:hover { background: linear-gradient(#22d3ee, #06b6d4); }

        /* ====== Picker vidéo / listes (media + épisodes) ====== */
        .abbev-search { position: relative; }
        .abbev-search > i {
            position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
            color: #52525b; font-size: 15px; pointer-events: none; transition: color .2s;
        }
        .abbev-search input { padding-left: 44px !important; }
        .abbev-search input:focus + i, .abbev-search:focus-within > i { color: #06b6d4; }

        .abbev-pick-item {
            display: flex; align-items: center; gap: 14px; width: 100%; text-align: left;
            padding: 10px 12px; border-radius: 12px; border: 1px solid transparent;
            transition: background .18s, border-color .18s, transform .12s;
        }
        .abbev-pick-item:hover {
            background: rgba(6,182,212,.08); border-color: rgba(6,182,212,.35);
            transform: translateX(2px);
        }
        .abbev-pick-thumb {
            position: relative; width: 84px; height: 50px; flex-shrink: 0;
            border-radius: 9px; overflow: hidden; background: #27272a;
            display: flex; align-items: center; justify-content: center;
        }
        .abbev-pick-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .abbev-pick-thumb .ph { color: #52525b; font-size: 16px; }
        .abbev-pick-thumb::after {
            content: '\f04b'; font-family: 'Font Awesome 6 Free'; font-weight: 900;
            position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 13px; background: rgba(6,182,212,.55);
            opacity: 0; transition: opacity .18s;
        }
        .abbev-pick-item:hover .abbev-pick-thumb::after { opacity: 1; }
        .abbev-badge-local {
            display: inline-flex; align-items: center; gap: 4px;
            font-size: 10px; font-weight: 600; padding: 2px 8px; border-radius: 999px;
            background: rgba(6,182,212,.15); color: #67e8f9; border: 1px solid rgba(6,182,212,.3);
        }
        .abbev-badge-bunny {
            display: inline-flex; align-items: center; gap: 4px;
            font-size: 10px; font-weight: 600; padding: 2px 8px; border-radius: 999px;
            background: rgba(249,115,22,.15); color: #fdba74; border: 1px solid rgba(249,115,22,.3);
        }
    </style>

    <div id="page-styles">@stack('styles')</div>
</head>
<body class="bg-dark-50" x-data="{ sidebarOpen: false }">
    {{-- Loader de navigation ABBEV : feedback immédiat au clic (lien, bouton,
         formulaire) pendant que le serveur traite. --}}
    <div id="abbev-nav-progress"><div class="bar"></div></div>
    <div id="abbev-nav-overlay" aria-hidden="true">
        <div class="box">
            <div class="ring">
                <span class="logo"><img src="{{ asset('logo/logo.jpeg') }}" alt=""></span>
            </div>
            <span class="label">Chargement…</span>
        </div>
    </div>

    {{-- Modal de confirmation ABBEV : remplace window.confirm(). Déclenchée par
         tout formulaire portant l'attribut data-confirm (+ options data-confirm-type
         « danger|warning|primary », data-confirm-title, data-confirm-confirm). --}}
    <div id="abbev-confirm" role="dialog" aria-modal="true" aria-hidden="true">
        <div class="cm-box">
            <div class="cm-ring cm-ring-danger" data-cm-ringwrap>
                <i class="fas fa-triangle-exclamation" data-cm-icon></i>
            </div>
            <h3 class="cm-title" data-cm-title>Confirmer l'action</h3>
            <p class="cm-message" data-cm-message></p>
            <div class="cm-actions">
                <button type="button" class="cm-btn cm-btn-cancel" data-cm-cancel>Annuler</button>
                <button type="button" class="cm-btn cm-btn-danger" data-cm-confirm>Confirmer</button>
            </div>
        </div>
    </div>

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
            <nav class="flex-1 mt-6 px-3 overflow-y-auto abbev-scroll">
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

                    @unless(auth()->user()->isAssistant())
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
                    @endunless

                    @if(auth()->user()->isAdmin() || auth()->user()->isAssistant())
                    @php $__pendingModeration = \App\Models\Media::where('moderation_status', 'pending')->count(); @endphp
                    <a href="{{ route('moderation.index') }}"
                       class="flex items-center px-4 py-3 text-sm rounded-lg transition-all {{ request()->routeIs('moderation.*') ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-md' : 'text-gray-300 hover:bg-dark-200 hover:text-white' }}">
                        <i class="fas fa-clipboard-check w-5 mr-3"></i>
                        Modération
                        @if($__pendingModeration > 0)
                            <span class="ml-auto text-xs px-2 py-0.5 rounded-full bg-amber-500 text-white">{{ $__pendingModeration }}</span>
                        @endif
                    </a>
                    @endif

                    @if(auth()->user()->isAdmin())
                    <a href="{{ route('categories.index') }}"
                       class="flex items-center px-4 py-3 text-sm rounded-lg transition-all {{ request()->routeIs('categories.*') ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-md' : 'text-gray-300 hover:bg-dark-200 hover:text-white' }}">
                        <i class="fas fa-th-large w-5 mr-3"></i>
                        Catégories
                    </a>

                    <a href="{{ route('screenings.index') }}"
                       class="flex items-center px-4 py-3 text-sm rounded-lg transition-all {{ request()->routeIs('screenings.*') ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-md' : 'text-gray-300 hover:bg-dark-200 hover:text-white' }}">
                        <i class="fas fa-ticket-alt w-5 mr-3"></i>
                        Séances cinéma
                    </a>

                    <a href="{{ route('admin.bunny.library') }}"
                       class="flex items-center px-4 py-3 text-sm rounded-lg transition-all {{ request()->routeIs('admin.bunny.library') || request()->routeIs('admin.bunny.videos.*') ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-md' : 'text-gray-300 hover:bg-dark-200 hover:text-white' }}">
                        <i class="fas fa-cloud w-5 mr-3"></i>
                        Bunny Library
                    </a>
                    @endif

                    @unless(auth()->user()->isAssistant())
                    @php
                        $__activeUploadsQuery = \App\Models\BunnyUpload::whereNotIn('status', \App\Models\BunnyUpload::TERMINAL);
                        if (auth()->user()->role === 'producer') {
                            $__activeUploadsQuery->where('user_id', auth()->id());
                        }
                        $__activeUploads = $__activeUploadsQuery->count();
                    @endphp
                    <a href="{{ route('admin.bunny.uploads.index') }}"
                       class="flex items-center px-4 py-3 text-sm rounded-lg transition-all {{ request()->routeIs('admin.bunny.uploads.*') || request()->routeIs('admin.bunny.upload.*') ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-md' : 'text-gray-300 hover:bg-dark-200 hover:text-white' }}">
                        <i class="fas fa-cloud-arrow-up w-5 mr-3"></i>
                        Upload vidéos
                        <span id="sidebar-upload-badge" class="ml-auto inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 text-[10px] font-bold rounded-full bg-blue-500 text-white animate-pulse {{ $__activeUploads > 0 ? '' : 'hidden' }}">{{ $__activeUploads }}</span>
                    </a>
                    @endunless
                </div>

                @if(auth()->user()->isAdmin())
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

                    <a href="{{ route('producers.index') }}"
                       class="flex items-center px-4 py-3 text-sm rounded-lg transition-all {{ request()->routeIs('producers.*') ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-md' : 'text-gray-300 hover:bg-dark-200 hover:text-white' }}">
                        <i class="fas fa-clapperboard w-5 mr-3"></i>
                        Producteurs
                    </a>

                    <a href="{{ route('assistants.index') }}"
                       class="flex items-center px-4 py-3 text-sm rounded-lg transition-all {{ request()->routeIs('assistants.*') ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-md' : 'text-gray-300 hover:bg-dark-200 hover:text-white' }}">
                        <i class="fas fa-user-shield w-5 mr-3"></i>
                        Assistants
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

                    <a href="{{ route('earnings.index') }}"
                       class="flex items-center px-4 py-3 text-sm rounded-lg transition-all {{ request()->routeIs('earnings.*') ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-md' : 'text-gray-300 hover:bg-dark-200 hover:text-white' }}">
                        <i class="fas fa-coins w-5 mr-3"></i>
                        Revenus producteurs
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
                @endif
            </nav>

            <!-- User Info at bottom -->
            <div class="p-4 border-t border-dark-200 flex-shrink-0">
                <div class="flex items-center">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center overflow-hidden flex-shrink-0 bg-gradient-to-br from-primary-500 to-primary-600 shadow-md">
                        <span class="text-white font-bold">{{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}</span>
                    </div>
                    <div class="ml-3 flex-1">
                        <p class="text-sm font-medium text-white">{{ auth()->user()->name ?? 'Admin' }}</p>
                        <p class="text-xs text-gray-400">{{ auth()->user()->isProducer() ? 'Producteur' : (auth()->user()->isAssistant() ? 'Assistant' : 'Administrateur') }}</p>
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
            <main class="flex-1 overflow-y-auto p-6 abbev-scroll">
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

    <div id="page-scripts">@stack('scripts')</div>

    {{-- Widget flottant d'upload (persiste entre les pages) --}}
    <div id="abbev-upload-widget" class="fixed bottom-4 right-4 z-50 hidden">
        <div class="bg-dark-100 border border-dark-200 rounded-xl shadow-2xl w-80 overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 cursor-pointer" onclick="ABBEV.uploads.toggleWidget()">
                <div class="flex items-center gap-2">
                    <i class="fas fa-cloud-arrow-up text-primary-400 animate-pulse"></i>
                    <span class="text-white text-sm font-medium">Upload en cours</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-400" id="uw-count"></span>
                    <i class="fas fa-chevron-up text-gray-500 text-xs" id="uw-chevron"></i>
                </div>
            </div>
            <div class="h-1 bg-dark-300"><div class="h-1 bg-primary-500 transition-all" id="uw-bar" style="width:0%"></div></div>
            <div id="uw-details" class="max-h-60 overflow-y-auto divide-y divide-dark-200/50"></div>
        </div>
    </div>

    {{-- Script permanent : PJAX + moteur d'upload (jamais détruit) --}}
    <script>
    (function(){
        'use strict';
        const ABBEV = window.ABBEV = window.ABBEV || {};
        const csrf = () => document.querySelector('meta[name="csrf-token"]').content;

        /* ====== MOTEUR D'UPLOAD (Resumable.js, persistant) ====== */
        const UE = ABBEV.uploads = {
            r: null, inFlight: 0, items: new Map(), pollTimer: null, widgetOpen: true, _serverActive: 0,

            init() {
                if (this.r || !window.Resumable) return;
                this.r = new Resumable({
                    target: '/admin/bunny/upload/chunk',
                    chunkSize: 5*1024*1024, simultaneousUploads: 3,
                    testChunks: true, fileParameterName: 'file',
                    maxChunkRetries: 5, chunkRetryInterval: 2000,
                    headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
                    query: (f) => ({ upload_id: f._uid }),
                });

                this.r.on('fileAdded', async (f) => {
                    try {
                        const res = await fetch('/admin/bunny/upload/start', {
                            method:'POST',
                            headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf(),'Accept':'application/json'},
                            body: JSON.stringify({filename:f.fileName||f.file.name, size:f.size, identifier:f.uniqueIdentifier}),
                        });
                        const d = await res.json();
                        if (!res.ok) { ABBEV.toast(d.error||'Démarrage impossible.','error'); this.r.removeFile(f); return; }
                        f._uid = d.upload_id;
                        this.inFlight++;
                        this.items.set(d.upload_id, {title:f.fileName, progress:0, status:'uploading', size:f.size});
                        this.updateWidget();
                        this.emit('progress',{id:d.upload_id,title:f.fileName,status:'uploading',progress:0,size_bytes:f.size});
                        this.r.upload();
                    } catch(e) { ABBEV.toast('Erreur réseau.','error'); this.r.removeFile(f); }
                });

                this.r.on('fileProgress', (f) => {
                    if (!f._uid) return;
                    const p = Math.floor(f.progress()*100);
                    const it = this.items.get(f._uid);
                    if (it) it.progress = p;
                    this.updateWidget();
                    this.emit('progress',{id:f._uid,title:f.fileName,status:'uploading',progress:p,size_bytes:f.size});
                });

                this.r.on('fileSuccess', (f) => {
                    this.inFlight = Math.max(0, this.inFlight-1);
                    if (f._uid) this.items.delete(f._uid);
                    this.updateWidget();
                    this.emit('complete',{id:f._uid});
                    this.ensurePolling();
                });

                this.r.on('fileError', (f) => {
                    this.inFlight = Math.max(0, this.inFlight-1);
                    const it = f._uid && this.items.get(f._uid);
                    if (it) it.status = 'failed';
                    this.updateWidget();
                    this.emit('error',{id:f._uid});
                });
            },

            connectDropzone(drop, browse) {
                if (!this.r) return;

                // Glisser-déposer : lié une seule fois par élément (le PJAX
                // reconstruit la zone à chaque visite → nouvel élément, pas
                // d'accumulation ; le flag évite un double-bind sur le même).
                if (drop && !drop._abbevBound) {
                    drop._abbevBound = true;
                    this.r.assignDrop(drop);
                }

                // Bouton « Choisir des fichiers » : on N'UTILISE PAS
                // Resumable.assignBrowse (son <input> overlay se cale mal sur le
                // bouton reconstruit par le PJAX → « des fois oui, des fois non »).
                //
                // Un SEUL input caché, attaché au <body> et RÉUTILISÉ. Important :
                // il ne doit PAS être enfant du bouton — sinon input.click()
                // rebondirait sur le handler du bouton (ré-entrance) et Chrome
                // bloquerait le sélecteur de fichiers (chaîne de clics
                // programmatiques). Ici l'input est hors du bouton : pas de rebond.
                if (!this._browseInput) {
                    const input = document.createElement('input');
                    input.type = 'file';
                    input.multiple = true;
                    input.accept = 'video/*,.mkv,.ts,.m4v,.webm,.avi,.mov';
                    input.style.cssText = 'position:fixed;left:-9999px;top:0;width:1px;height:1px;opacity:0;';
                    input.addEventListener('change', (ev) => {
                        const files = input.files;
                        if (files && files.length && this.r) {
                            if (typeof this.r.addFiles === 'function') this.r.addFiles(files, ev);
                            else Array.from(files).forEach((file) => this.r.addFile(file, ev));
                        }
                        input.value = ''; // autorise la re-sélection du même fichier
                    });
                    document.body.appendChild(input);
                    this._browseInput = input;
                }
                if (browse && !browse._abbevBound) {
                    browse._abbevBound = true;
                    browse.addEventListener('click', (e) => { e.preventDefault(); this._browseInput.click(); });
                }
            },

            emit(t, d) { window.dispatchEvent(new CustomEvent('abbev:upload-'+t, {detail:d})); },

            updateSidebarBadge() {
                const badge = document.getElementById('sidebar-upload-badge');
                if (!badge) return;
                const n = this.items.size + (this._serverActive || 0);
                badge.textContent = n;
                badge.classList.toggle('hidden', n === 0);
            },

            updateWidget() {
                this.updateSidebarBadge();
                const w = document.getElementById('abbev-upload-widget');
                if (!w) return;
                const n = this.items.size;
                w.classList.toggle('hidden', n===0 && this.inFlight===0);
                if (n===0) return;
                let total=0; this.items.forEach(it => total+=it.progress);
                const avg = Math.floor(total/n);
                const bar=document.getElementById('uw-bar'); if(bar) bar.style.width=avg+'%';
                const cnt=document.getElementById('uw-count'); if(cnt) cnt.textContent=n+' fichier'+(n>1?'s':'')+' · '+avg+'%';
                const det=document.getElementById('uw-details');
                if (det && this.widgetOpen) {
                    let h='';
                    this.items.forEach((it,id)=>{
                        h+='<div class="px-4 py-2"><p class="text-white text-xs truncate">'+it.title+'</p>'
                          +'<div class="flex items-center gap-2 mt-1"><div class="flex-1 bg-dark-300 rounded-full h-1">'
                          +'<div class="bg-primary-500 h-1 rounded-full" style="width:'+it.progress+'%"></div></div>'
                          +'<span class="text-[10px] text-gray-400">'+it.progress+'%</span></div></div>';
                    });
                    det.innerHTML=h;
                }
            },

            toggleWidget() {
                this.widgetOpen=!this.widgetOpen;
                const d=document.getElementById('uw-details'), c=document.getElementById('uw-chevron');
                if(d) d.classList.toggle('hidden',!this.widgetOpen);
                if(c){c.classList.toggle('fa-chevron-up',this.widgetOpen);c.classList.toggle('fa-chevron-down',!this.widgetOpen);}
                this.updateWidget();
            },

            ensurePolling(){ if(!this.pollTimer) this.poll(); },

            async poll(){
                try{
                    const r=await fetch('/admin/bunny/uploads/active',{headers:{'Accept':'application/json'}});
                    if(!r.ok){this.pollTimer=setTimeout(()=>this.poll(),5000);return;}
                    const{data}=await r.json();
                    const serverIds=(data||[]).filter(u=>!this.items.has(u.id));
                    this._serverActive=serverIds.length;
                    this.updateSidebarBadge();
                    this.emit('status',{data:data||[]});
                    this.pollTimer=(data||[]).length>0?setTimeout(()=>this.poll(),3000):null;
                }catch(e){this.pollTimer=setTimeout(()=>this.poll(),5000);}
            },
        };

        UE.init();

        /* ====== LOADER DE NAVIGATION (feedback immédiat au clic) ====== */
        const NavLoader = ABBEV.navLoader = {
            active:false, _p:0, trickle:null, overlayTimer:null, failsafe:null,
            _els(){
                return {
                    prog: document.getElementById('abbev-nav-progress'),
                    bar: document.querySelector('#abbev-nav-progress .bar'),
                    overlay: document.getElementById('abbev-nav-overlay'),
                };
            },
            start(){
                if(this.active) return; this.active=true;
                const {prog,bar,overlay}=this._els();
                if(prog&&bar){
                    this._p=8; prog.classList.add('is-active'); bar.style.width='8%';
                    requestAnimationFrame(()=>{ bar.style.width='32%'; });
                    clearInterval(this.trickle);
                    this.trickle=setInterval(()=>{
                        this._p=Math.min(90,this._p+Math.random()*9);
                        bar.style.width=this._p+'%';
                    },420);
                }
                clearTimeout(this.overlayTimer);
                // Overlay seulement si le serveur met > 240ms (évite le clignotement).
                this.overlayTimer=setTimeout(()=>{ if(overlay) overlay.classList.add('is-visible'); },240);
                // Filet de sécurité si la navigation n'aboutit pas (submit annulé…).
                clearTimeout(this.failsafe);
                this.failsafe=setTimeout(()=>this.done(),15000);
            },
            done(){
                if(!this.active) return; this.active=false;
                const {prog,bar,overlay}=this._els();
                clearInterval(this.trickle); clearTimeout(this.overlayTimer); clearTimeout(this.failsafe);
                if(overlay) overlay.classList.remove('is-visible');
                if(prog&&bar){
                    bar.style.width='100%';
                    setTimeout(()=>{ prog.classList.remove('is-active'); setTimeout(()=>{ bar.style.width='0%'; },300); },200);
                }
            },
        };

        /* ====== PJAX (navigation sans rechargement) ====== */
        async function pjax(url, push){
            NavLoader.start();
            try{
                const resp=await fetch(url);
                if(!resp.ok) throw resp;
                const html=await resp.text();
                const doc=new DOMParser().parseFromString(html,'text/html');

                const curMain=document.querySelector('main'), newMain=doc.querySelector('main');
                if(curMain&&newMain){
                    curMain.innerHTML=newMain.innerHTML;
                    // Retrigger l'animation de fondu du nouveau contenu.
                    curMain.classList.remove('abbev-nav-fade');
                    void curMain.offsetWidth;
                    curMain.classList.add('abbev-nav-fade');
                }

                // Re-exécuter les scripts spécifiques à la page
                const curPS=document.getElementById('page-scripts'), newPS=doc.getElementById('page-scripts');
                if(curPS&&newPS){
                    curPS.innerHTML='';
                    for(const el of [...newPS.querySelectorAll('script')]){
                        const s=document.createElement('script');
                        if(el.src) s.src=el.src; else s.textContent=el.textContent;
                        curPS.appendChild(s);
                    }
                }

                // Page styles
                const curST=document.getElementById('page-styles'),newST=doc.getElementById('page-styles');
                if(curST&&newST) curST.innerHTML=newST.innerHTML;

                document.title=doc.title;
                const nh=doc.querySelector('header h1'),ch=document.querySelector('header h1');
                if(nh&&ch) ch.innerHTML=nh.innerHTML;
                // Sidebar (active + badges)
                const nn=doc.querySelector('aside nav'),cn=document.querySelector('aside nav');
                if(nn&&cn) cn.innerHTML=nn.innerHTML;
                // CSRF
                const nc=doc.querySelector('meta[name="csrf-token"]'),cc=document.querySelector('meta[name="csrf-token"]');
                if(nc&&cc) cc.content=nc.content;
                // Alpine re-init
                if(window.Alpine&&curMain) Alpine.initTree(curMain);

                if(push!==false) history.pushState({pjax:1},'',url);
                window.scrollTo(0,0);
                NavLoader.done();
            }catch(e){ window.location.href=url; }
        }

        // Interception des liens
        document.addEventListener('click',(e)=>{
            if(e.defaultPrevented||e.ctrlKey||e.metaKey||e.shiftKey||e.altKey) return;
            const a=e.target.closest('a[href]');
            if(!a||a.target==='_blank'||a.hasAttribute('download')) return;
            const h=a.getAttribute('href');
            if(!h||h.startsWith('#')||h.startsWith('javascript:')||h.startsWith('mailto:')) return;
            try{
                const u=new URL(a.href,location.origin);
                if(u.origin!==location.origin) return;
                e.preventDefault();
                // Lien à confirmer (ex: « Annuler » avec données déjà saisies) → modal.
                if(a.hasAttribute('data-confirm') && typeof ConfirmModal!=='undefined' && ConfirmModal){
                    ConfirmModal.open(ConfirmModal.readOpts(a), ()=>pjax(u.href));
                    return;
                }
                pjax(u.href);
            }catch(ex){}
        },true);

        /* ====== Modal de confirmation (remplace window.confirm) ====== */
        const ConfirmModal = (function(){
            const root = document.getElementById('abbev-confirm');
            if(!root) return null;
            const elTitle = root.querySelector('[data-cm-title]');
            const elMsg   = root.querySelector('[data-cm-message]');
            const elIcon  = root.querySelector('[data-cm-icon]');
            const elRing  = root.querySelector('[data-cm-ringwrap]');
            const elOk    = root.querySelector('[data-cm-confirm]');
            const elNo    = root.querySelector('[data-cm-cancel]');
            const TYPES = {
                danger:  { icon:'fa-triangle-exclamation', ring:'cm-ring-danger',  btn:'cm-btn-danger'  },
                warning: { icon:'fa-circle-exclamation',   ring:'cm-ring-warning', btn:'cm-btn-warning' },
                primary: { icon:'fa-circle-question',      ring:'cm-ring-primary', btn:'cm-btn-primary' },
            };
            let onConfirm = null;
            // Lit les options depuis un élément porteur de data-confirm (form OU lien).
            function readOpts(el){
                return {
                    type:    el.getAttribute('data-confirm-type'),
                    title:   el.getAttribute('data-confirm-title'),
                    message: el.getAttribute('data-confirm'),
                    confirm: el.getAttribute('data-confirm-confirm'),
                };
            }
            let openedAt = 0;
            function open(opts, cb){
                onConfirm = cb;
                const t = TYPES[opts.type] || TYPES.danger;
                elTitle.textContent = opts.title   || "Confirmer l'action";
                elMsg.textContent   = opts.message || 'Confirmer cette action ?';
                elOk.textContent    = opts.confirm || 'Confirmer';
                elIcon.className = 'fas ' + t.icon;
                elRing.className = 'cm-ring ' + t.ring;
                elOk.className   = 'cm-btn ' + t.btn;
                root.classList.add('is-open');
                document.body.style.overflow = 'hidden';
                openedAt = Date.now();
                setTimeout(()=>elOk.focus(), 60);
            }
            function close(){ root.classList.remove('is-open'); document.body.style.overflow=''; onConfirm=null; }
            elOk.addEventListener('click',()=>{ const cb = onConfirm; close(); if(cb) cb(); });
            elNo.addEventListener('click', close);
            // Fermeture au clic sur le fond, MAIS pas pendant les 350ms qui suivent
            // l'ouverture : sinon un double-clic réflexe sur « Enregistrer » (le
            // 2e clic tombe sur le fond) referme le modal → sensation de « 2-3 clics ».
            root.addEventListener('click',(e)=>{ if(e.target===root && Date.now()-openedAt > 350) close(); });
            document.addEventListener('keydown',(e)=>{ if(e.key==='Escape' && root.classList.contains('is-open')) close(); });
            return { open, readOpts };
        })();

        /* ====== Toast ABBEV (notification non bloquante — remplace window.alert) ====== */
        ABBEV.toast = function(message, type, duration){
            type = type || 'success';
            duration = duration === undefined ? 5000 : duration;
            let host = document.getElementById('abbev-toast-host');
            if(!host){
                host = document.createElement('div');
                host.id = 'abbev-toast-host';
                host.style.cssText = 'position:fixed;top:20px;right:20px;z-index:10050;display:flex;flex-direction:column;gap:10px;max-width:min(440px,92vw);pointer-events:none;';
                document.body.appendChild(host);
            }
            const C = {
                success: { bg:'#052e1a', bd:'#22c55e', fg:'#bbf7d0', icon:'fa-circle-check' },
                error:   { bg:'#3a0d10', bd:'#ef4444', fg:'#fecaca', icon:'fa-circle-exclamation' },
                info:    { bg:'#0b2b34', bd:'#22d3ee', fg:'#a5f3fc', icon:'fa-circle-info' },
            }[type] || { bg:'#052e1a', bd:'#22c55e', fg:'#bbf7d0', icon:'fa-circle-check' };
            const el = document.createElement('div');
            el.style.cssText = 'pointer-events:auto;background:'+C.bg+';border-left:4px solid '+C.bd+';color:'+C.fg
                +';padding:12px 14px;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.35);font-size:.9rem;line-height:1.55;'
                +'white-space:pre-line;display:flex;gap:10px;align-items:flex-start;opacity:0;transform:translateX(14px);transition:opacity .2s,transform .2s;';
            el.innerHTML = '<i class="fas '+C.icon+'" style="margin-top:2px;color:'+C.bd+'"></i>'
                +'<div class="abbev-toast-msg" style="flex:1"></div>'
                +'<button type="button" aria-label="Fermer" style="background:none;border:none;color:'+C.fg+';cursor:pointer;opacity:.65"><i class="fas fa-times"></i></button>';
            el.querySelector('.abbev-toast-msg').textContent = message; // textContent = pas d'injection HTML
            const remove = ()=>{ el.style.opacity='0'; el.style.transform='translateX(14px)'; setTimeout(()=>el.remove(),200); };
            el.querySelector('button').addEventListener('click', remove);
            host.appendChild(el);
            requestAnimationFrame(()=>{ el.style.opacity='1'; el.style.transform='none'; });
            if(duration>0) setTimeout(remove, duration);
            return el;
        };

        // Intercepte les soumissions de FORMULAIRE à confirmer AVANT le loader/PJAX.
        document.addEventListener('submit',(e)=>{
            const f = e.target;
            if(!f || !f.hasAttribute('data-confirm') || !ConfirmModal) return;
            if(f.dataset.confirmed === '1'){ delete f.dataset.confirmed; return; }
            // Formulaire invalide (champs requis) → on laisse la validation navigateur.
            if(typeof f.checkValidity === 'function' && !f.checkValidity()) return;
            e.preventDefault(); e.stopImmediatePropagation();
            ConfirmModal.open(ConfirmModal.readOpts(f), ()=>{
                f.dataset.confirmed = '1';
                if(typeof f.requestSubmit === 'function') f.requestSubmit(); else f.submit();
            });
        }, true);

        // Interception des formulaires GET (recherche, filtres)
        document.addEventListener('submit',(e)=>{
            const f=e.target;
            if(!f||f.method.toUpperCase()!=='GET') return;
            e.preventDefault();
            const u=new URL(f.action,location.origin);
            new FormData(f).forEach((v,k)=>{if(v)u.searchParams.set(k,v);else u.searchParams.delete(k);});
            pjax(u.href);
        },true);

        // Feedback immédiat sur les formulaires POST/PUT/DELETE (création,
        // édition, suppression = navigation complète, pas de PJAX). On NE
        // preventDefault PAS : le navigateur soumet, le loader tient jusqu'au
        // rechargement de la nouvelle page.
        document.addEventListener('submit',(e)=>{
            const f=e.target;
            if(!f||f.method.toUpperCase()==='GET') return;
            // Si le formulaire est invalide, le navigateur bloque l'envoi.
            if(typeof f.checkValidity==='function' && !f.checkValidity()) return;
            NavLoader.start();
        },true);

        // bfcache / retour navigateur : on garantit que le loader est masqué.
        window.addEventListener('pageshow',()=>NavLoader.done());

        window.addEventListener('popstate',()=>pjax(location.href,false));
        history.replaceState({pjax:1},'',location.href);
        ABBEV.navigate=pjax;

        /* ====== BEFOREUNLOAD (fermeture de l'onglet uniquement) ====== */
        window.addEventListener('beforeunload',(e)=>{
            if(UE.inFlight>0){e.preventDefault();e.returnValue='';}
        });
    })();
    </script>
</body>
</html>
