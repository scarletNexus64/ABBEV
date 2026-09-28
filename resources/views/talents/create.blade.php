@extends('admin.layouts.app')

@section('title', 'Nouveau talent')
@section('header', 'Talents & casting')

@section('content')
<x-admin.page-header title="Nouveau talent" :back="route('talents.index')" back-label="Retour aux talents" />
<form action="{{ route('talents.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    @include('talents._form')
</form>
@endsection
