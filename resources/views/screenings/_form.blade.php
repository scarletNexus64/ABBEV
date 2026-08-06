{{-- Champs partagés création / édition d'une séance.
     Attend : $screening (nullable), $movies, $countries --}}
@php($s = $screening ?? null)
@php($existingTypes = old('ticket_types', $s ? $s->ticketTypes->map(fn($t) => [
        'id' => $t->id, 'name' => $t->name, 'price' => $t->price, 'capacity' => $t->capacity, 'sold' => $t->sold_seats,
    ])->values()->all() : [['id' => null, 'name' => 'Standard', 'price' => 3000, 'capacity' => 100, 'sold' => 0]]))
@php($countriesJson = $countries->map(fn($c) => ['code' => $c->code, 'name' => $c->name, 'flag' => $c->flag_emoji, 'currency' => $c->currency_code])->values())

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Left -->
    <div>
        <!-- Film du catalogue -->
        <div class="mb-6">
            <label for="media_id" class="block text-sm font-medium text-gray-300 mb-2">Film du catalogue</label>
            <select name="media_id" id="media_id"
                    class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500 transition">
                <option value="">— Aucun (titre libre ci-dessous) —</option>
                @foreach($movies as $movie)
                <option value="{{ $movie->id }}" {{ (string) old('media_id', $s->media_id ?? '') === (string) $movie->id ? 'selected' : '' }}>
                    {{ $movie->title }}
                </option>
                @endforeach
            </select>
            <p class="mt-2 text-sm text-gray-400">
                <i class="fas fa-info-circle mr-1"></i> Choisissez un film existant, ou laissez vide et saisissez un titre.
            </p>
        </div>

        <!-- Titre libre -->
        <div class="mb-6">
            <label for="movie_title" class="block text-sm font-medium text-gray-300 mb-2">Titre du film (si hors catalogue)</label>
            <input type="text" name="movie_title" id="movie_title"
                   value="{{ old('movie_title', $s->movie_title ?? '') }}"
                   placeholder="Ex: Avatar 3"
                   class="w-full bg-dark-50 border @error('movie_title') border-red-500 @else border-dark-200 @enderror rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500 transition">
            @error('movie_title')
            <p class="mt-2 text-sm text-red-400"><i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}</p>
            @enderror
        </div>

        <!-- Cinéma -->
        <div class="mb-6">
            <label for="cinema_name" class="block text-sm font-medium text-gray-300 mb-2">Nom du cinéma <span class="text-red-400">*</span></label>
            <input type="text" name="cinema_name" id="cinema_name" required
                   value="{{ old('cinema_name', $s->cinema_name ?? '') }}"
                   placeholder="Ex: Canal Olympia"
                   class="w-full bg-dark-50 border @error('cinema_name') border-red-500 @else border-dark-200 @enderror rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500 transition">
            @error('cinema_name')
            <p class="mt-2 text-sm text-red-400"><i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}</p>
            @enderror
        </div>

        <!-- Lieu -->
        <div class="mb-6">
            <label for="location" class="block text-sm font-medium text-gray-300 mb-2">Lieu / Adresse <span class="text-red-400">*</span></label>
            <input type="text" name="location" id="location" required
                   value="{{ old('location', $s->location ?? '') }}"
                   placeholder="Ex: Bessengue, Douala"
                   class="w-full bg-dark-50 border @error('location') border-red-500 @else border-dark-200 @enderror rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500 transition">
            @error('location')
            <p class="mt-2 text-sm text-red-400"><i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}</p>
            @enderror
        </div>

        <!-- Pays -->
        <div class="mb-6" x-data="countryPicker()" x-init="init()">
            <label for="country_code" class="block text-sm font-medium text-gray-300 mb-2">Pays de la séance <span class="text-red-400">*</span></label>
            <select name="country_code" id="country_code" required x-model="selected" @change="onCountryChange()"
                    class="w-full bg-dark-50 border @error('country_code') border-red-500 @else border-dark-200 @enderror rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500 transition">
                <option value="">— Choisir un pays —</option>
                @foreach($countries as $country)
                <option value="{{ $country->code }}" {{ (string) old('country_code', $s->country_code ?? 'CM') === (string) $country->code ? 'selected' : '' }}>
                    {{ $country->flag_emoji }} {{ $country->name }} ({{ $country->currency_code }})
                </option>
                @endforeach
            </select>
            <p class="mt-2 text-sm text-gray-400">
                <i class="fas fa-info-circle mr-1"></i>
                Devise des tickets : <strong class="text-primary-400" x-text="currencyLabel"></strong>
                — seuls les utilisateurs de ce pays verront cette séance.
            </p>
            @error('country_code')
            <p class="mt-2 text-sm text-red-400"><i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}</p>
            @enderror
        </div>
    </div>

    <!-- Right -->
    <div>
        <!-- Date / heure de la séance -->
        <div class="mb-6">
            <label for="starts_at" class="block text-sm font-medium text-gray-300 mb-2">Date &amp; heure de la séance <span class="text-red-400">*</span></label>
            <input type="datetime-local" name="starts_at" id="starts_at" required
                   value="{{ old('starts_at', isset($s->starts_at) ? $s->starts_at->format('Y-m-d\TH:i') : '') }}"
                   class="w-full bg-dark-50 border @error('starts_at') border-red-500 @else border-dark-200 @enderror rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500 transition">
            @error('starts_at')
            <p class="mt-2 text-sm text-red-400"><i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}</p>
            @enderror
        </div>

        <!-- Statut -->
        <div class="mb-6">
            <label for="status" class="block text-sm font-medium text-gray-300 mb-2">Statut</label>
            @php($curStatus = old('status', $s->status ?? 'published'))
            <select name="status" id="status"
                    class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500 transition">
                <option value="published" {{ $curStatus === 'published' ? 'selected' : '' }}>Publiée (réservable)</option>
                <option value="draft" {{ $curStatus === 'draft' ? 'selected' : '' }}>Brouillon (cachée)</option>
                <option value="canceled" {{ $curStatus === 'canceled' ? 'selected' : '' }}>Annulée</option>
            </select>
            <p class="mt-2 text-sm text-gray-400"><i class="fas fa-info-circle mr-1"></i> Seules les séances « Publiées » apparaissent dans l'application.</p>
        </div>
    </div>
</div>

<!-- Catégories de places -->
<div class="mt-4 border-t border-dark-200 pt-6" x-data="ticketTypes()">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-white">
            <i class="fas fa-chair text-primary-400 mr-2"></i> Catégories de places &amp; tarifs
        </h3>
        <button type="button" @click="add()"
                class="bg-dark-200 hover:bg-dark-300 text-gray-200 px-4 py-2 rounded-lg text-sm transition">
            <i class="fas fa-plus mr-2"></i> Ajouter une catégorie
        </button>
    </div>

    @error('ticket_types')
    <p class="mb-3 text-sm text-red-400"><i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}</p>
    @enderror

    <div class="space-y-3">
        <template x-for="(row, index) in rows" :key="index">
            <div class="grid grid-cols-12 gap-3 items-end bg-dark-50 rounded-lg p-4 border border-dark-200">
                <input type="hidden" :name="`ticket_types[${index}][id]`" :value="row.id">

                <div class="col-span-12 md:col-span-4">
                    <label class="block text-xs text-gray-400 mb-1">Nom</label>
                    <input type="text" :name="`ticket_types[${index}][name]`" x-model="row.name" required
                           placeholder="Standard, VIP…"
                           class="w-full bg-dark-100 border border-dark-200 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-primary-500">
                </div>

                <div class="col-span-6 md:col-span-3">
                    <label class="block text-xs text-gray-400 mb-1">Prix (<span class="ticket-currency-label">XAF</span>)</label>
                    <input type="number" min="0" step="1" :name="`ticket_types[${index}][price]`" x-model="row.price" required
                           class="w-full bg-dark-100 border border-dark-200 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-primary-500">
                </div>

                <div class="col-span-6 md:col-span-3">
                    <label class="block text-xs text-gray-400 mb-1">
                        Capacité
                        <span x-show="row.sold > 0" class="text-gray-500" x-text="`(${row.sold} vendues)`"></span>
                    </label>
                    <input type="number" min="1" :name="`ticket_types[${index}][capacity]`" x-model="row.capacity" required
                           class="w-full bg-dark-100 border border-dark-200 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-primary-500">
                </div>

                <div class="col-span-12 md:col-span-2 flex md:justify-end">
                    <button type="button" @click="remove(index)" x-show="rows.length > 1"
                            :disabled="row.sold > 0"
                            :class="row.sold > 0 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-red-500 hover:text-white'"
                            class="bg-red-500/20 text-red-400 px-3 py-2 rounded-lg text-sm transition"
                            :title="row.sold > 0 ? 'Des places ont déjà été vendues' : 'Retirer'">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            </div>
        </template>
    </div>
    <p class="mt-3 text-sm text-gray-400">
        <i class="fas fa-info-circle mr-1"></i> Les places ne sont décomptées qu'une fois la réservation <strong>payée</strong>.
    </p>
</div>

<script>
function ticketTypes() {
    return {
        rows: @json($existingTypes),
        add() {
            this.rows.push({ id: null, name: '', price: 0, capacity: 50, sold: 0 });
        },
        remove(index) {
            if (this.rows[index].sold > 0) return;
            this.rows.splice(index, 1);
        },
    };
}

function countryPicker() {
    const countries = @json($countriesJson);
    return {
        selected: @json(old('country_code', $s->country_code ?? 'CM')),
        currencyLabel: '',
        init() {
            this.updateCurrency();
        },
        onCountryChange() {
            this.updateCurrency();
        },
        updateCurrency() {
            const c = countries.find(c => c.code === this.selected);
            this.currencyLabel = c ? c.currency : '—';
            document.querySelectorAll('.ticket-currency-label').forEach(el => {
                el.textContent = this.currencyLabel;
            });
        },
    };
}
</script>
