@extends('admin.layouts.app')

@section('title', 'Lions Head Awards')
@section('header', 'Lions Head Awards')

@section('content')
<x-admin.page-header title="Lions Head Awards"
    subtitle="Le vote ouvert au public : une édition par an, 29 prix (cinéma, télévision, métiers), un vote par compte et par prix.">
    <x-slot:actions>
        <a href="{{ route('awards.create') }}" class="inline-flex items-center gap-2 bg-gradient-to-r from-yellow-500 to-amber-600 hover:from-yellow-400 hover:to-amber-500 text-black px-5 py-2.5 rounded-lg font-semibold transition"><i class="fas fa-plus text-sm"></i> Nouvelle édition</a>
    </x-slot:actions>
</x-admin.page-header>

@if($editions->isEmpty())
    <x-admin.card>
        <x-admin.empty icon="trophy" title="Aucune édition" text="Créez la première édition : la grille des 29 prix officiels est générée automatiquement.">
            <a href="{{ route('awards.create') }}" class="inline-flex items-center gap-2 bg-primary-500 hover:bg-primary-600 text-white px-5 py-2.5 rounded-lg transition"><i class="fas fa-plus"></i> Créer une édition</a>
        </x-admin.empty>
    </x-admin.card>
@else
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        @foreach($editions as $edition)
            <a href="{{ route('awards.show', $edition) }}" class="group relative overflow-hidden rounded-2xl border border-dark-200 hover:border-yellow-500/40 bg-dark-100 transition">
                <div class="absolute inset-0 opacity-40 group-hover:opacity-50 transition">
                    <x-admin.thumb :path="$edition->cover_path" icon="trophy" class="w-full h-full" />
                </div>
                <div class="absolute inset-0 bg-gradient-to-r from-dark-100 via-dark-100/90 to-dark-100/40"></div>
                <div class="relative p-6">
                    <div class="flex flex-wrap items-center gap-2 mb-3">
                        @include('awards._status', ['edition' => $edition])
                        @if($edition->is_current)<x-admin.badge tone="primary" icon="mobile-screen">Affichée dans l'app</x-admin.badge>@endif
                    </div>
                    <h3 class="text-2xl font-bold text-white">{{ $edition->name }}</h3>
                    <p class="text-sm text-gray-400 mt-1">{{ $edition->tagline }}</p>
                    <p class="text-sm text-gray-400 mt-3">
                        @if($edition->voting_starts_at)
                            Vote du {{ $edition->voting_starts_at->translatedFormat('d M') }} au {{ $edition->voting_ends_at?->translatedFormat('d M Y') }}
                        @else
                            Période de vote non définie
                        @endif
                    </p>
                    <div class="flex gap-6 mt-5">
                        <div><p class="text-2xl font-bold text-white">{{ $edition->categories_count }}</p><p class="text-xs text-gray-500">prix</p></div>
                        <div><p class="text-2xl font-bold text-white">{{ $edition->nominees_count }}</p><p class="text-xs text-gray-500">nommés</p></div>
                        <div><p class="text-2xl font-bold text-yellow-300">{{ number_format($edition->votes_count, 0, ',', ' ') }}</p><p class="text-xs text-gray-500">votes</p></div>
                    </div>
                </div>
            </a>
        @endforeach
    </div>
@endif
@endsection
