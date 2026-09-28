@extends('admin.layouts.app')

@section('title', 'Nouvel appel')
@section('header', 'Appels à projets')

@section('content')
<x-admin.page-header title="Nouvel appel à projets" :back="route('calls.index')" back-label="Tous les appels" />
<form action="{{ route('calls.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    @include('calls._form')
</form>
@endsection
