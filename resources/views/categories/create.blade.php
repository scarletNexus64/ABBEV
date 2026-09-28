@extends('admin.layouts.app')

@section('title', 'Nouveau genre')
@section('header', 'Genres')

@section('content')
<x-admin.page-header title="Nouveau genre" :back="route('categories.index')" back-label="Retour aux genres"
    subtitle="Le référentiel compte 15 genres : n'en ajoutez un qu'après validation éditoriale." />

<form action="{{ route('categories.store') }}" method="POST" class="max-w-2xl">
    @csrf
    <x-admin.card>
        @include('categories._form')
    </x-admin.card>
    <div class="flex gap-3 mt-5">
        <button type="submit" class="bg-primary-500 hover:bg-primary-600 text-white px-6 py-2.5 rounded-lg font-medium transition"><i class="fas fa-check mr-2"></i>Créer le genre</button>
        <a href="{{ route('categories.index') }}" class="bg-dark-200 hover:bg-dark-300 text-white px-6 py-2.5 rounded-lg transition">Annuler</a>
    </div>
</form>
@endsection
