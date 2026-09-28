@extends('admin.layouts.app')

@section('title', $rubrique->name)
@section('header', 'Sélections éditoriales')

@section('content')
<x-admin.page-header :title="$rubrique->name" :back="route('rubriques.index')" back-label="Sélections éditoriales"
    :subtitle="$rubrique->slug === 'avant-premiere'
        ? 'Films et séries dévoilés avant leur sortie. L\'app les présente en deux onglets : Films et Séries.'
        : 'Choisissez les programmes de la sélection et leur ordre d\'affichage.'" />

<div class="grid grid-cols-1 xl:grid-cols-5 gap-6">
    <div class="xl:col-span-3 space-y-6">
        <x-admin.card title="Contenu de la sélection" icon="list-ol" :subtitle="$items->where('type', 'movie')->count() . ' film(s) · ' . $items->where('type', 'series')->count() . ' série(s)'" padding="p-0">
            @forelse($items as $i => $m)
                <div class="flex items-center gap-4 px-6 py-3 border-b border-dark-200 last:border-0">
                    <span class="w-6 text-sm text-gray-500 tabular-nums">{{ $i + 1 }}</span>
                    <x-admin.thumb :path="$m->cover_path ?: $m->thumbnail_path" icon="film" class="w-11 h-16 rounded-md" />
                    <div class="flex-1 min-w-0">
                        <p class="text-white font-medium truncate">{{ $m->title }}</p>
                        <p class="text-xs text-gray-500">{{ $m->type === 'series' ? 'Série' : 'Film' }} · {{ $m->category?->name ?? '—' }} · {{ $m->release_year }}</p>
                    </div>
                    <div class="flex items-center gap-1">
                        @foreach(['up' => 'chevron-up', 'down' => 'chevron-down'] as $dir => $ico)
                            <form action="{{ route('rubriques.media.move', [$rubrique, $m]) }}" method="POST">
                                @csrf
                                <input type="hidden" name="direction" value="{{ $dir }}">
                                <button class="w-8 h-8 rounded-lg bg-dark-200 hover:bg-dark-300 text-gray-400 hover:text-white transition disabled:opacity-30"
                                    @disabled(($dir === 'up' && $i === 0) || ($dir === 'down' && $i === $items->count() - 1))><i class="fas fa-{{ $ico }} text-xs"></i></button>
                            </form>
                        @endforeach
                        <form action="{{ route('rubriques.media.detach', [$rubrique, $m]) }}" method="POST" class="ml-1">
                            @csrf
                            @method('DELETE')
                            <button class="w-8 h-8 rounded-lg bg-rose-500/10 hover:bg-rose-500 text-rose-300 hover:text-white transition" title="Retirer"><i class="fas fa-xmark text-xs"></i></button>
                        </form>
                    </div>
                </div>
            @empty
                <x-admin.empty icon="clapperboard" title="Sélection vide" text="Elle apparaît dans l'app avec la mention « Bientôt ». Ajoutez des programmes avec la recherche." />
            @endforelse
        </x-admin.card>
    </div>

    <div class="xl:col-span-2 space-y-6">
        <x-admin.card title="Ajouter un programme" icon="plus">
            @include('rubriques._catalog-picker', [
                'searchAction' => route('rubriques.edit', $rubrique),
                'addButton' => fn ($m) => new \Illuminate\Support\HtmlString(
                    '<form action="' . e(route('rubriques.media.attach', $rubrique)) . '" method="POST">' . csrf_field()
                    . '<input type="hidden" name="media_id" value="' . $m->id . '">'
                    . '<button class="text-sm px-3 py-2 rounded-lg bg-primary-500/15 hover:bg-primary-500 text-primary-300 hover:text-white transition"><i class="fas fa-plus"></i></button></form>'
                ),
            ])
        </x-admin.card>

        <form action="{{ route('rubriques.update', $rubrique) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <x-admin.card title="Réglages" icon="sliders">
                <div class="space-y-5">
                    <x-admin.input name="name" label="Nom affiché" :value="$rubrique->name" required />
                    <x-admin.textarea name="description" label="Description" :value="$rubrique->description" rows="2" />
                    <x-admin.select name="required_tier" label="Accès" :value="$rubrique->required_tier" placeholder="Ouvert à tous"
                        :options="collect($tiers)->mapWithKeys(fn ($l, $k) => [$k => 'Abonnés ' . $l . ' et plus'])->all()"
                        hint="Réservez par exemple l'avant-première aux abonnés Premium." />
                    <x-admin.image-upload name="cover" label="Visuel (facultatif)" :current="$rubrique->cover_path ? asset('storage/' . $rubrique->cover_path) : null" />
                    <x-admin.toggle name="is_active" label="Visible dans l'application" :checked="$rubrique->is_active" />
                    <x-admin.translation :model="$rubrique" :fields="['name' => ['Name', 'input'], 'description' => ['Description', 'textarea']]" />
                    <button type="submit" class="w-full bg-primary-500 hover:bg-primary-600 text-white px-5 py-2.5 rounded-lg font-medium transition"><i class="fas fa-check mr-2"></i>Enregistrer les réglages</button>
                </div>
            </x-admin.card>
        </form>
    </div>
</div>
@endsection
