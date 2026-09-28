@extends('admin.layouts.app')

@section('title', 'Nouvelle offre de billetterie')
@section('header', 'Billetterie')

@section('content')
<x-admin.page-header :title="$kind === 'code' ? 'Nouvelle offre de codes cinéma' : 'Nouvelle séance'"
    :back="route('screenings.index', ['kind' => $kind])" back-label="Retour à la billetterie" />

<div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-8">
    <form action="{{ route('screenings.store') }}" method="POST">
        @csrf

        @include('screenings._form', ['screening' => null, 'movies' => $movies, 'kind' => $kind])

        <div class="flex gap-4 mt-8">
            <button type="submit" class="bg-primary-500 hover:bg-primary-600 text-white px-6 py-3 rounded-lg transition flex-1">
                <i class="fas fa-check mr-2"></i> Créer l'offre
            </button>
            <a href="{{ route('screenings.index', ['kind' => $kind]) }}" class="bg-dark-200 hover:bg-dark-300 text-white px-6 py-3 rounded-lg transition text-center">
                <i class="fas fa-times mr-2"></i> Annuler
            </a>
        </div>
    </form>
</div>
@endsection
