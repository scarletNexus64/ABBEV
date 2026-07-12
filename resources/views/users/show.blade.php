@extends('admin.layouts.app')

@section('title', 'Détails Utilisateur - ABBEV')
@section('header', 'Détails de l\'utilisateur')

@section('content')
<!-- Back Button + actions -->
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <a href="{{ route('users.index') }}" class="inline-flex items-center text-primary-400 hover:text-primary-300 transition">
        <i class="fas fa-arrow-left mr-2"></i> Retour à la liste
    </a>

    <div class="flex flex-wrap items-center gap-2">
        @can('update', $user)
        <a href="{{ route('users.edit', $user) }}"
           class="bg-primary-500/20 hover:bg-primary-500 text-primary-400 hover:text-white px-4 py-2 rounded-lg text-sm transition">
            <i class="fas fa-pen mr-1"></i> Modifier
        </a>
        @endcan

        @can('updateStatus', $user)
        <form action="{{ route('users.status', $user) }}" method="POST"
              data-confirm="{{ $user->is_active ? 'Suspendre' : 'Réactiver' }} le compte de « {{ $user->name }} » ?"
              data-confirm-type="{{ $user->is_active ? 'warning' : 'primary' }}"
              data-confirm-confirm="{{ $user->is_active ? 'Suspendre' : 'Réactiver' }}">
            @csrf @method('PATCH')
            <input type="hidden" name="is_active" value="{{ $user->is_active ? 0 : 1 }}">
            @if($user->is_active)
            <button type="submit" class="bg-amber-500/20 hover:bg-amber-500 text-amber-400 hover:text-white px-4 py-2 rounded-lg text-sm transition">
                <i class="fas fa-ban mr-1"></i> Suspendre
            </button>
            @else
            <button type="submit" class="bg-green-500/20 hover:bg-green-500 text-green-400 hover:text-white px-4 py-2 rounded-lg text-sm transition">
                <i class="fas fa-circle-check mr-1"></i> Réactiver
            </button>
            @endif
        </form>
        @endcan

        @can('resetPassword', $user)
        <form action="{{ route('users.resetPassword', $user) }}" method="POST"
              data-confirm="Régénérer le mot de passe de « {{ $user->name }} » et le lui envoyer par email ?"
              data-confirm-type="primary" data-confirm-confirm="Réinitialiser">
            @csrf
            <button type="submit" class="bg-sky-500/20 hover:bg-sky-500 text-sky-400 hover:text-white px-4 py-2 rounded-lg text-sm transition">
                <i class="fas fa-key mr-1"></i> Réinit. mot de passe
            </button>
        </form>
        @endcan

        @can('delete', $user)
        @if($user->role === 'user')
        <form action="{{ route('users.destroy', $user) }}" method="POST"
              data-confirm="Supprimer définitivement l'utilisateur « {{ $user->name }} » ({{ $user->email }}) ? Cette action est irréversible et supprimera toutes ses données associées."
              data-confirm-type="danger" data-confirm-title="Supprimer l'utilisateur" data-confirm-confirm="Supprimer">
            @csrf @method('DELETE')
            <button type="submit"
                    class="bg-red-500/20 hover:bg-red-500 text-red-400 hover:text-white px-4 py-2 rounded-lg text-sm transition">
                <i class="fas fa-trash mr-1"></i> Supprimer
            </button>
        </form>
        @endif
        @endcan
    </div>
</div>

<!-- User Info Card -->
<div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6 mb-6">
    <div class="flex items-center gap-6">
        <div class="w-20 h-20 rounded-full bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center text-white font-bold text-3xl">
            {{ strtoupper(substr($user->name, 0, 1)) }}
        </div>
        <div class="flex-1">
            <h2 class="text-2xl font-bold text-white mb-1">{{ $user->name }}</h2>
            <p class="text-gray-400">{{ $user->email }}</p>
            <div class="flex items-center gap-4 mt-3">
                <span class="text-sm text-gray-400">
                    <i class="fas fa-calendar-alt mr-1"></i>
                    Inscrit le {{ $user->created_at->format('d/m/Y') }}
                </span>
                @if($user->subscriptions()->where('status', 'active')->exists())
                <span class="bg-green-500/20 text-green-400 px-3 py-1 rounded-full text-sm">
                    <i class="fas fa-check-circle mr-1"></i> Abonné
                </span>
                @else
                <span class="bg-gray-500/20 text-gray-400 px-3 py-1 rounded-full text-sm">
                    <i class="fas fa-times-circle mr-1"></i> Non abonné
                </span>
                @endif

                @if($user->is_active)
                <span class="bg-green-500/20 text-green-400 px-3 py-1 rounded-full text-sm">
                    <i class="fas fa-circle-check mr-1"></i> Actif
                </span>
                @else
                <span class="bg-red-500/20 text-red-400 px-3 py-1 rounded-full text-sm">
                    <i class="fas fa-ban mr-1"></i> Suspendu
                </span>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Stats Row -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-400">Abonnements</p>
                <p class="text-2xl font-bold text-white mt-1">{{ $user->subscriptions()->count() }}</p>
            </div>
            <div class="w-12 h-12 bg-primary-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-crown text-xl text-primary-400"></i>
            </div>
        </div>
    </div>

    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-400">Transactions</p>
                <p class="text-2xl font-bold text-white mt-1">{{ $user->transactions()->count() }}</p>
            </div>
            <div class="w-12 h-12 bg-blue-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-receipt text-xl text-blue-400"></i>
            </div>
        </div>
    </div>

    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-400">Total dépensé</p>
                <p class="text-2xl font-bold text-white mt-1">{{ number_format($user->transactions()->where('status', 'completed')->sum('amount')) }} XAF</p>
            </div>
            <div class="w-12 h-12 bg-green-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-money-bill-wave text-xl text-green-400"></i>
            </div>
        </div>
    </div>
</div>

<!-- Active Subscription -->
@php
    $activeSubscription = $user->subscriptions()->where('status', 'active')->with('plan')->latest()->first();
@endphp

@if($activeSubscription)
<div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6 mb-6">
    <h3 class="text-xl font-bold text-white mb-4">
        <i class="fas fa-crown text-primary-400 mr-2"></i> Abonnement actif
    </h3>
    <div class="bg-dark-50 rounded-lg p-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <p class="text-sm text-gray-400 mb-1">Pack</p>
                <p class="text-white font-medium">{{ $activeSubscription->plan->name }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-400 mb-1">Date de début</p>
                <p class="text-white font-medium">{{ $activeSubscription->starts_at->format('d/m/Y') }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-400 mb-1">Date d'expiration</p>
                <p class="text-white font-medium">{{ $activeSubscription->expires_at->format('d/m/Y') }}</p>
            </div>
        </div>
    </div>

    @can('manageSubscription', $user)
    <div class="mt-4 flex flex-wrap items-center gap-3">
        <form action="{{ route('users.subscription.extend', $user) }}" method="POST" class="flex items-center gap-2">
            @csrf
            <input type="number" name="days" min="1" max="3650" value="30"
                   class="w-24 bg-dark-50 border border-dark-200 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-primary-500">
            <button type="submit" class="bg-primary-500/20 hover:bg-primary-500 text-primary-400 hover:text-white px-4 py-2 rounded-lg text-sm transition">
                <i class="fas fa-clock mr-1"></i> Prolonger (jours)
            </button>
        </form>
        <form action="{{ route('users.subscription.cancel', [$user, $activeSubscription]) }}" method="POST"
              data-confirm="Annuler l'abonnement actif de « {{ $user->name }} » ? Son accès sera révoqué."
              data-confirm-type="danger" data-confirm-title="Annuler l'abonnement" data-confirm-confirm="Annuler l'abonnement">
            @csrf @method('DELETE')
            <button type="submit" class="bg-red-500/20 hover:bg-red-500 text-red-400 hover:text-white px-4 py-2 rounded-lg text-sm transition">
                <i class="fas fa-xmark mr-1"></i> Annuler l'abonnement
            </button>
        </form>
    </div>
    @endcan
</div>
@endif

<!-- Subscriptions History -->
<div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 overflow-hidden mb-6">
    <div class="p-6 border-b border-dark-200">
        <h3 class="text-xl font-bold text-white">
            <i class="fas fa-history text-primary-400 mr-2"></i> Historique des abonnements
        </h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-dark-50">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase">Pack</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase">Début</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase">Expiration</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase">Statut</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-dark-200">
                @forelse($user->subscriptions()->with('plan')->latest()->get() as $subscription)
                <tr class="hover:bg-dark-50 transition">
                    <td class="px-6 py-4 text-white">{{ $subscription->plan->name }}</td>
                    <td class="px-6 py-4 text-gray-300">{{ $subscription->starts_at->format('d/m/Y') }}</td>
                    <td class="px-6 py-4 text-gray-300">{{ $subscription->expires_at->format('d/m/Y') }}</td>
                    <td class="px-6 py-4">
                        @if($subscription->status === 'active')
                        <span class="bg-green-500/20 text-green-400 px-3 py-1 rounded-full text-sm">Actif</span>
                        @elseif($subscription->status === 'expired')
                        <span class="bg-gray-500/20 text-gray-400 px-3 py-1 rounded-full text-sm">Expiré</span>
                        @else
                        <span class="bg-red-500/20 text-red-400 px-3 py-1 rounded-full text-sm">Annulé</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-6 py-12 text-center text-gray-400">
                        Aucun abonnement trouvé
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Transactions History -->
<div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 overflow-hidden">
    <div class="p-6 border-b border-dark-200">
        <h3 class="text-xl font-bold text-white">
            <i class="fas fa-receipt text-primary-400 mr-2"></i> Historique des transactions
        </h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-dark-50">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase">ID Transaction</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase">Montant</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase">Méthode</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase">Date</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase">Statut</th>
                    <th class="px-6 py-4 text-center text-xs font-medium text-gray-400 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-dark-200">
                @forelse($user->transactions()->latest()->get() as $transaction)
                <tr class="hover:bg-dark-50 transition">
                    <td class="px-6 py-4 text-white font-mono text-sm">{{ $transaction->transaction_id }}</td>
                    <td class="px-6 py-4 text-white font-medium">{{ number_format($transaction->amount) }} {{ $transaction->currency }}</td>
                    <td class="px-6 py-4 text-gray-300 capitalize">{{ $transaction->payment_method }}</td>
                    <td class="px-6 py-4 text-gray-400 text-sm">{{ $transaction->created_at->format('d/m/Y H:i') }}</td>
                    <td class="px-6 py-4">
                        @if($transaction->status === 'completed')
                        <span class="bg-green-500/20 text-green-400 px-3 py-1 rounded-full text-sm">Complété</span>
                        @elseif($transaction->status === 'pending')
                        <span class="bg-yellow-500/20 text-yellow-400 px-3 py-1 rounded-full text-sm">En attente</span>
                        @elseif($transaction->status === 'failed')
                        <span class="bg-red-500/20 text-red-400 px-3 py-1 rounded-full text-sm">Échoué</span>
                        @else
                        <span class="bg-gray-500/20 text-gray-400 px-3 py-1 rounded-full text-sm">Annulé</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-center">
                        <a href="{{ route('transactions.show', $transaction) }}"
                           class="bg-primary-500/20 hover:bg-primary-500 text-primary-400 hover:text-white px-3 py-2 rounded-lg text-sm transition">
                            <i class="fas fa-eye"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-gray-400">
                        Aucune transaction trouvée
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
