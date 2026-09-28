@extends('admin.layouts.app')

@section('title', 'Nouveau cours')
@section('header', 'Formation')

@section('content')
<x-admin.page-header title="Nouveau cours" :back="route('courses.index')" back-label="Tous les cours"
    subtitle="Créez le cours, puis ajoutez ses leçons sur la page suivante." />
<form action="{{ route('courses.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    @include('courses._form')
</form>
@endsection
