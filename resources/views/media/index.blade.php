@extends('admin.layouts.app')

@section('title', 'Médias')
@section('header', 'Gestion des Médias')

@section('content')
<div class="mb-6 flex justify-between items-center">
    <div>
        <h2 class="text-2xl font-bold text-white mb-2">Tous les Médias</h2>
        <p class="text-gray-400">Films, séries et contenus de votre plateforme</p>
    </div>
    <a href="{{ route('media.create') }}"
       class="bg-gradient-to-r from-primary-500 to-primary-600 hover:shadow-lg hover:shadow-primary-500/50 text-white px-6 py-3 rounded-lg font-medium transition-all duration-300 flex items-center gap-2">
        <i class="fas fa-plus"></i>
        Ajouter un média
    </a>
</div>

<!-- Filters -->
<div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6 mb-8">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-400 mb-2">Type</label>
            <select class="w-full bg-dark-50 border border-dark-200 text-white rounded-lg px-4 py-2 focus:outline-none focus:border-primary-500">
                <option>Tous les types</option>
                <option>Films</option>
                <option>Séries</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-400 mb-2">Catégorie</label>
            <select class="w-full bg-dark-50 border border-dark-200 text-white rounded-lg px-4 py-2 focus:outline-none focus:border-primary-500">
                <option>Toutes les catégories</option>
                <option>Action</option>
                <option>Comédie</option>
                <option>Drame</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-400 mb-2">Statut</label>
            <select class="w-full bg-dark-50 border border-dark-200 text-white rounded-lg px-4 py-2 focus:outline-none focus:border-primary-500">
                <option>Tous</option>
                <option>En vedette</option>
                <option>Standard</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-400 mb-2">Recherche</label>
            <input type="text" placeholder="Rechercher..." class="w-full bg-dark-50 border border-dark-200 text-white rounded-lg px-4 py-2 focus:outline-none focus:border-primary-500">
        </div>
    </div>
</div>

<!-- Media Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
    @forelse($media as $item)
        <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 overflow-hidden hover:border-primary-500/50 transition-all duration-300 group">
            <!-- Thumbnail -->
            <div class="relative aspect-video bg-dark-200">
                @if($item->thumbnail_path)
                    <img src="{{ str_starts_with($item->thumbnail_path, 'http') ? $item->thumbnail_path : asset('storage/' . $item->thumbnail_path) }}"
                         alt="{{ $item->title }}"
                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                         loading="lazy"
                         onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'400\' height=\'600\' viewBox=\'0 0 400 600\'%3E%3Crect fill=\'%231a1a2e\' width=\'400\' height=\'600\'/%3E%3Ctext x=\'50%25\' y=\'50%25\' fill=\'%2306b6d4\' font-size=\'80\' text-anchor=\'middle\' dominant-baseline=\'middle\' font-family=\'Arial\'%3E📽️%3C/text%3E%3C/svg%3E';">
                @else
                    <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-dark-100 to-dark-200">
                        <i class="fas fa-film text-6xl text-primary-400/50"></i>
                    </div>
                @endif

                <!-- Overlay on hover -->
                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center">
                    <div class="flex gap-2">
                        <a href="{{ route('media.show', $item) }}"
                           class="bg-primary-500 hover:bg-primary-600 text-white w-10 h-10 rounded-full flex items-center justify-center transition">
                            <i class="fas fa-play"></i>
                        </a>
                        <a href="{{ route('media.edit', $item) }}"
                           class="bg-white/20 hover:bg-white/30 text-white w-10 h-10 rounded-full flex items-center justify-center transition">
                            <i class="fas fa-edit"></i>
                        </a>
                    </div>
                </div>

                <!-- Badges -->
                <div class="absolute top-2 right-2 flex flex-col gap-2">
                    @if($item->is_featured)
                        <span class="bg-gradient-to-r from-yellow-500 to-orange-500 text-white text-xs px-3 py-1 rounded-full font-medium shadow-lg">
                            <i class="fas fa-star"></i> Vedette
                        </span>
                    @endif
                    <span class="bg-primary-500 text-white text-xs px-3 py-1 rounded-full font-medium shadow-lg">
                        {{ $item->type === 'movie' ? 'Film' : 'Série' }}
                    </span>
                </div>

                <!-- Duration -->
                <div class="absolute bottom-2 left-2">
                    <span class="bg-black/70 backdrop-blur-sm text-white text-xs px-2 py-1 rounded">
                        <i class="fas fa-clock"></i> {{ gmdate('H:i:s', $item->duration ?? 0) }}
                    </span>
                </div>
            </div>

            <!-- Content -->
            <div class="p-4">
                <h3 class="text-lg font-semibold text-white mb-1 truncate group-hover:text-primary-400 transition">
                    {{ $item->title }}
                </h3>

                <div class="flex items-center gap-2 mb-3">
                    <span class="text-primary-400 text-sm font-medium">{{ $item->category->name ?? 'Non catégorisé' }}</span>
                    <span class="text-gray-500 text-xs">•</span>
                    <span class="text-gray-400 text-sm">
                        <i class="fas fa-eye"></i> {{ number_format($item->views_count ?? 0) }}
                    </span>
                </div>

                @if($item->description)
                    <p class="text-gray-400 text-sm mb-4 line-clamp-2">{{ $item->description }}</p>
                @endif

                <!-- Actions -->
                @if($item->type === 'series')
                    <!-- Actions pour les Séries -->
                    <div class="flex flex-col gap-2 pt-4 border-t border-dark-200">
                        <a href="{{ route('episodes.index', $item) }}"
                           class="bg-gradient-to-r from-purple-500 to-pink-500 hover:shadow-lg text-white px-3 py-2 rounded-lg text-center text-sm transition-all duration-300 font-medium">
                            <i class="fas fa-list mr-1"></i> Gérer les Épisodes
                        </a>
                        <div class="flex gap-2">
                            <a href="{{ route('media.edit', $item) }}"
                               class="flex-1 bg-primary-500/20 hover:bg-primary-500 text-primary-400 hover:text-white px-3 py-2 rounded-lg text-center text-sm transition-all duration-300 font-medium">
                                Modifier
                            </a>
                            <form action="{{ route('media.destroy', $item) }}" method="POST" class="flex-1"
                                  data-confirm="Supprimer cette série ? Cette action est irréversible."
                                  data-confirm-type="danger" data-confirm-title="Supprimer la série" data-confirm-confirm="Supprimer">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="w-full bg-red-500/20 hover:bg-red-500 text-red-400 hover:text-white px-3 py-2 rounded-lg text-sm transition-all duration-300 font-medium">
                                    Supprimer
                                </button>
                            </form>
                        </div>
                    </div>
                @else
                    <!-- Actions pour les Films -->
                    <div class="flex gap-2 pt-4 border-t border-dark-200">
                        <a href="{{ route('media.edit', $item) }}"
                           class="flex-1 bg-primary-500/20 hover:bg-primary-500 text-primary-400 hover:text-white px-3 py-2 rounded-lg text-center text-sm transition-all duration-300 font-medium">
                            Modifier
                        </a>
                        <form action="{{ route('media.destroy', $item) }}" method="POST" class="flex-1"
                              data-confirm="Supprimer ce film ? Cette action est irréversible."
                              data-confirm-type="danger" data-confirm-title="Supprimer le film" data-confirm-confirm="Supprimer">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="w-full bg-red-500/20 hover:bg-red-500 text-red-400 hover:text-white px-3 py-2 rounded-lg text-sm transition-all duration-300 font-medium">
                                Supprimer
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="col-span-4 bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-12 text-center">
            <div class="w-20 h-20 bg-primary-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-film text-4xl text-primary-400"></i>
            </div>
            <h3 class="text-xl font-semibold text-white mb-2">Aucun média trouvé</h3>
            <p class="text-gray-400 mb-6">Commencez par ajouter votre premier média</p>
            <a href="{{ route('media.create') }}"
               class="inline-flex items-center gap-2 bg-gradient-to-r from-primary-500 to-primary-600 hover:shadow-lg hover:shadow-primary-500/50 text-white px-6 py-3 rounded-lg font-medium transition-all duration-300">
                <i class="fas fa-plus"></i>
                Ajouter le premier média
            </a>
        </div>
    @endforelse
</div>

<!-- Pagination -->
@if($media->hasPages())
    <div class="mt-8">
        {{ $media->links() }}
    </div>
@endif
@endsection
