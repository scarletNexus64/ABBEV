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
    </style>

    <div id="page-styles">@stack('styles')</div>
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
                        <p class="text-xs text-gray-400">{{ auth()->user()->isProducer() ? 'Producteur' : 'Administrateur' }}</p>
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
                        if (!res.ok) { alert(d.error||'Démarrage impossible.'); this.r.removeFile(f); return; }
                        f._uid = d.upload_id;
                        this.inFlight++;
                        this.items.set(d.upload_id, {title:f.fileName, progress:0, status:'uploading', size:f.size});
                        this.updateWidget();
                        this.emit('progress',{id:d.upload_id,title:f.fileName,status:'uploading',progress:0,size_bytes:f.size});
                        this.r.upload();
                    } catch(e) { alert('Erreur réseau.'); this.r.removeFile(f); }
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
                this.r.assignDrop(drop);
                this.r.assignBrowse(browse, false, false);
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

        /* ====== PJAX (navigation sans rechargement) ====== */
        async function pjax(url, push){
            try{
                const resp=await fetch(url);
                if(!resp.ok) throw resp;
                const html=await resp.text();
                const doc=new DOMParser().parseFromString(html,'text/html');

                const curMain=document.querySelector('main'), newMain=doc.querySelector('main');
                if(curMain&&newMain) curMain.innerHTML=newMain.innerHTML;

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
                pjax(u.href);
            }catch(ex){}
        },true);

        // Interception des formulaires GET (recherche, filtres)
        document.addEventListener('submit',(e)=>{
            const f=e.target;
            if(!f||f.method.toUpperCase()!=='GET') return;
            e.preventDefault();
            const u=new URL(f.action,location.origin);
            new FormData(f).forEach((v,k)=>{if(v)u.searchParams.set(k,v);else u.searchParams.delete(k);});
            pjax(u.href);
        },true);

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
