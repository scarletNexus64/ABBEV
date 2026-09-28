@extends('admin.layouts.app')

@section('title', 'Nouvel agent')
@section('header', 'Talents & casting')

@section('content')
<x-admin.page-header title="Nouvel agent" :back="route('agents.index')" back-label="Retour aux agents" />
<form action="{{ route('agents.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    @include('agents._form')
</form>
@endsection
