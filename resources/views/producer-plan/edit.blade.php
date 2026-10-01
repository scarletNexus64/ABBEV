@extends('admin.layouts.app')

@section('title', 'Pack producteur - ABBEV')
@section('header', 'Pack producteur')

@section('content')
<x-admin.page-header title="Pack producteur"
    subtitle="Abonnement que paie chaque producteur pour ouvrir son espace : upload de films et séries, et tous les modules. Un seul pack, valable pour tous les producteurs." />

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <x-admin.stat label="Producteurs" :value="$stats['producers']" icon="clapperboard" :href="route('producers.index')" />
    <x-admin.stat label="Espaces actifs" :value="$stats['subscribed']" icon="lock-open" tone="emerald" hint="abonnement en cours" />
    <x-admin.stat label="Revenus du pack" :value="number_format((float) $stats['revenue'], 0, ',', ' ') . ' FCFA'" icon="coins" tone="amber" hint="paiements confirmés" />
</div>

<form action="{{ route('producer-plan.update') }}" method="POST"
      x-data="{ price: @js((float) old('price', $plan->price)), period: @js(old('billing_period', $plan->billing_period)), count: @js((int) old('period_count', $plan->period_count)) }">
    @csrf @method('PUT')

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <x-admin.card class="xl:col-span-2" title="Le pack" icon="tags">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <x-admin.input class="md:col-span-2" name="name" label="Nom du pack" :value="$plan->name" required placeholder="Pack Producteur" />

                <x-admin.input name="price" label="Prix" type="number" min="100" step="1" :value="(int) $plan->price" required prefix="FCFA"
                    x-model.number="price" hint="Minimum 100 FCFA (Mobile Money)." />

                <div class="space-y-1.5">
                    <label class="block text-sm font-medium text-gray-300">Période de facturation <span class="text-rose-400">*</span></label>
                    <div class="flex gap-2">
                        <div class="relative w-28 shrink-0">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm pointer-events-none">×</span>
                            <input type="number" name="period_count" min="1" max="24" required x-model.number="count" value="{{ old('period_count', $plan->period_count) }}"
                                   class="w-full bg-dark-50 border {{ $errors->has('period_count') ? 'border-rose-500' : 'border-dark-200' }} rounded-lg pl-8 pr-3 py-2.5 text-white focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition">
                        </div>
                        <select name="billing_period" x-model="period" required
                                class="flex-1 bg-dark-50 border {{ $errors->has('billing_period') ? 'border-rose-500' : 'border-dark-200' }} rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition">
                            @foreach(\App\Models\ProducerPlan::PERIODS as $value => $label)
                                <option value="{{ $value }}" @selected(old('billing_period', $plan->billing_period) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    @error('period_count')<p class="text-xs text-rose-400"><i class="fas fa-circle-exclamation mr-1"></i>{{ $message }}</p>@enderror
                    @error('billing_period')<p class="text-xs text-rose-400"><i class="fas fa-circle-exclamation mr-1"></i>{{ $message }}</p>@enderror
                    <p class="text-xs text-gray-500">Durée d'accès ouverte par chaque paiement.</p>
                </div>

                <x-admin.textarea class="md:col-span-2" name="description" label="Description" :value="$plan->description" rows="3"
                    placeholder="Ouvrez votre espace producteur : publiez vos films et séries sur ABBEV…" />

                <div class="md:col-span-2 space-y-1.5"
                     x-data="{ features: @js(array_values(old('features', $plan->features ?: ['']))) }">
                    <label class="block text-sm font-medium text-gray-300">Avantages affichés au producteur</label>
                    <template x-for="(feature, index) in features" :key="index">
                        <div class="flex gap-2 mb-2">
                            <input type="text" :name="'features[' + index + ']'" x-model="features[index]"
                                   placeholder="Ex : Upload illimité de films et séries"
                                   class="flex-1 bg-dark-50 border border-dark-200 rounded-lg px-4 py-2 text-white placeholder-gray-600 focus:outline-none focus:border-primary-500 transition">
                            <button type="button" @click="features.splice(index, 1)" x-show="features.length > 1"
                                    class="bg-red-500/20 hover:bg-red-500 text-red-400 hover:text-white px-3 py-2 rounded-lg transition">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </template>
                    <button type="button" @click="features.push('')"
                            class="w-full bg-dark-200 hover:bg-dark-300 text-gray-300 px-4 py-2 rounded-lg text-sm transition">
                        <i class="fas fa-plus mr-2"></i> Ajouter un avantage
                    </button>
                </div>
            </div>
        </x-admin.card>

        <div class="space-y-6">
            <x-admin.card title="Activation" icon="lock">
                <x-admin.toggle name="is_active" label="Exiger le paiement des producteurs" :checked="$plan->is_active"
                    hint="Activé : l'espace d'un producteur sans abonnement en cours est entièrement verrouillé (équipe comprise) ; il ne voit que la page d'abonnement. Désactivé : tous les espaces sont ouverts." />

                <div class="mt-5 rounded-xl border border-primary-500/30 bg-primary-500/10 p-4">
                    <p class="text-xs uppercase tracking-wider text-primary-300 font-semibold">Aperçu</p>
                    <p class="text-2xl font-bold text-white mt-1">
                        <span x-text="new Intl.NumberFormat('fr-FR').format(price || 0)"></span> <span class="text-base text-gray-300">FCFA</span>
                    </p>
                    <p class="text-sm text-gray-400"
                       x-text="count > 1 ? ('tous les ' + count + (period === 'year' ? ' ans' : ' mois')) : (period === 'year' ? 'par an' : 'par mois')"></p>
                </div>

                <p class="text-xs text-gray-500 mt-4">
                    <i class="fas fa-circle-info mr-1"></i>
                    Un changement de prix ou de période s'applique aux prochains paiements ; les périodes déjà payées restent acquises.
                    Pour offrir l'accès à un producteur, passez par sa fiche.
                </p>
            </x-admin.card>

            <button type="submit" class="w-full bg-primary-500 hover:bg-primary-600 text-white px-6 py-3 rounded-lg font-medium transition">
                <i class="fas fa-check mr-2"></i> Enregistrer le pack
            </button>
        </div>
    </div>
</form>

<x-admin.card class="mt-6" title="Derniers paiements" icon="receipt" padding="p-0">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-dark-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase">Producteur</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase">Moyen</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase">Montant</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase">Statut</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-dark-200">
                @forelse($payments as $payment)
                    <tr class="hover:bg-dark-50 transition">
                        <td class="px-6 py-3">
                            @if($payment->user)
                                <a href="{{ route('producers.show', $payment->user) }}" class="text-white hover:text-primary-300">{{ $payment->user->name }}</a>
                            @else
                                <span class="text-gray-500">Compte supprimé</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-gray-300">{{ $payment->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-6 py-3 text-gray-300">{{ $payment->payment_method === 'stripe' ? 'Carte bancaire' : 'Mobile Money' }}</td>
                        <td class="px-6 py-3 text-white font-medium">{{ number_format((float) $payment->amount, 0, ',', ' ') }} FCFA</td>
                        <td class="px-6 py-3">
                            @switch($payment->status)
                                @case('completed') <x-admin.badge tone="emerald" icon="check">Payé</x-admin.badge> @break
                                @case('pending') <x-admin.badge tone="amber" icon="clock">En attente</x-admin.badge> @break
                                @case('failed') <x-admin.badge tone="rose" icon="xmark">Échoué</x-admin.badge> @break
                                @default <x-admin.badge>{{ $payment->status }}</x-admin.badge>
                            @endswitch
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-10 text-center text-gray-400">Aucun paiement de pack producteur pour l'instant.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin.card>
@endsection
