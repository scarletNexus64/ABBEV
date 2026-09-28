@extends('admin.layouts.app')

@section('title', 'Modifier — ' . $call->title)
@section('header', 'Appels à projets')

@section('content')
<x-admin.page-header :title="$call->title" :back="route('calls.show', $call)" back-label="Retour à l'appel" />
<form action="{{ route('calls.update', $call) }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    @include('calls._form')
</form>
@endsection
