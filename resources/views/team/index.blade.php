@extends('admin.layouts.app')

@section('title', 'Équipe & permissions - ABBEV')
@section('header', 'Équipe & permissions')

@section('content')
<div class="space-y-6">

    {{-- Fallback : l'email n'a pas pu partir → mot de passe affiché UNE fois --}}
    @if(session('new_member'))
        @php $nm = session('new_member'); @endphp
        <div class="bg-amber-500/10 border border-amber-500/40 rounded-xl p-6" x-data="{ copied:false }">
            <div class="flex items-start gap-3">
                <i class="fas fa-triangle-exclamation text-amber-400 text-2xl mt-1"></i>
                <div class="flex-1 min-w-0">
                    <h3 class="text-white font-semibold text-lg">« {{ $nm['name'] }} » invité(e) — email non envoyé</h3>
                    <p class="text-amber-200/80 text-sm mt-1">
                        L'envoi automatique a échoué. <span class="font-semibold text-white">Transmettez ces identifiants vous-même</span> :
                        le mot de passe ne sera plus jamais affiché.
                    </p>
                    <div class="mt-4 grid sm:grid-cols-2 gap-3">
                        <div class="bg-dark-50 border border-dark-200 rounded-lg p-3">
                            <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Email</p>
                            <p class="text-white font-mono break-all">{{ $nm['email'] }}</p>
                        </div>
                        <div class="bg-dark-50 border border-dark-200 rounded-lg p-3">
                            <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Mot de passe</p>
                            <p class="text-white font-mono break-all">{{ $nm['password'] }}</p>
                        </div>
                    </div>
                    <button type="button"
                            @click="navigator.clipboard.writeText(@js("Email: {$nm['email']}\nMot de passe: {$nm['password']}")); copied=true; setTimeout(()=>copied=false,2000)"
                            class="mt-4 inline-flex items-center gap-2 bg-green-500/20 hover:bg-green-500/30 text-green-200 px-4 py-2 rounded-lg text-sm transition">
                        <i class="fas fa-copy"></i>
                        <span x-text="copied ? 'Copié !' : 'Copier les identifiants'"></span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <x-admin.page-header title="Équipe & permissions"
        subtitle="Invitez des collaborateurs sur votre espace et choisissez, module par module, ce que chacun peut gérer. Vous seul(e) gérez l'équipe.">
        <x-slot:actions>
            <a href="{{ route('team.create') }}"
               class="bg-primary-500 hover:bg-primary-600 text-white px-4 py-2 rounded-lg font-medium transition-all whitespace-nowrap">
                <i class="fas fa-user-plus mr-2"></i> Inviter un membre
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.card padding="p-0">
        @if($members->isEmpty())
            <x-admin.empty icon="people-group" title="Aucun membre pour l'instant"
                text="Invitez un monteur, un chargé de casting ou un contrôleur de billets : il ne verra que les modules que vous lui attribuez.">
                <a href="{{ route('team.create') }}" class="bg-primary-500 hover:bg-primary-600 text-white px-4 py-2 rounded-lg text-sm transition">
                    <i class="fas fa-user-plus mr-2"></i> Inviter un membre
                </a>
            </x-admin.empty>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-dark-200/40 text-gray-400 text-xs uppercase tracking-wide">
                        <tr>
                            <th class="text-left px-6 py-3">Membre</th>
                            <th class="text-left px-6 py-3">Modules</th>
                            <th class="text-right px-6 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-dark-200/70">
                        @foreach($members as $member)
                            <tr class="hover:bg-dark-200/30 transition-colors align-top">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center text-white font-bold shrink-0">
                                            {{ strtoupper(substr($member->name, 0, 1)) }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-white">{{ $member->name }}</p>
                                            <p class="text-gray-400 font-mono text-xs break-all">{{ $member->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-1.5 max-w-md">
                                        @foreach($member->permissions ?? [] as $key)
                                            @isset($modules[$key])
                                                <x-admin.badge tone="primary" :icon="$modules[$key]['icon']">{{ $modules[$key]['label'] }}</x-admin.badge>
                                            @endisset
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        <a href="{{ route('team.edit', $member) }}" title="Modifier les permissions"
                                           class="w-8 h-8 inline-flex items-center justify-center rounded-lg bg-dark-200 hover:bg-primary-500/30 text-primary-300">
                                            <i class="fas fa-sliders text-xs"></i>
                                        </a>
                                        <form action="{{ route('team.resend', $member) }}" method="POST" class="inline"
                                              data-confirm="Générer un nouveau mot de passe pour {{ $member->name }} et l'envoyer à {{ $member->email }} ? L'ancien ne fonctionnera plus, y compris dans l'app mobile."
                                              data-confirm-type="primary" data-confirm-confirm="Renvoyer">
                                            @csrf
                                            <button type="submit" class="w-8 h-8 inline-flex items-center justify-center rounded-lg bg-dark-200 hover:bg-primary-500/30 text-primary-300" title="Renvoyer des identifiants">
                                                <i class="fas fa-paper-plane text-xs"></i>
                                            </button>
                                        </form>
                                        <form action="{{ route('team.destroy', $member) }}" method="POST" class="inline"
                                              data-confirm="Retirer {{ $member->name }} de l'équipe ? Cette personne perd l'accès à votre espace ; son compte ABBEV reste actif dans l'app."
                                              data-confirm-type="danger" data-confirm-title="Retirer de l'équipe" data-confirm-confirm="Retirer">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="w-8 h-8 inline-flex items-center justify-center rounded-lg bg-dark-200 hover:bg-red-500/30 text-red-300" title="Retirer de l'équipe">
                                                <i class="fas fa-user-minus text-xs"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-admin.card>
</div>
@endsection
