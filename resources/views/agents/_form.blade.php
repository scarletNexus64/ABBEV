{{-- Formulaire agent. Attend : $agent, $countries --}}
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2 space-y-6">
        <x-admin.card title="Agent" icon="user-tie">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <x-admin.input name="name" label="Nom de l'agent" :value="$agent->name" required />
                <x-admin.input name="agency" label="Agence" :value="$agent->agency" />
                <x-admin.select class="md:col-span-2" name="represents" label="Représente" :value="$agent->represents" :options="\App\Models\Agent::REPRESENTS" required
                    hint="Un agent « Acteurs & techniciens » apparaît dans les deux sous-catégories de l'app." />
                <x-admin.input name="email" type="email" label="E-mail" :value="$agent->email" />
                <x-admin.input name="phone" label="Téléphone" :value="$agent->phone" placeholder="+237 6 …" />
                <x-admin.input name="website_url" type="url" label="Site web" :value="$agent->website_url" placeholder="https://" />
                <x-admin.input name="city" label="Ville" :value="$agent->city" />
                <x-admin.select name="country_code" label="Pays" :value="$agent->country_code" placeholder="—"
                    :options="$countries->mapWithKeys(fn ($c) => [$c->code => $c->flag_emoji . ' ' . $c->name])->all()" />
                <x-admin.textarea class="md:col-span-2" name="bio" label="Présentation" :value="$agent->bio" rows="5" />
                <div class="md:col-span-2">
                    <x-admin.translation :model="$agent->exists ? $agent : null" :fields="['bio' => ['Presentation', 'textarea']]" />
                </div>
            </div>
        </x-admin.card>
        @if($agent->exists && $agent->talents->isNotEmpty())
            <x-admin.card title="Talents représentés" icon="users" padding="p-0">
                @foreach($agent->talents as $t)
                    <a href="{{ route('talents.edit', $t) }}" class="flex items-center gap-3 px-6 py-3 border-b border-dark-200 last:border-0 hover:bg-dark-50/40 transition">
                        <x-admin.thumb :path="$t->photo_path" icon="user" class="w-9 h-11 rounded-md" />
                        <span class="text-white flex-1">{{ $t->displayName() }}</span>
                        @include('talents._tier-badge', ['tier' => $t->tier])
                        <span class="text-sm text-gray-500 w-44 text-right">{{ $t->professionLabel() }}</span>
                    </a>
                @endforeach
            </x-admin.card>
        @endif
    </div>
    <div class="space-y-6">
        <x-admin.card title="Photo" icon="camera">
            <x-admin.image-upload name="photo" label="Photo ou logo" ratio="aspect-square"
                :current="$agent->photo_path ? asset('storage/' . $agent->photo_path) : null" />
        </x-admin.card>
        <x-admin.card title="Publication" icon="eye">
            <x-admin.toggle name="is_published" label="Visible dans l'application" :checked="$agent->is_published ?? true" />
        </x-admin.card>
        <button type="submit" class="w-full bg-primary-500 hover:bg-primary-600 text-white px-6 py-3 rounded-lg font-medium transition">
            <i class="fas fa-check mr-2"></i>{{ $agent->exists ? 'Enregistrer' : "Ajouter l'agent" }}
        </button>
    </div>
</div>
