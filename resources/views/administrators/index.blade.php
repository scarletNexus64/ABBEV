@extends('admin.layouts.app')

@section('title', 'Administrateurs - ABBEV')
@section('header', 'Gestion des Administrateurs')

@section('content')
<!-- Header Actions -->
<div class="flex justify-between items-center mb-6">
    <div>
        <p class="text-gray-400">Gérez les accès administrateurs de la plateforme</p>
    </div>
    <a href="{{ route('administrators.create') }}" class="bg-primary-500 hover:bg-primary-600 text-white px-6 py-3 rounded-lg transition inline-flex items-center">
        <i class="fas fa-plus mr-2"></i> Ajouter un administrateur
    </a>
</div>

<!-- Stats Card -->
<div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6 mb-6">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm font-medium text-gray-400">Total administrateurs</p>
            <p class="text-3xl font-bold text-white mt-1">{{ $stats['total'] }}</p>
        </div>
        <div class="w-16 h-16 bg-primary-500/20 rounded-lg flex items-center justify-center">
            <i class="fas fa-user-shield text-2xl text-primary-400"></i>
        </div>
    </div>
</div>

<!-- Administrators Table -->
<div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-dark-50">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase">Administrateur</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase">Email</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase">Ajouté le</th>
                    <th class="px-6 py-4 text-center text-xs font-medium text-gray-400 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-dark-200">
                @forelse($admins as $admin)
                <tr class="hover:bg-dark-50 transition">
                    <td class="px-6 py-4">
                        <div class="flex items-center">
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center text-white font-bold">
                                {{ strtoupper(substr($admin->name, 0, 1)) }}
                            </div>
                            <div class="ml-3">
                                <p class="text-white font-medium">{{ $admin->name }}</p>
                                @if($admin->id === auth()->id())
                                <span class="text-xs text-primary-400">(Vous)</span>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-gray-300">{{ $admin->email }}</td>
                    <td class="px-6 py-4 text-gray-400 text-sm">{{ $admin->created_at->format('d/m/Y') }}</td>
                    <td class="px-6 py-4">
                        <div class="flex items-center justify-center gap-2">
                            @if($admin->id !== auth()->id())
                            <form action="{{ route('administrators.destroy', $admin) }}" method="POST"
                                  data-confirm="Retirer les droits administrateur à {{ $admin->name }} ?"
                                  data-confirm-type="danger" data-confirm-title="Retirer l'administrateur" data-confirm-confirm="Retirer">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="bg-red-500/20 hover:bg-red-500 text-red-400 hover:text-white px-3 py-2 rounded-lg text-sm transition">
                                    <i class="fas fa-trash-alt"></i> Retirer
                                </button>
                            </form>
                            @else
                            <span class="text-gray-500 text-sm italic">Compte actif</span>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-6 py-12 text-center">
                        <div class="text-gray-400">
                            <i class="fas fa-user-shield text-4xl mb-3"></i>
                            <p>Aucun administrateur trouvé</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Info Box -->
<div class="mt-6 bg-blue-500/10 border border-blue-500/30 rounded-lg p-4">
    <div class="flex items-start">
        <i class="fas fa-info-circle text-blue-400 text-xl mr-3 mt-1"></i>
        <div>
            <p class="text-blue-300 font-medium mb-1">Information importante</p>
            <p class="text-blue-200 text-sm">
                Les administrateurs ont accès à toutes les fonctionnalités de la plateforme.
                Assurez-vous de n'accorder ces droits qu'aux personnes de confiance.
            </p>
        </div>
    </div>
</div>
@endsection
