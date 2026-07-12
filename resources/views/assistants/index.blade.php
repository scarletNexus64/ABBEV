@extends('admin.layouts.app')

@section('title', 'Assistants - ABBEV')
@section('header', 'Assistants (direction artistique)')

@section('content')
<div class="space-y-6">

    {{-- Mot de passe affiché en repli si l'email n'est pas parti --}}
    @if(session('new_assistant'))
        @php $na = session('new_assistant'); @endphp
        <div class="bg-amber-500/10 border border-amber-500/30 rounded-xl p-5">
            <p class="text-amber-200 font-medium mb-2"><i class="fas fa-triangle-exclamation mr-2"></i>Email non envoyé — transmettez ces identifiants manuellement :</p>
            <div class="text-sm text-gray-200 space-y-1">
                <p>Nom : <span class="font-semibold">{{ $na['name'] }}</span></p>
                <p>Email : <span class="font-semibold">{{ $na['email'] }}</span></p>
                <p>Mot de passe : <span class="font-mono bg-dark-300 px-2 py-0.5 rounded">{{ $na['password'] }}</span></p>
            </div>
        </div>
    @endif

    <div class="flex items-center justify-between gap-3">
        <div>
            <p class="text-gray-400 text-sm">
                Les assistants valident les contenus soumis et leur attribuent catégorie + tier.
            </p>
            @if($pending > 0)
                <p class="text-amber-300 text-sm mt-1"><i class="fas fa-clock mr-1"></i>{{ $pending }} contenu(s) en attente de modération.</p>
            @endif
        </div>
        <a href="{{ route('assistants.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-primary-500 hover:bg-primary-600 text-white text-sm font-medium whitespace-nowrap">
            <i class="fas fa-user-plus"></i> Nouvel assistant
        </a>
    </div>

    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-dark-200/40 text-gray-400 text-xs uppercase tracking-wide">
                <tr>
                    <th class="text-left px-4 py-3">Nom</th>
                    <th class="text-left px-3 py-3">Email</th>
                    <th class="text-left px-3 py-3 w-32">Ajouté</th>
                    <th class="text-right px-4 py-3 w-48">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-dark-200/70">
                @forelse($assistants as $a)
                    <tr class="hover:bg-dark-200/30">
                        <td class="px-4 py-3 text-white font-medium">{{ $a->name }}</td>
                        <td class="px-3 py-3 text-gray-300">{{ $a->email }}</td>
                        <td class="px-3 py-3 text-gray-500 whitespace-nowrap">{{ $a->created_at?->diffForHumans(null, true) }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <form method="POST" action="{{ route('assistants.resend', $a->id) }}"
                                      data-confirm="Régénérer et renvoyer le mot de passe de {{ $a->name }} ? L'ancien sera invalidé."
                                      data-confirm-type="warning" data-confirm-confirm="Renvoyer">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-dark-200 hover:bg-dark-300 text-amber-300 text-xs">
                                        <i class="fas fa-key mr-1"></i>Renvoyer accès
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('assistants.destroy', $a->id) }}"
                                      data-confirm="Supprimer l'assistant {{ $a->name }} ?"
                                      data-confirm-type="danger" data-confirm-confirm="Supprimer">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-dark-200 hover:bg-red-500/30 text-red-300 text-xs">
                                        <i class="fas fa-trash mr-1"></i>Supprimer
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-6 py-12 text-center text-gray-500">
                        <i class="fas fa-user-shield text-3xl mb-2 block opacity-50"></i>
                        Aucun assistant. Créez-en un pour déléguer la modération.
                    </td></tr>
                @endforelse
            </tbody>
        </table>
        @if($assistants->hasPages())
            <div class="px-5 py-3 border-t border-dark-200">{{ $assistants->links() }}</div>
        @endif
    </div>
</div>
@endsection
