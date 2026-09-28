{{-- Recherche dans le catalogue + résultats à ajouter.
     Attend : $q (saisie), $results (médias trouvés), $searchAction (URL de la
     page, rechargée en GET) et $addButton (Media → HtmlString du bouton). --}}
<form method="GET" action="{{ $searchAction }}" class="abbev-search mb-4">
    <input type="text" name="q" value="{{ $q }}" placeholder="Rechercher un film ou une série du catalogue…"
           class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-2.5 text-white placeholder-gray-600 focus:outline-none focus:border-primary-500">
    <i class="fas fa-magnifying-glass"></i>
</form>
@if($q !== '')
    @forelse($results as $m)
        <div class="flex items-center gap-3 py-2.5 border-b border-dark-200 last:border-0">
            <x-admin.thumb :path="$m->cover_path ?: $m->thumbnail_path" icon="film" class="w-10 h-14 rounded-md" />
            <div class="flex-1 min-w-0">
                <p class="text-white text-sm font-medium truncate">{{ $m->title }}</p>
                <p class="text-xs text-gray-500">{{ $m->type === 'series' ? 'Série' : 'Film' }} · {{ $m->category?->name ?? '—' }} · {{ $m->release_year }}</p>
            </div>
            {{ $addButton($m) }}
        </div>
    @empty
        <p class="text-sm text-gray-500 py-3">Aucun contenu ne correspond à « {{ $q }} ».</p>
    @endforelse
@else
    <p class="text-xs text-gray-500">Tapez quelques lettres du titre puis Entrée.</p>
@endif
