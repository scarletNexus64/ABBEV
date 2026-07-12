@extends('admin.layouts.app')

@section('title', 'Gestion des Épisodes - ' . $media->title)
@section('header', 'Gestion des Saisons et Épisodes')

@section('content')
<div class="max-w-7xl mx-auto" x-data="{ openSeasonModal: false, selectedSeason: null }">
    <!-- Header with Back Button -->
    <div class="mb-6 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('media.index') }}" class="bg-dark-200 hover:bg-dark-300 text-white px-4 py-2 rounded-lg transition-all">
                <i class="fas fa-arrow-left mr-2"></i> Retour
            </a>
            <div>
                <h2 class="text-2xl font-bold text-white">{{ $media->title }}</h2>
                <p class="text-gray-400">Type: Série • {{ $seasons->count() }} saison(s)</p>
            </div>
        </div>

        <!-- Add Season Button -->
        <button @click="openSeasonModal = true"
                class="bg-gradient-to-r from-primary-500 to-primary-600 hover:shadow-lg hover:shadow-primary-500/50 text-white px-6 py-3 rounded-lg font-medium transition-all">
            <i class="fas fa-plus mr-2"></i>
            Ajouter une Saison
        </button>
    </div>

    <!-- Seasons List -->
    @if($seasons->count() > 0)
        <div class="space-y-6">
            @foreach($seasons as $season)
                <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
                    <!-- Season Header -->
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-primary-500 to-primary-600 rounded-xl flex items-center justify-center">
                                <span class="text-white font-bold text-xl">S{{ $season->season_number }}</span>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-white">
                                    Saison {{ $season->season_number }}
                                    @if($season->title)
                                        - {{ $season->title }}
                                    @endif
                                </h3>
                                <p class="text-gray-400 text-sm">{{ $season->episodes->count() }} épisode(s)</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <a href="{{ route('episodes.create', $season) }}"
                               class="bg-primary-500 hover:bg-primary-600 text-white px-4 py-2 rounded-lg transition-all">
                                <i class="fas fa-plus mr-2"></i>
                                Ajouter un Épisode
                            </a>
                            <form action="{{ route('episodes.season.destroy', $season) }}" method="POST"
                                  data-confirm="Supprimer cette saison et tous ses épisodes ?"
                                  data-confirm-type="danger" data-confirm-title="Supprimer la saison" data-confirm-confirm="Supprimer">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition-all">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Episodes List -->
                    @if($season->episodes->count() > 0)
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($season->episodes as $episode)
                                <div class="bg-dark-50 rounded-lg p-4 border border-dark-200 hover:border-primary-500 transition-all">
                                    <div class="flex items-start justify-between mb-2">
                                        <span class="bg-primary-500 text-white text-xs px-2 py-1 rounded">
                                            Ep {{ $episode->episode_number }}
                                        </span>
                                        <div class="flex gap-1">
                                            <a href="{{ route('episodes.edit', $episode) }}"
                                               class="text-gray-400 hover:text-primary-500 transition-colors">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form action="{{ route('episodes.destroy', $episode) }}" method="POST"
                                                  data-confirm="Supprimer cet épisode ?"
                                                  data-confirm-type="danger" data-confirm-confirm="Supprimer" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-gray-400 hover:text-red-500 transition-colors">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    <h4 class="text-white font-medium mb-1">{{ $episode->title }}</h4>
                                    <p class="text-gray-400 text-sm line-clamp-2">
                                        {{ $episode->description ?? 'Pas de description' }}
                                    </p>
                                    @if($episode->duration)
                                        <p class="text-gray-500 text-xs mt-2">
                                            <i class="fas fa-clock mr-1"></i> {{ gmdate('H:i:s', $episode->duration) }}
                                        </p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8">
                            <i class="fas fa-video text-gray-600 text-4xl mb-3"></i>
                            <p class="text-gray-400">Aucun épisode pour cette saison</p>
                            <a href="{{ route('episodes.create', $season) }}"
                               class="inline-block mt-3 text-primary-400 hover:text-primary-300">
                                Ajouter le premier épisode
                            </a>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-12 text-center">
            <i class="fas fa-film text-gray-600 text-6xl mb-4"></i>
            <h3 class="text-white text-xl font-bold mb-2">Aucune saison créée</h3>
            <p class="text-gray-400 mb-6">Commencez par créer la première saison de votre série</p>
            <button @click="openSeasonModal = true"
                    class="bg-gradient-to-r from-primary-500 to-primary-600 hover:shadow-lg hover:shadow-primary-500/50 text-white px-8 py-3 rounded-lg font-medium transition-all">
                <i class="fas fa-plus mr-2"></i>
                Créer la Saison 1
            </button>
        </div>
    @endif

    <!-- Add Season Modal -->
    <div x-show="openSeasonModal"
         x-cloak
         @click.self="openSeasonModal = false"
         class="fixed inset-0 bg-black bg-opacity-75 z-50 flex items-center justify-center p-4">
        <div class="bg-dark-100 rounded-xl shadow-2xl border border-dark-200 max-w-md w-full p-6"
             @click.away="openSeasonModal = false">
            <h3 class="text-2xl font-bold text-white mb-4">Ajouter une Saison</h3>

            <form action="{{ route('episodes.season.create', $media) }}" method="POST">
                @csrf

                <div class="mb-4">
                    <label for="season_number" class="block text-sm font-medium text-gray-300 mb-2">
                        Numéro de la Saison <span class="text-primary-400">*</span>
                    </label>
                    <input type="number" name="season_number" id="season_number" min="1"
                           value="{{ $seasons->count() + 1 }}"
                           class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500"
                           required>
                </div>

                <div class="mb-4">
                    <label for="title" class="block text-sm font-medium text-gray-300 mb-2">
                        Titre (optionnel)
                    </label>
                    <input type="text" name="title" id="title"
                           class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500"
                           placeholder="Ex: Le commencement">
                </div>

                <div class="mb-4">
                    <label for="description" class="block text-sm font-medium text-gray-300 mb-2">
                        Description
                    </label>
                    <textarea name="description" id="description" rows="3"
                              class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500 resize-none"
                              placeholder="Synopsis de la saison..."></textarea>
                </div>

                <div class="mb-6">
                    <label for="release_year" class="block text-sm font-medium text-gray-300 mb-2">
                        Année de sortie
                    </label>
                    <input type="number" name="release_year" id="release_year"
                           min="1900" max="{{ date('Y') + 5 }}" value="{{ date('Y') }}"
                           class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500">
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" @click="openSeasonModal = false"
                            class="bg-dark-200 hover:bg-dark-300 text-white px-6 py-2 rounded-lg transition-all">
                        Annuler
                    </button>
                    <button type="submit"
                            class="bg-gradient-to-r from-primary-500 to-primary-600 hover:shadow-lg text-white px-6 py-2 rounded-lg transition-all">
                        <i class="fas fa-check mr-2"></i>
                        Créer la Saison
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
