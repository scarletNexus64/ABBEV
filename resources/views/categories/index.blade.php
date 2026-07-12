@extends('admin.layouts.app')

@section('title', 'Catégories')
@section('header', 'Gestion des Catégories')

@section('content')
<div class="mb-6 flex justify-between items-center">
    <div>
        <h2 class="text-2xl font-bold text-white mb-2">Toutes les Catégories</h2>
        <p class="text-gray-400">Gérez les catégories de votre plateforme ABBEV</p>
    </div>
    <a href="{{ route('categories.create') }}"
       class="bg-gradient-to-r from-primary-500 to-primary-600 hover:shadow-lg hover:shadow-primary-500/50 text-white px-6 py-3 rounded-lg font-medium transition-all duration-300 flex items-center gap-2">
        <i class="fas fa-plus"></i>
        Nouvelle catégorie
    </a>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-400">Total Catégories</p>
                <p class="text-2xl font-bold text-white mt-1">{{ $categories->total() }}</p>
            </div>
            <div class="w-12 h-12 bg-primary-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-th-large text-xl text-primary-400"></i>
            </div>
        </div>
    </div>

    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-400">Films & Séries</p>
                <p class="text-2xl font-bold text-white mt-1">{{ $categories->where('slug', 'films')->count() + $categories->where('slug', 'series')->count() }}</p>
            </div>
            <div class="w-12 h-12 bg-blue-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-film text-xl text-blue-400"></i>
            </div>
        </div>
    </div>

    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-400">Cours en Ligne</p>
                <p class="text-2xl font-bold text-white mt-1">{{ $categories->filter(function($cat) { return str_starts_with($cat->slug, 'cours-'); })->count() }}</p>
            </div>
            <div class="w-12 h-12 bg-green-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-graduation-cap text-xl text-green-400"></i>
            </div>
        </div>
    </div>

    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-400">Lions Head Awards</p>
                <p class="text-2xl font-bold text-white mt-1">{{ $categories->filter(function($cat) { return str_starts_with($cat->slug, 'award-'); })->count() }}</p>
            </div>
            <div class="w-12 h-12 bg-yellow-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-trophy text-xl text-yellow-400"></i>
            </div>
        </div>
    </div>
</div>

<!-- Categories Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    @forelse($categories as $category)
        <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6 hover:border-primary-500/50 transition-all duration-300 group">
            <div class="flex items-start justify-between mb-4">
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-12 h-12 bg-gradient-to-br from-primary-500 to-primary-600 rounded-lg flex items-center justify-center shadow-lg shadow-primary-500/30">
                            <i class="fas fa-{{
                                str_contains($category->slug, 'film') ? 'film' :
                                (str_contains($category->slug, 'serie') ? 'tv' :
                                (str_contains($category->slug, 'cours') ? 'graduation-cap' :
                                (str_contains($category->slug, 'award') ? 'trophy' :
                                (str_contains($category->slug, 'reservation') ? 'ticket-alt' :
                                (str_contains($category->slug, 'financement') ? 'hand-holding-usd' : 'th-large')))))
                            }} text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-white">{{ $category->name }}</h3>
                            <p class="text-sm text-gray-400">{{ $category->media_count ?? 0 }} média(s)</p>
                        </div>
                    </div>
                </div>
            </div>

            @if($category->description)
                <p class="text-gray-300 text-sm mb-4 line-clamp-2">{{ $category->description }}</p>
            @endif

            <div class="flex items-center justify-between pt-4 border-t border-dark-200">
                <span class="text-xs text-gray-500">
                    <i class="fas fa-hashtag"></i> {{ $category->slug }}
                </span>
                <div class="flex gap-2">
                    <a href="{{ route('categories.edit', $category) }}"
                       class="bg-primary-500/20 hover:bg-primary-500 text-primary-400 hover:text-white px-3 py-2 rounded-lg text-sm transition-all duration-300">
                        <i class="fas fa-edit"></i>
                    </a>
                    <form action="{{ route('categories.destroy', $category) }}" method="POST" class="inline"
                          data-confirm="Supprimer cette catégorie ?"
                          data-confirm-type="danger" data-confirm-title="Supprimer la catégorie" data-confirm-confirm="Supprimer">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="bg-red-500/20 hover:bg-red-500 text-red-400 hover:text-white px-3 py-2 rounded-lg text-sm transition-all duration-300">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @empty
        <div class="col-span-3 bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-12 text-center">
            <div class="w-20 h-20 bg-primary-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-th-large text-4xl text-primary-400"></i>
            </div>
            <h3 class="text-xl font-semibold text-white mb-2">Aucune catégorie trouvée</h3>
            <p class="text-gray-400 mb-6">Commencez par créer votre première catégorie</p>
            <a href="{{ route('categories.create') }}"
               class="inline-flex items-center gap-2 bg-gradient-to-r from-primary-500 to-primary-600 hover:shadow-lg hover:shadow-primary-500/50 text-white px-6 py-3 rounded-lg font-medium transition-all duration-300">
                <i class="fas fa-plus"></i>
                Créer la première catégorie
            </a>
        </div>
    @endforelse
</div>

<!-- Pagination -->
@if($categories->hasPages())
    <div class="mt-8">
        {{ $categories->links() }}
    </div>
@endif
@endsection
