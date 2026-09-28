{{-- Téléversement d'image avec aperçu immédiat (sans aller-retour serveur).
     `current` : URL de l'image déjà enregistrée. `ratio` : aspect-video, aspect-[4/5]… --}}
@props(['name', 'label', 'current' => null, 'ratio' => 'aspect-video', 'hint' => 'JPG, PNG ou WebP — 4 Mo maximum.'])
<div {{ $attributes->class(['space-y-1.5']) }} x-data="{ preview: @js($current) }">
    <span class="block text-sm font-medium text-gray-300">{{ $label }}</span>
    <label class="relative block {{ $ratio }} w-full rounded-xl overflow-hidden border-2 border-dashed border-dark-300 hover:border-primary-500/60 bg-dark-50 cursor-pointer transition group">
        <template x-if="preview">
            <img :src="preview" alt="" class="absolute inset-0 w-full h-full object-cover">
        </template>
        <div class="absolute inset-0 flex flex-col items-center justify-center text-center p-4 transition"
             :class="preview ? 'bg-black/0 group-hover:bg-black/55 opacity-0 group-hover:opacity-100' : ''">
            <i class="fas fa-image text-2xl text-gray-500 group-hover:text-primary-300 mb-2"></i>
            <span class="text-xs text-gray-400" x-text="preview ? 'Remplacer l\'image' : 'Choisir une image'"></span>
        </div>
        <input type="file" name="{{ $name }}" accept="image/jpeg,image/png,image/webp" class="sr-only"
               @change="const f = $event.target.files[0]; if (f) preview = URL.createObjectURL(f)">
    </label>
    @if($hint && ! $errors->has($name))<p class="text-xs text-gray-500">{{ $hint }}</p>@endif
    @error($name)<p class="text-xs text-rose-400"><i class="fas fa-circle-exclamation mr-1"></i>{{ $message }}</p>@enderror
</div>
