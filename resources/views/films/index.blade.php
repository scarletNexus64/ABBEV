@extends('admin.layouts.app')

@section('title', 'Films')
@section('header', 'Catalogue de Films')

@section('content')
<div class="mb-6 flex justify-between items-center">
    <div>
        <h2 class="text-2xl font-bold text-white mb-2">Films</h2>
        <p class="text-gray-400">Explorez notre collection de films</p>
    </div>
    <a href="{{ route('media.create', ['type' => 'movie']) }}"
       class="bg-gradient-to-r from-primary-500 to-primary-600 hover:shadow-lg hover:shadow-primary-500/50 text-white px-6 py-3 rounded-lg font-medium transition-all duration-300 flex items-center gap-2">
        <i class="fas fa-plus"></i>
        Ajouter un film
    </a>
</div>

<!-- Stats -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-400">Total Films</p>
                <p class="text-2xl font-bold text-white mt-1">{{ $films->total() }}</p>
            </div>
            <div class="w-12 h-12 bg-primary-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-film text-xl text-primary-400"></i>
            </div>
        </div>
    </div>

    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-400">Vues Totales</p>
                <p class="text-2xl font-bold text-white mt-1">{{ number_format($films->sum('views_count')) }}</p>
            </div>
            <div class="w-12 h-12 bg-blue-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-eye text-xl text-blue-400"></i>
            </div>
        </div>
    </div>

    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-400">En Vedette</p>
                <p class="text-2xl font-bold text-white mt-1">{{ $films->where('is_featured', true)->count() }}</p>
            </div>
            <div class="w-12 h-12 bg-yellow-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-star text-xl text-yellow-400"></i>
            </div>
        </div>
    </div>

    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-400">Durée Moyenne</p>
                <p class="text-2xl font-bold text-white mt-1">{{ gmdate('H:i', $films->avg('duration') ?? 0) }}</p>
            </div>
            <div class="w-12 h-12 bg-green-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-clock text-xl text-green-400"></i>
            </div>
        </div>
    </div>
</div>

<!-- Films Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
    @forelse($films as $film)
        <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 overflow-hidden hover:border-primary-500/50 transition-all duration-300 group">
            <!-- Thumbnail -->
            <div class="relative aspect-[2/3] bg-dark-200">
                @if($film->cover_path || $film->thumbnail_path)
                    <img src="{{ str_starts_with($film->cover_path ?? $film->thumbnail_path, 'http') ? ($film->cover_path ?? $film->thumbnail_path) : asset('storage/' . ($film->cover_path ?? $film->thumbnail_path)) }}"
                         alt="{{ $film->title }}"
                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                         loading="lazy"
                         onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'400\' height=\'600\' viewBox=\'0 0 400 600\'%3E%3Crect fill=\'%231a1a2e\' width=\'400\' height=\'600\'/%3E%3Ctext x=\'50%25\' y=\'50%25\' fill=\'%2306b6d4\' font-size=\'80\' text-anchor=\'middle\' dominant-baseline=\'middle\' font-family=\'Arial\'%3E🎬%3C/text%3E%3C/svg%3E';">
                @else
                    <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-dark-200 to-dark-300">
                        <i class="fas fa-film text-6xl text-primary-400/50"></i>
                    </div>
                @endif

                <!-- Overlay -->
                <div class="absolute inset-0 bg-gradient-to-t from-black via-transparent to-transparent opacity-100"></div>

                <!-- Info overlay -->
                <div class="absolute inset-0 bg-black/80 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center">
                    <div class="text-center p-4">
                        <a href="{{ route('media.show', $film) }}" class="bg-primary-500 hover:bg-primary-600 text-white w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-3 transition">
                            <i class="fas fa-play text-xl"></i>
                        </a>
                        <p class="text-white text-sm">{{ gmdate('H:i:s', $film->duration ?? 0) }}</p>
                    </div>
                </div>

                <!-- Badges -->
                @if($film->is_featured)
                    <div class="absolute top-2 right-2">
                        <span class="bg-gradient-to-r from-yellow-500 to-orange-500 text-white text-xs px-3 py-1 rounded-full font-medium shadow-lg">
                            <i class="fas fa-star"></i> Vedette
                        </span>
                    </div>
                @endif

                <!-- Bottom info -->
                <div class="absolute bottom-0 left-0 right-0 p-4">
                    <h3 class="text-white font-bold text-lg mb-1 line-clamp-2">{{ $film->title }}</h3>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-primary-400 font-medium">{{ $film->category->name ?? 'Non catégorisé' }}</span>
                        <span class="text-gray-300">
                            <i class="fas fa-eye"></i> {{ number_format($film->views_count ?? 0) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-span-4 bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-12 text-center">
            <div class="w-20 h-20 bg-primary-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-film text-4xl text-primary-400"></i>
            </div>
            <h3 class="text-xl font-semibold text-white mb-2">Aucun film trouvé</h3>
            <p class="text-gray-400 mb-6">Commencez par ajouter des films à votre collection</p>
            <a href="{{ route('media.create', ['type' => 'movie']) }}"
               class="inline-flex items-center gap-2 bg-gradient-to-r from-primary-500 to-primary-600 hover:shadow-lg hover:shadow-primary-500/50 text-white px-6 py-3 rounded-lg font-medium transition-all duration-300">
                <i class="fas fa-plus"></i>
                Ajouter un film
            </a>
        </div>
    @endforelse
</div>

<!-- Pagination -->
@if($films->hasPages())
    <div class="mt-8">
        {{ $films->links() }}
    </div>
@endif
@endsection
