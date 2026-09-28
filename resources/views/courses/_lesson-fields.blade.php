{{-- Champs d'une leçon (ajout ou modification). Attend : $course, $lesson (nullable) --}}
@php $isVideo = $course->type === 'video'; @endphp
<div class="grid grid-cols-1 md:grid-cols-2 gap-4" x-data="{ provider: '{{ old('video_provider', $lesson?->video_provider ?? 'bunny') }}' }">
    <x-admin.input class="md:col-span-2" name="title" label="Titre de la leçon" :value="$lesson?->title" required />
    <x-admin.textarea class="md:col-span-2" name="summary" label="En deux mots" :value="$lesson?->summary" rows="2" maxlength="500" />
    @if($isVideo)
        <div class="md:col-span-2">
            <span class="block text-sm font-medium text-gray-300 mb-2">Source de la vidéo</span>
            <div class="grid grid-cols-2 gap-2 text-sm">
                <label class="flex items-center gap-2 rounded-lg border px-3 py-2 cursor-pointer" :class="provider === 'bunny' ? 'border-primary-500 bg-primary-500/10 text-white' : 'border-dark-200 text-gray-400'">
                    <input type="radio" name="video_provider" value="bunny" x-model="provider" class="sr-only"><i class="fas fa-cloud"></i> Bunny Stream
                </label>
                <label class="flex items-center gap-2 rounded-lg border px-3 py-2 cursor-pointer" :class="provider === 'url' ? 'border-primary-500 bg-primary-500/10 text-white' : 'border-dark-200 text-gray-400'">
                    <input type="radio" name="video_provider" value="url" x-model="provider" class="sr-only"><i class="fas fa-link"></i> Lien direct (MP4 / HLS)
                </label>
            </div>
        </div>
        <div class="md:col-span-2" x-show="provider === 'bunny'">
            <x-admin.input name="video_id" label="Identifiant de la vidéo Bunny" :value="$lesson?->video_id" placeholder="3f1c2a9e-…"
                hint="À copier depuis Bunny Library (même bibliothèque que les films)." />
        </div>
        <div class="md:col-span-2" x-show="provider === 'url'" x-cloak>
            <x-admin.input name="video_url" type="url" label="Lien de la vidéo" :value="$lesson?->video_url" placeholder="https://…/video.m3u8" />
        </div>
        <x-admin.input name="duration_minutes" type="number" label="Durée (minutes)" :value="$lesson?->duration_minutes" min="1" max="600" />
    @else
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-300 mb-1.5">Document PDF @unless($lesson?->file_path)<span class="text-rose-400">*</span>@endunless</label>
            <input type="file" name="file" accept="application/pdf" @unless($lesson?->file_path) required @endunless
                   class="block w-full text-sm text-gray-400 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-primary-500/20 file:text-primary-200 hover:file:bg-primary-500/30">
            <p class="text-xs text-gray-500 mt-1.5">30 Mo maximum. Lu dans l'app sans téléchargement ni impression.
                @if($lesson?->file_path) Laissez vide pour garder le fichier actuel. @endif</p>
            @error('file')<p class="text-xs text-rose-400 mt-1">{{ $message }}</p>@enderror
        </div>
    @endif
    <div class="md:col-span-2">
        <x-admin.toggle name="is_preview" label="Leçon d'aperçu" :checked="$lesson?->is_preview ?? false"
            hint="Ouverte à tout compte connecté, même sans le forfait requis : idéal pour la première leçon." />
    </div>
</div>
