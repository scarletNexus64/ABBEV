@extends('admin.layouts.app')

@section('title', 'Genres')
@section('header', 'Genres')

@section('content')
@php
    $totalMedia = $genres->sum('media_count');
    $maxMedia = max(1, (int) $genres->max('media_count'));
    $rows = $genres->map(fn ($g) => [
        'id' => $g->id,
        'name' => $g->name,
        'en' => $g->translations->firstWhere(fn ($t) => $t->locale === 'en' && $t->field === 'name')?->value,
        'slug' => $g->slug,
        'movies' => (int) $g->movies_count,
        'series' => (int) $g->series_count,
        'total' => (int) $g->media_count,
        'share' => round($g->media_count * 100 / $maxMedia),
        'outside' => $g->isOutsideReference(),
        'edit' => route('categories.edit', $g),
        'destroy' => route('categories.destroy', $g),
    ])->values();
@endphp

<x-admin.page-header title="Genres"
    subtitle="Les genres classent chaque film et chaque série. L'ordre ci-dessous est celui de l'application.">
    <x-slot:actions>
        <a href="{{ route('categories.create') }}" class="inline-flex items-center gap-2 bg-primary-500 hover:bg-primary-600 text-white px-5 py-2.5 rounded-lg font-medium transition">
            <i class="fas fa-plus text-sm"></i> Nouveau genre
        </a>
    </x-slot:actions>
</x-admin.page-header>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <x-admin.stat label="Genres" :value="$genres->count()" icon="masks-theater" hint="Référentiel : 15" />
    <x-admin.stat label="Contenus classés" :value="number_format($totalMedia, 0, ',', ' ')" icon="film" tone="sky" />
    <x-admin.stat label="Genres sans contenu" :value="$genres->where('media_count', 0)->count()" icon="circle-half-stroke" tone="amber" hint="Visibles, marqués « Bientôt » dans l'app" />
</div>

@if($missing->isNotEmpty() || $genres->contains(fn ($g) => $g->isOutsideReference()))
    <div class="mb-6 rounded-xl border border-amber-500/30 bg-amber-500/10 px-5 py-4 text-sm text-amber-200 flex gap-3">
        <i class="fas fa-circle-info mt-0.5"></i>
        <div class="space-y-1">
            @if($missing->isNotEmpty())
                <p>Genres du référentiel absents : <strong>{{ $missing->map(fn ($s) => $reference[$s])->implode(', ') }}</strong>.</p>
            @endif
            @if($genres->contains(fn ($g) => $g->isOutsideReference()))
                <p>Les genres marqués <em>hors référentiel</em> ne figurent pas dans le référentiel : réaffectez leurs contenus puis supprimez-les si besoin.</p>
            @endif
        </div>
    </div>
@endif

<div x-data="genreBoard(@js($rows))" class="bg-dark-100 rounded-xl border border-dark-200 overflow-hidden">
    <div class="px-6 py-3 border-b border-dark-200 flex items-center justify-between text-xs text-gray-500">
        <span>Réordonnez avec les flèches : l'ordre est enregistré aussitôt et repris par l'application.</span>
        <span x-show="saving" x-cloak class="text-primary-300"><i class="fas fa-circle-notch fa-spin mr-1"></i>Enregistrement…</span>
    </div>
    <ul class="divide-y divide-dark-200">
        <template x-for="(g, i) in rows" :key="g.id">
            <li class="flex items-center gap-4 px-6 py-3.5 hover:bg-dark-50/40 transition">
                <div class="flex flex-col">
                    <button type="button" @click="move(i, -1)" :disabled="i === 0" class="text-gray-500 hover:text-primary-300 disabled:opacity-20 leading-none p-1" title="Monter"><i class="fas fa-chevron-up text-xs"></i></button>
                    <button type="button" @click="move(i, 1)" :disabled="i === rows.length - 1" class="text-gray-500 hover:text-primary-300 disabled:opacity-20 leading-none p-1" title="Descendre"><i class="fas fa-chevron-down text-xs"></i></button>
                </div>
                <span class="w-7 text-sm font-semibold text-gray-500 tabular-nums" x-text="String(i + 1).padStart(2, '0')"></span>
                <div class="min-w-0 w-56">
                    <p class="text-white font-medium truncate flex items-center gap-2">
                        <span x-text="g.name"></span>
                        <span x-show="g.outside" class="text-[10px] px-2 py-0.5 rounded-full bg-amber-500/15 text-amber-300 border border-amber-500/30">hors référentiel</span>
                    </p>
                    <p class="text-xs text-gray-500 truncate"><span x-text="g.en || '—'"></span> · <span x-text="g.slug"></span></p>
                </div>
                <div class="flex-1 hidden md:block">
                    <div class="h-1.5 rounded-full bg-dark-300 overflow-hidden">
                        <div class="h-full rounded-full bg-gradient-to-r from-primary-500 to-primary-300" :style="`width: ${g.share}%`"></div>
                    </div>
                </div>
                <div class="w-40 text-right text-sm">
                    <span class="text-white font-semibold" x-text="g.total"></span>
                    <span class="text-gray-500 text-xs" x-text="` · ${g.movies} film(s), ${g.series} série(s)`"></span>
                </div>
                <div class="flex items-center gap-2">
                    <a :href="g.edit" class="bg-primary-500/15 hover:bg-primary-500 text-primary-300 hover:text-white px-3 py-2 rounded-lg text-sm transition" title="Modifier"><i class="fas fa-pen"></i></a>
                    <template x-if="g.total === 0">
                        <form :action="g.destroy" method="POST" data-confirm="Supprimer ce genre ? Il ne contient aucun film ni série."
                              data-confirm-type="danger" data-confirm-title="Supprimer le genre" data-confirm-confirm="Supprimer">
                            <input type="hidden" name="_token" value="{{ csrf_token() }}">
                            <input type="hidden" name="_method" value="DELETE">
                            <button type="submit" class="bg-rose-500/15 hover:bg-rose-500 text-rose-300 hover:text-white px-3 py-2 rounded-lg text-sm transition" title="Supprimer"><i class="fas fa-trash"></i></button>
                        </form>
                    </template>
                    <template x-if="g.total > 0">
                        <a :href="g.edit + '#suppression'" class="bg-dark-200 text-gray-500 hover:text-rose-300 px-3 py-2 rounded-lg text-sm transition" title="Réaffecter puis supprimer"><i class="fas fa-trash"></i></a>
                    </template>
                </div>
            </li>
        </template>
    </ul>
</div>
@endsection

@push('scripts')
<script>
function genreBoard(rows) {
    return {
        rows,
        saving: false,
        move(i, delta) {
            const j = i + delta;
            if (j < 0 || j >= this.rows.length) return;
            const list = [...this.rows];
            [list[i], list[j]] = [list[j], list[i]];
            this.rows = list;
            this.save();
        },
        async save() {
            this.saving = true;
            try {
                const res = await fetch(@js(route('categories.reorder')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ order: this.rows.map((r) => r.id) }),
                });
                if (!res.ok) throw new Error();
            } catch (e) {
                ABBEV.toast("L'ordre n'a pas pu être enregistré.", 'error');
            } finally {
                this.saving = false;
            }
        },
    };
}
</script>
@endpush
