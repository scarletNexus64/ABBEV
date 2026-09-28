@extends('admin.layouts.app')

@section('title', 'Genre — ' . $category->name)
@section('header', 'Genres')

@section('content')
<x-admin.page-header :title="$category->name" :back="route('categories.index')" back-label="Retour aux genres"
    :subtitle="$category->media_count . ' contenu(s) classé(s) dans ce genre · identifiant : ' . $category->slug" />

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <form action="{{ route('categories.update', $category) }}" method="POST" class="xl:col-span-2">
        @csrf
        @method('PUT')
        <x-admin.card title="Informations" icon="pen">
            @include('categories._form')
        </x-admin.card>
        <div class="flex gap-3 mt-5">
            <button type="submit" class="bg-primary-500 hover:bg-primary-600 text-white px-6 py-2.5 rounded-lg font-medium transition"><i class="fas fa-check mr-2"></i>Enregistrer</button>
            <a href="{{ route('categories.index') }}" class="bg-dark-200 hover:bg-dark-300 text-white px-6 py-2.5 rounded-lg transition">Annuler</a>
        </div>
    </form>

    <div id="suppression">
        <x-admin.card title="Supprimer le genre" icon="triangle-exclamation">
            @if($category->media_count > 0)
                <p class="text-sm text-gray-400 mb-4">
                    Ce genre contient <strong class="text-white">{{ $category->media_count }} contenu(s)</strong>. Choisissez le genre qui les recevra :
                    aucun film ni aucune série n'est supprimé.
                </p>
            @else
                <p class="text-sm text-gray-400 mb-4">Aucun contenu n'est classé dans ce genre : il peut être supprimé sans conséquence.</p>
            @endif
            <form action="{{ route('categories.destroy', $category) }}" method="POST" class="space-y-4"
                  data-confirm="Supprimer définitivement le genre « {{ $category->name }} » ?"
                  data-confirm-type="danger" data-confirm-title="Supprimer le genre" data-confirm-confirm="Supprimer">
                @csrf
                @method('DELETE')
                @if($category->media_count > 0)
                    <x-admin.select name="move_to" label="Transférer les contenus vers" required placeholder="— Choisir un genre —"
                        :options="$others->pluck('name', 'id')->all()" />
                @endif
                <button type="submit" class="w-full bg-rose-500/15 hover:bg-rose-500 text-rose-300 hover:text-white px-4 py-2.5 rounded-lg text-sm font-medium transition">
                    <i class="fas fa-trash mr-2"></i>{{ $category->media_count > 0 ? 'Transférer puis supprimer' : 'Supprimer le genre' }}
                </button>
            </form>
        </x-admin.card>
    </div>
</div>
@endsection
