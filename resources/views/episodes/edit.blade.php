@extends('admin.layouts.app')

@section('title', 'Modifier un épisode - ABBEV')
@section('header', 'Modifier l\'épisode')

@section('content')
@php
    $bunny = app(\App\Services\BunnyStreamService::class);
    $bunnyThumb = ($episode->video_provider === 'bunny' && $episode->video_id && $bunny->isConfigured())
        ? $bunny->thumbnailUrl($episode->video_id) : null;

    // Jeton de la vidéo actuelle pour pré-sélectionner le picker (Bunny ou locale).
    $currentVideoToken = $episode->video_id;
    if ($episode->video_provider === 'local' && $episode->video_path) {
        $lu = \App\Models\BunnyUpload::where('local_path', $episode->video_path)->first();
        $currentVideoToken = $lu ? 'local:' . $lu->id : null;
    }
@endphp

<div class="max-w-4xl mx-auto">

    <div class="mb-6 flex items-center gap-3">
        <div class="w-12 h-12 bg-gradient-to-br from-primary-500 to-primary-600 rounded-xl flex items-center justify-center">
            <i class="fas fa-edit text-white text-xl"></i>
        </div>
        <div>
            <h2 class="text-2xl font-bold text-white">
                {{ $season->media->title }} — S{{ $season->season_number }}E{{ $episode->episode_number }}
            </h2>
            <p class="text-gray-400">{{ $episode->title }}</p>
        </div>
    </div>

    <form action="{{ route('episodes.update', $episode) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        <!-- Vidéo Bunny -->
        <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
            <h3 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                <i class="fas fa-cloud text-primary-400"></i>
                Vidéo Bunny <span class="text-red-400 text-sm">*</span>
            </h3>

            <div x-data="bunnyPicker({{ json_encode($currentVideoToken ?? '') }})" x-init="init()">
                <input type="hidden" name="bunny_video_id" :value="selected?.guid || ''">

                <template x-if="selected">
                    <div class="flex items-center gap-3 p-3 bg-primary-500/10 border border-primary-500/40 rounded-lg mb-2">
                        <img :src="selected.thumb" class="w-20 h-12 rounded object-cover bg-dark-300" onerror="this.style.opacity=.2">
                        <div class="flex-1 min-w-0">
                            <p class="text-white text-sm font-medium truncate" x-text="selected.title"></p>
                            <p class="text-gray-400 text-xs font-mono truncate" x-text="selected.guid"></p>
                        </div>
                        <button type="button" @click="selected = null; refresh()" class="text-red-400 text-sm px-2 py-1">
                            <i class="fas fa-times"></i> Changer
                        </button>
                    </div>
                </template>

                <div x-show="!selected" class="abbev-search">
                    <input type="text" x-model="query" @input.debounce.300ms="refresh()"
                           placeholder="Rechercher par nom — vidéos Bunny et locales…"
                           class="w-full bg-dark-50 border border-dark-200 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-primary-500 transition" />
                    <i class="fas fa-magnifying-glass"></i>
                </div>

                <div x-show="!selected" class="abbev-scroll mt-3 max-h-72 overflow-y-auto bg-dark-50 border border-dark-200 rounded-xl p-2 space-y-1">
                    <template x-if="loading"><div class="p-4 text-gray-400 text-sm text-center"><i class="fas fa-spinner fa-spin mr-2"></i>Chargement…</div></template>
                    <template x-if="!loading && results.length === 0"><div class="p-6 text-gray-500 text-sm text-center"><i class="fas fa-film text-2xl mb-2 block opacity-30"></i>Aucune vidéo libre. <a href="{{ route('admin.bunny.uploads.index') }}" class="text-primary-300 underline">Uploader une vidéo</a>.</div></template>
                    <template x-for="v in results" :key="v.guid">
                        <button type="button" @click="selected = v" class="abbev-pick-item">
                            <span class="abbev-pick-thumb">
                                <template x-if="v.thumb"><img :src="v.thumb" onerror="this.remove()"></template>
                                <template x-if="!v.thumb"><i class="fas fa-film ph"></i></template>
                            </span>
                            <span class="flex-1 min-w-0">
                                <span class="block text-white text-sm font-medium truncate" x-text="v.title"></span>
                                <span class="block text-gray-500 text-xs font-mono truncate" x-text="v.guid"></span>
                            </span>
                            <span class="flex flex-col items-end gap-1 flex-shrink-0">
                                <span x-show="String(v.guid).startsWith('local:')" class="abbev-badge-local"><i class="fas fa-hard-drive"></i> Local</span>
                                <span x-show="!String(v.guid).startsWith('local:')" class="abbev-badge-bunny"><i class="fas fa-cloud"></i> Bunny</span>
                                <span class="text-xs text-gray-400" x-text="formatDuration(v.length)"></span>
                            </span>
                        </button>
                    </template>
                </div>
            </div>
            @error('bunny_video_id')<p class="text-red-400 text-sm mt-2">{{ $message }}</p>@enderror
        </div>

        <!-- Infos épisode -->
        <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
            <h3 class="text-lg font-semibold text-white mb-4"><i class="fas fa-info-circle text-primary-400 mr-2"></i>Informations</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">
                        Durée <span class="text-gray-500 text-xs font-normal">(détectée par Bunny)</span>
                    </label>
                    <div class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-gray-300 flex items-center gap-2">
                        <i class="fas fa-clock text-primary-400"></i>
                        <span>{{ $episode->duration ? gmdate('H:i:s', $episode->duration) : 'Sera récupérée au prochain enregistrement' }}</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Synchronisée automatiquement avec la vidéo Bunny.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Publication</label>
                    <input type="datetime-local" name="published_at" value="{{ old('published_at', optional($episode->published_at)->format('Y-m-d\TH:i')) }}" class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Titre <span class="text-primary-400">*</span></label>
                    <input type="text" name="title" value="{{ old('title', $episode->title) }}" required class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Description</label>
                    <textarea name="description" rows="3" class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white resize-none">{{ old('description', $episode->description) }}</textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Vignette</label>
                    @if ($episode->thumbnail_path)
                        <img src="{{ asset('storage/'.$episode->thumbnail_path) }}" alt="" class="w-48 h-28 object-cover rounded mb-2">
                    @elseif ($bunnyThumb)
                        <img src="{{ $bunnyThumb }}" alt="" class="w-48 h-28 object-cover rounded mb-2 opacity-60">
                        <p class="text-xs text-gray-500 mb-2">↑ Vignette Bunny par défaut</p>
                    @endif
                    <input type="file" name="thumbnail" accept="image/*" class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-2 text-white text-sm">
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-4">
            <a href="{{ route('episodes.index', $season->media) }}" class="bg-dark-200 hover:bg-dark-300 text-white px-8 py-3 rounded-lg font-medium"><i class="fas fa-times mr-2"></i>Annuler</a>
            <button type="submit" class="bg-gradient-to-r from-primary-500 to-primary-600 hover:shadow-lg hover:shadow-primary-500/50 text-white px-8 py-3 rounded-lg font-medium"><i class="fas fa-save mr-2"></i>Enregistrer</button>
        </div>
    </form>
</div>

@push('scripts')
<script>
    function bunnyPicker(initialGuid) {
        return {
            query: '', results: [], loading: false, selected: null, initialGuid: initialGuid || '',
            init() { this.refresh(); if (this.initialGuid) this.loadInitial(); },
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
