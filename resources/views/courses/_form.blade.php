{{-- Réglages d'un cours. Attend : $course, $tiers --}}
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2 space-y-6">
        <x-admin.card title="Le cours" icon="graduation-cap">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <x-admin.input class="md:col-span-2" name="title" label="Titre" :value="$course->title" required />
                <div class="md:col-span-2">
                    <span class="block text-sm font-medium text-gray-300 mb-2">Format <span class="text-rose-400">*</span></span>
                    <div class="grid grid-cols-2 gap-3">
                        @foreach(\App\Models\Course::TYPES as $value => $label)
                            <label class="flex items-center gap-3 rounded-xl border px-4 py-3 cursor-pointer transition has-[:checked]:border-primary-500 has-[:checked]:bg-primary-500/10 border-dark-200 {{ $course->exists && $course->type !== $value ? 'opacity-40 pointer-events-none' : '' }}">
                                <input type="radio" name="type" value="{{ $value }}" class="sr-only" @checked(old('type', $course->type) === $value)>
                                <i class="fas fa-{{ $value === 'video' ? 'circle-play' : 'file-pdf' }} text-primary-300"></i>
                                <span class="text-sm text-white font-medium">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    @if($course->exists)<p class="text-xs text-gray-500 mt-2">Le format d'un cours existant ne change plus : ses leçons en dépendent.</p>@endif
                </div>
                <x-admin.select name="discipline" label="Discipline" :value="$course->discipline" :options="\App\Models\Course::DISCIPLINES" required />
                <x-admin.select name="level" label="Niveau" :value="$course->level" :options="\App\Models\Course::LEVELS" required />
                <x-admin.input name="instructor_name" label="Formateur / formatrice" :value="$course->instructor_name" />
                <x-admin.input name="instructor_title" label="Qualité" :value="$course->instructor_title" placeholder="Réalisateur, 30 ans de carrière" />
                <x-admin.textarea class="md:col-span-2" name="summary" label="Résumé" :value="$course->summary" rows="2" maxlength="500" hint="Une ou deux phrases, affichées sur la carte du cours." />
                <x-admin.textarea class="md:col-span-2" name="description" label="Programme détaillé" :value="$course->description" rows="5" />
                <div class="md:col-span-2">
                    <x-admin.translation :model="$course->exists ? $course : null"
                        :fields="['title' => ['Title', 'input'], 'summary' => ['Summary', 'textarea'], 'description' => ['Programme', 'textarea']]" />
                </div>
            </div>
        </x-admin.card>
    </div>
    <div class="space-y-6">
        <x-admin.card title="Visuel" icon="image">
            <x-admin.image-upload name="cover" label="Bannière (16:9)" :current="$course->cover_path ? asset('storage/' . $course->cover_path) : null" />
        </x-admin.card>
        <x-admin.card title="Accès & publication" icon="lock">
            <div class="space-y-4">
                <x-admin.select name="required_tier" label="Forfait requis" :value="$course->required_tier" placeholder="Gratuit (compte requis)"
                    :options="collect($tiers)->mapWithKeys(fn ($l, $k) => [$k => 'Abonnés ' . $l . ' et plus'])->all()"
                    hint="Les leçons marquées « aperçu » restent ouvertes à tout compte." />
                <x-admin.toggle name="is_published" label="Publié dans l'application" :checked="$course->is_published ?? false" />
            </div>
        </x-admin.card>
        <button type="submit" class="w-full bg-primary-500 hover:bg-primary-600 text-white px-6 py-3 rounded-lg font-medium transition">
            <i class="fas fa-check mr-2"></i>{{ $course->exists ? 'Enregistrer le cours' : 'Créer le cours' }}
        </button>
    </div>
</div>
