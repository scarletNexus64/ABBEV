@extends('admin.layouts.app')

@section('title', 'Producteurs - ABBEV')
@section('header', 'Producteurs')

@section('content')
<div class="space-y-6">

    {{-- Fallback : l'email n'a pas pu partir → on affiche le mot de passe UNE fois --}}
    @if(session('new_producer'))
        @php $np = session('new_producer'); @endphp
        <div class="bg-amber-500/10 border border-amber-500/40 rounded-xl p-6"
             x-data="{ copied:false }">
            <div class="flex items-start gap-3">
                <i class="fas fa-triangle-exclamation text-amber-400 text-2xl mt-1"></i>
                <div class="flex-1 min-w-0">
                    <h3 class="text-white font-semibold text-lg">Producteur « {{ $np['name'] }} » créé — email non envoyé</h3>
                    <p class="text-amber-200/80 text-sm mt-1">
                        L'envoi automatique des identifiants a échoué. <span class="font-semibold text-white">Transmets-les manuellement</span> :
                        le mot de passe ne sera plus jamais affiché.
                    </p>
                    <div class="mt-4 grid sm:grid-cols-2 gap-3">
                        <div class="bg-dark-50 border border-dark-200 rounded-lg p-3">
                            <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Email</p>
                            <p class="text-white font-mono break-all">{{ $np['email'] }}</p>
                        </div>
                        <div class="bg-dark-50 border border-dark-200 rounded-lg p-3">
                            <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Mot de passe</p>
                            <p class="text-white font-mono break-all" id="np-pass">{{ $np['password'] }}</p>
                        </div>
                    </div>
                    <button type="button"
                            @click="navigator.clipboard.writeText('Email: {{ $np['email'] }}\nMot de passe: {{ $np['password'] }}'); copied=true; setTimeout(()=>copied=false,2000)"
                            class="mt-4 inline-flex items-center gap-2 bg-green-500/20 hover:bg-green-500/30 text-green-200 px-4 py-2 rounded-lg text-sm transition">
                        <i class="fas fa-copy"></i>
                        <span x-text="copied ? 'Copié !' : 'Copier les identifiants'"></span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Les messages flash succès/erreur sont affichés globalement par le
         layout (admin.layouts.app) : pas de doublon ici. --}}

    {{-- Header --}}
    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-white flex items-center gap-2">
                <i class="fas fa-clapperboard text-primary-400"></i> Producteurs
            </h2>
            <p class="text-gray-400 text-sm mt-1">
                {{ $producers->total() }} producteur(s). Chacun ne voit que ses propres films, séries et uploads.
            </p>
        </div>
        <a href="{{ route('producers.create') }}"
           class="bg-primary-500 hover:bg-primary-600 text-white px-4 py-2 rounded-lg font-medium transition-all whitespace-nowrap">
            <i class="fas fa-plus mr-2"></i> Ajouter un producteur
        </a>
    </div>

    {{-- Liste --}}
    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-dark-200/40 text-gray-400 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="text-left px-6 py-3">Producteur</th>
                        <th class="text-left px-6 py-3">Email</th>
                        <th class="text-left px-6 py-3">Contenus</th>
                        <th class="text-left px-6 py-3">Créé</th>
                        <th class="text-right px-6 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-dark-200/70">
                    @forelse($producers as $producer)
                        <tr class="hover:bg-dark-200/30 transition-colors">
                            <td class="px-6 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center text-white font-bold">
                                        {{ strtoupper(substr($producer->name, 0, 1)) }}
                                    </div>
                                    <span class="text-white">{{ $producer->name }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-3 text-gray-300 font-mono">{{ $producer->email }}</td>
                            <td class="px-6 py-3 text-gray-400">{{ $producer->media_count }}</td>
                            <td class="px-6 py-3 text-gray-500">{{ $producer->created_at?->diffForHumans() }}</td>
                            <td class="px-6 py-3 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <a href="{{ route('producers.show', $producer) }}" title="Voir la fiche"
                                       class="w-8 h-8 inline-flex items-center justify-center rounded-lg bg-dark-200 hover:bg-primary-500/30 text-primary-300">
                                        <i class="fas fa-eye text-xs"></i>
                                    </a>
                                    <form action="{{ route('producers.resend', $producer) }}" method="POST" class="inline"
                                          data-confirm="Régénérer un nouveau mot de passe et l'envoyer par email à {{ $producer->email }} ? L'ancien mot de passe sera invalidé."
                                          data-confirm-type="primary" data-confirm-confirm="Renvoyer">
                                        @csrf
                                        <button type="submit" class="w-8 h-8 inline-flex items-center justify-center rounded-lg bg-dark-200 hover:bg-primary-500/30 text-primary-300" title="Renvoyer les identifiants par email">
                                            <i class="fas fa-paper-plane text-xs"></i>
                                        </button>
                                    </form>
                                    <form action="{{ route('producers.destroy', $producer) }}" method="POST" class="inline"
                                          data-confirm="Supprimer ce producteur ? Ses contenus restent dans le catalogue, sans propriétaire."
                                          data-confirm-type="danger" data-confirm-title="Supprimer le producteur" data-confirm-confirm="Supprimer">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="w-8 h-8 inline-flex items-center justify-center rounded-lg bg-dark-200 hover:bg-red-500/30 text-red-300" title="Supprimer">
                                            <i class="fas fa-trash text-xs"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-10 text-center text-gray-500">
                            <i class="fas fa-clapperboard text-3xl mb-2 block opacity-50"></i>
                            Aucun producteur. Cliquez sur « Ajouter un producteur ».
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($producers->hasPages())
            <div class="px-6 py-3 border-t border-dark-200">{{ $producers->links() }}</div>
        @endif
    </div>
</div>
@endsection
