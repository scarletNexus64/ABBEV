@extends('admin.layouts.app')

@section('title', 'Modifier un média - ABBEV')
@section('header', 'Modifier le média')

@push('styles')
<style>
    .bunny-card { transition: all .15s ease; }
    .bunny-card:hover { transform: translateY(-1px); }
</style>
@endpush

@section('content')
@php
    $bunny       = app(\App\Services\BunnyStreamService::class);
    $bunnyThumb  = ($medium->video_provider === 'bunny' && $medium->video_id && $bunny->isConfigured())
        ? $bunny->thumbnailUrl($medium->video_id) : null;
    $bunnyTitle  = $medium->video_metadata['title'] ?? null;
    $bunnyLength = $medium->video_metadata['length'] ?? null;

    // Jeton de la vidéo actuellement attribuée, pour pré-sélectionner le picker :
    // - Bunny  → le guid
    // - locale → "local:{id}" de l'upload correspondant (sinon le picker s'ouvre vide
    //            et un enregistrement sans re-sélection serait refusé).
    $currentVideoToken = $medium->video_id;
    if ($medium->video_provider === 'local' && $medium->video_path) {
        $lu = \App\Models\BunnyUpload::where('local_path', $medium->video_path)->first();
        $currentVideoToken = $lu ? 'local:' . $lu->id : null;
    }
@endphp

<div class="max-w-5xl mx-auto" x-data="{ type: '{{ old('type', $medium->type) }}', openSeasonModal: false }">

    <div class="mb-6 flex items-center gap-3">
        <div class="w-12 h-12 bg-gradient-to-br from-primary-500 to-primary-600 rounded-xl flex items-center justify-center">
            <i class="fas fa-edit text-white text-xl"></i>
        </div>
        <div>
            <h2 class="text-2xl font-bold text-white">{{ $medium->title }}</h2>
            <p class="text-gray-400">Édition de la fiche éditoriale.</p>
        </div>
    </div>

    <form action="{{ route('media.update', $medium) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        <!-- Type + vidéo Bunny actuelle -->
        <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
            <h3 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                <i class="fas fa-clapperboard text-primary-400"></i>
                Type & Bunny
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @php $isSeries = $medium->type === 'series'; @endphp
                {{-- Le type d'un média existant ne se change pas (film reste film) --}}
                <input type="hidden" name="type" value="{{ $medium->type }}">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Type</label>
                    <div class="p-4 bg-dark-50 border-2 border-primary-500/40 rounded-lg flex items-center gap-3">
                        <i class="fas {{ $isSeries ? 'fa-tv' : 'fa-film' }} text-2xl text-primary-400"></i>
                        <div>
                            <p class="text-white font-medium">{{ $isSeries ? 'Série' : 'Film' }}</p>
                            <p class="text-gray-500 text-xs">{{ $isSeries ? 'Vidéos rattachées aux épisodes' : 'Une vidéo Bunny = un film' }}</p>
                        </div>
                    </div>
                </div>

                <div x-show="type === 'movie'">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Vidéo Bunny <span class="text-primary-400">*</span></label>

                    <div x-data="bunnyPicker({{ json_encode(old('bunny_video_id', $currentVideoToken ?? '')) }})" x-init="init()">
                        <input type="hidden" name="bunny_video_id" :value="selected?.guid || ''">

                        <template x-if="selected">
                            <div class="flex items-center gap-3 p-3 bg-primary-500/10 border border-primary-500/40 rounded-lg mb-2">
                                <img :src="selected.thumb" class="w-20 h-12 rounded object-cover bg-dark-300" onerror="this.style.opacity=.2">
                                <div class="flex-1 min-w-0">
                                    <p class="text-white text-sm font-medium truncate" x-text="selected.title"></p>
                                    <p class="text-gray-400 text-xs font-mono truncate" x-text="selected.guid"></p>
                                </div>
                                <button type="button" @click="selected = null; refresh()" class="text-red-400 hover:text-red-300 text-sm px-2 py-1">
                                    <i class="fas fa-times"></i> Changer
                                </button>
                            </div>
                        </template>

                        <input x-show="!selected" type="text" x-model="query" @input.debounce.300ms="refresh()"
                               placeholder="🔍 Rechercher par nom — vidéos Bunny et locales…"
                               class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500" />

                        <div x-show="!selected" class="mt-2 max-h-64 overflow-y-auto bg-dark-50 border border-dark-200 rounded-lg divide-y divide-dark-200">
                            <template x-if="loading"><div class="p-4 text-gray-400 text-sm text-center"><i class="fas fa-spinner fa-spin mr-2"></i>Chargement…</div></template>
                            <template x-if="!loading && results.length === 0"><div class="p-4 text-gray-500 text-sm text-center">Aucune vidéo libre. <a href="{{ route('admin.bunny.uploads.index') }}" class="text-primary-300 underline">Uploader une vidéo</a>.</div></template>
                            <template x-for="v in results" :key="v.guid">
                                <button type="button" @click="selected = v" class="w-full text-left flex items-center gap-3 p-3 hover:bg-dark-200/50 bunny-card">
                                    <img :src="v.thumb" class="w-20 h-12 rounded object-cover bg-dark-300 flex-shrink-0" onerror="this.style.opacity=.2">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-white text-sm truncate" x-text="v.title"></p>
                                        <p class="text-gray-500 text-xs font-mono truncate" x-text="v.guid"></p>
                                    </div>
                                    <span class="text-xs text-gray-400" x-text="formatDuration(v.length)"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    @error('bunny_video_id')<p class="text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
                </div>

                <div x-show="type === 'series'" class="md:col-span-1">
                    <div class="bg-dark-50 border border-dark-200 rounded-lg p-4 text-gray-400 text-sm h-full flex flex-col justify-center">
                        <p><i class="fas fa-tv mr-2 text-primary-400"></i> Les vidéos sont attribuées aux <strong>épisodes</strong>.</p>
                        <p class="mt-1 text-gray-500 text-xs">Gère les saisons et épisodes plus bas dans cette page.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Infos éditoriales -->
        <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
            <h3 class="text-lg font-semibold text-white mb-4"><i class="fas fa-info-circle text-primary-400 mr-2"></i>Informations</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Catégorie <span class="text-primary-400">*</span></label>
                    <select name="category_id" required class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white">
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ old('category_id', $medium->category_id) == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Année</label>
                    <input type="number" name="release_year" value="{{ old('release_year', $medium->release_year) }}" min="1900" max="{{ date('Y') + 5 }}" class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Titre <span class="text-primary-400">*</span></label>
                    <input type="text" name="title" value="{{ old('title', $medium->title) }}" required class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Description</label>
                    <textarea name="description" rows="4" class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white resize-none">{{ old('description', $medium->description) }}</textarea>
                </div>
                <div x-show="type === 'movie'">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Durée (minutes)</label>
                    <input type="number" name="duration" value="{{ old('duration', $medium->duration ? (int) round($medium->duration / 60) : '') }}" min="1" class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white">
                </div>
                <div x-show="type === 'series'">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Nombre de saisons</label>
                    <input type="number" name="seasons" value="{{ old('seasons', $medium->seasons ?? 1) }}" min="1" class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white">
                </div>
            </div>
        </div>

        <!-- Visuels -->
        <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
            <h3 class="text-lg font-semibold text-white mb-4"><i class="fas fa-images text-primary-400 mr-2"></i>Visuels</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                @foreach (['thumbnail' => 'Vignette', 'cover' => 'Poster vertical', 'banner' => 'Bannière'] as $f => $label)
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">{{ $label }}</label>
                        @php $current = $medium->{$f.'_path'}; @endphp
                        @if ($current)
                            <img src="{{ str_starts_with($current, 'http') ? $current : asset('storage/'.$current) }}"
                                 alt="" class="w-full h-32 object-cover rounded-lg bg-dark-300 mb-2"
                                 onerror="this.style.opacity=.2">
                        @elseif ($bunnyThumb && $f === 'thumbnail')
                            <img src="{{ $bunnyThumb }}" alt="" class="w-full h-32 object-cover rounded-lg bg-dark-300 mb-2 opacity-60">
                            <p class="text-xs text-gray-500 mb-1">↑ Vignette Bunny par défaut</p>
                        @endif
                        <input type="file" name="{{ $f }}" accept="image/*" class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-2 text-white text-sm file:mr-2 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:bg-primary-500 file:text-white">
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Réglages -->
        <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
            <h3 class="text-lg font-semibold text-white mb-4"><i class="fas fa-cog text-primary-400 mr-2"></i>Réglages</h3>
            <div class="space-y-4">
                <label class="flex items-center gap-3 p-4 bg-dark-50 rounded-lg cursor-pointer">
                    <input type="checkbox" name="is_featured" value="1" {{ old('is_featured', $medium->is_featured) ? 'checked' : '' }} class="w-5 h-5 text-primary-500 bg-dark-300 border-dark-400 rounded">
                    <div>
                        <span class="text-white font-medium">Mettre en vedette</span>
                    </div>
                </label>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Date de publication</label>
                    <input type="datetime-local" name="published_at" value="{{ old('published_at', optional($medium->published_at)->format('Y-m-d\TH:i')) }}" class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white">
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-4">
            <a href="{{ route('media.index') }}" class="bg-dark-200 hover:bg-dark-300 text-white px-8 py-3 rounded-lg font-medium"><i class="fas fa-times mr-2"></i>Annuler</a>
            <button type="submit" class="bg-gradient-to-r from-primary-500 to-primary-600 hover:shadow-lg hover:shadow-primary-500/50 text-white px-8 py-3 rounded-lg font-medium"><i class="fas fa-save mr-2"></i>Enregistrer</button>
        </div>
    </form>

    {{-- ─────────────────────────────────────────────────────────────
         Gestion des saisons & épisodes (séries uniquement)
         Hors du form principal pour éviter les nested forms.
    ───────────────────────────────────────────────────────────── --}}
    @if ($medium->isSeries())
    <div class="mt-8 bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gradient-to-br from-primary-500 to-primary-600 rounded-lg flex items-center justify-center">
                    <i class="fas fa-list-ol text-white"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-white">Saisons & Épisodes</h3>
                    <p class="text-gray-400 text-sm">{{ $seasons->count() }} saison(s) • {{ $seasons->sum(fn($s) => $s->episodes->count()) }} épisode(s)</p>
                </div>
            </div>
            <button type="button" @click="openSeasonModal = true"
                    class="bg-gradient-to-r from-primary-500 to-primary-600 hover:shadow-lg hover:shadow-primary-500/50 text-white px-5 py-2 rounded-lg font-medium transition-all">
                <i class="fas fa-plus mr-2"></i> Ajouter une saison
            </button>
        </div>

        @if ($seasons->count() > 0)
            <div class="space-y-6">
                @foreach ($seasons as $season)
                    <div class="bg-dark-50 rounded-lg border border-dark-200 p-5">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-gradient-to-br from-primary-500 to-primary-600 rounded-lg flex items-center justify-center">
                                    <span class="text-white font-bold">S{{ $season->season_number }}</span>
                                </div>
                                <div>
                                    <h4 class="text-white font-semibold">
                                        Saison {{ $season->season_number }}@if($season->title) — {{ $season->title }}@endif
                                    </h4>
                                    <p class="text-gray-400 text-xs">{{ $season->episodes->count() }} épisode(s)</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <a href="{{ route('episodes.create', $season) }}"
                                   class="bg-primary-500 hover:bg-primary-600 text-white px-3 py-2 rounded-lg text-sm transition-all">
                                    <i class="fas fa-plus mr-1"></i> Épisode
                                </a>
                                <form action="{{ route('episodes.season.destroy', $season) }}" method="POST"
                                      onsubmit="return confirm('Supprimer la saison {{ $season->season_number }} et tous ses épisodes ?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="bg-red-500/80 hover:bg-red-600 text-white px-3 py-2 rounded-lg text-sm transition-all">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>

                        @if ($season->episodes->count() > 0)
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                @foreach ($season->episodes as $episode)
                                    <div class="bg-dark-100 rounded-lg p-3 border border-dark-200 hover:border-primary-500 transition-all">
                                        <div class="flex items-start justify-between mb-2">
                                            <span class="bg-primary-500 text-white text-xs px-2 py-0.5 rounded">
                                                Ep {{ $episode->episode_number }}
                                            </span>
                                            <div class="flex gap-2">
                                                <a href="{{ route('episodes.edit', $episode) }}"
                                                   class="text-gray-400 hover:text-primary-400 transition-colors text-sm"
                                                   title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form action="{{ route('episodes.destroy', $episode) }}" method="POST"
                                                      onsubmit="return confirm('Supprimer cet épisode ?')" class="inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            class="text-gray-400 hover:text-red-500 transition-colors text-sm"
                                                            title="Supprimer">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                        <h5 class="text-white text-sm font-medium mb-1 truncate">{{ $episode->title }}</h5>
                                        <p class="text-gray-400 text-xs line-clamp-2">
                                            {{ $episode->description ?: 'Pas de description' }}
                                        </p>
                                        @if ($episode->duration)
                                            <p class="text-gray-500 text-xs mt-2">
                                                <i class="fas fa-clock mr-1"></i> {{ gmdate('H:i:s', $episode->duration) }}
                                            </p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-6 border border-dashed border-dark-300 rounded-lg">
                                <i class="fas fa-video text-gray-600 text-2xl mb-2"></i>
                                <p class="text-gray-400 text-sm">Aucun épisode dans cette saison</p>
                                <a href="{{ route('episodes.create', $season) }}"
                                   class="inline-block mt-2 text-primary-400 hover:text-primary-300 text-sm">
                                    <i class="fas fa-plus mr-1"></i> Ajouter le premier épisode
                                </a>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-12 border border-dashed border-dark-300 rounded-lg">
                <i class="fas fa-film text-gray-600 text-5xl mb-3"></i>
                <h4 class="text-white font-semibold mb-1">Aucune saison créée</h4>
                <p class="text-gray-400 text-sm mb-4">Commence par créer la première saison de cette série.</p>
                <button type="button" @click="openSeasonModal = true"
                        class="bg-gradient-to-r from-primary-500 to-primary-600 hover:shadow-lg text-white px-6 py-2 rounded-lg font-medium">
                    <i class="fas fa-plus mr-2"></i> Créer la Saison 1
                </button>
            </div>
        @endif
    </div>

    {{-- Modal: Ajouter une saison --}}
    <div x-show="openSeasonModal"
         x-cloak
         @click.self="openSeasonModal = false"
         @keydown.escape.window="openSeasonModal = false"
         class="fixed inset-0 bg-black/75 z-50 flex items-center justify-center p-4">
        <div class="bg-dark-100 rounded-xl shadow-2xl border border-dark-200 max-w-md w-full p-6">
            <h3 class="text-xl font-bold text-white mb-4">Ajouter une saison</h3>
            <form action="{{ route('episodes.season.create', $medium) }}" method="POST">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Numéro de la saison <span class="text-primary-400">*</span></label>
                    <input type="number" name="season_number" min="1"
                           value="{{ $seasons->count() + 1 }}"
                           required
                           class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Titre (optionnel)</label>
                    <input type="text" name="title"
                           class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500"
                           placeholder="Ex: Le commencement">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Description</label>
                    <textarea name="description" rows="3"
                              class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500 resize-none"
                              placeholder="Synopsis de la saison..."></textarea>
                </div>
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Année de sortie</label>
                    <input type="number" name="release_year" min="1900" max="{{ date('Y') + 5 }}" value="{{ date('Y') }}"
                           class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500">
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" @click="openSeasonModal = false"
                            class="bg-dark-200 hover:bg-dark-300 text-white px-5 py-2 rounded-lg">Annuler</button>
                    <button type="submit"
                            class="bg-gradient-to-r from-primary-500 to-primary-600 hover:shadow-lg text-white px-5 py-2 rounded-lg">
                        <i class="fas fa-check mr-2"></i> Créer la saison
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>

@push('scripts')
<script>
    function bunnyPicker(initialGuid) {
        return {
            query: '',
            results: [],
            loading: false,
            selected: null,
            initialGuid: initialGuid || '',
            init() {
                this.refresh();
                if (this.initialGuid) this.loadInitial();
            },
            async refresh() {
                this.loading = true;
                try {
                    const url = new URL('{{ route('admin.bunny.videos.available') }}', window.location.origin);
                    if (this.query) url.searchParams.set('q', this.query);
                    if (this.initialGuid) url.searchParams.set('include', this.initialGuid);
                    const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
                    const json = await res.json();
                    this.results = json.data || [];
                } finally { this.loading = false; }
            },
            async loadInitial() {
                const url = new URL('{{ route('admin.bunny.videos.available') }}', window.location.origin);
                url.searchParams.set('include', this.initialGuid);
                const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
                const json = await res.json();
                const found = (json.data || []).find(v => v.guid === this.initialGuid);
                if (found) this.selected = found;
            },
            formatDuration(s) {
                if (!s) return '—';
                if (s >= 3600) return new Date(s * 1000).toISOString().substr(11, 8);
                return new Date(s * 1000).toISOString().substr(14, 5);
            }
        }
    }
</script>
@endpush
@endsection
