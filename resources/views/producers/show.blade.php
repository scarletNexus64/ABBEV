@extends('admin.layouts.app')

@section('title', 'Producteur - ABBEV')
@section('header', 'Détails du producteur')

@section('content')
<!-- Back + actions -->
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <a href="{{ route('producers.index') }}" class="inline-flex items-center text-primary-400 hover:text-primary-300 transition">
        <i class="fas fa-arrow-left mr-2"></i> Retour aux producteurs
    </a>

    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('transfers.index', ['target' => $user->getRouteKey()]) }}"
           class="bg-primary-500/20 hover:bg-primary-500 text-primary-300 hover:text-white px-4 py-2 rounded-lg text-sm transition">
            <i class="fas fa-right-left mr-1"></i> Lui transférer des données
        </a>
        <form action="{{ route('producers.resend', $user) }}" method="POST" class="inline"
              data-confirm="Régénérer un nouveau mot de passe et l'envoyer par email à {{ $user->email }} ? L'ancien sera invalidé."
              data-confirm-type="primary" data-confirm-confirm="Renvoyer">
            @csrf
            <button type="submit" class="bg-sky-500/20 hover:bg-sky-500 text-sky-400 hover:text-white px-4 py-2 rounded-lg text-sm transition">
                <i class="fas fa-paper-plane mr-1"></i> Renvoyer les identifiants
            </button>
        </form>
        <form action="{{ route('producers.destroy', $user) }}" method="POST" class="inline"
              data-confirm="Supprimer ce producteur ? Ses contenus et modules restent sur la plateforme, gérés par l'admin, et son équipe perd l'accès au panel."
              data-confirm-type="danger" data-confirm-title="Supprimer le producteur" data-confirm-confirm="Supprimer">
            @csrf @method('DELETE')
            <button type="submit" class="bg-red-500/20 hover:bg-red-500 text-red-400 hover:text-white px-4 py-2 rounded-lg text-sm transition">
                <i class="fas fa-trash mr-1"></i> Supprimer
            </button>
        </form>
    </div>
</div>

<!-- Producer info -->
<div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6 mb-6">
    <div class="flex items-center gap-6">
        <div class="w-20 h-20 rounded-full bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center text-white font-bold text-3xl">
            {{ strtoupper(substr($user->name, 0, 1)) }}
        </div>
        <div class="flex-1">
            <h2 class="text-2xl font-bold text-white mb-1">{{ $user->name }}</h2>
            <p class="text-gray-400 font-mono">{{ $user->email }}</p>
            <div class="flex items-center gap-4 mt-3">
                <span class="text-sm text-gray-400">
                    <i class="fas fa-calendar-alt mr-1"></i> Créé le {{ $user->created_at->format('d/m/Y') }}
                </span>
                <span class="bg-primary-500/20 text-primary-300 px-3 py-1 rounded-full text-sm">
                    <i class="fas fa-clapperboard mr-1"></i> Producteur
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Abonnement producteur -->
<x-admin.card class="mb-6" title="Abonnement producteur" icon="lock"
    :subtitle="$access['payment_required'] ? 'Sans abonnement en cours, son espace est entièrement verrouillé (équipe comprise).' : 'Pack producteur désactivé : son espace est ouvert sans paiement.'">
    <div class="flex flex-col lg:flex-row lg:items-center gap-5">
        <div class="flex-1 min-w-0">
            @if($access['ends_at'])
                <x-admin.badge tone="emerald" icon="lock-open">Actif</x-admin.badge>
                <p class="text-white mt-2">Accès jusqu'au <strong>{{ $access['ends_at']->format('d/m/Y') }}</strong></p>
            @elseif($access['payment_required'])
                <x-admin.badge tone="amber" icon="lock">Verrouillé</x-admin.badge>
                <p class="text-gray-400 mt-2 text-sm">Aucune période payée ou offerte en cours.</p>
            @else
                <x-admin.badge tone="gray" icon="lock-open">Ouvert (paiement non exigé)</x-admin.badge>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <form action="{{ route('producers.access.grant', $user) }}" method="POST" class="flex items-center gap-2"
                  data-confirm="Offrir l'accès à l'espace de {{ $user->name }} sans paiement ? La période s'ajoute après l'accès en cours."
                  data-confirm-type="primary" data-confirm-confirm="Offrir">
                @csrf
                <select name="months" class="bg-dark-50 border border-dark-200 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-primary-500">
                    @foreach([1 => '1 mois', 3 => '3 mois', 6 => '6 mois', 12 => '1 an', 24 => '2 ans'] as $months => $label)
                        <option value="{{ $months }}">{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="bg-emerald-500/20 hover:bg-emerald-500 text-emerald-300 hover:text-white px-4 py-2 rounded-lg text-sm transition">
                    <i class="fas fa-gift mr-1"></i> Offrir l'accès
                </button>
            </form>
            @if($access['ends_at'])
                <form action="{{ route('producers.access.revoke', $user) }}" method="POST"
                      data-confirm="Couper l'accès de {{ $user->name }} ? Les périodes en cours et à venir (payées ou offertes) sont annulées et son espace est verrouillé immédiatement."
                      data-confirm-type="danger" data-confirm-title="Couper l'accès" data-confirm-confirm="Couper l'accès">
                    @csrf @method('DELETE')
                    <button type="submit" class="bg-red-500/20 hover:bg-red-500 text-red-400 hover:text-white px-4 py-2 rounded-lg text-sm transition">
                        <i class="fas fa-lock mr-1"></i> Couper l'accès
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if($access['history']->isNotEmpty())
        <div class="mt-5 border-t border-dark-200 pt-4 space-y-2">
            @foreach($access['history'] as $period)
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                    <span class="text-gray-300 w-48">{{ $period->starts_at->format('d/m/Y') }} → {{ $period->expires_at->format('d/m/Y') }}</span>
                    @if($period->source === 'admin')
                        <x-admin.badge tone="violet" icon="gift">Offert{{ $period->grantedBy ? ' par ' . $period->grantedBy->name : '' }}</x-admin.badge>
                    @else
                        <x-admin.badge tone="sky" icon="receipt">
                            Payé{{ $period->transaction ? ' · ' . number_format((float) $period->transaction->amount, 0, ',', ' ') . ' FCFA · ' . ($period->transaction->payment_method === 'stripe' ? 'carte' : 'Mobile Money') : '' }}
                        </x-admin.badge>
                    @endif
                    @if($period->status === 'cancelled')
                        <x-admin.badge tone="rose">Annulé</x-admin.badge>
                    @elseif($period->expires_at->isPast())
                        <x-admin.badge>Terminé</x-admin.badge>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</x-admin.card>

<!-- Stats -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-6">
    @php
        $cards = [
            ['label' => 'Contenus', 'value' => $stats['total'],  'icon' => 'fa-photo-film', 'color' => 'primary'],
            ['label' => 'Films',    'value' => $stats['movies'], 'icon' => 'fa-film',       'color' => 'blue'],
            ['label' => 'Séries',   'value' => $stats['series'], 'icon' => 'fa-tv',         'color' => 'purple'],
            ['label' => 'Vues',     'value' => number_format($stats['views']), 'icon' => 'fa-eye', 'color' => 'green'],
        ];
    @endphp
    @foreach($cards as $c)
    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-400">{{ $c['label'] }}</p>
                <p class="text-2xl font-bold text-white mt-1">{{ $c['value'] }}</p>
            </div>
            <div class="w-12 h-12 bg-{{ $c['color'] }}-500/20 rounded-lg flex items-center justify-center">
                <i class="fas {{ $c['icon'] }} text-xl text-{{ $c['color'] }}-400"></i>
            </div>
        </div>
    </div>
    @endforeach
</div>

<!-- Contenus -->
<div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 overflow-hidden">
    <div class="p-6 border-b border-dark-200 flex items-center justify-between">
        <h3 class="text-xl font-bold text-white">
            <i class="fas fa-photo-film text-primary-400 mr-2"></i> Contenus uploadés
        </h3>
        <span class="text-gray-400 text-sm">{{ $stats['total'] }} élément(s)</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-dark-50">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase">Titre</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase">Type</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase">Catégorie</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase">Source</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase">Vues</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase">Ajouté</th>
                    <th class="px-6 py-4 text-center text-xs font-medium text-gray-400 uppercase">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-dark-200">
                @forelse($media as $item)
                <tr class="hover:bg-dark-50 transition">
                    <td class="px-6 py-4 text-white font-medium">{{ $item->title }}</td>
                    <td class="px-6 py-4">
                        @if($item->type === 'series')
                        <span class="bg-purple-500/20 text-purple-300 px-2 py-1 rounded text-xs"><i class="fas fa-tv mr-1"></i>Série</span>
                        @else
                        <span class="bg-blue-500/20 text-blue-300 px-2 py-1 rounded text-xs"><i class="fas fa-film mr-1"></i>Film</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-gray-300">{{ $item->category->name ?? '—' }}</td>
                    <td class="px-6 py-4 text-gray-400 text-sm capitalize">{{ $item->video_provider ?? '—' }}</td>
                    <td class="px-6 py-4 text-gray-300">{{ number_format($item->views_count) }}</td>
                    <td class="px-6 py-4 text-gray-400 text-sm">{{ $item->created_at->format('d/m/Y') }}</td>
                    <td class="px-6 py-4 text-center">
                        <a href="{{ route('media.show', $item) }}" title="Voir le contenu"
                           class="w-9 h-9 inline-flex items-center justify-center bg-primary-500/20 hover:bg-primary-500 text-primary-400 hover:text-white rounded-lg text-sm transition">
                            <i class="fas fa-eye"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-gray-400">
                        <i class="fas fa-photo-film text-4xl mb-3 block opacity-50"></i>
                        Ce producteur n'a encore uploadé aucun contenu.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<!-- Équipe -->
<div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 overflow-hidden mt-6">
    <div class="p-6 border-b border-dark-200 flex items-center justify-between">
        <h3 class="text-xl font-bold text-white">
            <i class="fas fa-people-group text-primary-400 mr-2"></i> Équipe
        </h3>
        <span class="text-gray-400 text-sm">{{ $team->count() }} membre(s) · gérée par le producteur</span>
    </div>
    @forelse($team as $member)
        <div class="px-6 py-4 border-b border-dark-200/70 last:border-0 flex flex-col md:flex-row md:items-center gap-3">
            <div class="md:w-72 min-w-0">
                <p class="text-white">{{ $member->name }}</p>
                <p class="text-gray-400 font-mono text-xs break-all">{{ $member->email }}</p>
            </div>
            <div class="flex flex-wrap gap-1.5 flex-1">
                @foreach($member->permissions ?? [] as $key)
                    @isset(\App\Models\User::MODULES[$key])
                        <x-admin.badge tone="primary" :icon="\App\Models\User::MODULES[$key]['icon']">{{ \App\Models\User::MODULES[$key]['label'] }}</x-admin.badge>
                    @endisset
                @endforeach
            </div>
        </div>
    @empty
        <p class="px-6 py-8 text-center text-gray-400 text-sm">Aucun membre invité pour l'instant.</p>
    @endforelse
</div>
@endsection
