@extends('admin.layouts.app')

@section('title', 'Audience - ABBEV')
@section('header', 'Audience')

@php
    $duration = function (int $seconds): string {
        if ($seconds < 60) return $seconds . ' s';
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        return $h ? "{$h} h " . str_pad($m, 2, '0', STR_PAD_LEFT) : "{$m} min";
    };
@endphp

@section('content')
<x-admin.page-header title="Audience"
    subtitle="Qui regarde vos films et séries, quoi et combien de temps. Seuls le nom et le pays des spectateurs sont affichés." />

{{-- Filtres --}}
<form method="GET" class="bg-dark-100 rounded-xl border border-dark-200 p-4 mb-6 flex flex-col sm:flex-row gap-3">
    <select name="media" onchange="this.form.submit()"
            class="flex-1 bg-dark-50 border border-dark-200 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-primary-500">
        <option value="">Tous les contenus</option>
        @foreach($contents as $content)
            <option value="{{ $content->id }}" @selected($mediaId === $content->id)>
                {{ $content->title }} · {{ $content->type === 'series' ? 'Série' : 'Film' }}
            </option>
        @endforeach
    </select>
    <select name="period" onchange="this.form.submit()"
            class="bg-dark-50 border border-dark-200 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-primary-500">
        @foreach($periods as $key => $label)
            <option value="{{ $key }}" @selected($period === $key)>{{ $label }}</option>
        @endforeach
    </select>
    <noscript><button class="bg-primary-500 text-white px-4 py-2 rounded-lg text-sm">Filtrer</button></noscript>
</form>

{{-- Chiffres clés --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <x-admin.stat label="Spectateurs uniques" :value="number_format($stats['viewers'], 0, ',', ' ')" icon="users" tone="primary" />
    <x-admin.stat label="Sessions de visionnage" :value="number_format($stats['sessions'], 0, ',', ' ')" icon="play" tone="sky" />
    <x-admin.stat label="Heures regardées" :value="number_format($stats['hours'], 1, ',', ' ')" icon="clock" tone="emerald" />
    <x-admin.stat label="Lectures cumulées" :value="number_format($stats['views'], 0, ',', ' ')" icon="eye" tone="violet" hint="Depuis la mise en ligne" />
</div>

<div class="grid xl:grid-cols-5 gap-6">
    {{-- Contenus les plus vus --}}
    <x-admin.card title="Contenus les plus regardés" icon="ranking-star" padding="p-0" class="xl:col-span-2">
        @forelse($topContents as $row)
            <div class="flex items-center gap-3 px-5 py-3 border-b border-dark-200/70 last:border-0">
                <x-admin.thumb :path="$row->media?->thumbnail_path ?? $row->media?->cover_path" icon="film" class="w-12 h-12 rounded-lg" />
                <div class="flex-1 min-w-0">
                    <p class="text-white text-sm truncate">{{ $row->media?->title ?? 'Contenu supprimé' }}</p>
                    <p class="text-xs text-gray-500">{{ $duration((int) $row->seconds) }} regardées</p>
                </div>
                <div class="text-right">
                    <p class="text-white font-semibold">{{ $row->viewers }}</p>
                    <p class="text-[11px] text-gray-500">spectateur{{ $row->viewers > 1 ? 's' : '' }}</p>
                </div>
            </div>
        @empty
            <x-admin.empty icon="chart-line" title="Pas encore de visionnage" text="Les chiffres apparaîtront dès que vos contenus seront regardés dans l'app." />
        @endforelse
    </x-admin.card>

    {{-- Spectateurs --}}
    <x-admin.card title="Spectateurs" icon="users" padding="p-0" class="xl:col-span-3"
        :subtitle="$viewers->total() . ' spectateur(s) sur la période'">
        @if($viewers->isEmpty())
            <x-admin.empty icon="users" title="Aucun spectateur sur la période" />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-dark-200/40 text-gray-400 text-xs uppercase tracking-wide">
                        <tr>
                            <th class="text-left px-5 py-3">Spectateur</th>
                            <th class="text-right px-5 py-3">Contenus</th>
                            <th class="text-right px-5 py-3">Temps</th>
                            <th class="text-right px-5 py-3">Dernière vue</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-dark-200/70">
                        @foreach($viewers as $row)
                            <tr class="hover:bg-dark-200/30 transition-colors">
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-dark-200 flex items-center justify-center text-gray-300 text-xs font-bold shrink-0">
                                            {{ strtoupper(substr($row->user?->name ?? '?', 0, 1)) }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-white truncate">{{ $row->user?->name ?? 'Compte supprimé' }}</p>
                                            @if($row->user?->country)
                                                <p class="text-xs text-gray-500">{{ $row->user->country->flag_emoji }} {{ $row->user->country->name }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-right text-gray-300">{{ $row->contents }}</td>
                                <td class="px-5 py-3 text-right text-gray-300 whitespace-nowrap">{{ $duration((int) $row->seconds) }}</td>
                                <td class="px-5 py-3 text-right text-gray-500 whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($row->last_seen)->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($viewers->hasPages())
                <div class="px-5 py-3 border-t border-dark-200">{{ $viewers->links() }}</div>
            @endif
        @endif
    </x-admin.card>
</div>
@endsection
