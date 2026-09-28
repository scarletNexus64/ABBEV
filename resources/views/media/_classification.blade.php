{{-- Classement d'un contenu au-delà de son genre : format de durée (cat.md :
     Film court/moyen/long, Série très court/court/moyen) et sélections
     éditoriales (Avant-première, Sport, Jeux — admin seulement).
     Attend : $medium (nullable), $rubriques ; s'appuie sur la variable Alpine
     `type` du formulaire parent. --}}
@php
    $currentFormat = old('format', ($medium?->format_locked ? $medium->format : ''));
    $selected = collect(old('rubriques', $medium?->rubriques?->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id);
    $isAdmin = auth()->user()?->isAdmin();
@endphp
<div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
    <h3 class="text-lg font-semibold text-white mb-1 flex items-center gap-2">
        <i class="fas fa-layer-group text-primary-400"></i> Classement
    </h3>
    <p class="text-sm text-gray-500 mb-5">Où ce contenu apparaît dans l'explorateur de l'application.</p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Format</label>
            <select name="format" class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500">
                <option value="" @selected($currentFormat === '')>Automatique (d'après la durée)</option>
                <template x-if="type === 'movie'">
                    <optgroup label="Film">
                        @foreach(\App\Support\MediaFormat::MOVIE as $f)
                            <option value="{{ $f }}" @selected($currentFormat === $f)>{{ \App\Support\MediaFormat::label('movie', $f) }} — {{ \App\Support\MediaFormat::hint('movie', $f) }}</option>
                        @endforeach
                    </optgroup>
                </template>
                <template x-if="type === 'series'">
                    <optgroup label="Série & feuilleton">
                        @foreach(\App\Support\MediaFormat::SERIES as $f)
                            <option value="{{ $f }}" @selected($currentFormat === $f)>{{ \App\Support\MediaFormat::label('series', $f) }} — {{ \App\Support\MediaFormat::hint('series', $f) }}</option>
                        @endforeach
                    </optgroup>
                </template>
            </select>
            <p class="mt-2 text-xs text-gray-500">
                @if($medium?->format)
                    Actuellement : <strong class="text-gray-300">{{ \App\Support\MediaFormat::label($medium->type, $medium->format) }}</strong>{{ $medium->format_locked ? ' (fixé à la main)' : ' (automatique)' }}.
                @else
                    En automatique, un film de moins de 30 min est un court métrage, de 30 à 59 min un moyen métrage, au-delà un long métrage ; une série se juge à la durée moyenne de ses épisodes.
                @endif
            </p>
        </div>

        @if($isAdmin && $rubriques->isNotEmpty())
            <div>
                <input type="hidden" name="rubriques_present" value="1">
                <span class="block text-sm font-medium text-gray-300 mb-2">Sélections éditoriales</span>
                <div class="space-y-2">
                    @foreach($rubriques as $rubrique)
                        <label class="flex items-center gap-3 px-3 py-2.5 rounded-lg border border-dark-200 hover:border-dark-300 cursor-pointer has-[:checked]:border-primary-500 has-[:checked]:bg-primary-500/10 transition">
                            <input type="checkbox" name="rubriques[]" value="{{ $rubrique->id }}" @checked($selected->contains($rubrique->id))
                                   class="w-4 h-4 rounded bg-dark-300 border-dark-400 text-primary-500">
                            <span class="text-sm text-white flex-1">{{ $rubrique->name }}</span>
                            @if($rubrique->required_tier)<span class="text-[10px] text-yellow-300">{{ ucfirst($rubrique->required_tier) }}</span>@endif
                        </label>
                    @endforeach
                </div>
                <p class="mt-2 text-xs text-gray-500">L'ordre dans chaque sélection se règle dans « Sélections éditoriales ».</p>
            </div>
        @endif
    </div>
</div>
