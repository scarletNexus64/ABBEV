{{-- Formulaire d'une fiche talent (création / édition).
     Attend : $talent, $agents, $countries, $catalog --}}
@php
    $credits = old('credits', $talent->exists
        ? $talent->credits->map(fn ($c) => ['title' => $c->title, 'year' => $c->year, 'role' => $c->role, 'media_id' => $c->media_id])->values()->all()
        : [['title' => '', 'year' => '', 'role' => '', 'media_id' => '']]);
    $professions = collect(\App\Models\Talent::PROFESSIONS)->except('acteur')->all();
@endphp

<div x-data="talentForm(@js(old('kind', $talent->kind ?? 'acteur')), @js($credits))" class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2 space-y-6">
        <x-admin.card title="Identité" icon="id-card">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="md:col-span-2">
                    <span class="block text-sm font-medium text-gray-300 mb-2">Type de talent <span class="text-rose-400">*</span></span>
                    <div class="grid grid-cols-2 gap-3">
                        @foreach(\App\Models\Talent::KINDS as $value => $label)
                            <label class="flex items-center gap-3 rounded-xl border px-4 py-3 cursor-pointer transition"
                                   :class="kind === '{{ $value }}' ? 'border-primary-500 bg-primary-500/10' : 'border-dark-200 hover:border-dark-300'">
                                <input type="radio" name="kind" value="{{ $value }}" x-model="kind" class="sr-only">
                                <i class="fas fa-{{ $value === 'acteur' ? 'masks-theater' : 'video' }} text-primary-300"></i>
                                <span class="text-sm text-white font-medium">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <x-admin.input name="first_name" label="Prénom" :value="$talent->first_name" required />
                <x-admin.input name="last_name" label="Nom" :value="$talent->last_name" required />
                <x-admin.input name="stage_name" label="Nom de scène" :value="$talent->stage_name" hint="Remplace le nom complet à l'affichage s'il est renseigné." />
                <div x-show="kind === 'technicien'" x-cloak>
                    <x-admin.select name="profession" label="Métier" :value="$talent->profession === 'acteur' ? 'realisateur' : $talent->profession" :options="$professions" />
                </div>
                <template x-if="kind === 'acteur'"><input type="hidden" name="profession" value="acteur"></template>
                <x-admin.select name="gender" label="Genre" :value="$talent->gender" placeholder="Non précisé" :options="\App\Models\Talent::GENDERS" />
                <x-admin.input name="birth_year" type="number" label="Année de naissance" :value="$talent->birth_year" min="1920" :max="now()->year" />
                <x-admin.input name="city" label="Ville" :value="$talent->city" placeholder="Douala" />
                <x-admin.select name="country_code" label="Pays" :value="$talent->country_code" placeholder="—"
                    :options="$countries->mapWithKeys(fn ($c) => [$c->code => $c->flag_emoji . ' ' . $c->name])->all()" />
            </div>
        </x-admin.card>

        <x-admin.card title="Rang ABBEV" icon="ranking-star" subtitle="Le classement ABBEV, commun aux comédiens et aux techniciens.">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                @foreach(\App\Models\Talent::TIER_LABELS as $tier => $label)
                    <label class="relative rounded-xl border p-4 cursor-pointer transition has-[:checked]:border-primary-500 has-[:checked]:bg-primary-500/10 border-dark-200 hover:border-dark-300">
                        <input type="radio" name="tier" value="{{ $tier }}" class="sr-only" @checked(old('tier', $talent->tier) === $tier)>
                        <div class="mb-2">@include('talents._tier-badge', ['tier' => $tier])</div>
                        <p class="text-sm text-white font-semibold">{{ \Illuminate\Support\Str::after($label, '— ') }}</p>
                        <p class="text-[11px] text-gray-500 mt-1 leading-snug">{{ \App\Models\Talent::TIER_DESCRIPTIONS[$tier] }}</p>
                    </label>
                @endforeach
            </div>
            @error('tier')<p class="text-xs text-rose-400 mt-2">{{ $message }}</p>@enderror
        </x-admin.card>

        <x-admin.card title="Biographie" icon="feather" subtitle="La « bio d'acteur » ou « bio de technicien » affichée sur la fiche.">
            <div class="space-y-5">
                <x-admin.input name="headline" label="Accroche" :value="$talent->headline" maxlength="160" placeholder="Ex. Star des séries camerounaises" />
                <x-admin.textarea name="bio" label="Biographie" :value="$talent->bio" rows="7" maxlength="6000" />
                <x-admin.translation :model="$talent->exists ? $talent : null"
                    :fields="['headline' => ['Headline', 'input'], 'bio' => ['Biography', 'textarea']]" />
            </div>
        </x-admin.card>

        <x-admin.card title="Profil casting" icon="clipboard-user">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <template x-if="kind === 'acteur'">
                    <div class="md:col-span-3 grid grid-cols-1 md:grid-cols-3 gap-5">
                        <x-admin.input name="playing_age_min" type="number" label="Âge de jeu — min" :value="$talent->playing_age_min" min="1" max="100" />
                        <x-admin.input name="playing_age_max" type="number" label="Âge de jeu — max" :value="$talent->playing_age_max" min="1" max="100" />
                        <x-admin.input name="height_cm" type="number" label="Taille (cm)" :value="$talent->height_cm" min="50" max="250" />
                    </div>
                </template>
                <x-admin.input class="md:col-span-3" name="languages" label="Langues" :value="implode(', ', $talent->languages ?? [])" placeholder="Français, Anglais, Duala" hint="Séparées par des virgules." />
                <x-admin.input class="md:col-span-3" name="skills" label="Compétences" :value="implode(', ', $talent->skills ?? [])" placeholder="Chant, Danse, Cascades…" hint="Séparées par des virgules." />
                <x-admin.input class="md:col-span-3" name="showreel_url" type="url" label="Bande démo (lien)" :value="$talent->showreel_url" placeholder="https://" />
            </div>
        </x-admin.card>

        <x-admin.card title="Filmographie" icon="film" subtitle="Reliez une œuvre du catalogue pour que l'app propose de la regarder.">
            <div class="space-y-3">
                <template x-for="(row, i) in credits" :key="i">
                    <div class="grid grid-cols-12 gap-3 items-end bg-dark-50 rounded-lg p-3 border border-dark-200">
                        <div class="col-span-12 md:col-span-4">
                            <label class="block text-xs text-gray-400 mb-1">Titre</label>
                            <input type="text" :name="`credits[${i}][title]`" x-model="row.title" placeholder="Titre de l'œuvre"
                                   class="w-full bg-dark-100 border border-dark-200 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-primary-500">
                        </div>
                        <div class="col-span-4 md:col-span-2">
                            <label class="block text-xs text-gray-400 mb-1">Année</label>
                            <input type="number" :name="`credits[${i}][year]`" x-model="row.year" min="1900"
                                   class="w-full bg-dark-100 border border-dark-200 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-primary-500">
                        </div>
                        <div class="col-span-8 md:col-span-3">
                            <label class="block text-xs text-gray-400 mb-1">Rôle / poste</label>
                            <input type="text" :name="`credits[${i}][role]`" x-model="row.role" placeholder="Rôle principal — Awa"
                                   class="w-full bg-dark-100 border border-dark-200 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-primary-500">
                        </div>
                        <div class="col-span-10 md:col-span-2">
                            <label class="block text-xs text-gray-400 mb-1">Catalogue</label>
                            <select :name="`credits[${i}][media_id]`" x-model="row.media_id"
                                    class="w-full bg-dark-100 border border-dark-200 rounded-lg px-2 py-2 text-white text-sm focus:outline-none focus:border-primary-500">
                                <option value="">—</option>
                                @foreach($catalog as $m)
                                    <option value="{{ $m->id }}">{{ \Illuminate\Support\Str::limit($m->title, 34) }}{{ $m->release_year ? ' (' . $m->release_year . ')' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-span-2 md:col-span-1 flex justify-end">
                            <button type="button" @click="credits.splice(i, 1)" class="w-9 h-9 rounded-lg bg-rose-500/10 hover:bg-rose-500 text-rose-300 hover:text-white transition" title="Retirer"><i class="fas fa-xmark"></i></button>
                        </div>
                    </div>
                </template>
                <button type="button" @click="credits.push({ title: '', year: '', role: '', media_id: '' })"
                        class="w-full border border-dashed border-dark-300 hover:border-primary-500/60 text-gray-400 hover:text-primary-300 rounded-lg py-2.5 text-sm transition">
                    <i class="fas fa-plus mr-2"></i>Ajouter une ligne
                </button>
            </div>
        </x-admin.card>
    </div>

    <div class="space-y-6">
        <x-admin.card title="Photo" icon="camera">
            <x-admin.image-upload name="photo" label="Portrait" ratio="aspect-[4/5]"
                :current="$talent->photo_path ? asset('storage/' . $talent->photo_path) : null"
                hint="Portrait vertical de préférence (4:5), 4 Mo maximum." />
        </x-admin.card>

        <x-admin.card title="Représentation" icon="user-tie">
            <x-admin.select name="agent_id" label="Agent" :value="$talent->agent_id" placeholder="Sans agent"
                :options="$agents->mapWithKeys(fn ($a) => [$a->id => $a->name . ($a->agency ? ' — ' . $a->agency : '')])->all()"
                hint="L'app affiche ses coordonnées sur la fiche du talent." />
        </x-admin.card>

        <x-admin.card title="Publication" icon="eye">
            <div class="space-y-4">
                <x-admin.toggle name="is_published" label="Fiche publiée" :checked="$talent->is_published ?? true" hint="Visible dans l'annuaire de l'application." />
                <x-admin.toggle name="is_featured" label="Mettre en avant" :checked="$talent->is_featured ?? false" hint="Remonte en tête de l'annuaire." />
            </div>
        </x-admin.card>

        <button type="submit" class="w-full bg-primary-500 hover:bg-primary-600 text-white px-6 py-3 rounded-lg font-medium transition">
            <i class="fas fa-check mr-2"></i>{{ $talent->exists ? 'Enregistrer la fiche' : 'Créer la fiche' }}
        </button>
    </div>
</div>

@push('scripts')
<script>
function talentForm(kind, credits) {
    return {
        kind,
        credits: credits.map((c) => ({ title: c.title ?? '', year: c.year ?? '', role: c.role ?? '', media_id: c.media_id ? String(c.media_id) : '' })),
    };
}
</script>
@endpush
