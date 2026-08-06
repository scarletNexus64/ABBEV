{{-- Champs partages creation / edition d'une oeuvre.
     Attend : $oeuvre (nullable) --}}
@php($o = $oeuvre ?? null)

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Left -->
    <div>
        <!-- Titre -->
        <div class="mb-6">
            <label for="title" class="block text-sm font-medium text-gray-300 mb-2">Titre <span class="text-red-400">*</span></label>
            <input type="text" name="title" id="title" required
                   value="{{ old('title', $o->title ?? '') }}"
                   placeholder="Ex: Le Dernier Voyage"
                   class="w-full bg-dark-50 border @error('title') border-red-500 @else border-dark-200 @enderror rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500 transition">
            @error('title')
            <p class="mt-2 text-sm text-red-400"><i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}</p>
            @enderror
        </div>

        <!-- Auteur -->
        <div class="mb-6">
            <label for="author" class="block text-sm font-medium text-gray-300 mb-2">Auteur <span class="text-red-400">*</span></label>
            <input type="text" name="author" id="author" required
                   value="{{ old('author', $o->author ?? '') }}"
                   placeholder="Ex: Marie Fontaine"
                   class="w-full bg-dark-50 border @error('author') border-red-500 @else border-dark-200 @enderror rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500 transition">
            @error('author')
            <p class="mt-2 text-sm text-red-400"><i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}</p>
            @enderror
        </div>

        <!-- Description -->
        <div class="mb-6">
            <label for="description" class="block text-sm font-medium text-gray-300 mb-2">Description</label>
            <textarea name="description" id="description" rows="4"
                      placeholder="Resume de l'oeuvre..."
                      class="w-full bg-dark-50 border @error('description') border-red-500 @else border-dark-200 @enderror rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500 transition resize-none">{{ old('description', $o->description ?? '') }}</textarea>
            @error('description')
            <p class="mt-2 text-sm text-red-400"><i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}</p>
            @enderror
        </div>
    </div>

    <!-- Right -->
    <div>
        <!-- Fichier PDF -->
        <div class="mb-6">
            <label for="file" class="block text-sm font-medium text-gray-300 mb-2">
                Fichier PDF @unless($o) <span class="text-red-400">*</span> @endunless
            </label>
            <div class="relative">
                <input type="file" name="file" id="file" accept=".pdf"
                       {{ $o ? '' : 'required' }}
                       class="w-full bg-dark-50 border @error('file') border-red-500 @else border-dark-200 @enderror rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500 transition file:mr-4 file:py-1 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-primary-500/20 file:text-primary-400 hover:file:bg-primary-500/30">
            </div>
            @if($o && $o->file_path)
                <p class="mt-2 text-sm text-gray-400">
                    <i class="fas fa-file-pdf text-red-400 mr-1"></i>
                    Fichier actuel : {{ basename($o->file_path) }}
                    @if($o->pages) ({{ $o->pages }} pages) @endif
                </p>
            @endif
            @error('file')
            <p class="mt-2 text-sm text-red-400"><i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}</p>
            @enderror
            <p class="mt-2 text-xs text-gray-500">PDF uniquement, 20 Mo max.</p>
        </div>

        <!-- Couverture -->
        <div class="mb-6">
            <label for="cover" class="block text-sm font-medium text-gray-300 mb-2">Image de couverture</label>
            <div class="flex items-start gap-4">
                @if($o && $o->cover_path)
                    <img src="{{ asset('storage/' . $o->cover_path) }}" alt="Couverture" class="w-20 h-28 object-cover rounded border border-dark-200">
                @endif
                <div class="flex-1">
                    <input type="file" name="cover" id="cover" accept="image/*"
                           class="w-full bg-dark-50 border @error('cover') border-red-500 @else border-dark-200 @enderror rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500 transition file:mr-4 file:py-1 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-primary-500/20 file:text-primary-400 hover:file:bg-primary-500/30">
                    @error('cover')
                    <p class="mt-2 text-sm text-red-400"><i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}</p>
                    @enderror
                    <p class="mt-2 text-xs text-gray-500">Image optionnelle (JPG, PNG). 2 Mo max.</p>
                </div>
            </div>
        </div>

        <!-- Statut -->
        <div class="mb-6">
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1"
                       {{ old('is_active', $o ? $o->is_active : true) ? 'checked' : '' }}
                       class="w-5 h-5 rounded border-dark-200 bg-dark-50 text-primary-500 focus:ring-primary-500 focus:ring-offset-0">
                <span class="text-sm text-gray-300">Visible dans l'application</span>
            </label>
        </div>
    </div>
</div>
