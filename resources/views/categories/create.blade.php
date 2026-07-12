@extends('admin.layouts.app')

@section('title', 'Nouvelle catégorie - ABBEV')
@section('header', 'Nouvelle catégorie')

@section('content')
<div class="mb-6">
    <a href="{{ route('categories.index') }}" class="inline-flex items-center text-primary-400 hover:text-primary-300 transition">
        <i class="fas fa-arrow-left mr-2"></i> Retour aux catégories
    </a>
</div>

<div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-8 max-w-2xl">
    <form action="{{ route('categories.store') }}" method="POST">
        @csrf

        <div class="mb-6">
            <label for="name" class="block text-sm font-medium text-gray-300 mb-2">
                Nom de la catégorie <span class="text-red-400">*</span>
            </label>
            <input type="text" name="name" id="name" value="{{ old('name') }}" required
                   class="w-full bg-dark-50 border @error('name') border-red-500 @else border-dark-200 @enderror rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500 transition">
            @error('name')<p class="mt-2 text-sm text-red-400"><i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}</p>@enderror
        </div>

        <div class="mb-8">
            <label for="description" class="block text-sm font-medium text-gray-300 mb-2">
                Description
            </label>
            <textarea name="description" id="description" rows="4"
                      class="w-full bg-dark-50 border @error('description') border-red-500 @else border-dark-200 @enderror rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500 transition">{{ old('description') }}</textarea>
            @error('description')<p class="mt-2 text-sm text-red-400"><i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}</p>@enderror
        </div>

        <div class="flex gap-4">
            <button type="submit" class="bg-primary-500 hover:bg-primary-600 text-white px-6 py-3 rounded-lg transition flex-1">
                <i class="fas fa-plus mr-2"></i> Créer la catégorie
            </button>
            <a href="{{ route('categories.index') }}" class="bg-dark-200 hover:bg-dark-300 text-white px-6 py-3 rounded-lg transition text-center">
                <i class="fas fa-times mr-2"></i> Annuler
            </a>
        </div>
    </form>
</div>
@endsection
