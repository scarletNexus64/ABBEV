@extends('admin.layouts.app')

@section('title', 'Modifier — ' . $call->title)
@section('header', 'Talents & casting')

@section('content')
<x-admin.page-header :title="$call->title" :back="route('castings.show', $call)" back-label="Retour à l'annonce" />
<form action="{{ route('castings.update', $call) }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    @include('castings._form')
</form>
@endsection
