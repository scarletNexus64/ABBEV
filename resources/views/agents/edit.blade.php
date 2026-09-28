@extends('admin.layouts.app')

@section('title', $agent->name)
@section('header', 'Talents & casting')

@section('content')
<x-admin.page-header :title="$agent->name" :subtitle="$agent->agency" :back="route('agents.index')" back-label="Retour aux agents" />
<form action="{{ route('agents.update', $agent) }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    @include('agents._form')
</form>
@endsection
