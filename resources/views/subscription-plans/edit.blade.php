@extends('admin.layouts.app')

@section('title', 'Modifier le Pack - ABBEV')
@section('header', 'Modifier le Pack d\'abonnement')

@section('content')
<!-- Back Button -->
<div class="mb-6">
    <a href="{{ route('subscription-plans.index') }}" class="inline-flex items-center text-primary-400 hover:text-primary-300 transition">
        <i class="fas fa-arrow-left mr-2"></i> Retour à la liste
    </a>
</div>

<!-- Form Card -->
<div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-8"
     x-data="{ duration: {{ old('duration_days', $plan->duration_days) }}, setDuration(days){ this.duration = days } }">
    <form action="{{ route('subscription-plans.update', $plan) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Left Column -->
            <div>
                <!-- Name Field -->
                <div class="mb-6">
                    <label for="name" class="block text-sm font-medium text-gray-300 mb-2">
                        Nom du pack <span class="text-red-400">*</span>
                    </label>
                    <input type="text"
                           name="name"
                           id="name"
                           value="{{ old('name', $plan->name) }}"
                           required
                           placeholder="Ex: Premium, Basic, Annuel..."
                           class="w-full bg-dark-50 border @error('name') border-red-500 @else border-dark-200 @enderror rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500 transition">
                    @error('name')
                    <p class="mt-2 text-sm text-red-400">
                        <i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}
                    </p>
                    @enderror
                </div>

                <!-- Tier Field (rémunération producteurs) -->
                <div class="mb-6">
                    <label for="tier" class="block text-sm font-medium text-gray-300 mb-2">
                        Tier de rémunération <span class="text-red-400">*</span>
                    </label>
                    <select name="tier" id="tier" required
                            class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500 transition">
                        @foreach(['classique','standard','premium'] as $t)
                            <option value="{{ $t }}" {{ old('tier', $plan->tier) === $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
                        @endforeach
                    </select>
                    <p class="mt-2 text-xs text-gray-500">À chaque abonnement à ce forfait, tout le contenu de ce tier reçoit +1 vue producteur.</p>
                </div>

                <!-- Price Field -->
                <div class="mb-6">
                    <label for="price" class="block text-sm font-medium text-gray-300 mb-2">
                        Prix (XAF) <span class="text-red-400">*</span>
                    </label>
                    <input type="number"
                           name="price"
                           id="price"
                           value="{{ old('price', $plan->price) }}"
                           required
                           min="0"
                           step="0.01"
                           placeholder="Ex: 5000"
                           class="w-full bg-dark-50 border @error('price') border-red-500 @else border-dark-200 @enderror rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500 transition">
                    @error('price')
                    <p class="mt-2 text-sm text-red-400">
                        <i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}
                    </p>
                    @enderror
                </div>

                <!-- Duration Field -->
                <div class="mb-6">
                    <label for="duration_days" class="block text-sm font-medium text-gray-300 mb-2">
                        Durée (jours) <span class="text-red-400">*</span>
                    </label>
                    <div class="grid grid-cols-3 gap-2 mb-2">
                        <button type="button" @click="setDuration(30)" class="bg-dark-200 hover:bg-dark-300 text-gray-300 px-3 py-2 rounded-lg text-sm transition">
                            30 jours
                        </button>
                        <button type="button" @click="setDuration(90)" class="bg-dark-200 hover:bg-dark-300 text-gray-300 px-3 py-2 rounded-lg text-sm transition">
                            90 jours
                        </button>
                        <button type="button" @click="setDuration(365)" class="bg-dark-200 hover:bg-dark-300 text-gray-300 px-3 py-2 rounded-lg text-sm transition">
                            1 an
                        </button>
                    </div>
                    <input type="number"
                           name="duration_days"
                           id="duration_days"
                           x-model="duration"
                           value="{{ old('duration_days', $plan->duration_days) }}"
                           required
                           min="1"
                           placeholder="Ex: 30"
                           class="w-full bg-dark-50 border @error('duration_days') border-red-500 @else border-dark-200 @enderror rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500 transition">
                    @error('duration_days')
                    <p class="mt-2 text-sm text-red-400">
                        <i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}
                    </p>
                    @enderror
                </div>

                <!-- Order Field -->
                <div class="mb-6">
                    <label for="order" class="block text-sm font-medium text-gray-300 mb-2">
                        Ordre d'affichage
                    </label>
                    <input type="number"
                           name="order"
                           id="order"
                           value="{{ old('order', $plan->order) }}"
                           min="0"
                           placeholder="0"
                           class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500 transition">
                    <p class="mt-2 text-sm text-gray-400">
                        <i class="fas fa-info-circle mr-1"></i> Plus le nombre est petit, plus le pack apparaît en premier
                    </p>
                </div>
            </div>

            <!-- Right Column -->
            <div>
                <!-- Description Field -->
                <div class="mb-6">
                    <label for="description" class="block text-sm font-medium text-gray-300 mb-2">
                        Description
                    </label>
                    <textarea name="description"
                              id="description"
                              rows="4"
                              placeholder="Décrivez les avantages de ce pack..."
                              class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500 transition">{{ old('description', $plan->description) }}</textarea>
                </div>

                <!-- Features Field -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-300 mb-2">
                        Caractéristiques
                    </label>
                    <div x-data="{ features: {{ json_encode(old('features', $plan->features ?? [''])) }} }">
                        <template x-for="(feature, index) in features" :key="index">
                            <div class="flex gap-2 mb-2">
                                <input type="text"
                                       :name="'features[' + index + ']'"
                                       x-model="features[index]"
                                       placeholder="Ex: Accès illimité à tous les contenus"
                                       class="flex-1 bg-dark-50 border border-dark-200 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-primary-500 transition">
                                <button type="button"
                                        @click="features.splice(index, 1)"
                                        x-show="features.length > 1"
                                        class="bg-red-500/20 hover:bg-red-500 text-red-400 hover:text-white px-3 py-2 rounded-lg transition">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </template>
                        <button type="button"
                                @click="features.push('')"
                                class="w-full bg-dark-200 hover:bg-dark-300 text-gray-300 px-4 py-2 rounded-lg text-sm transition">
                            <i class="fas fa-plus mr-2"></i> Ajouter une caractéristique
                        </button>
                    </div>
                </div>

                <!-- Checkboxes -->
                <div class="space-y-4">
                    <label class="flex items-center">
                        <input type="checkbox"
                               name="is_active"
                               value="1"
                               {{ old('is_active', $plan->is_active) ? 'checked' : '' }}
                               class="w-5 h-5 bg-dark-50 border-dark-200 rounded text-primary-500 focus:ring-primary-500 focus:ring-offset-0">
                        <span class="ml-3 text-gray-300">Pack actif</span>
                    </label>

                    <label class="flex items-center">
                        <input type="checkbox"
                               name="is_popular"
                               value="1"
                               {{ old('is_popular', $plan->is_popular) ? 'checked' : '' }}
                               class="w-5 h-5 bg-dark-50 border-dark-200 rounded text-primary-500 focus:ring-primary-500 focus:ring-offset-0">
                        <span class="ml-3 text-gray-300">
                            Marquer comme populaire
                            <span class="text-gray-400 text-sm block ml-8">Affiche un badge "Populaire" sur le pack</span>
                        </span>
                    </label>
                </div>

                <!-- Subscription Info -->
                <div class="mt-6 bg-blue-500/10 border border-blue-500/30 rounded-lg p-4">
                    <p class="text-blue-300 text-sm">
                        <i class="fas fa-users mr-2"></i>
                        <strong>{{ $plan->subscriptions()->where('status', 'active')->count() }}</strong> abonnés actifs utilisent ce pack
                    </p>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex gap-4 mt-8">
            <button type="submit"
                    class="bg-primary-500 hover:bg-primary-600 text-white px-6 py-3 rounded-lg transition flex-1">
                <i class="fas fa-check mr-2"></i> Mettre à jour
            </button>
            <a href="{{ route('subscription-plans.index') }}"
               class="bg-dark-200 hover:bg-dark-300 text-white px-6 py-3 rounded-lg transition text-center">
                <i class="fas fa-times mr-2"></i> Annuler
            </a>
        </div>
    </form>
</div>

@endsection
