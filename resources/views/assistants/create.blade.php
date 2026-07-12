@extends('admin.layouts.app')

@section('title', 'Nouvel assistant - ABBEV')
@section('header', 'Nouvel assistant')

@section('content')
<div class="max-w-lg mx-auto">
    <a href="{{ route('assistants.index') }}" class="inline-flex items-center gap-2 text-gray-400 hover:text-white text-sm mb-4">
        <i class="fas fa-arrow-left"></i> Retour
    </a>

    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
        <p class="text-gray-400 text-sm mb-6">
            Un mot de passe sécurisé sera généré et envoyé par email. L'assistant se connecte sur
            l'espace admin et accède au panneau de modération.
        </p>

        <form method="POST" action="{{ route('assistants.store') }}" class="space-y-5">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Nom complet <span class="text-red-400">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" required
                       class="w-full bg-dark-50 border @error('name') border-red-500 @else border-dark-200 @enderror rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500">
                @error('name')<p class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Email <span class="text-red-400">*</span></label>
                <input type="email" name="email" value="{{ old('email') }}" required
                       class="w-full bg-dark-50 border @error('email') border-red-500 @else border-dark-200 @enderror rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500">
                @error('email')<p class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
            </div>

            <button type="submit"
                    class="w-full bg-primary-500 hover:bg-primary-600 text-white font-medium py-3 rounded-lg transition">
                <i class="fas fa-user-plus mr-1"></i> Créer l'assistant
            </button>
        </form>
    </div>
</div>
@endsection
