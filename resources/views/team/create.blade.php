@extends('admin.layouts.app')

@section('title', 'Inviter un membre - ABBEV')
@section('header', 'Inviter un membre')

@section('content')
<x-admin.page-header title="Inviter un membre" :back="route('team.index')" backLabel="Retour à l'équipe"
    subtitle="Le membre accède à votre espace et n'y voit que les modules que vous cochez. Vous pourrez les modifier à tout moment." />

<div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-8 max-w-3xl">
    <form action="{{ route('team.store') }}" method="POST" class="space-y-6">
        @csrf

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-300 mb-2">Nom <span class="text-red-400">*</span></label>
                <input type="text" name="name" id="name" value="{{ old('name') }}" required
                       class="w-full bg-dark-50 border @error('name') border-red-500 @else border-dark-200 @enderror rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500 transition">
                @error('name')<p class="mt-2 text-sm text-red-400"><i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}</p>@enderror
            </div>
            <div>
                <label for="email" class="block text-sm font-medium text-gray-300 mb-2">Adresse email <span class="text-red-400">*</span></label>
                <input type="email" name="email" id="email" value="{{ old('email') }}" required
                       class="w-full bg-dark-50 border @error('email') border-red-500 @else border-dark-200 @enderror rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500 transition">
                @error('email')<p class="mt-2 text-sm text-red-400"><i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}</p>@enderror
            </div>
        </div>

        @include('team._permissions')

        <div class="bg-primary-500/10 border border-primary-500/30 rounded-lg p-4 flex items-start gap-3">
            <i class="fas fa-envelope text-primary-400 text-xl mt-0.5"></i>
            <p class="text-primary-100/80 text-sm">
                Un mot de passe est généré et <span class="font-semibold">envoyé par email</span>. Si l'adresse
                correspond déjà à un abonné de l'app, son compte est simplement rattaché à votre équipe et il garde
                son mot de passe.
            </p>
        </div>

        <div class="flex gap-4">
            <button type="submit" class="bg-primary-500 hover:bg-primary-600 text-white px-6 py-3 rounded-lg transition flex-1">
                <i class="fas fa-paper-plane mr-2"></i> Envoyer l'invitation
            </button>
            <a href="{{ route('team.index') }}" class="bg-dark-200 hover:bg-dark-300 text-white px-6 py-3 rounded-lg transition text-center">
                Annuler
            </a>
        </div>
    </form>
</div>
@endsection
