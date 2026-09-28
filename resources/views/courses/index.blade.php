@extends('admin.layouts.app')

@section('title', 'Cours de cinéma')
@section('header', 'Formation')

@section('content')
<x-admin.page-header title="Cours de cinéma"
    subtitle="Deux formats : cours en vidéos et cours en documents. Une leçon d'aperçu peut être offerte ; le reste suit le forfait choisi.">
    <x-slot:actions>
        <a href="{{ route('courses.create', ['type' => 'document']) }}" class="inline-flex items-center gap-2 bg-dark-200 hover:bg-dark-300 text-gray-100 px-4 py-2.5 rounded-lg transition"><i class="fas fa-file-pdf text-sm"></i> Cours en documents</a>
        <a href="{{ route('courses.create', ['type' => 'video']) }}" class="inline-flex items-center gap-2 bg-primary-500 hover:bg-primary-600 text-white px-5 py-2.5 rounded-lg font-medium transition"><i class="fas fa-plus text-sm"></i> Cours en vidéos</a>
    </x-slot:actions>
</x-admin.page-header>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <x-admin.stat label="Cours vidéo" :value="$stats['video']" icon="circle-play" :href="route('courses.index', ['type' => 'video'])" />
    <x-admin.stat label="Cours documents" :value="$stats['document']" icon="file-lines" tone="sky" :href="route('courses.index', ['type' => 'document'])" />
    <x-admin.stat label="Leçons" :value="$stats['lessons']" icon="list-ol" tone="violet" />
    <x-admin.stat label="Publiés" :value="$stats['published']" icon="eye" tone="emerald" />
</div>

<div class="flex flex-wrap gap-2 mb-5">
    <a href="{{ route('courses.index') }}" @class(['px-3 py-1.5 rounded-lg text-sm border transition', 'bg-white text-dark-100 border-white font-semibold' => ! $type, 'border-dark-200 text-gray-300 hover:border-primary-500/50' => $type])>Tous</a>
    @foreach(\App\Models\Course::TYPES as $key => $label)
        <a href="{{ route('courses.index', ['type' => $key]) }}" @class(['px-3 py-1.5 rounded-lg text-sm border transition', 'bg-white text-dark-100 border-white font-semibold' => $type === $key, 'border-dark-200 text-gray-300 hover:border-primary-500/50' => $type !== $key])>{{ $label }}</a>
    @endforeach
</div>

@if($courses->isEmpty())
    <x-admin.card><x-admin.empty icon="graduation-cap" title="Aucun cours" text="Créez un cours en vidéos ou en documents, puis ajoutez ses leçons." /></x-admin.card>
@else
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
        @foreach($courses as $course)
            <a href="{{ route('courses.edit', $course) }}" class="group bg-dark-100 rounded-xl border border-dark-200 hover:border-primary-500/40 overflow-hidden transition flex flex-col">
                <div class="relative aspect-video">
                    <x-admin.thumb :path="$course->cover_path" :icon="$course->type === 'video' ? 'circle-play' : 'file-lines'" class="w-full h-full" />
                    <div class="absolute top-3 left-3 flex gap-1.5">
                        <x-admin.badge :tone="$course->type === 'video' ? 'primary' : 'sky'" :icon="$course->type === 'video' ? 'circle-play' : 'file-lines'">{{ $course->typeLabel() }}</x-admin.badge>
                        @unless($course->is_published)<x-admin.badge tone="gray" icon="eye-slash">Brouillon</x-admin.badge>@endunless
                    </div>
                    @if($course->required_tier)
                        <div class="absolute top-3 right-3"><x-admin.badge tone="gold" icon="crown">{{ ucfirst($course->required_tier) }}</x-admin.badge></div>
                    @endif
                </div>
                <div class="p-5 flex-1 flex flex-col">
                    <p class="text-xs text-primary-300 font-medium">{{ $course->disciplineLabel() }} · {{ $course->levelLabel() }}</p>
                    <h3 class="text-white font-semibold mt-1 group-hover:text-primary-200">{{ $course->title }}</h3>
                    <p class="text-sm text-gray-400 mt-1 line-clamp-2 flex-1">{{ $course->summary }}</p>
                    <div class="flex items-center justify-between mt-4 pt-3 border-t border-dark-200 text-xs text-gray-500">
                        <span><i class="fas fa-chalkboard-user mr-1"></i>{{ $course->instructor_name ?: '—' }}</span>
                        <span>{{ $course->lessons_count }} leçon(s) ·
                            {{ $course->type === 'video' ? ((int) $course->lessons_sum_duration_minutes) . ' min' : ((int) $course->lessons_sum_pages) . ' p.' }}</span>
                    </div>
                </div>
            </a>
        @endforeach
    </div>
@endif
@endsection
