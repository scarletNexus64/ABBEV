{{-- Traduction ANGLAISE des textes d'un contenu. Le français reste la valeur
     de référence (colonne du modèle) ; un champ laissé vide retombe sur le
     français dans l'app, jamais sur un libellé blanc.

     `fields` : [champ => [libellé, 'input'|'textarea']] ; `model` : le
     contenu édité (ou null en création). --}}
@props(['fields', 'model' => null])
<details class="group rounded-xl border border-dark-200 bg-dark-50/60" @if($errors->has('en.*')) open @endif>
    <summary class="flex items-center justify-between gap-3 px-5 py-3.5 cursor-pointer select-none list-none">
        <span class="flex items-center gap-2 text-sm font-medium text-gray-200">
            <span class="text-base leading-none">🇬🇧</span> Version anglaise
            <span class="text-xs font-normal text-gray-500">— facultative, affichée aux utilisateurs anglophones</span>
        </span>
        <i class="fas fa-chevron-down text-xs text-gray-500 transition-transform group-open:rotate-180"></i>
    </summary>
    <div class="px-5 pb-5 pt-1 space-y-4 border-t border-dark-200">
        @foreach($fields as $field => [$fieldLabel, $kind])
            @php $existing = $model?->translations?->firstWhere(fn ($t) => $t->field === $field && $t->locale === 'en')?->value; @endphp
            @if($kind === 'textarea')
                <x-admin.textarea name="en[{{ $field }}]" :label="$fieldLabel" :value="old('en.' . $field, $existing)" rows="3" />
            @else
                <x-admin.input name="en[{{ $field }}]" :label="$fieldLabel" :value="old('en.' . $field, $existing)" />
            @endif
        @endforeach
    </div>
</details>
