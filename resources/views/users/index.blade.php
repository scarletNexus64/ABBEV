@extends('admin.layouts.app')

@section('title', 'Utilisateurs - ABBEV')
@section('header', 'Gestion des Utilisateurs')

@section('content')
<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-400">Total</p>
                <p class="text-2xl font-bold text-white mt-1">{{ number_format($stats['total']) }}</p>
            </div>
            <div class="w-12 h-12 bg-primary-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-users text-xl text-primary-400"></i>
            </div>
        </div>
    </div>

    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-400">Aujourd'hui</p>
                <p class="text-2xl font-bold text-white mt-1">{{ $stats['today'] }}</p>
            </div>
            <div class="w-12 h-12 bg-green-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-user-plus text-xl text-green-400"></i>
            </div>
        </div>
    </div>

    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-400">Cette semaine</p>
                <p class="text-2xl font-bold text-white mt-1">{{ $stats['week'] }}</p>
            </div>
            <div class="w-12 h-12 bg-blue-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-calendar-week text-xl text-blue-400"></i>
            </div>
        </div>
    </div>

    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-400">Ce mois</p>
                <p class="text-2xl font-bold text-white mt-1">{{ $stats['month'] }}</p>
            </div>
            <div class="w-12 h-12 bg-purple-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-calendar-alt text-xl text-purple-400"></i>
            </div>
        </div>
    </div>
</div>

<!-- Search Bar + Ajouter -->
<div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-4 mb-6">
    <div class="flex flex-col md:flex-row gap-4 md:items-center">
        <form action="{{ route('users.index') }}" method="GET" class="flex gap-4 flex-1">
            <div class="flex-1">
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Rechercher par nom ou email..."
                       class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-primary-500">
            </div>
            <button type="submit" class="bg-primary-500 hover:bg-primary-600 text-white px-6 py-2 rounded-lg transition">
                <i class="fas fa-search mr-2"></i> Rechercher
            </button>
            @if(request('search'))
            <a href="{{ route('users.index') }}" class="bg-dark-200 hover:bg-dark-300 text-white px-6 py-2 rounded-lg transition">
                <i class="fas fa-times"></i>
            </a>
            @endif
        </form>
        @can('create', App\Models\User::class)
        <a href="{{ route('users.create') }}"
           class="bg-green-500 hover:bg-green-600 text-white px-6 py-2 rounded-lg transition text-center whitespace-nowrap">
            <i class="fas fa-user-plus mr-2"></i> Ajouter un utilisateur
        </a>
        @endcan
    </div>
</div>

<!-- Users Table -->
<div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-dark-50">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase">Utilisateur</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase">Email</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase">Inscription</th>
                    <th class="px-6 py-4 text-center text-xs font-medium text-gray-400 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-dark-200">
                @forelse($users as $user)
                <tr class="hover:bg-dark-50 transition">
                    <td class="px-6 py-4">
                        <div class="flex items-center">
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center text-white font-bold">
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                            </div>
                            <div class="ml-3">
                                <p class="text-white font-medium">{{ $user->name }}</p>
                                @if($user->is_active)
                                <span class="text-green-400 text-xs"><i class="fas fa-circle-check mr-1"></i>Actif</span>
                                @else
                                <span class="text-red-400 text-xs"><i class="fas fa-ban mr-1"></i>Suspendu</span>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-gray-300">{{ $user->email }}</td>
                    <td class="px-6 py-4 text-gray-400 text-sm">{{ $user->created_at->format('d/m/Y') }}</td>
                    <td class="px-6 py-4">
                        <div class="flex items-center justify-center gap-2">
                            <a href="{{ route('users.show', $user) }}" title="Voir la fiche"
                               class="w-9 h-9 inline-flex items-center justify-center bg-primary-500/20 hover:bg-primary-500 text-primary-400 hover:text-white rounded-lg text-sm transition">
                                <i class="fas fa-eye"></i>
                            </a>

                            @can('update', $user)
                            <a href="{{ route('users.edit', $user) }}" title="Modifier les infos"
                               class="w-9 h-9 inline-flex items-center justify-center bg-blue-500/20 hover:bg-blue-500 text-blue-400 hover:text-white rounded-lg text-sm transition">
                                <i class="fas fa-pen"></i>
                            </a>
                            @endcan

                            @can('updateStatus', $user)
                            <form action="{{ route('users.status', $user) }}" method="POST" class="inline"
                                  data-confirm="{{ $user->is_active ? 'Suspendre' : 'Réactiver' }} le compte de « {{ $user->name }} » ?"
                                  data-confirm-type="{{ $user->is_active ? 'warning' : 'primary' }}"
                                  data-confirm-confirm="{{ $user->is_active ? 'Suspendre' : 'Réactiver' }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="is_active" value="{{ $user->is_active ? 0 : 1 }}">
                                @if($user->is_active)
                                <button type="submit" title="Suspendre le compte"
                                        class="w-9 h-9 inline-flex items-center justify-center bg-amber-500/20 hover:bg-amber-500 text-amber-400 hover:text-white rounded-lg text-sm transition">
                                    <i class="fas fa-ban"></i>
                                </button>
                                @else
                                <button type="submit" title="Réactiver le compte"
                                        class="w-9 h-9 inline-flex items-center justify-center bg-green-500/20 hover:bg-green-500 text-green-400 hover:text-white rounded-lg text-sm transition">
                                    <i class="fas fa-circle-check"></i>
                                </button>
                                @endif
                            </form>
                            @endcan

                            @can('resetPassword', $user)
                            <form action="{{ route('users.resetPassword', $user) }}" method="POST" class="inline"
                                  data-confirm="Régénérer le mot de passe de « {{ $user->name }} » et le lui envoyer par email ?"
                                  data-confirm-type="primary" data-confirm-confirm="Réinitialiser">
                                @csrf
                                <button type="submit" title="Réinitialiser le mot de passe"
                                        class="w-9 h-9 inline-flex items-center justify-center bg-sky-500/20 hover:bg-sky-500 text-sky-400 hover:text-white rounded-lg text-sm transition">
                                    <i class="fas fa-key"></i>
                                </button>
                            </form>
                            @endcan

                            @can('delete', $user)
                            <form action="{{ route('users.destroy', $user) }}" method="POST"
                                  data-confirm="Supprimer définitivement l'utilisateur « {{ $user->name }} » ({{ $user->email }}) ? Cette action est irréversible."
                                  data-confirm-type="danger" data-confirm-title="Supprimer l'utilisateur" data-confirm-confirm="Supprimer"
                                  class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" title="Supprimer l'utilisateur"
                                        class="w-9 h-9 inline-flex items-center justify-center bg-red-500/20 hover:bg-red-500 text-red-400 hover:text-white rounded-lg text-sm transition">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-6 py-12 text-center">
                        <div class="text-gray-400">
                            <i class="fas fa-users text-4xl mb-3"></i>
                            <p>Aucun utilisateur trouvé</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    @if($users->hasPages())
    <div class="px-6 py-4 border-t border-dark-200">
        {{ $users->links() }}
    </div>
    @endif
</div>
@endsection
