{{-- Champs communs création / édition d'un genre. --}}
<div class="grid grid-cols-1 gap-5">
    <x-admin.input name="name" label="Nom du genre" :value="$category->name" required maxlength="80" placeholder="Ex. Drame" />
    <x-admin.textarea name="description" label="Description" :value="$category->description" rows="3"
        hint="Une phrase, affichée sous le genre dans l'application." maxlength="500" />
    <x-admin.translation :model="$category->exists ? $category : null"
        :fields="['name' => ['Name', 'input'], 'description' => ['Description', 'textarea']]" />
</div>
