@extends('admin.layouts.app')

@section('title', 'Modération - ABBEV')
@section('header', 'Modération des contenus')

@section('content')
@php
    $tierBadge = [
        'classique' => 'bg-sky-500/15 text-sky-300 border-sky-500/30',
        'standard'  => 'bg-amber-500/15 text-amber-300 border-amber-500/30',
        'premium'   => 'bg-fuchsia-500/15 text-fuchsia-300 border-fuchsia-500/30',
    ];
    $tabs = [
        'pending'  => ['label' => 'En attente', 'icon' => 'fa-clock',          'color' => 'text-amber-400'],
        'approved' => ['label' => 'Approuvés',  'icon' => 'fa-circle-check',    'color' => 'text-green-400'],
        'rejected' => ['label' => 'Rejetés',    'icon' => 'fa-circle-xmark',    'color' => 'text-red-400'],
    ];
@endphp

<div class="space-y-6">
    {{-- Onglets de statut --}}
    <div class="flex flex-wrap gap-2">
        @foreach($tabs as $key => $tab)
            <a href="{{ route('moderation.index', ['status' => $key]) }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border text-sm font-medium transition
                      {{ $status === $key ? 'bg-primary-500/15 border-primary-500/40 text-white' : 'bg-dark-100 border-dark-200 text-gray-400 hover:text-white' }}">
                <i class="fas {{ $tab['icon'] }} {{ $tab['color'] }}"></i>
                {{ $tab['label'] }}
                <span class="text-xs px-1.5 py-0.5 rounded-full bg-dark-300 text-gray-300">{{ $counts[$key] }}</span>
            </a>
        @endforeach
    </div>

    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-dark-200/40 text-gray-400 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="text-left px-4 py-3">Contenu</th>
                        <th class="text-left px-3 py-3">Producteur</th>
                        <th class="text-left px-3 py-3 w-24">Type</th>
                        <th class="text-left px-3 py-3 w-28">Tier</th>
                        <th class="text-left px-3 py-3 w-32">Soumis</th>
                        <th class="text-right px-4 py-3 w-32">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-dark-200/70">
                    @forelse($items as $m)
                        <tr class="hover:bg-dark-200/30">
                            <td class="px-4 py-3">
                                <p class="text-white font-medium">{{ $m->title }}</p>
                                <p class="text-gray-500 text-xs">{{ $m->category?->name ?? 'Sans catégorie' }}</p>
                                @if($m->moderation_status === 'rejected' && $m->rejection_reason)
                                    <p class="text-red-400 text-xs mt-1"><i class="fas fa-ban mr-1"></i>{{ $m->rejection_reason }}</p>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-gray-300">{{ $m->producer?->name ?? '—' }}</td>
                            <td class="px-3 py-3 text-gray-400">{{ $m->type === 'series' ? 'Série' : 'Film' }}</td>
                            <td class="px-3 py-3">
                                <span class="text-xs px-2 py-1 rounded-full border {{ $tierBadge[$m->tier] ?? '' }}">{{ ucfirst($m->tier) }}</span>
                            </td>
                            <td class="px-3 py-3 text-gray-500 whitespace-nowrap">{{ $m->created_at?->diffForHumans(null, true) }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('moderation.show', $m->id) }}"
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-primary-500 hover:bg-primary-600 text-white text-xs font-medium">
                                    <i class="fas fa-eye"></i> Examiner
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-inbox text-3xl mb-2 block opacity-50"></i>
                            Aucun contenu {{ $tabs[$status]['label'] ?? '' }}.
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($items->hasPages())
            <div class="px-5 py-3 border-t border-dark-200">{{ $items->links() }}</div>
        @endif
    </div>
</div>
@endsection
