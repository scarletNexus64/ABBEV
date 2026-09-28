@extends('admin.layouts.app')

@section('title', 'Appels à projets')
@section('header', 'Appels à projets')

@section('content')
@php $callMeta = \App\Models\ProjectCall::LOOK; @endphp
<x-admin.page-header title="Appels à projets"
    subtitle="Financement (film, série & feuilleton, documentaire), écriture de scénario (mêmes formats) et musique (cinéma, télévision).">
    <x-slot:actions>
        @foreach(\App\Models\ProjectCall::TYPES as $key => $label)
            <a href="{{ route('calls.create', ['type' => $key]) }}" class="inline-flex items-center gap-2 {{ $loop->first ? 'bg-primary-500 hover:bg-primary-600 text-white font-medium' : 'bg-dark-200 hover:bg-dark-300 text-gray-100' }} px-4 py-2.5 rounded-lg text-sm transition">
                <i class="fas fa-{{ $callMeta[$key][0] }}"></i> {{ \App\Models\ProjectCall::shortTypeLabel($key) }}
            </a>
        @endforeach
    </x-slot:actions>
</x-admin.page-header>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <x-admin.stat label="Appels ouverts" :value="$stats['open']" icon="door-open" tone="emerald" />
    <x-admin.stat label="Candidatures à lire" :value="$stats['submissions']" icon="inbox" tone="violet" />
    <x-admin.stat label="Promesses à confirmer" :value="$stats['pledges']" icon="hourglass-half" tone="amber" />
    <x-admin.stat label="Soutiens confirmés" :value="\App\Support\Money::format($stats['confirmed'], 'XAF')" icon="hand-holding-dollar" tone="sky" />
</div>

<div class="flex flex-wrap gap-2 mb-5">
    <a href="{{ route('calls.index') }}" @class(['px-3 py-1.5 rounded-lg text-sm border transition', 'bg-white text-dark-100 border-white font-semibold' => ! $type, 'border-dark-200 text-gray-300 hover:border-primary-500/50' => $type])>Tous</a>
    @foreach(\App\Models\ProjectCall::TYPES as $key => $label)
        <a href="{{ route('calls.index', ['type' => $key]) }}" @class(['px-3 py-1.5 rounded-lg text-sm border transition', 'bg-white text-dark-100 border-white font-semibold' => $type === $key, 'border-dark-200 text-gray-300 hover:border-primary-500/50' => $type !== $key])>
            <i class="fas fa-{{ $callMeta[$key][0] }} mr-1 opacity-70"></i>{{ \App\Models\ProjectCall::shortTypeLabel($key) }}
        </a>
    @endforeach
</div>

@if($calls->isEmpty())
    <x-admin.card><x-admin.empty icon="lightbulb" title="Aucun appel" text="Lancez un appel à financement, à écriture de scénario ou à musique." /></x-admin.card>
@else
    <div class="space-y-3">
        @foreach($calls as $call)
            @php [$icon, $tone] = $callMeta[$call->type] ?? ['lightbulb', 'primary']; @endphp
            <a href="{{ route('calls.show', $call) }}" class="group flex flex-col md:flex-row md:items-center gap-4 bg-dark-100 rounded-xl border border-dark-200 hover:border-primary-500/40 p-4 transition">
                <x-admin.thumb :path="$call->cover_path" :icon="$icon" class="w-full md:w-32 h-20 rounded-lg" />
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-admin.badge :tone="$tone" :icon="$icon">{{ \App\Models\ProjectCall::shortTypeLabel($call->type) }} · {{ $call->targetLabel() }}</x-admin.badge>
                        @include('calls._status', ['call' => $call])
                    </div>
                    <p class="text-white font-semibold mt-1.5 truncate">{{ $call->title }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">{{ $call->organizer ?: '—' }}
                        @if($call->closes_at) · clôture {{ $call->closes_at->translatedFormat('d M Y') }} @endif</p>
                </div>
                @if($call->isFunding())
                    @php $pct = min(100, (int) $call->progressPercent()); @endphp
                    <div class="md:w-64 shrink-0">
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-white font-semibold">{{ \App\Support\Money::format($call->raisedAmount(), $call->currency) }}</span>
                            <span class="text-gray-500">{{ (int) $call->progressPercent() }} %</span>
                        </div>
                        <div class="h-2 rounded-full bg-dark-300 overflow-hidden"><div class="h-full bg-gradient-to-r from-emerald-500 to-emerald-300" style="width: {{ $pct }}%"></div></div>
                        <p class="text-xs text-gray-500 mt-1">sur {{ \App\Support\Money::format($call->goal_amount, $call->currency) }}
                            @if($call->pending_pledges) · <span class="text-amber-300">{{ $call->pending_pledges }} à confirmer</span> @endif</p>
                    </div>
                @else
                    <div class="text-right shrink-0">
                        <p class="text-2xl font-bold text-white leading-none">{{ $call->submissions_count }}</p>
                        <p class="text-xs text-gray-500 mt-1">candidature(s)</p>
                        @if($call->new_submissions)<span class="inline-block mt-1.5 text-[11px] font-semibold px-2 py-0.5 rounded-full bg-violet-500 text-white">{{ $call->new_submissions }} à lire</span>@endif
                    </div>
                @endif
                <i class="fas fa-chevron-right text-gray-600 group-hover:text-primary-300 hidden md:block"></i>
            </a>
        @endforeach
    </div>
@endif
@endsection
