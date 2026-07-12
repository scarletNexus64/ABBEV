@extends('admin.layouts.app')

@section('title', 'Séances cinéma - ABBEV')
@section('header', 'Séances cinéma')

@section('content')
<!-- Header Actions -->
<div class="flex justify-between items-center mb-6">
    <div>
        <p class="text-gray-400">Programmez des séances ; les utilisateurs les voient dans l'application et réservent des places payantes.</p>
    </div>
    <a href="{{ route('screenings.create') }}" class="bg-primary-500 hover:bg-primary-600 text-white px-6 py-3 rounded-lg transition inline-flex items-center">
        <i class="fas fa-plus mr-2"></i> Nouvelle séance
    </a>
</div>

{{-- Flash affiché par le layout admin (pas de doublon ici). --}}

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-400">Total séances</p>
                <p class="text-2xl font-bold text-white mt-1">{{ $stats['total'] }}</p>
            </div>
            <div class="w-12 h-12 bg-primary-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-ticket-alt text-xl text-primary-400"></i>
            </div>
        </div>
    </div>

    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-400">Publiées</p>
                <p class="text-2xl font-bold text-white mt-1">{{ $stats['published'] }}</p>
            </div>
            <div class="w-12 h-12 bg-green-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-eye text-xl text-green-400"></i>
            </div>
        </div>
    </div>

    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-400">Séances à venir</p>
                <p class="text-2xl font-bold text-white mt-1">{{ $stats['upcoming'] }}</p>
            </div>
            <div class="w-12 h-12 bg-blue-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-calendar-alt text-xl text-blue-400"></i>
            </div>
        </div>
    </div>

    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-400">Revenu réservations</p>
                <p class="text-2xl font-bold text-white mt-1">{{ number_format($stats['revenue']) }} XAF</p>
            </div>
            <div class="w-12 h-12 bg-purple-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-money-bill-wave text-xl text-purple-400"></i>
            </div>
        </div>
    </div>
</div>

<!-- Screenings Table -->
<div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-dark-50 text-gray-400 uppercase text-xs">
                <tr>
                    <th class="px-6 py-4 text-left">Film</th>
                    <th class="px-6 py-4 text-left">Cinéma / Lieu</th>
                    <th class="px-6 py-4 text-left">Séance</th>
                    <th class="px-6 py-4 text-left">Catégories &amp; places</th>
                    <th class="px-6 py-4 text-left">Statut</th>
                    <th class="px-6 py-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-dark-200">
                @forelse($screenings as $s)
                <tr class="hover:bg-dark-50/50 transition">
                    <td class="px-6 py-4 text-white font-medium">{{ $s->movie_title }}</td>
                    <td class="px-6 py-4 text-gray-300">
                        <div>{{ $s->cinema_name }}</div>
                        <div class="text-gray-500 text-xs">{{ $s->location }}</div>
                    </td>
                    <td class="px-6 py-4 text-gray-300">{{ $s->starts_at->format('d/m/Y H:i') }}</td>
                    <td class="px-6 py-4 text-gray-300">
                        @forelse($s->ticketTypes as $t)
                        <div class="flex items-center gap-2 text-xs mb-1">
                            <span class="px-2 py-0.5 rounded bg-dark-50 text-gray-200">{{ $t->name }}</span>
                            <span class="text-primary-400">{{ number_format($t->price) }} XAF</span>
                            <span class="text-gray-500">{{ $t->sold_seats }}/{{ $t->capacity }} vendues</span>
                        </div>
                        @empty
                        <span class="text-gray-500 text-xs">Aucune catégorie</span>
                        @endforelse
                    </td>
                    <td class="px-6 py-4">
                        @php
                            $badge = [
                                'draft'     => ['Brouillon', 'bg-gray-500/20 text-gray-400'],
                                'published' => ['Publiée', 'bg-green-500/20 text-green-400'],
                                'canceled'  => ['Annulée', 'bg-red-500/20 text-red-400'],
                            ][$s->status] ?? ['—', 'bg-gray-500/20 text-gray-400'];
                        @endphp
                        <span class="px-3 py-1 rounded-full text-xs font-medium {{ $badge[1] }}">{{ $badge[0] }}</span>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('screenings.edit', $s) }}" title="Modifier"
                               class="bg-primary-500/20 hover:bg-primary-500 text-primary-400 hover:text-white px-3 py-2 rounded-lg transition">
                                <i class="fas fa-edit"></i>
                            </a>
                            @if($s->status !== 'canceled')
                            <form action="{{ route('screenings.cancel', $s) }}" method="POST"
                                  data-confirm="Annuler cette séance ? Elle ne sera plus réservable."
                                  data-confirm-type="warning" data-confirm-title="Annuler la séance" data-confirm-confirm="Annuler la séance">
                                @csrf
                                <button type="submit" title="Annuler"
                                        class="bg-yellow-500/20 hover:bg-yellow-500 text-yellow-400 hover:text-white px-3 py-2 rounded-lg transition">
                                    <i class="fas fa-ban"></i>
                                </button>
                            </form>
                            @endif
                            <form action="{{ route('screenings.destroy', $s) }}" method="POST"
                                  data-confirm="Supprimer définitivement cette séance ?"
                                  data-confirm-type="danger" data-confirm-title="Supprimer la séance" data-confirm-confirm="Supprimer">
                                @csrf
                                @method('DELETE')
                                <button type="submit" title="Supprimer"
                                        class="bg-red-500/20 hover:bg-red-500 text-red-400 hover:text-white px-3 py-2 rounded-lg transition">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center">
                        <i class="fas fa-ticket-alt text-4xl text-gray-400 mb-3"></i>
                        <p class="text-gray-400 mb-4">Aucune séance programmée</p>
                        <a href="{{ route('screenings.create') }}" class="bg-primary-500 hover:bg-primary-600 text-white px-6 py-3 rounded-lg transition inline-flex items-center">
                            <i class="fas fa-plus mr-2"></i> Créer la première séance
                        </a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
