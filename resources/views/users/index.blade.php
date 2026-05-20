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

<!-- Search Bar -->
<div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-4 mb-6">
    <form action="{{ route('users.index') }}" method="GET" class="flex gap-4">
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
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-gray-300">{{ $user->email }}</td>
                    <td class="px-6 py-4 text-gray-400 text-sm">{{ $user->created_at->format('d/m/Y') }}</td>
                    <td class="px-6 py-4">
                        <div class="flex items-center justify-center gap-2">
                            <a href="{{ route('users.show', $user) }}"
                               class="bg-primary-500/20 hover:bg-primary-500 text-primary-400 hover:text-white px-3 py-2 rounded-lg text-sm transition">
                                <i class="fas fa-eye"></i> Voir
                            </a>
                            <form action="{{ route('users.destroy', $user) }}" method="POST"
                                  onsubmit="return confirm('Supprimer définitivement l\'utilisateur « {{ $user->name }} » ({{ $user->email }}) ?\n\nCette action est irréversible.');"
                                  class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="bg-red-500/20 hover:bg-red-500 text-red-400 hover:text-white px-3 py-2 rounded-lg text-sm transition">
                                    <i class="fas fa-trash"></i> Supprimer
                                </button>
                            </form>
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
