@extends('admin.layouts.app')

@section('title', 'Transfert de données - ABBEV')
@section('header', 'Transfert de données')

@php
    $sameSpace = $source && $target && $source->is($target);
    $total = collect($groups)->sum(fn ($g) => $g['items']->count());
@endphp

@section('content')
<x-admin.page-header title="Transfert de données"
    subtitle="Rattachez des contenus et des modules à l'espace d'un producteur. Il pourra alors les gérer depuis son espace ; rien ne change dans l'app mobile." />

@if(session('transfer_warnings'))
    <div class="mb-6 bg-amber-500/10 border border-amber-500/40 rounded-xl p-5 flex items-start gap-3">
        <i class="fas fa-link-slash text-amber-400 text-xl mt-0.5"></i>
        <div class="text-sm">
            <p class="text-white font-medium">Liens vers des éléments restés dans un autre espace</p>
            <p class="text-amber-200/80 mt-1">Ils restent visibles dans l'app, mais le producteur ne les verra pas dans son espace. Transférez aussi les éléments liés si besoin.</p>
            <ul class="mt-2 space-y-1 text-amber-100/90 list-disc pl-5">
                @foreach(session('transfer_warnings') as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

{{-- 1. Source et destinataire --}}
<form method="GET" class="bg-dark-100 rounded-xl border border-dark-200 p-5 mb-6 grid md:grid-cols-[1fr_auto_1fr_auto] gap-4 items-end">
    <div>
        <label class="block text-sm text-gray-300 mb-1">Depuis</label>
        <select name="source" class="w-full bg-dark-50 border border-dark-200 rounded-lg px-3 py-2.5 text-white focus:outline-none focus:border-primary-500">
            <option value="platform" @selected(! $source)>Plateforme ABBEV (données de l'admin)</option>
            @foreach($producers as $p)
                <option value="{{ $p->getRouteKey() }}" @selected($source?->is($p))>{{ $p->name }}</option>
            @endforeach
        </select>
    </div>
    <i class="fas fa-arrow-right text-gray-500 hidden md:block pb-3"></i>
    <div>
        <label class="block text-sm text-gray-300 mb-1">Vers le producteur</label>
        <select name="target" required class="w-full bg-dark-50 border border-dark-200 rounded-lg px-3 py-2.5 text-white focus:outline-none focus:border-primary-500">
            <option value="">Choisir…</option>
            @foreach($producers as $p)
                <option value="{{ $p->getRouteKey() }}" @selected($target?->is($p))>{{ $p->name }}</option>
            @endforeach
        </select>
    </div>
    <button class="bg-dark-200 hover:bg-dark-300 text-white px-5 py-2.5 rounded-lg text-sm transition">
        <i class="fas fa-magnifying-glass mr-1"></i> Afficher
    </button>
</form>

@if($producers->isEmpty())
    <x-admin.card><x-admin.empty icon="clapperboard" title="Aucun producteur" text="Créez d'abord un producteur pour pouvoir lui transférer des données." /></x-admin.card>
@elseif($source === false)
    <x-admin.card><x-admin.empty icon="circle-question" title="Source introuvable" /></x-admin.card>
@elseif(! $target)
    <x-admin.card><x-admin.empty icon="right-left" title="Choisissez le producteur destinataire" text="La liste des éléments transférables s'affiche ensuite, module par module." /></x-admin.card>
@elseif($sameSpace)
    <x-admin.card><x-admin.empty icon="equals" title="Source et destinataire identiques" text="Choisissez deux espaces différents." /></x-admin.card>
@elseif($total === 0)
    <x-admin.card><x-admin.empty icon="box-open" title="Rien à transférer" :text="($source ? $source->name : 'La plateforme') . ' n\'a aucun élément dans ces modules.'" /></x-admin.card>
@else
{{-- 2. Sélection --}}
<form method="POST" action="{{ route('transfers.store') }}"
      x-data="{ count: 0, refresh() { this.count = $el.querySelectorAll('input[data-item]:checked').length } }"
      @change="refresh()"
      data-confirm="Transférer les éléments sélectionnés à « {{ $target->name }} » ?" data-confirm-type="primary"
      data-confirm-title="Confirmer le transfert" data-confirm-confirm="Transférer">
    @csrf
    <input type="hidden" name="source" value="{{ $sourceKey }}">
    <input type="hidden" name="target" value="{{ $target->getRouteKey() }}">

    @error('items')<p class="mb-4 text-sm text-red-400"><i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}</p>@enderror

    <div class="grid lg:grid-cols-2 gap-6">
        @foreach($groups as $key => $group)
            @continue($group['items']->isEmpty())
            <x-admin.card :title="$group['label']" :icon="$group['icon']" :subtitle="$group['items']->count() . ' élément(s)'" padding="p-0">
                <x-slot:actions>
                    <button type="button" class="text-xs text-primary-400 hover:text-primary-300"
                            @click="$el.closest('section').querySelectorAll('input[data-item]').forEach(c => c.checked = true); refresh()">Tout cocher</button>
                    <button type="button" class="text-xs text-gray-400 hover:text-gray-300"
                            @click="$el.closest('section').querySelectorAll('input[data-item]').forEach(c => c.checked = false); refresh()">Aucun</button>
                </x-slot:actions>
                <div class="max-h-80 overflow-y-auto abbev-scroll divide-y divide-dark-200/70">
                    @foreach($group['items'] as $item)
                        <label class="flex items-center gap-3 px-5 py-2.5 hover:bg-dark-200/30 cursor-pointer">
                            <input type="checkbox" data-item name="items[{{ $key }}][]" value="{{ $item['id'] }}"
                                   @checked(in_array($item['id'], old("items.$key", [])))
                                   class="rounded border-dark-200 bg-dark-50 text-primary-500 focus:ring-primary-500">
                            <span class="flex-1 min-w-0">
                                <span class="block text-sm text-white truncate">{{ $item['title'] }}</span>
                                @if($item['meta'])<span class="block text-xs text-gray-500 truncate">{{ $item['meta'] }}</span>@endif
                            </span>
                        </label>
                    @endforeach
                </div>
            </x-admin.card>
        @endforeach
    </div>

    {{-- Options + validation --}}
    <div class="sticky bottom-4 mt-6 bg-dark-100/95 backdrop-blur rounded-xl border border-primary-500/30 shadow-2xl shadow-black/40 p-5 flex flex-col lg:flex-row lg:items-center gap-4">
        <div class="flex-1 space-y-2 text-sm">
            <label class="flex items-start gap-2 cursor-pointer">
                <input type="hidden" name="include_agents" value="0">
                <input type="checkbox" name="include_agents" value="1" checked class="mt-0.5 rounded border-dark-200 bg-dark-50 text-primary-500 focus:ring-primary-500">
                <span class="text-gray-300">Transférer aussi l'agent des talents sélectionnés</span>
            </label>
            <label class="flex items-start gap-2 cursor-pointer">
                <input type="hidden" name="reset_views" value="0">
                <input type="checkbox" name="reset_views" value="1" checked class="mt-0.5 rounded border-dark-200 bg-dark-50 text-primary-500 focus:ring-primary-500">
                <span class="text-gray-300">
                    Films & séries : ne rémunérer « {{ $target->name }} » que sur les vues à venir
                    <span class="block text-xs text-gray-500">Remet à zéro les vues rémunérées déjà comptées. Décoché, elles s'ajoutent à ses revenus{{ $source ? ' et sortent de ceux de ' . $source->name : '' }}.</span>
                </span>
            </label>
        </div>
        <button type="submit" :disabled="count === 0"
                class="bg-primary-500 hover:bg-primary-600 disabled:opacity-40 disabled:cursor-not-allowed text-white px-6 py-3 rounded-lg font-medium transition whitespace-nowrap">
            <i class="fas fa-right-left mr-2"></i>
            Transférer <span x-text="count"></span> élément(s) à {{ $target->name }}
        </button>
    </div>
</form>
@endif
@endsection
