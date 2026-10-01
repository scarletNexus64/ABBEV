@extends('admin.layouts.app')

@section('title', 'Détails Transaction - ABBEV')
@section('header', 'Détails de la Transaction')

@section('content')
<!-- Back Button -->
<div class="mb-6">
    <a href="{{ route('transactions.index') }}" class="inline-flex items-center text-primary-400 hover:text-primary-300 transition">
        <i class="fas fa-arrow-left mr-2"></i> Retour à la liste
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Main Transaction Info -->
    <div class="lg:col-span-2">
        <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-8">
            <!-- Header with Status -->
            <div class="flex items-center justify-between mb-6 pb-6 border-b border-dark-200">
                <div>
                    <h2 class="text-2xl font-bold text-white mb-1">Transaction</h2>
                    <p class="font-mono text-gray-400">{{ $transaction->transaction_id }}</p>
                </div>
                <div>
                    @if($transaction->status === 'completed')
                    <span class="bg-green-500/20 text-green-400 px-4 py-2 rounded-full text-sm font-medium">
                        <i class="fas fa-check-circle mr-1"></i> Complété
                    </span>
                    @elseif($transaction->status === 'pending')
                    <span class="bg-yellow-500/20 text-yellow-400 px-4 py-2 rounded-full text-sm font-medium">
                        <i class="fas fa-clock mr-1"></i> En attente
                    </span>
                    @elseif($transaction->status === 'failed')
                    <span class="bg-red-500/20 text-red-400 px-4 py-2 rounded-full text-sm font-medium">
                        <i class="fas fa-times-circle mr-1"></i> Échoué
                    </span>
                    @else
                    <span class="bg-gray-500/20 text-gray-400 px-4 py-2 rounded-full text-sm font-medium">
                        <i class="fas fa-ban mr-1"></i> Annulé
                    </span>
                    @endif
                </div>
            </div>

            <!-- Transaction Details Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Amount -->
                <div>
                    <p class="text-sm text-gray-400 mb-1">Montant</p>
                    <p class="text-2xl font-bold text-white">{{ number_format($transaction->amount) }} {{ $transaction->currency }}</p>
                </div>

                <!-- Net Amount -->
                <div>
                    <p class="text-sm text-gray-400 mb-1">Montant net</p>
                    <p class="text-2xl font-bold text-primary-400">{{ number_format($transaction->net_amount) }} {{ $transaction->currency }}</p>
                </div>

                <!-- Fees -->
                @if($transaction->fees > 0)
                <div>
                    <p class="text-sm text-gray-400 mb-1">Frais</p>
                    <p class="text-lg font-medium text-gray-300">{{ number_format($transaction->fees) }} {{ $transaction->currency }}</p>
                </div>
                @endif

                <!-- Payment Method -->
                <div>
                    <p class="text-sm text-gray-400 mb-1">Méthode de paiement</p>
                    <p class="text-lg font-medium text-white capitalize">
                        @if($transaction->payment_method === 'mobile')
                        <i class="fas fa-mobile-alt text-primary-400 mr-2"></i>
                        @elseif($transaction->payment_method === 'card')
                        <i class="fas fa-credit-card text-primary-400 mr-2"></i>
                        @elseif($transaction->payment_method === 'paypal')
                        <i class="fab fa-paypal text-primary-400 mr-2"></i>
                        @elseif($transaction->payment_method === 'cash')
                        <i class="fas fa-money-bill text-primary-400 mr-2"></i>
                        @else
                        <i class="fas fa-wallet text-primary-400 mr-2"></i>
                        @endif
                        {{ $transaction->payment_method }}
                    </p>
                </div>

                <!-- Type -->
                <div>
                    <p class="text-sm text-gray-400 mb-1">Type</p>
                    <p class="text-lg font-medium text-white capitalize">{{ $transaction->type === 'producer_subscription' ? 'Pack producteur' : $transaction->type }}</p>
                </div>

                <!-- External Reference -->
                @if($transaction->external_reference)
                <div>
                    <p class="text-sm text-gray-400 mb-1">Référence externe</p>
                    <p class="text-sm font-mono text-gray-300">{{ $transaction->external_reference }}</p>
                </div>
                @endif

                <!-- Created At -->
                <div>
                    <p class="text-sm text-gray-400 mb-1">Date de création</p>
                    <p class="text-lg font-medium text-white">{{ $transaction->created_at->format('d/m/Y H:i') }}</p>
                </div>

                <!-- Completed At -->
                @if($transaction->completed_at)
                <div>
                    <p class="text-sm text-gray-400 mb-1">Date de complétion</p>
                    <p class="text-lg font-medium text-white">{{ $transaction->completed_at->format('d/m/Y H:i') }}</p>
                </div>
                @endif
            </div>

            <!-- Description -->
            @if($transaction->description)
            <div class="mt-6 pt-6 border-t border-dark-200">
                <p class="text-sm text-gray-400 mb-2">Description</p>
                <p class="text-white">{{ $transaction->description }}</p>
            </div>
            @endif

            <!-- Metadata -->
            @if($transaction->metadata)
            <div class="mt-6 pt-6 border-t border-dark-200">
                <p class="text-sm text-gray-400 mb-3">Métadonnées</p>
                <div class="bg-dark-50 rounded-lg p-4">
                    <pre class="text-xs text-gray-300 overflow-x-auto">{{ json_encode($transaction->metadata, JSON_PRETTY_PRINT) }}</pre>
                </div>
            </div>
            @endif
        </div>
    </div>

    <!-- Sidebar -->
    <div class="space-y-6">
        <!-- User Info Card -->
        <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
            <h3 class="text-lg font-bold text-white mb-4">
                <i class="fas fa-user text-primary-400 mr-2"></i> Utilisateur
            </h3>
            <div class="flex items-center gap-3 mb-4">
                <div class="w-12 h-12 rounded-full bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center text-white font-bold">
                    {{ strtoupper(substr($transaction->user->name, 0, 1)) }}
                </div>
                <div>
                    <p class="text-white font-medium">{{ $transaction->user->name }}</p>
                    <p class="text-gray-400 text-sm">{{ $transaction->user->email }}</p>
                </div>
            </div>
            <a href="{{ route('users.show', $transaction->user) }}"
               class="w-full bg-primary-500/20 hover:bg-primary-500 text-primary-400 hover:text-white px-4 py-2 rounded-lg text-sm transition text-center block">
                <i class="fas fa-eye mr-2"></i> Voir le profil
            </a>
        </div>

        <!-- Payer Info Card -->
        @if($transaction->payer_email || $transaction->payer_name)
        <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
            <h3 class="text-lg font-bold text-white mb-4">
                <i class="fas fa-credit-card text-primary-400 mr-2"></i> Payeur
            </h3>
            @if($transaction->payer_name)
            <div class="mb-3">
                <p class="text-sm text-gray-400 mb-1">Nom</p>
                <p class="text-white">{{ $transaction->payer_name }}</p>
            </div>
            @endif
            @if($transaction->payer_email)
            <div>
                <p class="text-sm text-gray-400 mb-1">Email</p>
                <p class="text-white text-sm">{{ $transaction->payer_email }}</p>
            </div>
            @endif
        </div>
        @endif

        <!-- Timeline Card -->
        <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
            <h3 class="text-lg font-bold text-white mb-4">
                <i class="fas fa-clock text-primary-400 mr-2"></i> Chronologie
            </h3>
            <div class="space-y-4">
                <div class="flex items-start">
                    <div class="w-2 h-2 bg-primary-400 rounded-full mt-2 mr-3"></div>
                    <div>
                        <p class="text-white text-sm">Transaction créée</p>
                        <p class="text-gray-400 text-xs">{{ $transaction->created_at->format('d/m/Y H:i:s') }}</p>
                    </div>
                </div>
                @if($transaction->completed_at)
                <div class="flex items-start">
                    <div class="w-2 h-2 bg-green-400 rounded-full mt-2 mr-3"></div>
                    <div>
                        <p class="text-white text-sm">Transaction complétée</p>
                        <p class="text-gray-400 text-xs">{{ $transaction->completed_at->format('d/m/Y H:i:s') }}</p>
                    </div>
                </div>
                @endif
                <div class="flex items-start">
                    <div class="w-2 h-2 bg-gray-400 rounded-full mt-2 mr-3"></div>
                    <div>
                        <p class="text-white text-sm">Dernière mise à jour</p>
                        <p class="text-gray-400 text-xs">{{ $transaction->updated_at->format('d/m/Y H:i:s') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
