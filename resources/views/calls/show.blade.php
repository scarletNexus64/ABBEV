@extends('admin.layouts.app')

@section('title', $call->title)
@section('header', 'Appels à projets')

@section('content')
@php $callMeta = \App\Models\ProjectCall::LOOK; @endphp
@php
    [$icon, $tone] = $callMeta[$call->type] ?? ['lightbulb', 'primary'];
    $funding = $call->isFunding();
    $statuses = $funding ? \App\Models\ProjectPledge::STATUSES : \App\Models\ProjectSubmission::STATUSES;
    $total = $counts->sum();
@endphp

<x-admin.page-header :title="$call->title" :back="route('calls.index', ['type' => $call->type])" :back-label="'Appels · ' . \App\Models\ProjectCall::shortTypeLabel($call->type)">
    <x-slot:actions>
        <a href="{{ route('calls.export', $call) }}" class="inline-flex items-center gap-2 bg-dark-200 hover:bg-dark-300 text-gray-200 px-4 py-2.5 rounded-lg text-sm transition" download><i class="fas fa-file-csv"></i> Exporter</a>
        <a href="{{ route('calls.edit', $call) }}" class="inline-flex items-center gap-2 bg-primary-500 hover:bg-primary-600 text-white px-4 py-2.5 rounded-lg text-sm font-medium transition"><i class="fas fa-pen"></i> Modifier</a>
        <form action="{{ route('calls.destroy', $call) }}" method="POST"
              data-confirm="Supprimer cet appel et ses {{ $total }} participation(s) ?" data-confirm-type="danger" data-confirm-title="Supprimer l'appel" data-confirm-confirm="Supprimer">
            @csrf @method('DELETE')
            <button class="inline-flex items-center bg-rose-500/15 hover:bg-rose-500 text-rose-300 hover:text-white px-4 py-2.5 rounded-lg text-sm transition"><i class="fas fa-trash"></i></button>
        </form>
    </x-slot:actions>
</x-admin.page-header>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
    <x-admin.card class="xl:col-span-2" padding="p-0">
        <div class="flex flex-col md:flex-row">
            <x-admin.thumb :path="$call->cover_path" :icon="$icon" class="md:w-64 aspect-video md:aspect-auto md:rounded-l-xl" />
            <div class="p-6 flex-1">
                <div class="flex flex-wrap items-center gap-2 mb-2">
                    <x-admin.badge :tone="$tone" :icon="$icon">{{ \App\Models\ProjectCall::shortTypeLabel($call->type) }} · {{ $call->targetLabel() }}</x-admin.badge>
                    @include('calls._status', ['call' => $call])
                </div>
                <p class="text-sm text-gray-300">{{ $call->summary }}</p>
                <dl class="grid grid-cols-2 gap-x-6 gap-y-2 mt-4 text-sm">
                    <div><dt class="text-gray-500 text-xs">Porteur / organisateur</dt><dd class="text-gray-200">{{ $call->organizer ?: '—' }}</dd></div>
                    <div><dt class="text-gray-500 text-xs">Clôture</dt><dd class="text-gray-200">{{ $call->closes_at ? $call->closes_at->format('d/m/Y H:i') . ' · ' . $call->closes_at->diffForHumans() : 'Aucune' }}</dd></div>
                    @if(! $funding)
                        <div><dt class="text-gray-500 text-xs">Dotation</dt><dd class="text-gray-200">{{ $call->prize ?: '—' }}</dd></div>
                        <div><dt class="text-gray-500 text-xs">{{ $call->type === 'musique' ? 'Style' : 'Genre' }}</dt><dd class="text-gray-200">{{ ($call->type === 'musique' ? $call->music_style : $call->genre) ?: '—' }}</dd></div>
                    @endif
                </dl>
            </div>
        </div>
    </x-admin.card>

    @if($funding)
        @php $pct = (int) $call->progressPercent(); @endphp
        <x-admin.card title="Collecte" icon="hand-holding-dollar">
            <p class="text-3xl font-bold text-white">{{ \App\Support\Money::format($call->raisedAmount(), $call->currency) }}</p>
            <p class="text-sm text-gray-400">sur {{ \App\Support\Money::format($call->goal_amount, $call->currency) }} · <strong class="text-emerald-300">{{ $pct }} %</strong></p>
            <div class="h-3 rounded-full bg-dark-300 overflow-hidden mt-3"><div class="h-full bg-gradient-to-r from-emerald-500 to-emerald-300" style="width: {{ min(100, $pct) }}%"></div></div>
            <dl class="mt-4 space-y-1.5 text-sm">
                <div class="flex justify-between"><dt class="text-gray-400">Soutiens confirmés</dt><dd class="text-white">{{ \App\Support\Money::format($call->raisedAmount() - (float) $call->raised_offline, $call->currency) }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-400">Réuni hors app</dt><dd class="text-white">{{ \App\Support\Money::format($call->raised_offline, $call->currency) }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-400">Promesses à confirmer</dt><dd class="text-amber-300">{{ \App\Support\Money::format($pendingAmount, $call->currency) }}</dd></div>
            </dl>
        </x-admin.card>
    @else
        <x-admin.card title="Candidatures" icon="inbox">
            <p class="text-4xl font-bold text-white">{{ $total }}</p>
            <div class="mt-4 space-y-2">
                @foreach($statuses as $key => $label)
                    <div class="flex items-center justify-between text-sm"><span class="text-gray-400">{{ $label }}</span><span class="text-white tabular-nums">{{ (int) ($counts[$key] ?? 0) }}</span></div>
                @endforeach
            </div>
        </x-admin.card>
    @endif
</div>

<div class="flex flex-wrap gap-2 mb-5">
    <a href="{{ route('calls.show', $call) }}" @class(['px-3 py-1.5 rounded-lg text-sm border transition', 'bg-white text-dark-100 border-white font-semibold' => ! $status, 'border-dark-200 text-gray-300 hover:border-primary-500/50' => $status])>Toutes <span class="opacity-60">{{ $total }}</span></a>
    @foreach($statuses as $key => $label)
        <a href="{{ route('calls.show', [$call, 'status' => $key]) }}" @class(['px-3 py-1.5 rounded-lg text-sm border transition', 'bg-white text-dark-100 border-white font-semibold' => $status === $key, 'border-dark-200 text-gray-300 hover:border-primary-500/50' => $status !== $key])>{{ $label }} <span class="opacity-60">{{ (int) ($counts[$key] ?? 0) }}</span></a>
    @endforeach
</div>

@if($funding)
    <x-admin.card title="Promesses de soutien" icon="handshake" padding="p-0"
        subtitle="Contactez le soutien (téléphone), finalisez le versement hors de l'app, puis confirmez : le montant rejoint alors la jauge publique.">
        @forelse($pledges as $p)
            @php $pTone = ['pending' => 'amber', 'confirmed' => 'emerald', 'cancelled' => 'gray'][$p->status] ?? 'gray'; @endphp
            <div class="flex flex-col lg:flex-row lg:items-center gap-4 px-6 py-4 border-b border-dark-200 last:border-0">
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-white font-semibold">{{ \App\Support\Money::format($p->amount, $p->currency) }}</p>
                        <x-admin.badge :tone="$pTone">{{ $p->statusLabel() }}</x-admin.badge>
                        @if($p->reward_title)<x-admin.badge tone="violet" icon="gift">{{ $p->reward_title }}</x-admin.badge>@endif
                        @if($p->is_anonymous)<x-admin.badge tone="gray" icon="user-secret">Anonyme</x-admin.badge>@endif
                    </div>
                    <p class="text-sm text-gray-400 mt-1">{{ $p->user?->name ?? 'Compte supprimé' }}
                        @if($p->user?->email) · <a href="mailto:{{ $p->user->email }}" class="text-primary-300">{{ $p->user->email }}</a> @endif
                        @if($p->phone) · <a href="tel:{{ $p->phone }}" class="text-primary-300">{{ $p->phone }}</a> @endif
                        · {{ $p->created_at->diffForHumans() }}</p>
                    @if($p->message)<p class="text-sm text-gray-300 mt-1 italic">« {{ $p->message }} »</p>@endif
                </div>
                <form action="{{ route('calls.pledges.review', [$call, $p]) }}" method="POST" class="flex items-center gap-2 shrink-0">
                    @csrf @method('PATCH')
                    <input type="text" name="admin_note" value="{{ $p->admin_note }}" placeholder="Note interne"
                           class="w-44 bg-dark-50 border border-dark-200 rounded-lg px-3 py-2 text-white text-xs placeholder-gray-600 focus:outline-none focus:border-primary-500">
                    <button type="submit" name="status" value="{{ $p->status }}" class="sr-only" tabindex="-1">Enregistrer</button>
                    @if($p->status !== 'confirmed')
                        <button name="status" value="confirmed" class="px-3 py-2 rounded-lg text-xs font-medium bg-emerald-500/15 text-emerald-300 hover:bg-emerald-500 hover:text-white transition"><i class="fas fa-check mr-1"></i>Fonds reçus</button>
                    @endif
                    @if($p->status !== 'cancelled')
                        <button name="status" value="cancelled" class="px-3 py-2 rounded-lg text-xs font-medium bg-rose-500/10 text-rose-300 hover:bg-rose-500 hover:text-white transition"><i class="fas fa-xmark mr-1"></i>Annuler</button>
                    @else
                        <button name="status" value="pending" class="px-3 py-2 rounded-lg text-xs font-medium bg-dark-200 text-gray-300 hover:text-white transition"><i class="fas fa-rotate-left mr-1"></i>Rouvrir</button>
                    @endif
                </form>
            </div>
        @empty
            <x-admin.empty icon="handshake" title="Aucune promesse" text="Les promesses de soutien envoyées depuis l'app apparaîtront ici." />
        @endforelse
    </x-admin.card>
@else
    <x-admin.card title="Candidatures" icon="inbox" padding="p-0">
        @forelse($submissions as $s)
            @php $sTone = ['received' => 'amber', 'shortlisted' => 'violet', 'selected' => 'gold', 'rejected' => 'gray'][$s->status] ?? 'gray'; @endphp
            <div class="px-6 py-4 border-b border-dark-200 last:border-0 flex flex-col lg:flex-row gap-4" x-data="{ open: false }">
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-white font-semibold">{{ $s->title }}</p>
                        <x-admin.badge :tone="$sTone">{{ $s->statusLabel() }}</x-admin.badge>
                    </div>
                    <p class="text-sm text-gray-400 mt-0.5">{{ $s->user?->name ?? 'Compte supprimé' }}
                        @if($s->user?->email) · <a href="mailto:{{ $s->user->email }}" class="text-primary-300">{{ $s->user->email }}</a> @endif
                        @if($s->phone) · {{ $s->phone }} @endif · {{ $s->created_at->diffForHumans() }}</p>
                    @if($s->logline)<p class="text-sm text-gray-200 mt-2"><span class="text-gray-500">Pitch :</span> {{ $s->logline }}</p>@endif
                    <div x-show="open" x-cloak class="mt-2 space-y-2 text-sm text-gray-300 leading-relaxed">
                        @if($s->synopsis)<p><span class="text-gray-500">Synopsis :</span> {{ $s->synopsis }}</p>@endif
                        @if($s->message)<p><span class="text-gray-500">Note d'intention :</span> {{ $s->message }}</p>@endif
                    </div>
                    <div class="flex flex-wrap gap-x-4 gap-y-1 mt-2 text-sm">
                        @if($s->synopsis || $s->message)<button type="button" @click="open = !open" class="text-gray-400 hover:text-gray-200" x-text="open ? 'Réduire' : 'Lire le dossier'"></button>@endif
                        @if($s->link_url)<a href="{{ $s->link_url }}" target="_blank" rel="noopener" class="text-primary-300 hover:text-primary-200"><i class="fas fa-{{ $call->type === 'musique' ? 'headphones' : 'link' }} mr-1"></i>{{ $call->type === 'musique' ? 'Écouter' : 'Ouvrir le lien' }}</a>@endif
                        @if($s->file_path)<a href="{{ route('calls.submissions.file', [$call, $s]) }}" target="_blank" class="text-primary-300 hover:text-primary-200"><i class="fas fa-file-pdf mr-1"></i>Lire le PDF</a>@endif
                    </div>
                </div>
                <form action="{{ route('calls.submissions.review', [$call, $s]) }}" method="POST" class="lg:w-80 shrink-0 space-y-2">
                    @csrf @method('PATCH')
                    <button type="submit" name="status" value="{{ $s->status }}" class="sr-only" tabindex="-1">Enregistrer</button>
                    <div class="grid grid-cols-3 gap-1.5">
                        @foreach(['shortlisted' => ['Présélect.', 'star', 'violet'], 'selected' => ['Lauréat', 'trophy', 'amber'], 'rejected' => ['Refuser', 'xmark', 'rose']] as $st => [$lbl, $ico, $t])
                            <button name="status" value="{{ $st }}"
                                @class(["px-2 py-2 rounded-lg text-xs font-medium transition border",
                                    "bg-{$t}-500 text-white border-{$t}-500" => $s->status === $st,
                                    "bg-{$t}-500/10 text-{$t}-300 border-{$t}-500/30 hover:bg-{$t}-500/25" => $s->status !== $st])>
                                <i class="fas fa-{{ $ico }} mr-1"></i>{{ $lbl }}
                            </button>
                        @endforeach
                    </div>
                    <input type="text" name="admin_note" value="{{ $s->admin_note }}" placeholder="Note interne (facultative)"
                           class="w-full bg-dark-50 border border-dark-200 rounded-lg px-3 py-2 text-white text-xs placeholder-gray-600 focus:outline-none focus:border-primary-500">
                </form>
            </div>
        @empty
            <x-admin.empty icon="inbox" title="Aucune candidature" text="Les candidatures envoyées depuis l'app apparaîtront ici." />
        @endforelse
    </x-admin.card>
@endif
@endsection
