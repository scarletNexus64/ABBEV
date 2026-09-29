@extends('admin.layouts.app')

@section('title', 'Permissions de ' . $member->name . ' - ABBEV')
@section('header', 'Permissions du membre')

@section('content')
<x-admin.page-header :title="'Permissions de ' . $member->name" :subtitle="$member->email"
    :back="route('team.index')" backLabel="Retour à l'équipe" />

<div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-8 max-w-3xl">
    <form action="{{ route('team.update', $member) }}" method="POST" class="space-y-6">
        @csrf @method('PUT')

        @include('team._permissions')

        <div class="flex gap-4">
            <button type="submit" class="bg-primary-500 hover:bg-primary-600 text-white px-6 py-3 rounded-lg transition flex-1">
                <i class="fas fa-check mr-2"></i> Enregistrer
            </button>
            <a href="{{ route('team.index') }}" class="bg-dark-200 hover:bg-dark-300 text-white px-6 py-3 rounded-lg transition text-center">
                Annuler
            </a>
        </div>
    </form>
</div>
@endsection
