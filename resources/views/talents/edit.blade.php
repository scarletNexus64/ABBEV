@extends('admin.layouts.app')

@section('title', $talent->displayName())
@section('header', 'Talents & casting')

@section('content')
<x-admin.page-header :title="$talent->displayName()" :back="route('talents.index')" back-label="Retour aux talents"
    :subtitle="$talent->tierLabel() . ' · ' . $talent->professionLabel()" />
<form action="{{ route('talents.update', $talent) }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    @include('talents._form')
</form>
@endsection
