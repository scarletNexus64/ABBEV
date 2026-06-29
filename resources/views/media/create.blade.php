@extends('admin.layouts.app')

@php
    $isSeries = ($forcedType ?? null) === 'series';
    $isMovie  = ($forcedType ?? null) === 'movie';
    $pageNoun = $isSeries ? 'une série' : ($isMovie ? 'un film' : 'un média');
@endphp

@section('title', 'Ajouter ' . $pageNoun . ' - ABBEV')
@section('header', 'Ajouter ' . $pageNoun)

@push('styles')
<style>
    /* picker Bunny */
    .bunny-card { transition: all .15s ease; }
    .bunny-card:hover { transform: translateY(-1px); }
    .bunny-card.selected { border-color: #06b6d4 !important; background-color: rgba(6,182,212,.07) !important; }
</style>
@endpush

@section('content')
<div class="max-w-5xl mx-auto" x-data="{ type: '{{ old('type', $forcedType ?? 'movie') }}' }">

    <div class="mb-6 flex items-center gap-3">
        <div class="w-12 h-12 bg-gradient-to-br from-primary-500 to-primary-600 rounded-xl flex items-center justify-center">
            <i class="fas fa-plus text-white text-xl"></i>
        </div>
        <div>
            <h2 class="text-2xl font-bold text-white">Nouveau média</h2>
            <p class="text-gray-400">Référence une vidéo hébergée chez Bunny et ajoute sa fiche éditoriale.</p>
        </div>
    </div>

    <form action="{{ route('media.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf

        <!-- Type de média -->
        <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
            <h3 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                <i class="fas fa-clapperboard text-primary-400"></i>
                Type & Bunny
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @if($forcedType ?? null)
                    {{-- Type imposé par la section (menu Films / menu Séries) --}}
                    <input type="hidden" name="type" value="{{ $forcedType }}">
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
                @else
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">
                            Type <span class="text-primary-400">*</span>
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="relative cursor-pointer">
                                <input type="radio" name="type" value="movie" x-model="type" class="peer sr-only" {{ old('type', 'movie') == 'movie' ? 'checked' : '' }}>
                                <div class="p-4 bg-dark-50 border-2 border-dark-200 rounded-lg peer-checked:border-primary-500 peer-checked:bg-primary-500/10 transition-all">
                                    <i class="fas fa-film text-2xl text-gray-400 peer-checked:text-primary-400 mb-2"></i>
                                    <p class="text-white font-medium">Film</p>
                                    <p class="text-gray-500 text-xs mt-1">Une vidéo Bunny = un film</p>
                                </div>
                            </label>
                            <label class="relative cursor-pointer">
                                <input type="radio" name="type" value="series" x-model="type" class="peer sr-only" {{ old('type') == 'series' ? 'checked' : '' }}>
                                <div class="p-4 bg-dark-50 border-2 border-dark-200 rounded-lg peer-checked:border-primary-500 peer-checked:bg-primary-500/10 transition-all">
                                    <i class="fas fa-tv text-2xl text-gray-400 peer-checked:text-primary-400 mb-2"></i>
                                    <p class="text-white font-medium">Série</p>
                                    <p class="text-gray-500 text-xs mt-1">Vidéos rattachées aux épisodes</p>
                                </div>
                            </label>
                        </div>
                        @error('type')<p class="text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
                    </div>
                @endif

                <!-- Bunny picker (film uniquement) -->
                <div x-show="type === 'movie'">
                    <label class="block text-sm font-medium text-gray-300 mb-2">
                        Vidéo Bunny <span class="text-primary-400">*</span>
                    </label>

                    <div x-data="bunnyPicker({{ json_encode(old('bunny_video_id', $preselectedBunnyGuid ?? '')) }})" x-init="init()">
                        <input type="hidden" name="bunny_video_id" :value="selected?.guid || ''">

                        <!-- Sélection actuelle -->
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

                        <!-- Champ de recherche -->
                        <input x-show="!selected" type="text" x-model="query" @input.debounce.300ms="refresh()"
                               placeholder="🔍 Rechercher par nom — vidéos Bunny et locales…"
                               class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20" />

                        <!-- Liste résultats -->
                        <div x-show="!selected" class="mt-2 max-h-64 overflow-y-auto bg-dark-50 border border-dark-200 rounded-lg divide-y divide-dark-200">
                            <template x-if="loading">
                                <div class="p-4 text-gray-400 text-sm text-center">
                                    <i class="fas fa-spinner fa-spin mr-2"></i> Chargement…
                                </div>
                            </template>
                            <template x-if="!loading && results.length === 0">
                                <div class="p-4 text-gray-500 text-sm text-center">
                                    Aucune vidéo libre.
                                    <a href="{{ route('admin.bunny.uploads.index') }}" class="text-primary-300 underline">Uploader une vidéo</a>.
                                </div>
                            </template>
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

                <div x-show="type === 'series'" class="md:col-span-1 flex items-center">
                    <div class="bg-dark-50 border border-dark-200 rounded-lg p-4 text-gray-400 text-sm">
                        <i class="fas fa-info-circle mr-2 text-primary-400"></i>
                        Pour une série, tu créeras d'abord la fiche, puis tu attribueras une vidéo Bunny à chaque épisode dans la gestion des saisons.
                    </div>
                </div>
            </div>
        </div>

        <!-- Infos éditoriales -->
        <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
            <h3 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                <i class="fas fa-info-circle text-primary-400"></i>
                Informations
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Catégorie <span class="text-primary-400">*</span></label>
                    <select name="category_id" required class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20">
                        <option value="">— Choisir une catégorie —</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id')<p class="text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Année</label>
                    <input type="number" name="release_year" value="{{ old('release_year', date('Y')) }}" min="1900" max="{{ date('Y') + 5 }}"
                           class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500">
                    @error('release_year')<p class="text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Titre <span class="text-primary-400">*</span></label>
                    <input type="text" name="title" value="{{ old('title') }}" required placeholder="Ex: Inception, Black Panther…"
                           class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500">
                    @error('title')<p class="text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Description</label>
                    <textarea name="description" rows="4" placeholder="Synopsis du contenu…"
                              class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500 resize-none">{{ old('description') }}</textarea>
                </div>

                <div x-show="type === 'movie'">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Durée (minutes)</label>
                    <input type="number" name="duration" value="{{ old('duration') }}" min="1" placeholder="120 — laissé vide, on prendra la durée détectée par Bunny"
                           class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500">
                </div>

                <div x-show="type === 'series'">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Nombre de saisons</label>
                    <input type="number" name="seasons" value="{{ old('seasons', 1) }}" min="1"
                           class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500">
                </div>
            </div>
        </div>

        <!-- Visuels -->
        <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
            <h3 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                <i class="fas fa-images text-primary-400"></i>
                Visuels
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2"><i class="fas fa-image mr-1"></i> Vignette (Thumbnail)</label>
                    <input type="file" name="thumbnail" accept="image/*" class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-gradient-to-r file:from-primary-500 file:to-primary-600 file:text-white">
                    <p class="text-gray-500 text-xs mt-1">Image carrée ~400×400. Laisse vide pour utiliser celle de Bunny.</p>
                    @error('thumbnail')<p class="text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2"><i class="fas fa-portrait mr-1"></i> Poster vertical (Cover)</label>
                    <input type="file" name="cover" accept="image/*" class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-gradient-to-r file:from-primary-500 file:to-primary-600 file:text-white">
                    <p class="text-gray-500 text-xs mt-1">~500×750. Affiché dans la grille.</p>
                    @error('cover')<p class="text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-300 mb-2"><i class="fas fa-panorama mr-1"></i> Bannière (Backdrop)</label>
                    <input type="file" name="banner" accept="image/*" class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-gradient-to-r file:from-primary-500 file:to-primary-600 file:text-white">
                    <p class="text-gray-500 text-xs mt-1">~1920×1080. Pour la fiche détail et la home carousel.</p>
                    @error('banner')<p class="text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <!-- Réglages -->
        <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
            <h3 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                <i class="fas fa-cog text-primary-400"></i>
                Réglages
            </h3>
            <div class="space-y-4">
                <label class="flex items-center gap-3 p-4 bg-dark-50 rounded-lg cursor-pointer hover:bg-dark-200 transition-all">
                    <input type="checkbox" name="is_featured" value="1" {{ old('is_featured') ? 'checked' : '' }} class="w-5 h-5 text-primary-500 bg-dark-300 border-dark-400 rounded">
                    <div>
                        <span class="text-white font-medium">Mettre en vedette</span>
                        <p class="text-gray-400 text-sm">Apparaîtra dans les sections mises en avant.</p>
                    </div>
                </label>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Date de publication</label>
                    <input type="datetime-local" name="published_at" value="{{ old('published_at', now()->format('Y-m-d\TH:i')) }}"
                           class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white">
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex justify-end gap-4">
            <a href="{{ route('media.index') }}" class="bg-dark-200 hover:bg-dark-300 text-white px-8 py-3 rounded-lg font-medium">
                <i class="fas fa-times mr-2"></i> Annuler
            </a>
            <button type="submit" class="bg-gradient-to-r from-primary-500 to-primary-600 hover:shadow-lg hover:shadow-primary-500/50 text-white px-8 py-3 rounded-lg font-medium">
                <i class="fas fa-save mr-2"></i> Enregistrer
            </button>
        </div>
    </form>
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
                if (this.initialGuid) {
                    this.loadInitial();
                }
            },
            async refresh() {
                this.loading = true;
                try {
                    const url = new URL('{{ route('admin.bunny.videos.available') }}', window.location.origin);
                    if (this.query) url.searchParams.set('q', this.query);
                    const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
                    const json = await res.json();
                    this.results = json.data || [];
                } catch (e) {
                    this.results = [];
                } finally {
                    this.loading = false;
                }
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
