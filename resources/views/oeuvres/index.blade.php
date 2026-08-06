@extends('admin.layouts.app')

@section('title', 'Oeuvres adaptables')
@section('header', 'Oeuvres adaptables')

@section('content')
<div class="flex justify-between items-center mb-6">
    <div>
        <p class="text-gray-400">Gerez les oeuvres litteraires (PDF) visibles dans la rubrique "Oeuvre adaptable" de l'application.</p>
    </div>
    <a href="{{ route('oeuvres.create') }}" class="bg-primary-500 hover:bg-primary-600 text-white px-6 py-3 rounded-lg transition inline-flex items-center">
        <i class="fas fa-plus mr-2"></i> Nouvelle oeuvre
    </a>
</div>

<!-- Stats -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-400">Total oeuvres</p>
                <p class="text-2xl font-bold text-white mt-1">{{ $stats['total'] }}</p>
            </div>
            <div class="w-12 h-12 bg-primary-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-book text-xl text-primary-400"></i>
            </div>
        </div>
    </div>

    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-400">Publiees</p>
                <p class="text-2xl font-bold text-white mt-1">{{ $stats['active'] }}</p>
            </div>
            <div class="w-12 h-12 bg-green-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-eye text-xl text-green-400"></i>
            </div>
        </div>
    </div>

    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-400">Masquees</p>
                <p class="text-2xl font-bold text-white mt-1">{{ $stats['inactive'] }}</p>
            </div>
            <div class="w-12 h-12 bg-yellow-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-eye-slash text-xl text-yellow-400"></i>
            </div>
        </div>
    </div>
</div>

<!-- Table -->
<div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-dark-50 text-gray-400 uppercase text-xs">
                <tr>
                    <th class="px-6 py-4 text-left">Oeuvre</th>
                    <th class="px-6 py-4 text-left">Auteur</th>
                    <th class="px-6 py-4 text-left">Pages</th>
                    <th class="px-6 py-4 text-left">Statut</th>
                    <th class="px-6 py-4 text-left">Date</th>
                    <th class="px-6 py-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-dark-200">
                @forelse($oeuvres as $oeuvre)
                <tr class="hover:bg-dark-50 transition">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            @if($oeuvre->cover_path)
                                <img src="{{ asset('storage/' . $oeuvre->cover_path) }}" alt="{{ $oeuvre->title }}" class="w-10 h-14 object-cover rounded">
                            @else
                                <div class="w-10 h-14 bg-dark-200 rounded flex items-center justify-center">
                                    <i class="fas fa-file-pdf text-red-400"></i>
                                </div>
                            @endif
                            <div>
                                <p class="text-white font-medium">{{ $oeuvre->title }}</p>
                                @if($oeuvre->description)
                                    <p class="text-gray-500 text-xs line-clamp-1">{{ $oeuvre->description }}</p>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-gray-300">{{ $oeuvre->author }}</td>
                    <td class="px-6 py-4 text-gray-300">{{ $oeuvre->pages ?? '-' }}</td>
                    <td class="px-6 py-4">
                        @if($oeuvre->is_active)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-500/20 text-green-400">
                                <i class="fas fa-circle text-[6px] mr-1.5"></i> Active
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-500/20 text-gray-400">
                                <i class="fas fa-circle text-[6px] mr-1.5"></i> Masquee
                            </span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-gray-400 text-xs">{{ $oeuvre->created_at->format('d/m/Y') }}</td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('oeuvres.edit', $oeuvre) }}"
                               class="text-primary-400 hover:text-primary-300 p-2 rounded-lg hover:bg-dark-200 transition"
                               title="Modifier">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form action="{{ route('oeuvres.destroy', $oeuvre) }}" method="POST"
                                  onsubmit="return confirm('Supprimer cette oeuvre ?')">
                                @csrf @method('DELETE')
                                <button type="submit"
                                        class="text-red-400 hover:text-red-300 p-2 rounded-lg hover:bg-dark-200 transition"
                                        title="Supprimer">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center">
                        <div class="w-16 h-16 bg-primary-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-book text-3xl text-primary-400"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-white mb-2">Aucune oeuvre</h3>
                        <p class="text-gray-400 mb-4">Ajoutez des oeuvres adaptables pour les rendre visibles dans l'application.</p>
                        <a href="{{ route('oeuvres.create') }}"
                           class="inline-flex items-center gap-2 bg-primary-500 hover:bg-primary-600 text-white px-5 py-2.5 rounded-lg transition">
                            <i class="fas fa-plus"></i> Ajouter une oeuvre
                        </a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($oeuvres->hasPages())
    <div class="mt-6">{{ $oeuvres->links() }}</div>
@endif
@endsection
