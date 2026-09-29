{{-- Formulaire d'édition des Lions Head Awards. Attend : $edition --}}
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2 space-y-6">
        <x-admin.card title="Édition" icon="trophy">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <x-admin.input class="md:col-span-2" name="name" label="Nom" :value="$edition->name" required />
                <x-admin.input name="year" type="number" label="Année" :value="$edition->year" required min="2000" max="2100" />
                <x-admin.input class="md:col-span-3" name="tagline" label="Accroche" :value="$edition->tagline" placeholder="Le public couronne le meilleur du cinéma et de la télévision" />
                <x-admin.textarea class="md:col-span-3" name="description" label="Présentation" :value="$edition->description" rows="4"
                    hint="Affichée en tête de l'écran de vote : règles, calendrier, cérémonie." />
                <div class="md:col-span-3">
                    <x-admin.translation :model="$edition->exists ? $edition : null"
                        :fields="['tagline' => ['Tagline', 'input'], 'description' => ['Presentation', 'textarea']]" />
                </div>
            </div>
        </x-admin.card>
        <x-admin.card title="Calendrier" icon="calendar-days" subtitle="Le vote s'ouvre et se ferme tout seul à ces dates.">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <x-admin.input name="voting_starts_at" type="datetime-local" label="Ouverture du vote" :value="$edition->voting_starts_at?->format('Y-m-d\TH:i')" />
                <x-admin.input name="voting_ends_at" type="datetime-local" label="Clôture du vote" :value="$edition->voting_ends_at?->format('Y-m-d\TH:i')" />
                <x-admin.input name="ceremony_at" type="datetime-local" label="Cérémonie" :value="$edition->ceremony_at?->format('Y-m-d\TH:i')" />
                <x-admin.input name="ceremony_venue" label="Lieu de la cérémonie" :value="$edition->ceremony_venue" placeholder="Palais des Congrès, Yaoundé" />
            </div>
        </x-admin.card>
    </div>
    <div class="space-y-6">
        <x-admin.card title="Visuel" icon="image">
            <x-admin.image-upload name="cover" label="Affiche de l'édition (16:9)" :current="$edition->cover_path ? asset('storage/' . $edition->cover_path) : null" />
        </x-admin.card>
        <x-admin.card title="Options" icon="sliders">
            <div class="space-y-4">
                @if(auth()->user()->isAdmin())
                <x-admin.toggle name="is_current" label="Afficher cette édition dans l'app" :checked="$edition->is_current || ! $edition->exists"
                    hint="Une seule édition à la fois : elle remplace la précédente dans l'écran « Lions Head Awards »." />
                @else
                <p class="text-xs text-gray-400"><i class="fas fa-mobile-screen mr-1"></i>
                    L'app n'affiche qu'une édition à la fois : l'administration ABBEV choisit laquelle.</p>
                @endif
                @unless($edition->exists)
                    <x-admin.toggle name="apply_template" label="Créer les 29 prix officiels" :checked="true"
                        hint="Cinéma (13), Télévision (13), Métiers (3). Modifiables ensuite." />
                @endunless
            </div>
        </x-admin.card>
        <button type="submit" class="w-full bg-gradient-to-r from-yellow-500 to-amber-600 hover:from-yellow-400 hover:to-amber-500 text-black px-6 py-3 rounded-lg font-semibold transition">
            <i class="fas fa-check mr-2"></i>{{ $edition->exists ? "Enregistrer l'édition" : "Créer l'édition" }}
        </button>
    </div>
</div>
