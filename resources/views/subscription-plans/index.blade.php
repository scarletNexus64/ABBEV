@extends('admin.layouts.app')

@section('title', 'Packs d\'abonnement - ABBEV')
@section('header', 'Gestion des Packs d\'abonnement')

@section('content')
<!-- Header Actions -->
<div class="flex justify-between items-center mb-6">
    <div>
        <p class="text-gray-400">Gérez les différents packs d'abonnement proposés aux utilisateurs</p>
    </div>
    <a href="{{ route('subscription-plans.create') }}" class="bg-primary-500 hover:bg-primary-600 text-white px-6 py-3 rounded-lg transition inline-flex items-center">
        <i class="fas fa-plus mr-2"></i> Nouveau pack
    </a>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-400">Total packs</p>
                <p class="text-2xl font-bold text-white mt-1">{{ $stats['total'] }}</p>
            </div>
            <div class="w-12 h-12 bg-primary-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-crown text-xl text-primary-400"></i>
            </div>
        </div>
    </div>

    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-400">Packs actifs</p>
                <p class="text-2xl font-bold text-white mt-1">{{ $stats['active'] }}</p>
            </div>
            <div class="w-12 h-12 bg-green-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-check-circle text-xl text-green-400"></i>
            </div>
        </div>
    </div>

    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-400">Abonnements actifs</p>
                <p class="text-2xl font-bold text-white mt-1">{{ $stats['subscriptions'] }}</p>
            </div>
            <div class="w-12 h-12 bg-blue-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-users text-xl text-blue-400"></i>
            </div>
        </div>
    </div>

    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-400">Revenu mensuel</p>
                <p class="text-2xl font-bold text-white mt-1">{{ number_format($stats['revenue']) }} XAF</p>
            </div>
            <div class="w-12 h-12 bg-purple-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-money-bill-wave text-xl text-purple-400"></i>
            </div>
        </div>
    </div>
</div>

<!-- Subscription Plans Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    @forelse($plans as $plan)
    <div class="bg-dark-100 rounded-xl shadow-lg border @if($plan->is_popular) border-primary-500 @else border-dark-200 @endif overflow-hidden relative">
        @if($plan->is_popular)
        <div class="bg-gradient-to-r from-primary-500 to-primary-600 text-white text-center py-2 text-sm font-medium">
            <i class="fas fa-star mr-1"></i> POPULAIRE
        </div>
        @endif

        @if(!$plan->is_active)
        <div class="absolute top-4 right-4 bg-red-500/20 text-red-400 px-3 py-1 rounded-full text-xs font-medium">
            Inactif
        </div>
        @endif

        <div class="p-6">
            <!-- Plan Name & Price -->
            <div class="text-center mb-6">
                <h3 class="text-2xl font-bold text-white mb-2">{{ $plan->name }}</h3>
                <div class="flex items-baseline justify-center">
                    <span class="text-4xl font-bold text-primary-400">{{ number_format($plan->price) }}</span>
                    <span class="text-gray-400 ml-2">XAF</span>
                </div>
                <p class="text-gray-400 text-sm mt-2">
                    <i class="fas fa-calendar-alt mr-1"></i>
                    @if($plan->duration_days >= 365)
                        {{ round($plan->duration_days / 365) }} an(s)
                    @elseif($plan->duration_days >= 30)
                        {{ round($plan->duration_days / 30) }} mois
                    @else
                        {{ $plan->duration_days }} jours
                    @endif
                </p>
            </div>

            <!-- Description -->
            @if($plan->description)
            <p class="text-gray-300 text-sm text-center mb-6">{{ $plan->description }}</p>
            @endif

            <!-- Features -->
            @if(is_array($plan->features) && count($plan->features))
            <div class="mb-6">
                <ul class="space-y-2">
                    @foreach($plan->features as $feature)
                    <li class="flex items-start text-gray-300 text-sm">
                        <i class="fas fa-check text-primary-400 mr-2 mt-1"></i>
                        <span>{{ $feature }}</span>
                    </li>
                    @endforeach
                </ul>
            </div>
            @endif

            <!-- Stats -->
            <div class="bg-dark-50 rounded-lg p-4 mb-6">
                <div class="flex justify-between items-center text-sm">
                    <span class="text-gray-400">Abonnés</span>
                    <span class="text-white font-medium">{{ $plan->subscriptions()->where('status', 'active')->count() }}</span>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex gap-2">
                <a href="{{ route('subscription-plans.edit', $plan) }}"
                   class="flex-1 bg-primary-500/20 hover:bg-primary-500 text-primary-400 hover:text-white px-4 py-2 rounded-lg text-sm text-center transition">
                    <i class="fas fa-edit"></i> Modifier
                </a>
                <form action="{{ route('subscription-plans.destroy', $plan) }}" method="POST"
                      data-confirm="Supprimer ce pack d'abonnement ?"
                      data-confirm-type="danger" data-confirm-title="Supprimer le pack" data-confirm-confirm="Supprimer"
                      class="flex-1">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="w-full bg-red-500/20 hover:bg-red-500 text-red-400 hover:text-white px-4 py-2 rounded-lg text-sm transition">
                        <i class="fas fa-trash-alt"></i> Supprimer
                    </button>
                </form>
            </div>
        </div>
    </div>
    @empty
    <div class="col-span-full bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-12 text-center">
        <i class="fas fa-crown text-4xl text-gray-400 mb-3"></i>
        <p class="text-gray-400 mb-4">Aucun pack d'abonnement trouvé</p>
        <a href="{{ route('subscription-plans.create') }}" class="bg-primary-500 hover:bg-primary-600 text-white px-6 py-3 rounded-lg transition inline-flex items-center">
            <i class="fas fa-plus mr-2"></i> Créer le premier pack
        </a>
    </div>
    @endforelse
</div>

<!-- Info Box -->
<div class="mt-6 bg-blue-500/10 border border-blue-500/30 rounded-lg p-4">
    <div class="flex items-start">
        <i class="fas fa-info-circle text-blue-400 text-xl mr-3 mt-1"></i>
        <div>
            <p class="text-blue-300 font-medium mb-1">Conseil</p>
            <p class="text-blue-200 text-sm">
                Proposez plusieurs options de durée (mensuel, annuel) pour maximiser vos revenus.
                Les packs annuels génèrent généralement plus de conversions avec une réduction attractive.
            </p>
        </div>
    </div>
</div>
@endsection
