@extends('admin.layouts.app')

@section('title', $call->title)
@section('header', 'Talents & casting')

@section('content')
@php
    $appTones = ['pending' => 'amber', 'shortlisted' => 'violet', 'accepted' => 'emerald', 'rejected' => 'gray'];
    $total = $counts->sum();
@endphp

<x-admin.page-header :title="$call->title" :back="route('castings.index')" back-label="Toutes les annonces">
    <x-slot:actions>
        <a href="{{ route('castings.export', $call) }}" class="inline-flex items-center gap-2 bg-dark-200 hover:bg-dark-300 text-gray-200 px-4 py-2.5 rounded-lg text-sm transition" download><i class="fas fa-file-csv"></i> Exporter</a>
        <a href="{{ route('castings.edit', $call) }}" class="inline-flex items-center gap-2 bg-primary-500 hover:bg-primary-600 text-white px-4 py-2.5 rounded-lg text-sm font-medium transition"><i class="fas fa-pen"></i> Modifier</a>
        <form action="{{ route('castings.destroy', $call) }}" method="POST"
              data-confirm="Supprimer cette annonce et ses {{ $total }} candidature(s) ? Cette action est définitive." data-confirm-type="danger" data-confirm-title="Supprimer l'annonce" data-confirm-confirm="Supprimer">
            @csrf @method('DELETE')
            <button class="inline-flex items-center gap-2 bg-rose-500/15 hover:bg-rose-500 text-rose-300 hover:text-white px-4 py-2.5 rounded-lg text-sm transition"><i class="fas fa-trash"></i></button>
        </form>
    </x-slot:actions>
</x-admin.page-header>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
    <x-admin.card class="xl:col-span-2" padding="p-0">
        <div class="flex flex-col md:flex-row">
            <x-admin.thumb :path="$call->cover_path" icon="bullhorn" class="md:w-64 aspect-video md:aspect-auto md:rounded-l-xl" />
            <div class="p-6 flex-1">
                <div class="flex flex-wrap items-center gap-2 mb-2">
                    @include('castings._status', ['call' => $call])
                    <x-admin.badge tone="sky">{{ $call->projectTypeLabel() }}</x-admin.badge>
                    <x-admin.badge tone="primary">{{ $call->compensationLabel() }}</x-admin.badge>
                </div>
                <p class="text-white text-lg font-semibold">{{ $call->project_title }}</p>
                <p class="text-sm text-gray-400">{{ collect([$call->production_company, $call->director ? 'réal. ' . $call->director : null])->filter()->implode(' · ') }}</p>
                <dl class="grid grid-cols-2 gap-x-6 gap-y-2 mt-4 text-sm">
                    <div><dt class="text-gray-500 text-xs">Lieu</dt><dd class="text-gray-200">{{ collect([$call->city, $call->country_code])->filter()->implode(', ') ?: '—' }}</dd></div>
                    <div><dt class="text-gray-500 text-xs">Tournage</dt><dd class="text-gray-200">
                        @if($call->shooting_starts_on) {{ $call->shooting_starts_on->format('d/m/Y') }} → {{ $call->shooting_ends_on?->format('d/m/Y') ?? '…' }} @else — @endif
                    </dd></div>
                    <div><dt class="text-gray-500 text-xs">Date limite</dt><dd class="text-gray-200">{{ $call->deadline_at ? $call->deadline_at->format('d/m/Y H:i') . ' · ' . $call->deadline_at->diffForHumans() : 'Aucune' }}</dd></div>
                    <div><dt class="text-gray-500 text-xs">Rémunération</dt><dd class="text-gray-200">{{ $call->compensation_details ?: $call->compensationLabel() }}</dd></div>
                </dl>
            </div>
        </div>
    </x-admin.card>

    <x-admin.card title="Candidatures" icon="inbox">
        <p class="text-4xl font-bold text-white">{{ $total }}</p>
        <div class="mt-4 space-y-2">
            @foreach(\App\Models\CastingApplication::STATUSES as $key => $label)
                @php $n = (int) ($counts[$key] ?? 0); @endphp
                <div class="flex items-center gap-3 text-sm">
                    <span class="w-28 text-gray-400">{{ $label }}</span>
                    <div class="flex-1 h-2 rounded-full bg-dark-300 overflow-hidden">
                        <div class="h-full rounded-full bg-{{ $appTones[$key] === 'gray' ? 'gray' : $appTones[$key] }}-500" style="width: {{ $total ? round($n * 100 / $total) : 0 }}%"></div>
                    </div>
                    <span class="w-6 text-right text-white tabular-nums">{{ $n }}</span>
                </div>
            @endforeach
        </div>
    </x-admin.card>
</div>

<div class="flex flex-wrap gap-2 mb-5">
    <a href="{{ route('castings.show', $call) }}" @class(['px-3 py-1.5 rounded-lg text-sm border transition', 'bg-white text-dark-100 border-white font-semibold' => ! $filter, 'border-dark-200 text-gray-300 hover:border-primary-500/50' => $filter])>Toutes <span class="opacity-60">{{ $total }}</span></a>
    @foreach(\App\Models\CastingApplication::STATUSES as $key => $label)
        <a href="{{ route('castings.show', [$call, 'status' => $key]) }}" @class(['px-3 py-1.5 rounded-lg text-sm border transition', 'bg-white text-dark-100 border-white font-semibold' => $filter === $key, 'border-dark-200 text-gray-300 hover:border-primary-500/50' => $filter !== $key])>{{ $label }} <span class="opacity-60">{{ (int) ($counts[$key] ?? 0) }}</span></a>
    @endforeach
</div>

<div class="space-y-6">
    @foreach($call->roles as $role)
        @php $apps = $applicationsByRole->get($role->id, collect()); @endphp
        <x-admin.card padding="p-0">
            <header class="px-6 py-4 border-b border-dark-200 flex flex-col md:flex-row md:items-center gap-3">
                <div class="flex-1 min-w-0">
                    <h3 class="text-white font-semibold">{{ $role->name }}</h3>
                    <p class="text-xs text-gray-500 mt-0.5">
                        {{ $role->kind === 'technicien' ? (\App\Models\Talent::PROFESSIONS[$role->profession] ?? 'Technicien') : (\App\Models\CastingRole::IMPORTANCE[$role->importance] ?? 'Comédien(ne)') }}
                        · {{ \App\Models\CastingRole::GENDERS[$role->gender] ?? '—' }}
                        @if($role->ageRangeLabel()) · {{ $role->ageRangeLabel() }} @endif
                        @if($role->min_tier) · rang {{ \App\Models\Talent::TIER_LABELS[$role->min_tier] }} minimum @endif
                        · {{ $role->positions }} poste(s)
                    </p>
                </div>
                <x-admin.badge tone="primary">{{ $role->applications_count }} candidature(s)</x-admin.badge>
            </header>
            @forelse($apps as $app)
                <div class="px-6 py-4 border-b border-dark-200 last:border-0 flex flex-col lg:flex-row gap-4" x-data="{ open: false }">
                    <div class="flex gap-4 flex-1 min-w-0">
                        @if($app->photo_path)
                            <a href="{{ route('castings.applications.photo', [$call, $app]) }}" target="_blank" class="shrink-0">
                                <img src="{{ route('castings.applications.photo', [$call, $app]) }}" alt="" class="w-14 h-16 rounded-lg object-cover border border-dark-200">
                            </a>
                        @else
                            <div class="w-14 h-16 rounded-lg bg-dark-200 flex items-center justify-center shrink-0 text-lg font-bold text-gray-500">
                                {{ mb_strtoupper(mb_substr($app->full_name, 0, 1)) }}
                            </div>
                        @endif
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-white font-medium">{{ $app->full_name }}</p>
                                <x-admin.badge :tone="$appTones[$app->status] ?? 'gray'">{{ $app->statusLabel() }}</x-admin.badge>
                            </div>
                            <p class="text-xs text-gray-500 mt-0.5">
                                {{ collect([$app->age ? $app->age . ' ans' : null, $app->city])->filter()->implode(' · ') }}
                                · reçue {{ $app->created_at->diffForHumans() }}
                            </p>
                            <div class="flex flex-wrap gap-x-4 gap-y-1 mt-2 text-sm">
                                <a href="mailto:{{ $app->email }}" class="text-primary-300 hover:text-primary-200"><i class="fas fa-envelope mr-1 text-xs"></i>{{ $app->email }}</a>
                                @if($app->phone)<a href="tel:{{ $app->phone }}" class="text-primary-300 hover:text-primary-200"><i class="fas fa-phone mr-1 text-xs"></i>{{ $app->phone }}</a>@endif
                                @if($app->portfolio_url)<a href="{{ $app->portfolio_url }}" target="_blank" rel="noopener" class="text-primary-300 hover:text-primary-200"><i class="fas fa-link mr-1 text-xs"></i>Travaux / bande démo</a>@endif
                            </div>
                            @if($app->message)
                                <p class="text-sm text-gray-300 mt-2 leading-relaxed" :class="open ? '' : 'line-clamp-2'">{{ $app->message }}</p>
                                <button type="button" @click="open = !open" class="text-xs text-gray-500 hover:text-gray-300 mt-1" x-text="open ? 'Réduire' : 'Lire le message'"></button>
                            @endif
                        </div>
                    </div>
                    <form action="{{ route('castings.applications.review', [$call, $app]) }}" method="POST" class="lg:w-80 shrink-0 space-y-2">
                        @csrf @method('PATCH')
                        {{-- Bouton par défaut (Entrée dans la note) : conserve le
                             statut actuel au lieu de présélectionner par erreur. --}}
                        <button type="submit" name="status" value="{{ $app->status }}" class="sr-only" tabindex="-1">Enregistrer la note</button>
                        <div class="grid grid-cols-3 gap-1.5">
                            @foreach(['shortlisted' => ['Présélect.', 'star', 'violet'], 'accepted' => ['Retenir', 'check', 'emerald'], 'rejected' => ['Refuser', 'xmark', 'rose']] as $st => [$lbl, $ico, $tone])
                                <button name="status" value="{{ $st }}"
                                    @class(["px-2 py-2 rounded-lg text-xs font-medium transition border",
                                        "bg-{$tone}-500 text-white border-{$tone}-500" => $app->status === $st,
                                        "bg-{$tone}-500/10 text-{$tone}-300 border-{$tone}-500/30 hover:bg-{$tone}-500/25" => $app->status !== $st])>
                                    <i class="fas fa-{{ $ico }} mr-1"></i>{{ $lbl }}
                                </button>
                            @endforeach
                        </div>
                        <input type="text" name="admin_note" value="{{ $app->admin_note }}" placeholder="Note interne (facultative)"
                               class="w-full bg-dark-50 border border-dark-200 rounded-lg px-3 py-2 text-white text-xs placeholder-gray-600 focus:outline-none focus:border-primary-500">
                    </form>
                </div>
            @empty
                <p class="px-6 py-6 text-sm text-gray-500">{{ $filter ? 'Aucune candidature avec ce statut pour ce rôle.' : 'Aucune candidature pour ce rôle pour le moment.' }}</p>
            @endforelse
        </x-admin.card>
    @endforeach
</div>
@endsection
