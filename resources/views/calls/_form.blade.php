{{-- Formulaire d'appel à projets. Les blocs propres à chaque famille
     s'affichent selon le type choisi. Attend : $call --}}
@php
    $rewards = old('rewards', $call->rewards ?? []);
    if (empty($rewards)) {
        $rewards = [['amount' => '', 'title' => '', 'description' => '']];
    }
    $targets = \App\Models\ProjectCall::TARGETS;
@endphp
<div x-data="callForm(@js(old('type', $call->type)), @js(old('target', $call->target)), @js($targets), @js(array_values($rewards)))" class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2 space-y-6">
        <x-admin.card title="L'appel" icon="lightbulb">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="md:col-span-2">
                    <span class="block text-sm font-medium text-gray-300 mb-2">Famille d'appel <span class="text-rose-400">*</span></span>
                    <div class="grid grid-cols-3 gap-3">
                        @foreach(['financement' => ['hand-holding-dollar', 'Financement'], 'ecriture' => ['feather-pointed', 'Écriture de scénario'], 'musique' => ['music', 'Musique']] as $value => [$icon, $label])
                            <label class="flex flex-col items-center gap-2 rounded-xl border px-3 py-4 cursor-pointer transition text-center"
                                   :class="type === '{{ $value }}' ? 'border-primary-500 bg-primary-500/10' : 'border-dark-200 hover:border-dark-300'">
                                <input type="radio" name="type" value="{{ $value }}" x-model="type" @change="target = Object.keys(targets[type])[0]" class="sr-only">
                                <i class="fas fa-{{ $icon }} text-lg text-primary-300"></i>
                                <span class="text-sm text-white font-medium">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="md:col-span-2">
                    <span class="block text-sm font-medium text-gray-300 mb-2">Sous-catégorie <span class="text-rose-400">*</span></span>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="(label, key) in targets[type]" :key="key">
                            <label class="px-4 py-2 rounded-lg border text-sm cursor-pointer transition"
                                   :class="target === key ? 'border-primary-500 bg-primary-500/10 text-white' : 'border-dark-200 text-gray-400 hover:border-dark-300'">
                                <input type="radio" name="target" :value="key" x-model="target" class="sr-only"><span x-text="label"></span>
                            </label>
                        </template>
                    </div>
                </div>
                <x-admin.input class="md:col-span-2" name="title" label="Titre" :value="$call->title" required />
                <x-admin.input class="md:col-span-2" name="organizer" label="Porteur du projet / organisateur" :value="$call->organizer" placeholder="Sanaga Pictures" />
                <x-admin.textarea class="md:col-span-2" name="summary" label="Résumé" :value="$call->summary" rows="2" maxlength="500" hint="Une ou deux phrases pour la carte de l'appel." />
                <x-admin.textarea class="md:col-span-2" name="description" label="Présentation complète" :value="$call->description" rows="6" />
            </div>
        </x-admin.card>

        {{-- Financement --}}
        <div x-show="type === 'financement'" x-cloak>
            <x-admin.card title="Objectif & contreparties" icon="hand-holding-dollar"
                subtitle="Les soutiens s'engagent dans l'app ; aucun paiement n'y transite. Vous confirmez chaque promesse à réception des fonds.">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
                    <x-admin.input class="md:col-span-2" name="goal_amount" type="number" step="1" label="Objectif" :value="$call->goal_amount ? (int) $call->goal_amount : null" min="1" x-bind:required="type === 'financement'" />
                    <x-admin.input name="currency" label="Devise" :value="$call->currency ?? 'XAF'" maxlength="3" />
                    <x-admin.input name="min_pledge" type="number" step="1" label="Soutien minimum" :value="$call->min_pledge ? (int) $call->min_pledge : null" min="0" />
                    <x-admin.input class="md:col-span-4" name="raised_offline" type="number" step="1" label="Déjà réuni hors application" :value="$call->raised_offline ? (int) $call->raised_offline : 0" min="0"
                        hint="Apports déjà sécurisés (partenaires, préventes) : ils comptent dans la jauge affichée." />
                </div>
                <div class="mt-6">
                    <p class="text-sm font-medium text-gray-300 mb-2">Contreparties</p>
                    <div class="space-y-2">
                        <template x-for="(r, i) in rewards" :key="i">
                            <div class="grid grid-cols-12 gap-2 items-start">
                                <input type="number" min="0" :name="`rewards[${i}][amount]`" x-model="r.amount" placeholder="À partir de…"
                                       class="col-span-3 bg-dark-50 border border-dark-200 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-primary-500">
                                <input type="text" :name="`rewards[${i}][title]`" x-model="r.title" placeholder="Affiche dédicacée"
                                       class="col-span-3 bg-dark-50 border border-dark-200 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-primary-500">
                                <input type="text" :name="`rewards[${i}][description]`" x-model="r.description" placeholder="Détail de la contrepartie"
                                       class="col-span-5 bg-dark-50 border border-dark-200 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-primary-500">
                                <button type="button" @click="rewards.splice(i, 1)" class="col-span-1 h-9 rounded-lg bg-rose-500/10 hover:bg-rose-500 text-rose-300 hover:text-white transition"><i class="fas fa-xmark"></i></button>
                            </div>
                        </template>
                    </div>
                    <button type="button" @click="rewards.push({ amount: '', title: '', description: '' })" class="mt-2 text-sm text-primary-300 hover:text-primary-200"><i class="fas fa-plus mr-1"></i>Ajouter une contrepartie</button>
                </div>
            </x-admin.card>
        </div>

        {{-- Écriture & musique --}}
        <div x-show="type !== 'financement'" x-cloak>
            <x-admin.card title="Cahier des charges" icon="clipboard-list">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <x-admin.textarea class="md:col-span-2" name="requirements" label="Pièces demandées" :value="$call->requirements" rows="3"
                        placeholder="Pitch, synopsis, note d'intention, scénario en PDF…" />
                    <x-admin.input class="md:col-span-2" name="prize" label="Dotation / rémunération du lauréat" :value="$call->prize" placeholder="2 000 000 FCFA + résidence d'écriture" />
                    <div x-show="type === 'ecriture'" class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-5">
                        <x-admin.input name="genre" label="Genre attendu" :value="$call->genre" placeholder="Tous genres" />
                        <x-admin.input name="max_pages" type="number" label="Nombre de pages maximum" :value="$call->max_pages" min="1" />
                    </div>
                    <div x-show="type === 'musique'" x-cloak class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-5">
                        <x-admin.input name="music_style" label="Style musical" :value="$call->music_style" placeholder="Afrobeat, makossa…" />
                        <x-admin.input name="max_duration_minutes" type="number" label="Durée maximale (minutes)" :value="$call->max_duration_minutes" min="1" />
                    </div>
                </div>
            </x-admin.card>
        </div>

        <x-admin.translation :model="$call->exists ? $call : null"
            :fields="['title' => ['Title', 'input'], 'summary' => ['Summary', 'textarea'], 'description' => ['Presentation', 'textarea'], 'requirements' => ['Requirements', 'textarea']]" />
    </div>

    <div class="space-y-6">
        <x-admin.card title="Calendrier & statut" icon="calendar-days">
            <div class="space-y-4">
                <x-admin.select name="status" label="Statut" :value="$call->status" :options="\App\Models\ProjectCall::STATUSES" required />
                <x-admin.input name="opens_at" type="datetime-local" label="Ouverture" :value="$call->opens_at?->format('Y-m-d\TH:i')" hint="Vide : ouvert dès la publication." />
                <x-admin.input name="closes_at" type="datetime-local" label="Clôture" :value="$call->closes_at?->format('Y-m-d\TH:i')" />
                <x-admin.toggle name="is_featured" label="Mettre à la une" :checked="$call->is_featured ?? false" />
            </div>
        </x-admin.card>
        <x-admin.card title="Visuel & liens" icon="image">
            <div class="space-y-4">
                <x-admin.image-upload name="cover" label="Bannière (16:9)" :current="$call->cover_path ? asset('storage/' . $call->cover_path) : null" />
                <x-admin.input name="rules_url" type="url" label="Règlement complet (lien)" :value="$call->rules_url" placeholder="https://" />
                <x-admin.input name="contact_email" type="email" label="E-mail de contact" :value="$call->contact_email" hint="Interne : non affiché dans l'app." />
            </div>
        </x-admin.card>
        <button type="submit" class="w-full bg-primary-500 hover:bg-primary-600 text-white px-6 py-3 rounded-lg font-medium transition">
            <i class="fas fa-check mr-2"></i>{{ $call->exists ? "Enregistrer l'appel" : "Créer l'appel" }}
        </button>
    </div>
</div>

@push('scripts')
<script>
function callForm(type, target, targets, rewards) {
    return { type, target, targets, rewards: rewards.map((r) => ({ amount: r.amount ?? '', title: r.title ?? '', description: r.description ?? '' })) };
}
</script>
@endpush
