@extends('admin.layouts.app')

@section('title', 'Sélections éditoriales')
@section('header', 'Sélections éditoriales')

@section('content')
<x-admin.page-header title="Sélections éditoriales"
    subtitle="Les sections éditoriales de l'application : À la une, Avant-première (films et séries), Sport et Jeux. Vous choisissez ce qu'elles contiennent et dans quel ordre." />

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    {{-- À la une : contenus marqués « mis en avant » --}}
    <a href="{{ route('rubriques.featured') }}" class="group bg-dark-100 rounded-xl border border-dark-200 hover:border-primary-500/40 transition overflow-hidden">
        <div class="p-6 flex items-start gap-4">
            <div class="w-12 h-12 rounded-xl bg-amber-500/15 border border-amber-500/30 flex items-center justify-center shrink-0">
                <i class="fas fa-bolt text-amber-300"></i>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                    <h3 class="text-lg font-semibold text-white">À la une</h3>
                    <x-admin.badge tone="amber">Nouveautés</x-admin.badge>
                </div>
                <p class="text-sm text-gray-400 mt-1">Bandeau de l'accueil et onglet « Nouveautés » de l'app. Les dernières sorties s'y ajoutent automatiquement.</p>
                <p class="text-sm text-white mt-3"><strong>{{ $featuredCount }}</strong> <span class="text-gray-400">contenu(s) mis en avant</span></p>
            </div>
            <i class="fas fa-chevron-right text-gray-600 group-hover:text-primary-300 mt-1"></i>
        </div>
        @if($featuredPreview->isNotEmpty())
            <div class="flex gap-2 px-6 pb-6">
                @foreach($featuredPreview as $m)
                    <x-admin.thumb :path="$m->cover_path ?: $m->thumbnail_path" icon="film" class="w-12 h-16 rounded-md" />
                @endforeach
            </div>
        @endif
    </a>

    @foreach($rubriques as $rubrique)
        @php
            $isOeuvre = $rubrique->isOeuvre();
            $icon = match ($rubrique->slug) {
                'avant-premiere' => 'star', 'sport' => 'futbol', 'jeux' => 'gamepad', 'oeuvre-adaptable' => 'book-open', default => 'layer-group',
            };
            $tone = match ($rubrique->slug) {
                'avant-premiere' => 'violet', 'sport' => 'emerald', 'jeux' => 'sky', default => 'primary',
            };
        @endphp
        <a href="{{ $isOeuvre ? route('oeuvres.index') : route('rubriques.edit', $rubrique) }}"
           class="group bg-dark-100 rounded-xl border border-dark-200 hover:border-primary-500/40 transition p-6 flex items-start gap-4">
            <div class="w-12 h-12 rounded-xl bg-{{ $tone }}-500/15 border border-{{ $tone }}-500/30 flex items-center justify-center shrink-0">
                <i class="fas fa-{{ $icon }} text-{{ $tone }}-300"></i>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="text-lg font-semibold text-white">{{ $rubrique->name }}</h3>
                    @if(! $rubrique->is_active)
                        <x-admin.badge tone="gray" icon="eye-slash">Masquée</x-admin.badge>
                    @endif
                    @if($rubrique->required_tier)
                        <x-admin.badge tone="gold" icon="crown">{{ ucfirst($rubrique->required_tier) }} minimum</x-admin.badge>
                    @endif
                </div>
                <p class="text-sm text-gray-400 mt-1 line-clamp-2">{{ $rubrique->description }}</p>
                <p class="text-sm mt-3">
                    @if($isOeuvre)
                        <strong class="text-white">{{ $rubrique->oeuvres_count }}</strong> <span class="text-gray-400">œuvre(s) à lire</span>
                    @else
                        <strong class="text-white">{{ $rubrique->movies_count }}</strong> <span class="text-gray-400">film(s)</span>
                        <span class="text-gray-600 mx-1">·</span>
                        <strong class="text-white">{{ $rubrique->series_count }}</strong> <span class="text-gray-400">série(s)</span>
                    @endif
                </p>
            </div>
            <i class="fas fa-chevron-right text-gray-600 group-hover:text-primary-300 mt-1"></i>
        </a>
    @endforeach
</div>
@endsection
