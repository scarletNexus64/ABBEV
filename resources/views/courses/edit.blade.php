@extends('admin.layouts.app')

@section('title', $course->title)
@section('header', 'Formation')

@section('content')
@php $isVideo = $course->type === 'video'; @endphp
<x-admin.page-header :title="$course->title" :back="route('courses.index')" back-label="Tous les cours"
    :subtitle="$course->typeLabel() . ' · ' . $course->disciplineLabel() . ' · ' . $course->levelLabel()">
    <x-slot:actions>
        <form action="{{ route('courses.destroy', $course) }}" method="POST"
              data-confirm="Supprimer le cours « {{ $course->title }} » et ses {{ $course->lessons->count() }} leçon(s) ?" data-confirm-type="danger" data-confirm-title="Supprimer le cours" data-confirm-confirm="Supprimer">
            @csrf @method('DELETE')
            <button class="inline-flex items-center gap-2 bg-rose-500/15 hover:bg-rose-500 text-rose-300 hover:text-white px-4 py-2.5 rounded-lg text-sm transition"><i class="fas fa-trash"></i> Supprimer</button>
        </form>
    </x-slot:actions>
</x-admin.page-header>

{{-- Leçons --}}
<div id="lecons" class="grid grid-cols-1 xl:grid-cols-5 gap-6 mb-8">
    <x-admin.card class="xl:col-span-3" title="Leçons" icon="list-ol" padding="p-0"
        :subtitle="$course->lessons->count() . ' leçon(s) · ' . ($isVideo ? $course->lessons->sum('duration_minutes') . ' min au total' : $course->lessons->sum('pages') . ' page(s) au total')">
        @forelse($course->lessons as $i => $lesson)
            <details id="lecon-{{ $lesson->id }}" class="group border-b border-dark-200 last:border-0">
                <summary class="flex items-center gap-4 px-6 py-3.5 cursor-pointer list-none">
                    <span class="w-8 h-8 rounded-lg bg-dark-200 flex items-center justify-center text-sm font-semibold text-gray-300 shrink-0">{{ $i + 1 }}</span>
                    <div class="flex-1 min-w-0">
                        <p class="text-white text-sm font-medium truncate">{{ $lesson->title }}</p>
                        <p class="text-xs text-gray-500">
                            @if($isVideo)
                                <i class="fas fa-{{ $lesson->video_provider === 'bunny' ? 'cloud' : 'link' }} mr-1"></i>{{ $lesson->video_provider === 'bunny' ? 'Bunny' : 'Lien direct' }}
                                @if($lesson->duration_minutes) · {{ $lesson->duration_minutes }} min @endif
                            @else
                                <i class="fas fa-file-pdf mr-1"></i>PDF @if($lesson->pages) · {{ $lesson->pages }} page(s) @endif
                            @endif
                        </p>
                    </div>
                    @if($lesson->is_preview)<x-admin.badge tone="emerald" icon="unlock">Aperçu</x-admin.badge>@endif
                    @unless($lesson->hasContent())<x-admin.badge tone="rose">Sans contenu</x-admin.badge>@endunless
                    <div class="flex items-center gap-1">
                        @foreach(['up' => 'chevron-up', 'down' => 'chevron-down'] as $dir => $ico)
                            <form action="{{ route('courses.lessons.move', $lesson) }}" method="POST">@csrf
                                <input type="hidden" name="direction" value="{{ $dir }}">
                                <button class="w-7 h-7 rounded-md bg-dark-200 hover:bg-dark-300 text-gray-400 hover:text-white disabled:opacity-30"
                                    @disabled(($dir === 'up' && $i === 0) || ($dir === 'down' && $i === $course->lessons->count() - 1))><i class="fas fa-{{ $ico }} text-[10px]"></i></button>
                            </form>
                        @endforeach
                    </div>
                    <i class="fas fa-pen text-xs text-gray-500 group-open:text-primary-300"></i>
                </summary>
                <div class="px-6 pb-5 pt-2 bg-dark-50/40">
                    <form action="{{ route('courses.lessons.update', $lesson) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                        @csrf @method('PUT')
                        @include('courses._lesson-fields', ['lesson' => $lesson])
                        <div class="flex flex-wrap items-center gap-3">
                            <button class="bg-primary-500 hover:bg-primary-600 text-white px-4 py-2 rounded-lg text-sm transition"><i class="fas fa-check mr-1.5"></i>Enregistrer la leçon</button>
                            @if($lesson->file_path)
                                <a href="{{ route('courses.lessons.file', $lesson) }}" target="_blank" class="text-sm text-primary-300 hover:text-primary-200"><i class="fas fa-eye mr-1"></i>Voir le PDF</a>
                            @endif
                        </div>
                    </form>
                    <form action="{{ route('courses.lessons.destroy', $lesson) }}" method="POST" class="mt-3"
                          data-confirm="Supprimer la leçon « {{ $lesson->title }} » ?" data-confirm-type="danger" data-confirm-title="Supprimer la leçon" data-confirm-confirm="Supprimer">
                        @csrf @method('DELETE')
                        <button class="text-sm text-rose-300 hover:text-rose-200"><i class="fas fa-trash mr-1"></i>Supprimer la leçon</button>
                    </form>
                </div>
            </details>
        @empty
            <x-admin.empty icon="list-ol" title="Aucune leçon" text="Ajoutez la première leçon avec le formulaire ci-contre. Pensez à la marquer « aperçu »." />
        @endforelse
    </x-admin.card>

    <x-admin.card class="xl:col-span-2 self-start" title="Ajouter une leçon" icon="plus">
        <form action="{{ route('courses.lessons.store', $course) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            @include('courses._lesson-fields', ['lesson' => null])
            <button class="w-full bg-primary-500 hover:bg-primary-600 text-white px-4 py-2.5 rounded-lg font-medium transition"><i class="fas fa-plus mr-2"></i>Ajouter la leçon</button>
        </form>
    </x-admin.card>
</div>

<h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-3">Réglages du cours</h3>
<form action="{{ route('courses.update', $course) }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    @include('courses._form')
</form>
@endsection
