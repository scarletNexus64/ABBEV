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

        <div class="mb-6 bg-dark-50 border border-dark-200 rounded-lg p-4">
            <p class="text-sm font-medium text-gray-200 mb-3"><i class="fas fa-layer-group text-primary-400 mr-2"></i>Son espace comprend tous les modules</p>
            <div class="grid sm:grid-cols-2 gap-x-4 gap-y-2">
                @foreach(\App\Models\User::MODULES as $module)
                    <p class="text-sm text-gray-400"><i class="fas fa-{{ $module['icon'] }} w-4 text-center text-primary-400 mr-2"></i>{{ $module['label'] }}</p>
                @endforeach
            </div>
            <p class="text-xs text-gray-500 mt-3">
                Il ne voit que ses propres données, invite lui-même son équipe et attribue à chaque membre les modules qu'il peut gérer.
                Le tier (rémunération) de ses contenus reste fixé par l'administration.
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

        @if(\App\Models\ProducerPlan::paymentRequired())
        <div class="mb-8 bg-amber-500/10 border border-amber-500/30 rounded-lg p-4">
            <div class="flex items-start gap-3">
                <i class="fas fa-lock text-amber-400 text-xl mt-1"></i>
                <div>
                    <p class="text-amber-200 font-medium mb-1">Espace verrouillé jusqu'au paiement</p>
                    <p class="text-amber-100/80 text-sm">
                        Le producteur devra souscrire au pack producteur pour accéder à son espace. Tu peux aussi lui
                        offrir l'accès depuis sa fiche.
                    </p>
                </div>
            </div>
        </div>
        @endif

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
