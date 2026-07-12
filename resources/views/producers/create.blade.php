@extends('admin.layouts.app')

@section('title', 'Ajouter un Producteur - ABBEV')
@section('header', 'Ajouter un Producteur')

@section('content')
<div class="mb-6">
    <a href="{{ route('producers.index') }}" class="inline-flex items-center text-primary-400 hover:text-primary-300 transition">
        <i class="fas fa-arrow-left mr-2"></i> Retour à la liste
    </a>
</div>

<div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-8 max-w-2xl">
    <form action="{{ route('producers.store') }}" method="POST">
        @csrf

        <div class="mb-6">
            <label for="name" class="block text-sm font-medium text-gray-300 mb-2">
                Nom du producteur <span class="text-red-400">*</span>
            </label>
            <input type="text" name="name" id="name" value="{{ old('name') }}" required
                   class="w-full bg-dark-50 border @error('name') border-red-500 @else border-dark-200 @enderror rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500 transition">
            @error('name')<p class="mt-2 text-sm text-red-400"><i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}</p>@enderror
        </div>

        <div class="mb-6">
            <label for="email" class="block text-sm font-medium text-gray-300 mb-2">
                Adresse email <span class="text-red-400">*</span>
            </label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" required
                   class="w-full bg-dark-50 border @error('email') border-red-500 @else border-dark-200 @enderror rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500 transition">
            @error('email')<p class="mt-2 text-sm text-red-400"><i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}</p>@enderror
            <p class="mt-2 text-sm text-gray-400">
                <i class="fas fa-info-circle mr-1"></i> Cet email servira d'identifiant de connexion.
            </p>
        </div>

        <div class="mb-8 bg-primary-500/10 border border-primary-500/30 rounded-lg p-4">
            <div class="flex items-start gap-3">
                <i class="fas fa-key text-primary-400 text-xl mt-1"></i>
                <div>
                    <p class="text-primary-200 font-medium mb-1">Mot de passe généré et envoyé par email</p>
                    <p class="text-primary-100/80 text-sm">
                        Un mot de passe fort sera créé puis <span class="font-semibold">envoyé automatiquement</span> au
                        producteur à l'adresse ci-dessus. En cas d'échec d'envoi, il te sera affiché une seule fois pour
                        que tu le transmettes à la main.
                    </p>
                </div>
            </div>
        </div>

        <div class="flex gap-4">
            <button type="submit" class="bg-primary-500 hover:bg-primary-600 text-white px-6 py-3 rounded-lg transition flex-1">
                <i class="fas fa-check mr-2"></i> Créer le producteur
            </button>
            <a href="{{ route('producers.index') }}" class="bg-dark-200 hover:bg-dark-300 text-white px-6 py-3 rounded-lg transition text-center"
               data-confirm="Annuler la création ? Les informations saisies seront perdues."
               data-confirm-type="warning" data-confirm-title="Annuler la création" data-confirm-confirm="Oui, annuler">
                <i class="fas fa-times mr-2"></i> Annuler
            </a>
        </div>
    </form>
</div>
@endsection
