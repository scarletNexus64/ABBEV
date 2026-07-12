@extends('admin.layouts.app')

@section('title', 'Modifier l\'utilisateur - ABBEV')
@section('header', 'Modifier l\'utilisateur')

@section('content')
<div class="mb-6">
    <a href="{{ route('users.show', $user) }}" class="inline-flex items-center text-primary-400 hover:text-primary-300 transition">
        <i class="fas fa-arrow-left mr-2"></i> Retour à la fiche
    </a>
</div>

<div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-8 max-w-2xl">
    <div class="flex items-center gap-4 mb-6">
        <div class="w-14 h-14 rounded-full bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center text-white font-bold text-xl">
            {{ strtoupper(substr($user->name, 0, 1)) }}
        </div>
        <div>
            <h2 class="text-lg font-bold text-white">{{ $user->name }}</h2>
            <p class="text-gray-400 text-sm capitalize">Rôle : {{ $user->role }}</p>
        </div>
    </div>

    <form action="{{ route('users.update', $user) }}" method="POST"
          data-confirm="Enregistrer les nouvelles informations de « {{ $user->name }} » ?"
          data-confirm-type="primary" data-confirm-title="Enregistrer les modifications" data-confirm-confirm="Enregistrer">
        @csrf
        @method('PUT')

        <div class="mb-6">
            <label for="name" class="block text-sm font-medium text-gray-300 mb-2">
                Nom <span class="text-red-400">*</span>
            </label>
            <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" required
                   class="w-full bg-dark-50 border @error('name') border-red-500 @else border-dark-200 @enderror rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500 transition">
            @error('name')<p class="mt-2 text-sm text-red-400"><i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}</p>@enderror
        </div>

        <div class="mb-8">
            <label for="email" class="block text-sm font-medium text-gray-300 mb-2">
                Adresse email <span class="text-red-400">*</span>
            </label>
            <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" required
                   class="w-full bg-dark-50 border @error('email') border-red-500 @else border-dark-200 @enderror rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500 transition">
            @error('email')<p class="mt-2 text-sm text-red-400"><i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}</p>@enderror
        </div>

        <div class="flex gap-4">
            <button type="submit" class="bg-primary-500 hover:bg-primary-600 text-white px-6 py-3 rounded-lg transition flex-1">
                <i class="fas fa-save mr-2"></i> Enregistrer
            </button>
            <a href="{{ route('users.show', $user) }}" class="bg-dark-200 hover:bg-dark-300 text-white px-6 py-3 rounded-lg transition text-center">
                <i class="fas fa-times mr-2"></i> Annuler
            </a>
        </div>
    </form>
</div>
@endsection
