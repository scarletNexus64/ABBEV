@extends('admin.layouts.app')

@section('title', 'Nouvelle annonce casting')
@section('header', 'Talents & casting')

@section('content')
<x-admin.page-header title="Nouvelle annonce" :back="route('castings.index')" back-label="Retour aux annonces"
    subtitle="Décrivez le projet puis chaque rôle recherché : c'est ce que les candidats liront dans l'application." />
<form action="{{ route('castings.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    @include('castings._form')
</form>
@endsection
