@extends('admin.layouts.app')

@section('title', 'Nouvelle édition')
@section('header', 'Lions Head Awards')

@section('content')
<x-admin.page-header title="Nouvelle édition" :back="route('awards.index')" back-label="Toutes les éditions" />
<form action="{{ route('awards.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    @include('awards._form')
</form>
@endsection
