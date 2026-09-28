{{-- Formulaire d'annonce de casting + rôles. Attend : $call, $countries --}}
@php
    $roles = old('roles', $call->exists
        ? $call->roles->map(fn ($r) => $r->only(['id', 'name', 'kind', 'profession', 'importance', 'gender', 'age_min', 'age_max', 'min_tier', 'description', 'requirements', 'positions']))->values()->all()
        : [['id' => null, 'name' => '', 'kind' => 'acteur', 'profession' => '', 'importance' => 'principal', 'gender' => 'indifferent', 'age_min' => '', 'age_max' => '', 'min_tier' => '', 'description' => '', 'requirements' => '', 'positions' => 1]]);
@endphp

<div x-data="castingForm(@js($roles))" class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2 space-y-6">
        <x-admin.card title="Le projet" icon="clapperboard">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <x-admin.input class="md:col-span-2" name="title" label="Titre de l'annonce" :value="$call->title" required placeholder="Série « Les Héritiers » — saison 3" />
                <x-admin.input name="project_title" label="Titre du projet" :value="$call->project_title" required />
                <x-admin.select name="project_type" label="Type de projet" :value="$call->project_type" :options="\App\Models\CastingCall::PROJECT_TYPES" required />
                <x-admin.input name="production_company" label="Société de production" :value="$call->production_company" />
                <x-admin.input name="director" label="Réalisation" :value="$call->director" />
                <x-admin.textarea class="md:col-span-2" name="description" label="Présentation du projet" :value="$call->description" rows="5"
                    hint="Synopsis court, ton, conditions de tournage : ce qu'un comédien doit savoir avant de postuler." />
                <div class="md:col-span-2">
                    <x-admin.translation :model="$call->exists ? $call : null" :fields="['description' => ['Project description', 'textarea']]" />
                </div>
            </div>
        </x-admin.card>

        <x-admin.card title="Rôles à pourvoir" icon="users" subtitle="Un candidat postule à un rôle précis. Retirer un rôle supprime aussi ses candidatures.">
            <div class="space-y-4">
                <template x-for="(role, i) in roles" :key="i">
                    <div class="rounded-xl border border-dark-200 bg-dark-50 p-4">
                        <input type="hidden" :name="`roles[${i}][id]`" :value="role.id ?? ''">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider" x-text="`Rôle ${i + 1}`"></span>
                            <button type="button" @click="roles.splice(i, 1)" class="text-xs text-rose-300 hover:text-rose-200"><i class="fas fa-xmark mr-1"></i>Retirer</button>
                        </div>
                        <div class="grid grid-cols-12 gap-3">
                            <div class="col-span-12 md:col-span-5">
                                <label class="block text-xs text-gray-400 mb-1">Nom du rôle / poste</label>
                                <input type="text" :name="`roles[${i}][name]`" x-model="role.name" required placeholder="Nadia Mbappé"
                                       class="w-full bg-dark-100 border border-dark-200 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-primary-500">
                            </div>
                            <div class="col-span-6 md:col-span-3">
                                <label class="block text-xs text-gray-400 mb-1">Profil</label>
                                <select :name="`roles[${i}][kind]`" x-model="role.kind" class="w-full bg-dark-100 border border-dark-200 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-primary-500">
                                    <option value="acteur">Comédien(ne)</option>
                                    <option value="technicien">Technicien(ne)</option>
                                </select>
                            </div>
                            <div class="col-span-6 md:col-span-4" x-show="role.kind === 'acteur'">
                                <label class="block text-xs text-gray-400 mb-1">Importance</label>
                                <select :name="`roles[${i}][importance]`" x-model="role.importance" class="w-full bg-dark-100 border border-dark-200 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-primary-500">
                                    @foreach(\App\Models\CastingRole::IMPORTANCE as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-span-6 md:col-span-4" x-show="role.kind === 'technicien'" x-cloak>
                                <label class="block text-xs text-gray-400 mb-1">Métier</label>
                                <select :name="`roles[${i}][profession]`" x-model="role.profession" class="w-full bg-dark-100 border border-dark-200 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-primary-500">
                                    <option value="">—</option>
                                    @foreach(collect(\App\Models\Talent::PROFESSIONS)->except('acteur') as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-span-6 md:col-span-3">
                                <label class="block text-xs text-gray-400 mb-1">Genre</label>
                                <select :name="`roles[${i}][gender]`" x-model="role.gender" class="w-full bg-dark-100 border border-dark-200 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-primary-500">
                                    @foreach(\App\Models\CastingRole::GENDERS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-span-3 md:col-span-2">
                                <label class="block text-xs text-gray-400 mb-1">Âge min</label>
                                <input type="number" min="1" max="100" :name="`roles[${i}][age_min]`" x-model="role.age_min" class="w-full bg-dark-100 border border-dark-200 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-primary-500">
                            </div>
                            <div class="col-span-3 md:col-span-2">
                                <label class="block text-xs text-gray-400 mb-1">Âge max</label>
                                <input type="number" min="1" max="100" :name="`roles[${i}][age_max]`" x-model="role.age_max" class="w-full bg-dark-100 border border-dark-200 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-primary-500">
                            </div>
                            <div class="col-span-6 md:col-span-3">
                                <label class="block text-xs text-gray-400 mb-1">Rang minimum</label>
                                <select :name="`roles[${i}][min_tier]`" x-model="role.min_tier" class="w-full bg-dark-100 border border-dark-200 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-primary-500">
                                    <option value="">Ouvert à tous</option>
                                    @foreach(\App\Models\Talent::TIER_LABELS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-span-6 md:col-span-2">
                                <label class="block text-xs text-gray-400 mb-1">Postes</label>
                                <input type="number" min="1" :name="`roles[${i}][positions]`" x-model="role.positions" class="w-full bg-dark-100 border border-dark-200 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-primary-500">
                            </div>
                            <div class="col-span-12 md:col-span-7">
                                <label class="block text-xs text-gray-400 mb-1">Description du personnage / de la mission</label>
                                <textarea rows="2" :name="`roles[${i}][description]`" x-model="role.description" class="w-full bg-dark-100 border border-dark-200 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-primary-500"></textarea>
                            </div>
                            <div class="col-span-12 md:col-span-5">
                                <label class="block text-xs text-gray-400 mb-1">Exigences (langues, compétences…)</label>
                                <textarea rows="2" :name="`roles[${i}][requirements]`" x-model="role.requirements" class="w-full bg-dark-100 border border-dark-200 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-primary-500"></textarea>
                            </div>
                        </div>
                    </div>
                </template>
                <button type="button" @click="addRole()" class="w-full border border-dashed border-dark-300 hover:border-primary-500/60 text-gray-400 hover:text-primary-300 rounded-lg py-2.5 text-sm transition">
                    <i class="fas fa-plus mr-2"></i>Ajouter un rôle
                </button>
            </div>
        </x-admin.card>
    </div>

    <div class="space-y-6">
        <x-admin.card title="Publication" icon="eye">
            <div class="space-y-4">
                <x-admin.select name="status" label="Statut" :value="$call->status" :options="\App\Models\CastingCall::STATUSES" required
                    hint="Seules les annonces ouvertes acceptent des candidatures, jusqu'à la date limite." />
                <x-admin.input name="deadline_at" type="datetime-local" label="Date limite de candidature"
                    :value="$call->deadline_at?->format('Y-m-d\TH:i')" />
                <x-admin.toggle name="is_featured" label="Mettre à la une" :checked="$call->is_featured ?? false" />
            </div>
        </x-admin.card>
        <x-admin.card title="Tournage" icon="location-dot">
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <x-admin.input name="shooting_starts_on" type="date" label="Début" :value="$call->shooting_starts_on?->format('Y-m-d')" />
                    <x-admin.input name="shooting_ends_on" type="date" label="Fin" :value="$call->shooting_ends_on?->format('Y-m-d')" />
                </div>
                <x-admin.input name="city" label="Ville" :value="$call->city" />
                <x-admin.select name="country_code" label="Pays" :value="$call->country_code" placeholder="—"
                    :options="$countries->mapWithKeys(fn ($c) => [$c->code => $c->flag_emoji . ' ' . $c->name])->all()" />
                <x-admin.select name="compensation" label="Rémunération" :value="$call->compensation" :options="\App\Models\CastingCall::COMPENSATIONS" required />
                <x-admin.input name="compensation_details" label="Précisions" :value="$call->compensation_details" placeholder="Cachet selon expérience…" />
                <x-admin.input name="contact_email" type="email" label="E-mail de la production" :value="$call->contact_email" hint="Interne : non affiché dans l'app." />
            </div>
        </x-admin.card>
        <x-admin.card title="Visuel" icon="image">
            <x-admin.image-upload name="cover" label="Bannière (16:9)" :current="$call->cover_path ? asset('storage/' . $call->cover_path) : null" />
        </x-admin.card>
        <button type="submit" class="w-full bg-primary-500 hover:bg-primary-600 text-white px-6 py-3 rounded-lg font-medium transition">
            <i class="fas fa-check mr-2"></i>{{ $call->exists ? "Enregistrer l'annonce" : "Créer l'annonce" }}
        </button>
    </div>
</div>

@push('scripts')
<script>
function castingForm(roles) {
    return {
        roles: roles.map((r) => ({ ...r, age_min: r.age_min ?? '', age_max: r.age_max ?? '', min_tier: r.min_tier ?? '', profession: r.profession ?? '', importance: r.importance ?? 'principal', description: r.description ?? '', requirements: r.requirements ?? '' })),
        addRole() {
            this.roles.push({ id: null, name: '', kind: 'acteur', profession: '', importance: 'secondaire', gender: 'indifferent', age_min: '', age_max: '', min_tier: '', description: '', requirements: '', positions: 1 });
        },
    };
}
</script>
@endpush
