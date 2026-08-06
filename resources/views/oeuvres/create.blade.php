@extends('admin.layouts.app')

@section('title', 'Ajouter une oeuvre')
@section('header', 'Ajouter une oeuvre')

@section('content')
<div class="max-w-4xl">
    <div class="mb-6">
        <a href="{{ route('oeuvres.index') }}" class="text-gray-400 hover:text-white transition inline-flex items-center gap-2">
            <i class="fas fa-arrow-left"></i> Retour aux oeuvres
        </a>
    </div>

    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
        <h2 class="text-xl font-bold text-white mb-6">
            <i class="fas fa-book text-primary-400 mr-2"></i> Nouvelle oeuvre adaptable
        </h2>

        <form action="{{ route('oeuvres.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @include('oeuvres._form')

            <div class="flex justify-end gap-3 mt-6 pt-6 border-t border-dark-200">
                <a href="{{ route('oeuvres.index') }}"
                   class="px-6 py-3 rounded-lg border border-dark-200 text-gray-300 hover:bg-dark-200 transition">
                    Annuler
                </a>
                <button type="submit"
                        class="bg-primary-500 hover:bg-primary-600 text-white px-6 py-3 rounded-lg transition inline-flex items-center gap-2">
                    <i class="fas fa-save"></i> Publier l'oeuvre
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
