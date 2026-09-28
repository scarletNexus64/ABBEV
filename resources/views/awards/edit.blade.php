@extends('admin.layouts.app')

@section('title', 'Modifier — ' . $edition->name)
@section('header', 'Lions Head Awards')

@section('content')
<x-admin.page-header :title="$edition->name" :back="route('awards.show', $edition)" back-label="Retour à l'édition" />
<form action="{{ route('awards.update', $edition) }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    @include('awards._form')
</form>
<div class="mt-8 max-w-xl">
    <x-admin.card title="Supprimer l'édition" icon="triangle-exclamation">
        <p class="text-sm text-gray-400 mb-4">Supprime aussi ses prix, ses nommés et tous les votes enregistrés. Action définitive.</p>
        <form action="{{ route('awards.destroy', $edition) }}" method="POST"
              data-confirm="Supprimer « {{ $edition->name }} » et tous ses votes ?" data-confirm-type="danger" data-confirm-title="Supprimer l'édition" data-confirm-confirm="Supprimer">
            @csrf @method('DELETE')
            <button class="bg-rose-500/15 hover:bg-rose-500 text-rose-300 hover:text-white px-4 py-2.5 rounded-lg text-sm font-medium transition"><i class="fas fa-trash mr-2"></i>Supprimer l'édition</button>
        </form>
    </x-admin.card>
</div>
@endsection
