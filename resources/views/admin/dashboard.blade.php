@extends('admin.layouts.app')

@section('title', 'Dashboard')
@section('header', 'Dashboard')

@section('content')
@php $isProducer = $isProducer ?? false; @endphp
<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Total Media Card -->
    <div class="bg-gradient-to-br from-primary-500 to-primary-600 rounded-xl shadow-lg shadow-primary-500/30 p-6 hover:shadow-2xl hover:shadow-primary-500/40 transition-all duration-300 transform hover:-translate-y-1">
        <div class="flex items-center justify-between">
            <div class="flex-1">
                <p class="text-sm font-medium text-white/90 mb-1">Total Médias</p>
                <p class="text-3xl font-bold text-white">{{ number_format($stats['media']['total'] ?? 0) }}</p>
                <p class="text-sm text-white/90 mt-2 flex items-center">
                    <i class="fas fa-film mr-1"></i>
                    Films & Séries
                </p>
            </div>
            <div class="w-14 h-14 bg-white/20 backdrop-blur-sm rounded-full flex items-center justify-center shadow-lg">
                <i class="fas fa-photo-video text-2xl text-white"></i>
            </div>
        </div>
    </div>

    <!-- Movies Card -->
    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6 hover:shadow-2xl hover:shadow-blue-500/10 transition-all duration-300 transform hover:-translate-y-1">
        <div class="flex items-center justify-between">
            <div class="flex-1">
                <p class="text-sm font-medium text-gray-400 mb-1">Films</p>
                <p class="text-3xl font-bold text-white">{{ number_format($stats['media']['movies'] ?? 0) }}</p>
                <p class="text-sm text-green-400 mt-2 flex items-center">
                    <i class="fas fa-arrow-up mr-1"></i>
                    {{ $stats['media']['recent'] ?? 0 }} cette semaine
                </p>
            </div>
            <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center shadow-lg shadow-blue-500/30">
                <i class="fas fa-video text-2xl text-white"></i>
            </div>
        </div>
    </div>

    <!-- Series Card -->
    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6 hover:shadow-2xl hover:shadow-purple-500/10 transition-all duration-300 transform hover:-translate-y-1">
        <div class="flex items-center justify-between">
            <div class="flex-1">
                <p class="text-sm font-medium text-gray-400 mb-1">Séries</p>
                <p class="text-3xl font-bold text-white">{{ number_format($stats['media']['series'] ?? 0) }}</p>
                <p class="text-sm text-gray-400 mt-2 flex items-center">
                    <i class="fas fa-tv mr-1"></i>
                    TV Shows
                </p>
            </div>
            <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-purple-600 rounded-full flex items-center justify-center shadow-lg shadow-purple-500/30">
                <i class="fas fa-tv text-2xl text-white"></i>
            </div>
        </div>
    </div>

    @if(! $isProducer)
    <!-- Users Card -->
    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6 hover:shadow-2xl hover:shadow-green-500/10 transition-all duration-300 transform hover:-translate-y-1">
        <div class="flex items-center justify-between">
            <div class="flex-1">
                <p class="text-sm font-medium text-gray-400 mb-1">Utilisateurs</p>
                <p class="text-3xl font-bold text-white">{{ number_format($stats['users']['total'] ?? 0) }}</p>
                <p class="text-sm text-green-400 mt-2 flex items-center">
                    <i class="fas fa-arrow-up mr-1"></i>
                    +{{ $stats['users']['users_today'] ?? 0 }} aujourd'hui
                </p>
            </div>
            <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-green-600 rounded-full flex items-center justify-center shadow-lg shadow-green-500/30">
                <i class="fas fa-users text-2xl text-white"></i>
            </div>
        </div>
    </div>
    @else
    <!-- Upload shortcut (producteur) -->
    <a href="{{ route('admin.bunny.uploads.index') }}" class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6 hover:shadow-2xl hover:shadow-primary-500/10 transition-all duration-300 transform hover:-translate-y-1 flex items-center justify-between">
        <div class="flex-1">
            <p class="text-sm font-medium text-gray-400 mb-1">Mes vidéos</p>
            <p class="text-lg font-bold text-white">Uploader du contenu</p>
            <p class="text-sm text-primary-400 mt-2 flex items-center"><i class="fas fa-cloud-arrow-up mr-1"></i>Aller à l'upload</p>
        </div>
        <div class="w-14 h-14 bg-gradient-to-br from-primary-500 to-primary-600 rounded-full flex items-center justify-center shadow-lg shadow-primary-500/30">
            <i class="fas fa-cloud-arrow-up text-2xl text-white"></i>
        </div>
    </a>
    @endif
</div>

{{-- Gains producteur : vues générées + montant estimé (tarifs configurés par l'admin) --}}
@if($isProducer && $earnings)
<div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6 mb-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
        <div>
            <h3 class="text-white font-semibold text-lg"><i class="fas fa-coins mr-2 text-amber-400"></i>Mes revenus</h3>
            <p class="text-gray-500 text-sm">Générés par les abonnements aux tiers de vos contenus.</p>
        </div>
        <div class="text-right">
            <p class="text-3xl font-bold text-amber-300">{{ number_format($earnings['total_amount'], 2, ',', ' ') }} {{ $earnings['currency'] }}</p>
            <p class="text-gray-400 text-sm">{{ number_format($earnings['total_views']) }} vue(s) au total</p>
        </div>
    </div>
    <div class="grid grid-cols-3 gap-4">
        @foreach(['classique' => 'sky', 'standard' => 'amber', 'premium' => 'fuchsia'] as $tier => $color)
            <div class="bg-dark-200/40 rounded-lg p-4">
                <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">{{ ucfirst($tier) }}</p>
                <p class="text-white font-bold">{{ number_format($earnings['by_tier'][$tier]['views']) }} vues</p>
                <p class="text-{{ $color }}-300 text-sm">{{ number_format($earnings['by_tier'][$tier]['amount'], 2, ',', ' ') }} {{ $earnings['currency'] }}</p>
            </div>
        @endforeach
    </div>
</div>
@endif

<!-- Secondary Stats -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
    <!-- Categories Card -->
    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6 hover:shadow-xl hover:shadow-orange-500/10 transition-all duration-300">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-400">Catégories</p>
                <p class="text-2xl font-bold text-white mt-1">{{ number_format($stats['categories']['total'] ?? 0) }}</p>
            </div>
            <div class="w-12 h-12 bg-orange-500/20 rounded-lg flex items-center justify-center border border-orange-500/30">
                <i class="fas fa-th-large text-xl text-orange-400"></i>
            </div>
        </div>
    </div>

    @if(! $isProducer)
    <!-- Admins Card -->
    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6 hover:shadow-xl hover:shadow-yellow-500/10 transition-all duration-300">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-400">Administrateurs</p>
                <p class="text-2xl font-bold text-white mt-1">{{ number_format($stats['users']['admins'] ?? 0) }}</p>
            </div>
            <div class="w-12 h-12 bg-yellow-500/20 rounded-lg flex items-center justify-center border border-yellow-500/30">
                <i class="fas fa-user-shield text-xl text-yellow-400"></i>
            </div>
        </div>
    </div>

    <!-- Regular Users Card -->
    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6 hover:shadow-xl hover:shadow-cyan-500/10 transition-all duration-300">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-400">Utilisateurs</p>
                <p class="text-2xl font-bold text-white mt-1">{{ number_format($stats['users']['users'] ?? 0) }}</p>
            </div>
            <div class="w-12 h-12 bg-cyan-500/20 rounded-lg flex items-center justify-center border border-cyan-500/30">
                <i class="fas fa-user text-xl text-cyan-400"></i>
            </div>
        </div>
    </div>
    @endif
</div>

<!-- Charts Row -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <!-- Media Chart -->
    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-white">Médias ajoutés (30 derniers jours)</h3>
            <div class="flex items-center space-x-3">
                <div class="flex items-center">
                    <span class="w-3 h-3 bg-blue-500 rounded-full mr-2"></span>
                    <span class="text-xs text-gray-400">Films</span>
                </div>
                <div class="flex items-center">
                    <span class="w-3 h-3 bg-purple-500 rounded-full mr-2"></span>
                    <span class="text-xs text-gray-400">Séries</span>
                </div>
            </div>
        </div>
        <div class="relative h-64">
            <canvas id="mediaChart"></canvas>
        </div>
    </div>

    @if(! $isProducer)
    <!-- Users Chart -->
    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-white">Inscriptions (30 derniers jours)</h3>
            <div class="flex items-center space-x-3">
                <div class="flex items-center">
                    <span class="w-3 h-3 bg-green-500 rounded-full mr-2"></span>
                    <span class="text-xs text-gray-400">Utilisateurs</span>
                </div>
            </div>
        </div>
        <div class="relative h-64">
            <canvas id="usersChart"></canvas>
        </div>
    </div>
    @endif
</div>

<!-- Content Lists -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <!-- Top Categories -->
    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
        <h3 class="text-lg font-semibold text-white mb-4 flex items-center">
            <i class="fas fa-chart-bar text-primary-500 mr-2"></i>
            Top Catégories
        </h3>
        <div class="space-y-3">
            @forelse($topCategories ?? [] as $category)
                <div class="flex items-center justify-between p-3 bg-dark-50 rounded-lg hover:bg-dark-200 transition-colors border border-dark-300">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-gradient-to-br from-primary-500 to-primary-600 rounded-lg flex items-center justify-center text-white font-semibold mr-3 shadow-lg shadow-primary-500/30">
                            {{ strtoupper(substr($category->name ?? 'C', 0, 1)) }}
                        </div>
                        <div>
                            <p class="font-medium text-white">{{ $category->name ?? 'Catégorie' }}</p>
                            <p class="text-xs text-gray-400">{{ $category->media_count ?? 0 }} médias</p>
                        </div>
                    </div>
                    <span class="px-3 py-1 bg-primary-500/20 text-primary-400 rounded-full text-xs font-medium border border-primary-500/30">
                        {{ $category->media_count ?? 0 }}
                    </span>
                </div>
            @empty
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-folder-open text-4xl text-gray-600 mb-2"></i>
                    <p>Aucune catégorie</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Recent Media -->
    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
        <h3 class="text-lg font-semibold text-white mb-4 flex items-center">
            <i class="fas fa-film text-blue-500 mr-2"></i>
            Médias Récents
        </h3>
        <div class="space-y-3">
            @forelse($recentMedia ?? [] as $media)
                <div class="flex items-center justify-between p-3 bg-dark-50 rounded-lg hover:bg-dark-200 transition-colors border border-dark-300">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg flex items-center justify-center text-white mr-3 shadow-lg shadow-blue-500/30">
                            <i class="fas fa-{{ $media->type === 'movie' ? 'video' : 'tv' }}"></i>
                        </div>
                        <div>
                            <p class="font-medium text-white text-sm">{{ \Illuminate\Support\Str::limit($media->title ?? 'Média', 30) }}</p>
                            <p class="text-xs text-gray-400">{{ $media->created_at?->diffForHumans() }}</p>
                        </div>
                    </div>
                    <span class="text-xs font-semibold px-2 py-1 rounded-full {{ $media->type === 'movie' ? 'bg-blue-500/20 text-blue-400' : 'bg-purple-500/20 text-purple-400' }}">
                        {{ $media->type === 'movie' ? 'Film' : 'Série' }}
                    </span>
                </div>
            @empty
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-film text-4xl text-gray-600 mb-2"></i>
                    <p>Aucun média</p>
                </div>
            @endforelse
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    // Media Chart
    const mediaCtx = document.getElementById('mediaChart');
    if (mediaCtx) {
        new Chart(mediaCtx, {
            type: 'line',
            data: {
                labels: @json($chartData['media']['labels'] ?? []),
                datasets: [
                    {
                        label: 'Films',
                        data: @json($chartData['media']['movies'] ?? []),
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Séries',
                        data: @json($chartData['media']['series'] ?? []),
                        borderColor: 'rgb(168, 85, 247)',
                        backgroundColor: 'rgba(168, 85, 247, 0.1)',
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: '#a1a1aa'
                        },
                        grid: {
                            color: 'rgba(161, 161, 170, 0.1)'
                        }
                    },
                    x: {
                        ticks: {
                            color: '#a1a1aa'
                        },
                        grid: {
                            color: 'rgba(161, 161, 170, 0.1)'
                        }
                    }
                }
            }
        });
    }

    // Users Chart
    const usersCtx = document.getElementById('usersChart');
    if (usersCtx) {
        new Chart(usersCtx, {
            type: 'bar',
            data: {
                labels: @json($chartData['users']['labels'] ?? []),
                datasets: [{
                    label: 'Utilisateurs',
                    data: @json($chartData['users']['data'] ?? []),
                    backgroundColor: 'rgba(34, 197, 94, 0.8)',
                    borderColor: 'rgb(34, 197, 94)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: '#a1a1aa'
                        },
                        grid: {
                            color: 'rgba(161, 161, 170, 0.1)'
                        }
                    },
                    x: {
                        ticks: {
                            color: '#a1a1aa'
                        },
                        grid: {
                            color: 'rgba(161, 161, 170, 0.1)'
                        }
                    }
                }
            }
        });
    }
</script>
@endpush
