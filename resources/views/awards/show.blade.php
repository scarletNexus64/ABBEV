@extends('admin.layouts.app')

@section('title', $edition->name)
@section('header', 'Lions Head Awards')

@section('content')
@php
    $status = $edition->status();
    $byScope = $edition->categories->groupBy('scope');
    $talentOptions = $talents->mapWithKeys(fn ($t) => [$t->id => $t->displayName() . ' — ' . (\App\Models\Talent::TIER_LABELS[$t->tier] ?? $t->tier) . ' · ' . $t->professionLabel()])->all();
    $mediaOptions = $catalog->mapWithKeys(fn ($m) => [$m->id => $m->title . ($m->release_year ? ' (' . $m->release_year . ')' : '') . ' · ' . ($m->type === 'series' ? 'Série' : 'Film')])->all();
@endphp

{{-- En-tête de l'édition --}}
<div class="relative overflow-hidden rounded-2xl border border-yellow-500/20 mb-6">
    <div class="absolute inset-0 opacity-35">
        <x-admin.thumb :path="$edition->cover_path" icon="trophy" class="w-full h-full" />
    </div>
    <div class="absolute inset-0 bg-gradient-to-r from-dark-50 via-dark-50/95 to-dark-50/40"></div>
    <div class="relative p-6 md:p-8 flex flex-col lg:flex-row lg:items-end gap-6">
        <div class="flex-1 min-w-0">
            <a href="{{ route('awards.index') }}" class="inline-flex items-center text-sm text-yellow-300/80 hover:text-yellow-200 mb-3"><i class="fas fa-arrow-left mr-2 text-xs"></i>Toutes les éditions</a>
            <div class="flex flex-wrap items-center gap-2 mb-2">
                @include('awards._status', ['edition' => $edition])
                @if($edition->is_current)<x-admin.badge tone="primary" icon="mobile-screen">Affichée dans l'app</x-admin.badge>@endif
            </div>
            <h2 class="text-3xl font-extrabold text-white">{{ $edition->name }}</h2>
            <p class="text-gray-300 mt-1">{{ $edition->tagline }}</p>
            <p class="text-sm text-gray-400 mt-3 flex flex-wrap gap-x-5 gap-y-1">
                <span><i class="fas fa-calendar mr-1.5 text-yellow-400/80"></i>
                    @if($edition->voting_starts_at) Vote du {{ $edition->voting_starts_at->format('d/m/Y H:i') }} au {{ $edition->voting_ends_at?->format('d/m/Y H:i') }} @else Période de vote à définir @endif
                </span>
                @if($edition->ceremony_at)
                    <span><i class="fas fa-champagne-glasses mr-1.5 text-yellow-400/80"></i>Cérémonie le {{ $edition->ceremony_at->format('d/m/Y') }}{{ $edition->ceremony_venue ? ' · ' . $edition->ceremony_venue : '' }}</span>
                @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('awards.edit', $edition) }}" class="inline-flex items-center gap-2 bg-dark-200 hover:bg-dark-300 text-gray-100 px-4 py-2.5 rounded-lg text-sm transition"><i class="fas fa-pen"></i> Paramètres</a>
            @unless($edition->is_current)
                <form action="{{ route('awards.current', $edition) }}" method="POST">@csrf
                    <button class="inline-flex items-center gap-2 bg-primary-500/20 hover:bg-primary-500 text-primary-200 hover:text-white px-4 py-2.5 rounded-lg text-sm transition"><i class="fas fa-mobile-screen"></i> Afficher dans l'app</button>
                </form>
            @endunless
            @if($edition->resultsArePublic())
                <form action="{{ route('awards.unpublish', $edition) }}" method="POST"
                      data-confirm="Retirer le palmarès de l'application ? Les lauréats désignés sont conservés." data-confirm-type="warning" data-confirm-title="Retirer les résultats" data-confirm-confirm="Retirer">
                    @csrf
                    <button class="inline-flex items-center gap-2 bg-amber-500/20 hover:bg-amber-500 text-amber-200 hover:text-white px-4 py-2.5 rounded-lg text-sm transition"><i class="fas fa-eye-slash"></i> Retirer les résultats</button>
                </form>
            @else
                <form action="{{ route('awards.publish', $edition) }}" method="POST"
                      data-confirm="{{ $status === 'voting' ? 'Le vote est encore ouvert ! ' : '' }}Publier le palmarès ? Dans chaque prix sans lauréat désigné par le jury, le nommé le plus voté l'emporte. Les résultats deviennent visibles dans l'application."
                      data-confirm-type="{{ $status === 'voting' ? 'warning' : 'primary' }}" data-confirm-title="Publier le palmarès" data-confirm-confirm="Publier">
                    @csrf
                    <button class="inline-flex items-center gap-2 bg-gradient-to-r from-yellow-500 to-amber-600 hover:from-yellow-400 hover:to-amber-500 text-black font-semibold px-4 py-2.5 rounded-lg text-sm transition"><i class="fas fa-trophy"></i> Publier le palmarès</button>
                </form>
            @endif
        </div>
    </div>
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <x-admin.stat label="Votes" :value="number_format($stats['votes'], 0, ',', ' ')" icon="check-to-slot" tone="amber" />
    <x-admin.stat label="Votants" :value="number_format($stats['voters'], 0, ',', ' ')" icon="users" tone="emerald" hint="Comptes ayant voté au moins une fois" />
    <x-admin.stat label="Nommés" :value="$stats['nominees']" icon="user-group" tone="violet" :hint="$edition->categories->count() . ' prix'" />
    <x-admin.stat label="Prix sans nommé" :value="$stats['empty']" icon="circle-exclamation" :tone="$stats['empty'] ? 'rose' : 'slate'" hint="Masqués de l'app tant qu'ils sont vides" />
</div>

<div class="flex items-center gap-1 border-b border-dark-200 mb-6">
    @foreach(['prix' => ['Prix & nommés', 'list-check'], 'resultats' => ['Résultats en direct', 'chart-simple']] as $key => [$label, $icon])
        <a href="{{ route('awards.show', [$edition, 'tab' => $key]) }}"
           @class(['px-4 py-3 text-sm font-medium border-b-2 -mb-px transition', 'border-yellow-400 text-white' => $tab === $key, 'border-transparent text-gray-400 hover:text-gray-200' => $tab !== $key])>
            <i class="fas fa-{{ $icon }} mr-2"></i>{{ $label }}
        </a>
    @endforeach
    <form action="{{ route('awards.template', $edition) }}" method="POST" class="ml-auto">@csrf
        <button class="text-xs text-gray-400 hover:text-yellow-300 px-3 py-2" title="Ajoute les prix officiels manquants"><i class="fas fa-wand-magic-sparkles mr-1"></i>Compléter la grille officielle</button>
    </form>
</div>

@if($tab === 'resultats')
    {{-- ================= RÉSULTATS ================= --}}
    <div class="mb-5 rounded-xl border border-sky-500/30 bg-sky-500/10 px-5 py-3 text-sm text-sky-200">
        <i class="fas fa-lock mr-2"></i>Ces chiffres ne sont visibles qu'ici tant que le palmarès n'est pas publié : afficher une tendance pendant le vote pousserait à voter pour le favori.
    </div>
    @foreach(\App\Models\AwardCategory::SCOPES as $scope => $scopeLabel)
        @continue(! $byScope->has($scope))
        <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-3 mt-2">{{ $scopeLabel }}</h3>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-8">
            @foreach($byScope[$scope] as $category)
                @php $rows = $standings[$category->id] ?? []; $sum = collect($rows)->sum('votes'); @endphp
                <x-admin.card id="resultats-{{ $category->id }}" :title="$category->name" :subtitle="$sum . ' vote(s)'" padding="p-5">
                    @forelse($rows as $row)
                        @php $n = $row['nominee']; @endphp
                        <div class="flex items-center gap-3 py-2">
                            <x-admin.thumb :path="$n->imagePath()" icon="user" class="w-9 h-9 rounded-full" />
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-2 text-sm">
                                    <span class="text-white truncate">{{ $n->name }} @if($n->is_winner)<i class="fas fa-trophy text-yellow-400 ml-1" title="Lauréat"></i>@endif</span>
                                    <span class="text-gray-400 tabular-nums shrink-0">{{ $row['votes'] }} · {{ $row['percent'] }} %</span>
                                </div>
                                <div class="h-2 rounded-full bg-dark-300 mt-1.5 overflow-hidden">
                                    <div class="h-full rounded-full {{ $n->is_winner ? 'bg-gradient-to-r from-yellow-400 to-amber-500' : 'bg-gradient-to-r from-primary-600 to-primary-400' }}" style="width: {{ $row['percent'] }}%"></div>
                                </div>
                            </div>
                            <form action="{{ route('awards.nominees.winner', $n) }}" method="POST">@csrf
                                <button class="w-8 h-8 rounded-lg transition {{ $n->is_winner ? 'bg-yellow-500 text-black' : 'bg-dark-200 text-gray-500 hover:text-yellow-300' }}"
                                        title="{{ $n->is_winner ? 'Retirer le titre de lauréat' : 'Désigner lauréat (choix du jury)' }}"><i class="fas fa-crown text-xs"></i></button>
                            </form>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Aucun nommé.</p>
                    @endforelse
                </x-admin.card>
            @endforeach
        </div>
    @endforeach
@else
    {{-- ================= PRIX & NOMMÉS ================= --}}
    @foreach(\App\Models\AwardCategory::SCOPES as $scope => $scopeLabel)
        @continue(! $byScope->has($scope))
        <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-3 mt-2">{{ $scopeLabel }} <span class="text-gray-600">· {{ $byScope[$scope]->count() }} prix</span></h3>
        <div class="space-y-3 mb-8">
            @foreach($byScope[$scope] as $category)
                <details id="prix-{{ $category->id }}" class="group rounded-xl border border-dark-200 bg-dark-100" @if($category->nominees->isEmpty()) open @endif>
                    <summary class="flex items-center gap-4 px-5 py-4 cursor-pointer list-none select-none">
                        <div class="w-9 h-9 rounded-lg bg-yellow-500/10 border border-yellow-500/25 flex items-center justify-center shrink-0">
                            <i class="fas fa-{{ $category->nominee_type === 'media' ? 'film' : 'user' }} text-yellow-300 text-sm"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-white font-medium">{{ $category->name }}</p>
                            <p class="text-xs text-gray-500 truncate">{{ $category->description }}</p>
                        </div>
                        <div class="hidden md:flex -space-x-2">
                            @foreach($category->nominees->take(5) as $n)
                                <x-admin.thumb :path="$n->imagePath()" icon="user" class="w-8 h-8 rounded-full ring-2 ring-dark-100" />
                            @endforeach
                        </div>
                        <x-admin.badge :tone="$category->nominees->isEmpty() ? 'rose' : 'gray'">{{ $category->nominees->count() }} nommé(s)</x-admin.badge>
                        <i class="fas fa-chevron-down text-xs text-gray-500 transition-transform group-open:rotate-180"></i>
                    </summary>
                    <div class="border-t border-dark-200 p-5 grid grid-cols-1 xl:grid-cols-5 gap-6">
                        <div class="xl:col-span-3">
                            @forelse($category->nominees as $n)
                                <div class="flex items-center gap-3 py-2 border-b border-dark-200 last:border-0">
                                    <x-admin.thumb :path="$n->imagePath()" icon="user" class="w-10 h-12 rounded-lg" />
                                    <div class="flex-1 min-w-0">
                                        <p class="text-white text-sm font-medium truncate">{{ $n->name }} @if($n->is_winner)<i class="fas fa-trophy text-yellow-400 ml-1"></i>@endif</p>
                                        <p class="text-xs text-gray-500 truncate">
                                            {{ $n->subtitle }}
                                            @if($n->talent) · talent de l'annuaire @elseif($n->media) · œuvre du catalogue @else · entrée libre @endif
                                        </p>
                                    </div>
                                    <form action="{{ route('awards.nominees.destroy', $n) }}" method="POST"
                                          data-confirm="Retirer {{ $n->name }} de ce prix ? Ses {{ $n->votes_count }} vote(s) seront perdus." data-confirm-type="danger" data-confirm-title="Retirer le nommé" data-confirm-confirm="Retirer">
                                        @csrf @method('DELETE')
                                        <button class="w-8 h-8 rounded-lg bg-rose-500/10 hover:bg-rose-500 text-rose-300 hover:text-white transition" title="Retirer"><i class="fas fa-xmark text-xs"></i></button>
                                    </form>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">Aucun nommé : ce prix n'apparaît pas encore dans l'application.</p>
                            @endforelse

                            <details class="mt-4 text-sm">
                                <summary class="cursor-pointer text-gray-400 hover:text-gray-200 list-none"><i class="fas fa-pen mr-1.5 text-xs"></i>Modifier ou supprimer ce prix</summary>
                                <form action="{{ route('awards.categories.update', $category) }}" method="POST" class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-3">
                                    @csrf @method('PUT')
                                    <x-admin.input name="name" label="Nom du prix" :value="$category->name" required />
                                    <x-admin.select name="scope" label="Volet" :value="$category->scope" :options="\App\Models\AwardCategory::SCOPES" />
                                    <x-admin.select name="nominee_type" label="On nomme" :value="$category->nominee_type" :options="\App\Models\AwardCategory::NOMINEE_TYPES" />
                                    <x-admin.input name="description" label="Description" :value="$category->description" />
                                    <input type="hidden" name="en[name]" value="{{ $category->translations->firstWhere(fn ($t) => $t->field === 'name' && $t->locale === 'en')?->value }}">
                                    <input type="hidden" name="en[description]" value="{{ $category->translations->firstWhere(fn ($t) => $t->field === 'description' && $t->locale === 'en')?->value }}">
                                    <div class="md:col-span-2 flex gap-2">
                                        <button class="bg-primary-500 hover:bg-primary-600 text-white px-4 py-2 rounded-lg text-sm transition">Enregistrer</button>
                                    </div>
                                </form>
                                <form action="{{ route('awards.categories.destroy', $category) }}" method="POST" class="mt-2"
                                      data-confirm="Supprimer le prix « {{ $category->name }} », ses nommés et leurs votes ?" data-confirm-type="danger" data-confirm-title="Supprimer le prix" data-confirm-confirm="Supprimer">
                                    @csrf @method('DELETE')
                                    <button class="text-rose-300 hover:text-rose-200 text-sm"><i class="fas fa-trash mr-1"></i>Supprimer ce prix</button>
                                </form>
                            </details>
                        </div>

                        <form action="{{ route('awards.nominees.store', $category) }}" method="POST" enctype="multipart/form-data"
                              class="xl:col-span-2 rounded-xl bg-dark-50 border border-dark-200 p-4 space-y-3"
                              x-data="{ source: '{{ $category->nominee_type === 'media' ? 'media' : 'talent' }}' }">
                            @csrf
                            <p class="text-sm font-medium text-white"><i class="fas fa-plus mr-1.5 text-yellow-300"></i>Ajouter un nommé</p>
                            <div class="grid grid-cols-3 gap-1.5 text-xs">
                                @foreach(['talent' => 'Talent', 'media' => 'Œuvre', 'free' => 'Libre'] as $src => $srcLabel)
                                    <label class="text-center py-1.5 rounded-lg border cursor-pointer transition"
                                           :class="source === '{{ $src }}' ? 'border-yellow-400 bg-yellow-500/10 text-yellow-200' : 'border-dark-200 text-gray-400'">
                                        <input type="radio" name="source" value="{{ $src }}" x-model="source" class="sr-only">{{ $srcLabel }}
                                    </label>
                                @endforeach
                            </div>
                            <div x-show="source === 'talent'">
                                <select name="talent_id" :disabled="source !== 'talent'" class="w-full bg-dark-100 border border-dark-200 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-yellow-500">
                                    <option value="">— Choisir un talent —</option>
                                    @foreach($talentOptions as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                                </select>
                            </div>
                            <div x-show="source === 'media'" x-cloak>
                                <select name="media_id" :disabled="source !== 'media'" class="w-full bg-dark-100 border border-dark-200 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-yellow-500">
                                    <option value="">— Choisir une œuvre —</option>
                                    @foreach($mediaOptions as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                                </select>
                            </div>
                            <input type="text" name="name" :required="source === 'free'" x-show="source === 'free'" x-cloak placeholder="Nom du nommé"
                                   class="w-full bg-dark-100 border border-dark-200 rounded-lg px-3 py-2 text-white text-sm placeholder-gray-600 focus:outline-none focus:border-yellow-500">
                            <input type="text" name="subtitle" placeholder="Œuvre concernée, ex. Le Fleuve des Promesses"
                                   class="w-full bg-dark-100 border border-dark-200 rounded-lg px-3 py-2 text-white text-sm placeholder-gray-600 focus:outline-none focus:border-yellow-500">
                            <label class="block text-xs text-gray-500">Photo (facultative — sinon celle du talent ou l'affiche)
                                <input type="file" name="photo" accept="image/*" class="mt-1 block w-full text-xs text-gray-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-dark-200 file:text-gray-200">
                            </label>
                            <button class="w-full bg-yellow-500/90 hover:bg-yellow-400 text-black font-semibold px-4 py-2 rounded-lg text-sm transition">Ajouter</button>
                        </form>
                    </div>
                </details>
            @endforeach
        </div>
    @endforeach

    <x-admin.card title="Ajouter un prix" icon="plus" subtitle="Pour un prix hors grille (prix du jury, prix spécial…)">
        <form action="{{ route('awards.categories.store', $edition) }}" method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            @csrf
            <x-admin.input name="name" label="Nom du prix" required placeholder="Prix spécial du jury" />
            <x-admin.select name="scope" label="Volet" :options="\App\Models\AwardCategory::SCOPES" />
            <x-admin.select name="nominee_type" label="On nomme" :options="\App\Models\AwardCategory::NOMINEE_TYPES" />
            <button class="bg-primary-500 hover:bg-primary-600 text-white px-5 py-2.5 rounded-lg font-medium transition"><i class="fas fa-plus mr-2"></i>Ajouter</button>
        </form>
    </x-admin.card>
@endif
@endsection
