@extends('admin.layouts.app')

@section('title', $medium->title . ' - ABBEV')
@section('header', $medium->title)

@section('content')
<div class="space-y-6">
    <!-- Hero Banner -->
    @if($medium->banner_path)
    <div class="relative w-full h-96 rounded-xl overflow-hidden shadow-2xl">
        <img src="{{ str_starts_with($medium->banner_path, 'http') ? $medium->banner_path : asset('storage/' . $medium->banner_path) }}"
             alt="{{ $medium->title }}"
             class="w-full h-full object-cover"
             loading="lazy">

        <!-- Gradient Overlay -->
        <div class="absolute inset-0 bg-gradient-to-t from-dark-bg via-dark-bg/60 to-transparent"></div>

        <!-- Quick Info -->
        <div class="absolute bottom-6 left-6 right-6">
            <div class="flex items-center gap-3 mb-3">
                @if($medium->is_featured)
                    <span class="bg-gradient-to-r from-yellow-500 to-orange-500 text-white text-sm px-4 py-1.5 rounded-full font-medium shadow-lg">
                        <i class="fas fa-star"></i> En Vedette
                    </span>
                @endif
                <span class="bg-primary-500 text-white text-sm px-4 py-1.5 rounded-full font-medium shadow-lg">
                    {{ $medium->type === 'movie' ? 'Film' : 'Série' }}
                </span>
                @if($medium->release_year)
                    <span class="bg-dark-100/80 backdrop-blur-sm text-white text-sm px-4 py-1.5 rounded-full font-medium">
                        {{ $medium->release_year }}
                    </span>
                @endif
            </div>

            <h1 class="text-4xl font-bold text-white mb-2">{{ $medium->title }}</h1>

            <div class="flex items-center gap-4 text-gray-300">
                <span class="flex items-center gap-2">
                    <i class="fas fa-folder text-primary-400"></i>
                    {{ $medium->category->name ?? 'Non catégorisé' }}
                </span>
                @if($medium->duration)
                    <span>•</span>
                    <span class="flex items-center gap-2">
                        <i class="fas fa-clock text-primary-400"></i>
                        {{ gmdate('H:i:s', $medium->duration) }}
                    </span>
                @endif
                @if($medium->type === 'series')
                    <span>•</span>
                    <span class="flex items-center gap-2">
                        <i class="fas fa-list text-primary-400"></i>
                        {{ $seasons->count() }} {{ $seasons->count() > 1 ? 'Saisons' : 'Saison' }}
                    </span>
                @endif
                <span>•</span>
                <span class="flex items-center gap-2">
                    <i class="fas fa-eye text-primary-400"></i>
                    {{ number_format($medium->views_count ?? 0) }} vues
                </span>
            </div>
        </div>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Video Player -->
            @php $bunnyEmbed = $medium->bunnyEmbedUrl(); @endphp
            @if($bunnyEmbed && $medium->type === 'movie')
            <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 overflow-hidden">
                <div class="aspect-video bg-black">
                    <iframe src="{{ $bunnyEmbed }}"
                            loading="lazy"
                            class="w-full h-full border-0"
                            allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture"
                            allowfullscreen></iframe>
                </div>
            </div>
            @elseif($medium->type === 'series')
            @php
                $libraryId = config('services.bunny.library_id');
                $firstEpisode = $seasons->flatMap->episodes->first(fn($e) => $e->video_provider === 'bunny' && $e->video_id);
                $firstLocalEp = $seasons->flatMap->episodes->first(fn($e) => $e->video_provider === 'local' && $e->video_path);
                $firstEpUrl = $firstEpisode && $libraryId
                    ? "https://iframe.mediadelivery.net/embed/{$libraryId}/{$firstEpisode->video_id}"
                    : null;
            @endphp
            <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 overflow-hidden"
                 x-data="{ playerUrl: @js($firstEpUrl), playingEp: {{ $firstEpisode?->id ?? 'null' }} }">
                <div class="aspect-video bg-black">
                    <template x-if="playerUrl">
                        <iframe :src="playerUrl"
                                loading="lazy"
                                class="w-full h-full border-0"
                                allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture"
                                allowfullscreen></iframe>
                    </template>
                    <template x-if="!playerUrl">
                        @if($firstLocalEp)
                            <video controls preload="metadata" class="w-full h-full"
                                   src="{{ asset('storage/' . ltrim($firstLocalEp->video_path, '/')) }}"></video>
                        @else
                            <div class="w-full h-full flex flex-col items-center justify-center text-gray-400">
                                <i class="fas fa-tv text-6xl mb-3 text-primary-400"></i>
                                <p class="text-lg">Aucun épisode avec vidéo.</p>
                                <p class="text-sm text-gray-500">Ajoute des épisodes pour pouvoir les lire ici.</p>
                            </div>
                        @endif
                    </template>
                </div>
            </div>
            @endif

            @if($medium->type === 'series')
            <!-- Saisons & Épisodes -->
            <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-2xl font-bold text-white flex items-center gap-2">
                        <i class="fas fa-list-ol text-primary-400"></i>
                        Saisons & Épisodes
                    </h2>
                    <a href="{{ route('episodes.index', $medium) }}"
                       class="text-primary-400 hover:text-primary-300 text-sm">
                        <i class="fas fa-cog mr-1"></i> Gérer
                    </a>
                </div>

                @if($seasons->count() > 0)
                <div x-data="{ activeSeason: {{ $seasons->first()->id }} }" class="space-y-4">
                    <!-- Tabs Saisons -->
                    <div class="flex flex-wrap gap-2 border-b border-dark-200 pb-3">
                        @foreach($seasons as $season)
                            <button type="button"
                                    @click="activeSeason = {{ $season->id }}"
                                    :class="activeSeason === {{ $season->id }} ? 'bg-primary-500 text-white' : 'bg-dark-50 text-gray-400 hover:text-white'"
                                    class="px-4 py-2 rounded-lg text-sm font-medium transition-all">
                                S{{ $season->season_number }}@if($season->title) — {{ $season->title }}@endif
                                <span class="ml-1 text-xs opacity-70">({{ $season->episodes->count() }})</span>
                            </button>
                        @endforeach
                    </div>

                    <!-- Contenu des saisons -->
                    @foreach($seasons as $season)
                        <div x-show="activeSeason === {{ $season->id }}" x-cloak class="space-y-2">
                            @if($season->description)
                                <p class="text-gray-400 text-sm italic mb-3">{{ $season->description }}</p>
                            @endif

                            @if($season->episodes->count() > 0)
                                <div class="space-y-2">
                                    @foreach($season->episodes as $episode)
                                        @php
                                            $epUrl = ($episode->video_provider === 'bunny' && $episode->video_id && $libraryId)
                                                ? "https://iframe.mediadelivery.net/embed/{$libraryId}/{$episode->video_id}"
                                                : null;
                                            $epLocalUrl = ($episode->video_provider === 'local' && $episode->video_path)
                                                ? asset('storage/' . ltrim($episode->video_path, '/'))
                                                : null;
                                        @endphp
                                        <div :class="playingEp === {{ $episode->id }} ? 'border-primary-500 bg-primary-500/10' : 'border-dark-200 bg-dark-50'"
                                             class="border rounded-lg p-3 transition-all flex items-center gap-3">
                                            @if($episode->thumbnail_path)
                                                <img src="{{ str_starts_with($episode->thumbnail_path, 'http') ? $episode->thumbnail_path : asset('storage/'.$episode->thumbnail_path) }}"
                                                     alt="" class="w-24 h-14 rounded object-cover flex-shrink-0 bg-dark-300">
                                            @else
                                                <div class="w-24 h-14 rounded bg-dark-300 flex-shrink-0 flex items-center justify-center text-gray-500">
                                                    <i class="fas fa-film"></i>
                                                </div>
                                            @endif

                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <span class="bg-primary-500 text-white text-xs px-2 py-0.5 rounded">Ep {{ $episode->episode_number }}</span>
                                                    @if($episode->duration)
                                                        <span class="text-gray-500 text-xs">
                                                            <i class="fas fa-clock mr-1"></i>{{ gmdate('H:i:s', $episode->duration) }}
                                                        </span>
                                                    @endif
                                                </div>
                                                <h4 class="text-white font-medium truncate">{{ $episode->title }}</h4>
                                                @if($episode->description)
                                                    <p class="text-gray-400 text-xs line-clamp-1">{{ $episode->description }}</p>
                                                @endif
                                            </div>

                                            <div class="flex items-center gap-2">
                                                @if($epUrl)
                                                    <button type="button"
                                                            @click="playerUrl = @js($epUrl); playingEp = {{ $episode->id }}; window.scrollTo({top: 0, behavior: 'smooth'})"
                                                            class="bg-primary-500 hover:bg-primary-600 text-white px-3 py-2 rounded-lg text-sm">
                                                        <i class="fas fa-play"></i>
                                                    </button>
                                                @elseif($epLocalUrl)
                                                    <a href="{{ $epLocalUrl }}" target="_blank"
                                                       class="bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded-lg text-sm"
                                                       title="Lire la vidéo locale (test)">
                                                        <i class="fas fa-play"></i>
                                                    </a>
                                                @else
                                                    <span class="text-gray-600 text-xs px-3 py-2" title="Pas de vidéo">
                                                        <i class="fas fa-video-slash"></i>
                                                    </span>
                                                @endif
                                                <a href="{{ route('episodes.edit', $episode) }}"
                                                   class="bg-dark-200 hover:bg-dark-300 text-gray-300 hover:text-white px-3 py-2 rounded-lg text-sm"
                                                   title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center py-8 border border-dashed border-dark-300 rounded-lg">
                                    <i class="fas fa-video text-gray-600 text-3xl mb-2"></i>
                                    <p class="text-gray-400 text-sm">Aucun épisode dans cette saison</p>
                                    <a href="{{ route('episodes.create', $season) }}"
                                       class="inline-block mt-2 text-primary-400 hover:text-primary-300 text-sm">
                                        <i class="fas fa-plus mr-1"></i> Ajouter un épisode
                                    </a>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
                @else
                <div class="text-center py-12 border border-dashed border-dark-300 rounded-lg">
                    <i class="fas fa-film text-gray-600 text-5xl mb-3"></i>
                    <h3 class="text-white font-semibold mb-1">Aucune saison créée</h3>
                    <p class="text-gray-400 text-sm mb-4">Cette série n'a pas encore de saisons.</p>
                    <a href="{{ route('episodes.index', $medium) }}"
                       class="inline-block bg-gradient-to-r from-primary-500 to-primary-600 hover:shadow-lg text-white px-6 py-2 rounded-lg font-medium">
                        <i class="fas fa-plus mr-2"></i> Créer la première saison
                    </a>
                </div>
                @endif
            </div>
            @endif

            @php $hasLocalMovie = $medium->type === 'movie' && $medium->video_provider === 'local' && $medium->video_path; @endphp
            @if($hasLocalMovie)
            <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 overflow-hidden">
                <div class="aspect-video bg-black">
                    <video controls preload="metadata" class="w-full h-full"
                           src="{{ asset('storage/' . ltrim($medium->video_path, '/')) }}"></video>
                </div>
                <div class="px-4 py-2 text-xs text-yellow-300/90 bg-yellow-500/5 border-t border-dark-200">
                    <i class="fas fa-clapperboard mr-1"></i>
                    Lecture locale (fallback de test) — cette vidéo n'est pas encore sur Bunny.
                </div>
            </div>
            @endif

            @if($medium->type === 'movie' && !$bunnyEmbed && !$hasLocalMovie)
            <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-8">
                <div class="text-center text-gray-400">
                    <i class="fas fa-video-slash text-6xl mb-4"></i>
                    <p class="text-xl">Aucune vidéo attribuée à ce film.</p>
                    <a href="{{ route('media.edit', $medium) }}"
                       class="inline-block mt-4 bg-primary-500 hover:bg-primary-600 text-white px-6 py-3 rounded-lg transition">
                        Attribuer une vidéo
                    </a>
                </div>
            </div>
            @endif

            <!-- Description -->
            @if($medium->description)
            <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
                <h2 class="text-2xl font-bold text-white mb-4 flex items-center gap-2">
                    <i class="fas fa-info-circle text-primary-400"></i>
                    Synopsis
                </h2>
                <p class="text-gray-300 leading-relaxed text-lg">{{ $medium->description }}</p>
            </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Poster -->
            @if($medium->cover_path || $medium->thumbnail_path)
            <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 overflow-hidden">
                <img src="{{ str_starts_with($medium->cover_path ?? $medium->thumbnail_path, 'http') ? ($medium->cover_path ?? $medium->thumbnail_path) : asset('storage/' . ($medium->cover_path ?? $medium->thumbnail_path)) }}"
                     alt="{{ $medium->title }}"
                     class="w-full h-auto"
                     loading="lazy">
            </div>
            @endif

            <!-- Details -->
            <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
                <h2 class="text-xl font-bold text-white mb-4 flex items-center gap-2">
                    <i class="fas fa-list text-primary-400"></i>
                    Détails
                </h2>

                <div class="space-y-3">
                    <div class="flex justify-between items-start pb-3 border-b border-dark-200">
                        <span class="text-gray-400">Type</span>
                        <span class="text-white font-medium">{{ $medium->type === 'movie' ? 'Film' : 'Série' }}</span>
                    </div>

                    @if($medium->release_year)
                    <div class="flex justify-between items-start pb-3 border-b border-dark-200">
                        <span class="text-gray-400">Année</span>
                        <span class="text-white font-medium">{{ $medium->release_year }}</span>
                    </div>
                    @endif

                    @if($medium->duration)
                    <div class="flex justify-between items-start pb-3 border-b border-dark-200">
                        <span class="text-gray-400">Durée</span>
                        <span class="text-white font-medium">{{ gmdate('H:i:s', $medium->duration) }}</span>
                    </div>
                    @endif

                    @if($medium->type === 'series')
                    <div class="flex justify-between items-start pb-3 border-b border-dark-200">
                        <span class="text-gray-400">Saisons</span>
                        <span class="text-white font-medium">{{ $seasons->count() }}</span>
                    </div>
                    <div class="flex justify-between items-start pb-3 border-b border-dark-200">
                        <span class="text-gray-400">Épisodes</span>
                        <span class="text-white font-medium">{{ $seasons->sum(fn($s) => $s->episodes->count()) }}</span>
                    </div>
                    @endif

                    <div class="flex justify-between items-start pb-3 border-b border-dark-200">
                        <span class="text-gray-400">Catégorie</span>
                        <span class="text-primary-400 font-medium">{{ $medium->category->name ?? 'Non catégorisé' }}</span>
                    </div>

                    <div class="flex justify-between items-start pb-3 border-b border-dark-200">
                        <span class="text-gray-400">Vues</span>
                        <span class="text-white font-medium">{{ number_format($medium->views_count ?? 0) }}</span>
                    </div>

                    @if($medium->published_at)
                    <div class="flex justify-between items-start">
                        <span class="text-gray-400">Publié le</span>
                        <span class="text-white font-medium">{{ $medium->published_at->format('d/m/Y') }}</span>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Actions -->
            <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
                <h2 class="text-xl font-bold text-white mb-4 flex items-center gap-2">
                    <i class="fas fa-cog text-primary-400"></i>
                    Actions
                </h2>

                <div class="space-y-3">
                    <a href="{{ route('media.edit', $medium) }}"
                       class="w-full bg-primary-500 hover:bg-primary-600 text-white px-4 py-3 rounded-lg font-medium transition-all duration-300 flex items-center justify-center gap-2">
                        <i class="fas fa-edit"></i>
                        Modifier
                    </a>

                    <a href="{{ route('media.index') }}"
                       class="w-full bg-dark-200 hover:bg-dark-300 text-white px-4 py-3 rounded-lg font-medium transition-all duration-300 flex items-center justify-center gap-2">
                        <i class="fas fa-arrow-left"></i>
                        Retour à la liste
                    </a>

                    <form action="{{ route('media.destroy', $medium) }}" method="POST"
                          onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce média ?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="w-full bg-red-500/20 hover:bg-red-500 text-red-400 hover:text-white px-4 py-3 rounded-lg font-medium transition-all duration-300 flex items-center justify-center gap-2">
                            <i class="fas fa-trash"></i>
                            Supprimer
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
