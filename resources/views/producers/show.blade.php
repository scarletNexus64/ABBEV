@extends('admin.layouts.app')

@section('title', 'Producteur - ABBEV')
@section('header', 'Détails du producteur')

@section('content')
<!-- Back + actions -->
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <a href="{{ route('producers.index') }}" class="inline-flex items-center text-primary-400 hover:text-primary-300 transition">
        <i class="fas fa-arrow-left mr-2"></i> Retour aux producteurs
    </a>

    <div class="flex flex-wrap items-center gap-2">
        <form action="{{ route('producers.resend', $user) }}" method="POST" class="inline"
              data-confirm="Régénérer un nouveau mot de passe et l'envoyer par email à {{ $user->email }} ? L'ancien sera invalidé."
              data-confirm-type="primary" data-confirm-confirm="Renvoyer">
            @csrf
            <button type="submit" class="bg-sky-500/20 hover:bg-sky-500 text-sky-400 hover:text-white px-4 py-2 rounded-lg text-sm transition">
                <i class="fas fa-paper-plane mr-1"></i> Renvoyer les identifiants
            </button>
        </form>
        <form action="{{ route('producers.destroy', $user) }}" method="POST" class="inline"
              data-confirm="Supprimer ce producteur ? Ses contenus restent dans le catalogue, sans propriétaire."
              data-confirm-type="danger" data-confirm-title="Supprimer le producteur" data-confirm-confirm="Supprimer">
            @csrf @method('DELETE')
            <button type="submit" class="bg-red-500/20 hover:bg-red-500 text-red-400 hover:text-white px-4 py-2 rounded-lg text-sm transition">
                <i class="fas fa-trash mr-1"></i> Supprimer
            </button>
        </form>
    </div>
</div>

<!-- Producer info -->
<div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6 mb-6">
    <div class="flex items-center gap-6">
        <div class="w-20 h-20 rounded-full bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center text-white font-bold text-3xl">
            {{ strtoupper(substr($user->name, 0, 1)) }}
        </div>
        <div class="flex-1">
            <h2 class="text-2xl font-bold text-white mb-1">{{ $user->name }}</h2>
            <p class="text-gray-400 font-mono">{{ $user->email }}</p>
            <div class="flex items-center gap-4 mt-3">
                <span class="text-sm text-gray-400">
                    <i class="fas fa-calendar-alt mr-1"></i> Créé le {{ $user->created_at->format('d/m/Y') }}
                </span>
                <span class="bg-primary-500/20 text-primary-300 px-3 py-1 rounded-full text-sm">
                    <i class="fas fa-clapperboard mr-1"></i> Producteur
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Stats -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-6">
    @php
        $cards = [
            ['label' => 'Contenus', 'value' => $stats['total'],  'icon' => 'fa-photo-film', 'color' => 'primary'],
            ['label' => 'Films',    'value' => $stats['movies'], 'icon' => 'fa-film',       'color' => 'blue'],
            ['label' => 'Séries',   'value' => $stats['series'], 'icon' => 'fa-tv',         'color' => 'purple'],
            ['label' => 'Vues',     'value' => number_format($stats['views']), 'icon' => 'fa-eye', 'color' => 'green'],
        ];
    @endphp
    @foreach($cards as $c)
    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-400">{{ $c['label'] }}</p>
                <p class="text-2xl font-bold text-white mt-1">{{ $c['value'] }}</p>
            </div>
            <div class="w-12 h-12 bg-{{ $c['color'] }}-500/20 rounded-lg flex items-center justify-center">
                <i class="fas {{ $c['icon'] }} text-xl text-{{ $c['color'] }}-400"></i>
            </div>
        </div>
    </div>
    @endforeach
</div>

<!-- Contenus -->
<div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 overflow-hidden">
    <div class="p-6 border-b border-dark-200 flex items-center justify-between">
        <h3 class="text-xl font-bold text-white">
            <i class="fas fa-photo-film text-primary-400 mr-2"></i> Contenus uploadés
        </h3>
        <span class="text-gray-400 text-sm">{{ $stats['total'] }} élément(s)</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-dark-50">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase">Titre</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase">Type</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase">Catégorie</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase">Source</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase">Vues</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase">Ajouté</th>
                    <th class="px-6 py-4 text-center text-xs font-medium text-gray-400 uppercase">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-dark-200">
                @forelse($media as $item)
                <tr class="hover:bg-dark-50 transition">
                    <td class="px-6 py-4 text-white font-medium">{{ $item->title }}</td>
                    <td class="px-6 py-4">
                        @if($item->type === 'series')
                        <span class="bg-purple-500/20 text-purple-300 px-2 py-1 rounded text-xs"><i class="fas fa-tv mr-1"></i>Série</span>
                        @else
                        <span class="bg-blue-500/20 text-blue-300 px-2 py-1 rounded text-xs"><i class="fas fa-film mr-1"></i>Film</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-gray-300">{{ $item->category->name ?? '—' }}</td>
                    <td class="px-6 py-4 text-gray-400 text-sm capitalize">{{ $item->video_provider ?? '—' }}</td>
                    <td class="px-6 py-4 text-gray-300">{{ number_format($item->views_count) }}</td>
                    <td class="px-6 py-4 text-gray-400 text-sm">{{ $item->created_at->format('d/m/Y') }}</td>
                    <td class="px-6 py-4 text-center">
                        <a href="{{ route('media.show', $item) }}" title="Voir le contenu"
                           class="w-9 h-9 inline-flex items-center justify-center bg-primary-500/20 hover:bg-primary-500 text-primary-400 hover:text-white rounded-lg text-sm transition">
                            <i class="fas fa-eye"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-gray-400">
                        <i class="fas fa-photo-film text-4xl mb-3 block opacity-50"></i>
                        Ce producteur n'a encore uploadé aucun contenu.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
