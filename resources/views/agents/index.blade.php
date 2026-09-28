@extends('admin.layouts.app')

@section('title', 'Agents')
@section('header', 'Talents & casting')

@section('content')
<x-admin.page-header title="Agents"
    subtitle="Agents d'acteurs et de techniciens. Leurs coordonnées figurent sur la fiche des talents qu'ils représentent.">
    <x-slot:actions>
        <a href="{{ route('agents.create') }}" class="inline-flex items-center gap-2 bg-primary-500 hover:bg-primary-600 text-white px-5 py-2.5 rounded-lg font-medium transition"><i class="fas fa-plus text-sm"></i> Nouvel agent</a>
    </x-slot:actions>
</x-admin.page-header>

<div class="flex flex-wrap gap-2 mb-5">
    <a href="{{ route('agents.index') }}" @class(['px-3 py-1.5 rounded-lg text-sm border transition', 'bg-white text-dark-100 border-white font-semibold' => ! $filter, 'border-dark-200 text-gray-300 hover:border-primary-500/50' => $filter])>Tous</a>
    @foreach(\App\Models\Agent::REPRESENTS as $key => $label)
        <a href="{{ route('agents.index', ['represents' => $key]) }}" @class(['px-3 py-1.5 rounded-lg text-sm border transition', 'bg-white text-dark-100 border-white font-semibold' => $filter === $key, 'border-dark-200 text-gray-300 hover:border-primary-500/50' => $filter !== $key])>{{ $label }}</a>
    @endforeach
</div>

@if($agents->isEmpty())
    <x-admin.card><x-admin.empty icon="user-tie" title="Aucun agent" text="Ajoutez les agents qui représentent vos talents." /></x-admin.card>
@else
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
        @foreach($agents as $agent)
            <div class="bg-dark-100 rounded-xl border border-dark-200 p-5 flex flex-col">
                <div class="flex items-start gap-4">
                    <x-admin.thumb :path="$agent->photo_path" icon="user-tie" class="w-14 h-14 rounded-xl" />
                    <div class="min-w-0 flex-1">
                        <p class="text-white font-semibold truncate">{{ $agent->name }}</p>
                        <p class="text-sm text-gray-400 truncate">{{ $agent->agency ?: '—' }}</p>
                        <div class="flex flex-wrap gap-1.5 mt-2">
                            <x-admin.badge :tone="$agent->represents === 'techniciens' ? 'sky' : ($agent->represents === 'mixte' ? 'violet' : 'primary')">{{ $agent->representsLabel() }}</x-admin.badge>
                            @unless($agent->is_published)<x-admin.badge tone="gray" icon="eye-slash">Masqué</x-admin.badge>@endunless
                        </div>
                    </div>
                </div>
                <dl class="mt-4 space-y-1.5 text-sm text-gray-400 flex-1">
                    @if($agent->email)<div class="flex items-center gap-2 truncate"><i class="fas fa-envelope w-4 text-gray-600"></i>{{ $agent->email }}</div>@endif
                    @if($agent->phone)<div class="flex items-center gap-2"><i class="fas fa-phone w-4 text-gray-600"></i>{{ $agent->phone }}</div>@endif
                    <div class="flex items-center gap-2"><i class="fas fa-location-dot w-4 text-gray-600"></i>{{ collect([$agent->city, $agent->country_code])->filter()->implode(', ') ?: '—' }}</div>
                </dl>
                <div class="flex items-center justify-between mt-4 pt-4 border-t border-dark-200">
                    <span class="text-sm text-gray-400"><strong class="text-white">{{ $agent->talents_count }}</strong> talent(s) représenté(s)</span>
                    <div class="flex gap-2">
                        <a href="{{ route('agents.edit', $agent) }}" class="bg-primary-500/15 hover:bg-primary-500 text-primary-300 hover:text-white px-3 py-2 rounded-lg text-sm transition"><i class="fas fa-pen"></i></a>
                        <form action="{{ route('agents.destroy', $agent) }}" method="POST"
                              data-confirm="Supprimer l'agent {{ $agent->name }} ? Ses talents resteront dans l'annuaire, sans agent." data-confirm-type="danger" data-confirm-title="Supprimer l'agent" data-confirm-confirm="Supprimer">
                            @csrf @method('DELETE')
                            <button class="bg-rose-500/15 hover:bg-rose-500 text-rose-300 hover:text-white px-3 py-2 rounded-lg text-sm transition"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection
