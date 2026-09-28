@extends('admin.layouts.app')

@section('title', 'À la une')
@section('header', 'Sélections éditoriales')

@section('content')
<x-admin.page-header title="À la une" :back="route('rubriques.index')" back-label="Sélections éditoriales"
    subtitle="Les contenus mis en avant alimentent le bandeau d'accueil et l'onglet « Nouveautés » de l'application, en plus des dernières sorties." />

<div class="grid grid-cols-1 xl:grid-cols-5 gap-6">
    <x-admin.card title="Mis en avant" icon="bolt" :subtitle="$items->count() . ' contenu(s)'" class="xl:col-span-3" padding="p-0">
        @forelse($items as $m)
            <div class="flex items-center gap-4 px-6 py-3 border-b border-dark-200 last:border-0">
                <x-admin.thumb :path="$m->cover_path ?: $m->thumbnail_path" icon="film" class="w-11 h-16 rounded-md" />
                <div class="flex-1 min-w-0">
                    <p class="text-white font-medium truncate">{{ $m->title }}</p>
                    <p class="text-xs text-gray-500">{{ $m->type === 'series' ? 'Série' : 'Film' }} · {{ $m->category?->name ?? '—' }}
                        @if($m->published_at) · publié le {{ $m->published_at->format('d/m/Y') }} @endif</p>
                </div>
                <form action="{{ route('rubriques.featured.toggle', $m) }}" method="POST">
                    @csrf
                    <button class="text-sm px-3 py-2 rounded-lg bg-dark-200 hover:bg-rose-500/20 text-gray-300 hover:text-rose-300 transition" title="Retirer de la une">
                        <i class="fas fa-xmark mr-1"></i> Retirer
                    </button>
                </form>
            </div>
        @empty
            <x-admin.empty icon="bolt" title="Rien n'est à la une" text="Recherchez un titre à droite pour le mettre en avant." />
        @endforelse
    </x-admin.card>

    <x-admin.card title="Ajouter à la une" icon="plus" class="xl:col-span-2">
        @include('rubriques._catalog-picker', [
            'searchAction' => route('rubriques.featured'),
            'addButton' => fn ($m) => new \Illuminate\Support\HtmlString(
                '<form action="' . e(route('rubriques.featured.toggle', $m)) . '" method="POST">' . csrf_field()
                . '<button class="text-sm px-3 py-2 rounded-lg bg-primary-500/15 hover:bg-primary-500 text-primary-300 hover:text-white transition"><i class="fas fa-plus"></i></button></form>'
            ),
        ])
    </x-admin.card>
</div>
@endsection
