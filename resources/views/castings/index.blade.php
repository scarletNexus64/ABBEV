@extends('admin.layouts.app')

@section('title', 'Annonces casting')
@section('header', 'Talents & casting')

@section('content')
<x-admin.page-header title="Annonces casting"
    subtitle="Publiez les rôles à pourvoir d'un projet ; les candidatures arrivent ici depuis l'application, rôle par rôle.">
    <x-slot:actions>
        <a href="{{ route('castings.create') }}" class="inline-flex items-center gap-2 bg-primary-500 hover:bg-primary-600 text-white px-5 py-2.5 rounded-lg font-medium transition"><i class="fas fa-plus text-sm"></i> Nouvelle annonce</a>
    </x-slot:actions>
</x-admin.page-header>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <x-admin.stat label="Annonces ouvertes" :value="$stats['open']" icon="bullhorn" tone="emerald" />
    <x-admin.stat label="Candidatures reçues" :value="$stats['applications']" icon="inbox" />
    <x-admin.stat label="À examiner" :value="$stats['pending']" icon="hourglass-half" tone="amber" />
    <x-admin.stat label="Présélectionnées" :value="$stats['shortlisted']" icon="star" tone="violet" />
</div>

<div class="flex flex-wrap gap-2 mb-5">
    <a href="{{ route('castings.index') }}" @class(['px-3 py-1.5 rounded-lg text-sm border transition', 'bg-white text-dark-100 border-white font-semibold' => ! $status, 'border-dark-200 text-gray-300 hover:border-primary-500/50' => $status])>Toutes</a>
    @foreach(\App\Models\CastingCall::STATUSES as $key => $label)
        <a href="{{ route('castings.index', ['status' => $key]) }}" @class(['px-3 py-1.5 rounded-lg text-sm border transition', 'bg-white text-dark-100 border-white font-semibold' => $status === $key, 'border-dark-200 text-gray-300 hover:border-primary-500/50' => $status !== $key])>{{ $label }}s</a>
    @endforeach
</div>

@if($calls->isEmpty())
    <x-admin.card><x-admin.empty icon="bullhorn" title="Aucune annonce" text="Créez votre première annonce de casting : projet, rôles, date limite." /></x-admin.card>
@else
    <div class="space-y-3">
        @foreach($calls as $call)
            <a href="{{ route('castings.show', $call) }}" class="group flex items-center gap-5 bg-dark-100 rounded-xl border border-dark-200 hover:border-primary-500/40 p-4 transition">
                <x-admin.thumb :path="$call->cover_path" icon="bullhorn" class="w-28 h-16 rounded-lg hidden sm:flex" />
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-white font-semibold truncate">{{ $call->title }}</p>
                        @include('castings._status', ['call' => $call])
                        @if($call->is_featured)<x-admin.badge tone="gold" icon="star">À la une</x-admin.badge>@endif
                    </div>
                    <p class="text-sm text-gray-400 mt-1 truncate">
                        {{ $call->projectTypeLabel() }} · {{ $call->production_company ?: 'Production non précisée' }}
                        @if($call->city) · {{ $call->city }} @endif
                    </p>
                    <p class="text-xs text-gray-500 mt-1">
                        {{ $call->roles_count }} rôle(s)
                        @if($call->deadline_at) · date limite {{ $call->deadline_at->translatedFormat('d M Y') }} ({{ $call->deadline_at->diffForHumans() }}) @endif
                    </p>
                </div>
                <div class="text-right shrink-0">
                    <p class="text-2xl font-bold text-white leading-none">{{ $call->applications_count }}</p>
                    <p class="text-xs text-gray-500 mt-1">candidature(s)</p>
                    @if($call->pending_count)
                        <span class="inline-block mt-1.5 text-[11px] font-semibold px-2 py-0.5 rounded-full bg-amber-500 text-white">{{ $call->pending_count }} à examiner</span>
                    @endif
                </div>
                <i class="fas fa-chevron-right text-gray-600 group-hover:text-primary-300"></i>
            </a>
        @endforeach
    </div>
@endif
@endsection
