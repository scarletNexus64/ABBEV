@extends('admin.layouts.app')

@section('title', 'Contrôle des billets')
@section('header', 'Billetterie')

@section('content')
<x-admin.page-header title="Contrôle des billets"
    subtitle="Saisissez la référence présentée par le client (billet de séance ou code cinéma) : vous voyez aussitôt s'il est valable, puis vous validez les entrées." />

<div class="grid grid-cols-1 xl:grid-cols-5 gap-6">
    <div class="xl:col-span-3 space-y-6">
        <x-admin.card>
            <form method="GET" action="{{ route('tickets.check') }}" class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 font-mono text-lg">ABBEV-</span>
                    <input type="text" name="code" value="{{ \Illuminate\Support\Str::after(strtoupper($code), 'ABBEV-') }}" autofocus autocomplete="off"
                           placeholder="XXXXXXXX" maxlength="30"
                           class="w-full bg-dark-50 border border-dark-200 rounded-xl pl-24 pr-4 py-4 text-2xl font-mono tracking-[0.2em] uppercase text-white placeholder-gray-700 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20">
                </div>
                <button class="bg-primary-500 hover:bg-primary-600 text-white px-8 py-4 rounded-xl font-semibold transition"><i class="fas fa-magnifying-glass mr-2"></i>Vérifier</button>
            </form>
        </x-admin.card>

        @if($code !== '')
            @if(! $reservation)
                <div class="rounded-2xl border border-rose-500/40 bg-rose-500/10 p-6 flex items-center gap-4">
                    <div class="w-14 h-14 rounded-full bg-rose-500/20 flex items-center justify-center"><i class="fas fa-xmark text-2xl text-rose-300"></i></div>
                    <div>
                        <p class="text-lg font-semibold text-white">Référence inconnue</p>
                        <p class="text-sm text-rose-200/80">Aucun billet ni code ne correspond à « {{ strtoupper($code) }} ». Vérifiez la saisie.</p>
                    </div>
                </div>
            @else
                @php
                    $ok = $verdict['ok'];
                    $offer = $reservation->screening;
                    $isCode = $offer?->isCode();
                @endphp
                <div class="rounded-2xl border {{ $ok ? 'border-emerald-500/40 bg-emerald-500/10' : 'border-amber-500/40 bg-amber-500/10' }} overflow-hidden">
                    <div class="p-6 flex items-center gap-4">
                        <div class="w-14 h-14 rounded-full {{ $ok ? 'bg-emerald-500/20' : 'bg-amber-500/20' }} flex items-center justify-center shrink-0">
                            <i class="fas fa-{{ $ok ? 'check' : 'triangle-exclamation' }} text-2xl {{ $ok ? 'text-emerald-300' : 'text-amber-300' }}"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-lg font-semibold text-white">{{ $ok ? 'Billet valable' : 'Billet non valable' }}</p>
                            <p class="text-sm {{ $ok ? 'text-emerald-200/80' : 'text-amber-200/90' }}">{{ $ok ? $reservation->remainingEntries() . ' entrée(s) disponible(s) sur ' . $reservation->quantity . '.' : $verdict['reason'] }}</p>
                        </div>
                        <span class="font-mono text-lg text-white tracking-wider">{{ $reservation->reference }}</span>
                    </div>
                    <dl class="grid grid-cols-2 md:grid-cols-4 gap-4 px-6 py-5 bg-dark-100/60 text-sm">
                        <div><dt class="text-xs text-gray-500">{{ $isCode ? 'Offre' : 'Film' }}</dt><dd class="text-white">{{ $offer?->movie_title ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Cinéma</dt><dd class="text-white">{{ $offer?->cinema_name ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-gray-500">{{ $isCode ? 'Valable jusqu\'au' : 'Séance' }}</dt>
                            <dd class="text-white">{{ $isCode ? ($offer?->valid_until?->format('d/m/Y') ?? '—') : ($offer?->starts_at?->format('d/m/Y H:i') ?? '—') }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Catégorie</dt><dd class="text-white">{{ $reservation->ticketType?->name ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Titulaire</dt><dd class="text-white">{{ $reservation->user?->name ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Entrées</dt><dd class="text-white">{{ $reservation->redeemed_quantity }} / {{ $reservation->quantity }} utilisée(s)</dd></div>
                        <div><dt class="text-xs text-gray-500">Payé</dt><dd class="text-white">{{ number_format($reservation->total_amount, 0, ',', ' ') }} {{ $reservation->currency }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Dernier passage</dt><dd class="text-white">{{ $reservation->redeemed_at?->format('d/m H:i') ?? '—' }}</dd></div>
                    </dl>
                    @if($ok)
                        <form action="{{ route('tickets.redeem', $reservation) }}" method="POST" class="px-6 py-5 flex flex-col sm:flex-row sm:items-center gap-3 border-t border-dark-200">
                            @csrf
                            <label class="text-sm text-gray-300">Entrées à valider</label>
                            <input type="number" name="entries" value="{{ $reservation->remainingEntries() }}" min="1" max="{{ $reservation->remainingEntries() }}"
                                   class="w-28 bg-dark-50 border border-dark-200 rounded-lg px-3 py-2.5 text-white text-lg text-center focus:outline-none focus:border-emerald-500">
                            <button class="sm:ml-auto bg-emerald-500 hover:bg-emerald-600 text-white px-8 py-3 rounded-xl font-semibold transition"><i class="fas fa-door-open mr-2"></i>Valider l'entrée</button>
                        </form>
                    @endif
                </div>
            @endif
        @endif
    </div>

    <x-admin.card class="xl:col-span-2 self-start" title="Derniers passages" icon="clock-rotate-left" padding="p-0">
        @forelse($recent as $r)
            <a href="{{ route('tickets.check', ['code' => $r->reference]) }}" class="flex items-center gap-3 px-6 py-3 border-b border-dark-200 last:border-0 hover:bg-dark-50/40 transition">
                <i class="fas fa-{{ $r->screening?->isCode() ? 'ticket' : 'film' }} text-gray-500 w-4"></i>
                <div class="flex-1 min-w-0">
                    <p class="text-sm text-white font-mono">{{ $r->reference }}</p>
                    <p class="text-xs text-gray-500 truncate">{{ $r->screening?->movie_title }} · {{ $r->redeemed_quantity }}/{{ $r->quantity }}</p>
                </div>
                <span class="text-xs text-gray-500">{{ $r->redeemed_at->diffForHumans() }}</span>
            </a>
        @empty
            <p class="px-6 py-6 text-sm text-gray-500">Aucun billet validé pour le moment.</p>
        @endforelse
    </x-admin.card>
</div>
@endsection
