@extends('admin.layouts.app')

@section('title', 'Billetterie')
@section('header', 'Billetterie')

@section('content')
@php $isCode = $kind === 'code'; @endphp
<x-admin.page-header title="Séances & codes cinéma"
    subtitle="Deux formes de billetterie : la réservation d'une séance en salle, et l'achat d'un code cinéma valable sans séance fixe. Les places ne sont décomptées qu'une fois le paiement confirmé.">
    <x-slot:actions>
        <a href="{{ route('tickets.check') }}" class="inline-flex items-center gap-2 bg-dark-200 hover:bg-dark-300 text-gray-100 px-4 py-2.5 rounded-lg transition"><i class="fas fa-qrcode"></i> Contrôle des billets</a>
        <a href="{{ route('screenings.create', ['kind' => $kind]) }}" class="inline-flex items-center gap-2 bg-primary-500 hover:bg-primary-600 text-white px-5 py-2.5 rounded-lg font-medium transition">
            <i class="fas fa-plus text-sm"></i> {{ $isCode ? 'Nouvelle offre de codes' : 'Nouvelle séance' }}
        </a>
    </x-slot:actions>
</x-admin.page-header>

<div class="flex items-center gap-1 border-b border-dark-200 mb-6">
    @foreach(['seance' => ['Séances en salle', 'film', $stats['seances']], 'code' => ['Codes cinéma', 'ticket', $stats['codes']]] as $k => [$label, $icon, $n])
        <a href="{{ route('screenings.index', ['kind' => $k]) }}"
           @class(['px-4 py-3 text-sm font-medium border-b-2 -mb-px transition', 'border-primary-400 text-white' => $kind === $k, 'border-transparent text-gray-400 hover:text-gray-200' => $kind !== $k])>
            <i class="fas fa-{{ $icon }} mr-2"></i>{{ $label }} <span class="ml-1 text-xs opacity-60">{{ $n }}</span>
        </a>
    @endforeach
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <x-admin.stat :label="$isCode ? 'Offres' : 'Séances'" :value="$stats['total']" :icon="$isCode ? 'ticket' : 'film'" />
    <x-admin.stat label="Publiées" :value="$stats['published']" icon="eye" tone="emerald" />
    <x-admin.stat label="En vente" :value="$stats['upcoming']" icon="cart-shopping" tone="sky" :hint="$isCode ? 'Codes non expirés' : 'Séances à venir'" />
    <x-admin.stat label="Ventes confirmées" :value="number_format($stats['revenue'], 0, ',', ' ') . ' XAF'" icon="money-bill-wave" tone="violet" />
</div>

<div class="bg-dark-100 rounded-xl border border-dark-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-dark-50 text-gray-500 uppercase text-[11px] tracking-wider">
                <tr>
                    <th class="px-6 py-3 text-left">{{ $isCode ? 'Offre' : 'Film' }}</th>
                    <th class="px-4 py-3 text-left">Cinéma / lieu</th>
                    <th class="px-4 py-3 text-left">{{ $isCode ? 'Validité' : 'Séance' }}</th>
                    <th class="px-4 py-3 text-left">Tarifs & ventes</th>
                    <th class="px-4 py-3 text-left">Statut</th>
                    <th class="px-6 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-dark-200">
                @forelse($screenings as $s)
                    @php
                        $past = $isCode ? ($s->valid_until && $s->valid_until->isPast()) : $s->starts_at->isPast();
                        $badge = [
                            'draft'     => ['Brouillon', 'gray'],
                            'published' => [$past ? ($isCode ? 'Expirée' : 'Passée') : 'En vente', $past ? 'slate' : 'emerald'],
                            'canceled'  => ['Annulée', 'rose'],
                        ][$s->status] ?? ['—', 'gray'];
                    @endphp
                    <tr class="hover:bg-dark-50/40 transition {{ $past ? 'opacity-70' : '' }}">
                        <td class="px-6 py-3">
                            <div class="flex items-center gap-3">
                                <x-admin.thumb :path="$s->media?->cover_path ?: $s->media?->thumbnail_path" :icon="$isCode ? 'ticket' : 'film'" class="w-9 h-12 rounded-md" />
                                <span class="text-white font-medium">{{ $s->movie_title }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-gray-300">
                            <div>{{ $s->cinema_name }}</div>
                            <div class="text-gray-500 text-xs">{{ $s->location }} @if($s->country) · {{ $s->country->flag_emoji }} {{ $s->country->name }} @endif</div>
                        </td>
                        <td class="px-4 py-3 text-gray-300">
                            @if($isCode)
                                jusqu'au {{ $s->valid_until?->format('d/m/Y') ?? '—' }}
                                <div class="text-xs text-gray-500">vente depuis le {{ $s->starts_at->format('d/m/Y') }}</div>
                            @else
                                {{ $s->starts_at->format('d/m/Y H:i') }}
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @foreach($s->ticketTypes as $t)
                                <div class="flex items-center gap-2 text-xs mb-1">
                                    <span class="px-2 py-0.5 rounded bg-dark-50 text-gray-200">{{ $t->name }}</span>
                                    <span class="text-primary-300">{{ number_format($t->price, in_array($t->currency, ['XAF', 'XOF'], true) ? 0 : 2, ',', ' ') }} {{ $t->currency }}</span>
                                    <span class="text-gray-500">{{ $t->sold_seats }}/{{ $t->capacity }}</span>
                                </div>
                            @endforeach
                            @if($s->confirmed_reservations)
                                <div class="text-xs text-gray-500 mt-1">{{ $s->confirmed_reservations }} commande(s) · {{ (int) $s->redeemed_entries }} entrée(s) validée(s)</div>
                            @endif
                        </td>
                        <td class="px-4 py-3"><x-admin.badge :tone="$badge[1]">{{ $badge[0] }}</x-admin.badge></td>
                        <td class="px-6 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('screenings.edit', $s) }}" title="Modifier" class="bg-primary-500/15 hover:bg-primary-500 text-primary-300 hover:text-white px-3 py-2 rounded-lg transition"><i class="fas fa-pen"></i></a>
                                @if($s->status !== 'canceled')
                                    <form action="{{ route('screenings.cancel', $s) }}" method="POST"
                                          data-confirm="Annuler cette offre ? Elle ne sera plus en vente." data-confirm-type="warning" data-confirm-title="Annuler l'offre" data-confirm-confirm="Annuler l'offre">
                                        @csrf
                                        <button title="Annuler" class="bg-amber-500/15 hover:bg-amber-500 text-amber-300 hover:text-white px-3 py-2 rounded-lg transition"><i class="fas fa-ban"></i></button>
                                    </form>
                                @endif
                                <form action="{{ route('screenings.destroy', $s) }}" method="POST"
                                      data-confirm="Supprimer définitivement cette offre ?" data-confirm-type="danger" data-confirm-title="Supprimer" data-confirm-confirm="Supprimer">
                                    @csrf @method('DELETE')
                                    <button title="Supprimer" class="bg-rose-500/15 hover:bg-rose-500 text-rose-300 hover:text-white px-3 py-2 rounded-lg transition"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">
                        <x-admin.empty :icon="$isCode ? 'ticket' : 'film'" :title="$isCode ? 'Aucune offre de codes cinéma' : 'Aucune séance programmée'"
                            :text="$isCode ? 'Un code cinéma se vend comme un billet, sans séance fixe : le client le présente en caisse avant sa date d\'expiration.' : 'Programmez une séance : film, salle, date et catégories de places.'" />
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
