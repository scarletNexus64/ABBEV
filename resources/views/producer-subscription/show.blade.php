@extends('admin.layouts.app')

@section('title', 'Abonnement producteur - ABBEV')
@section('header', 'Abonnement producteur')

@section('content')
@php
    $active = $plan && $plan->is_active;
    $priceLabel = $plan ? number_format((float) $plan->price, 0, ',', ' ') . ' FCFA' : '';
@endphp

{{-- ===== État de l'espace ===== --}}
@if(! $active)
    <x-admin.card class="mb-6">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-emerald-500/15 border border-emerald-500/30 flex items-center justify-center shrink-0">
                <i class="fas fa-lock-open text-emerald-300"></i>
            </div>
            <div class="flex-1">
                <p class="text-white font-semibold">Aucun abonnement n'est requis pour le moment</p>
                <p class="text-sm text-gray-400">Votre espace producteur est ouvert.</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="bg-primary-500 hover:bg-primary-600 text-white px-4 py-2 rounded-lg text-sm transition">
                Tableau de bord
            </a>
        </div>
    </x-admin.card>
@elseif($locked)
    <div class="relative overflow-hidden rounded-2xl border border-amber-500/30 bg-gradient-to-br from-amber-500/10 via-dark-100 to-dark-100 p-6 md:p-8 mb-6">
        <div class="flex flex-col md:flex-row md:items-center gap-5">
            <div class="w-14 h-14 rounded-2xl bg-amber-500/15 border border-amber-500/30 flex items-center justify-center shrink-0">
                <i class="fas fa-lock text-2xl text-amber-300"></i>
            </div>
            <div class="flex-1 min-w-0">
                @if($user->isProducerOwner())
                    <h2 class="text-xl md:text-2xl font-bold text-white">Votre espace producteur est verrouillé</h2>
                    <p class="text-gray-300 mt-1 max-w-3xl">
                        Abonnez-vous au pack producteur pour uploader vos films et séries et débloquer tous les modules de votre espace.
                        L'accès s'ouvre automatiquement dès que le paiement est confirmé.
                    </p>
                @else
                    <h2 class="text-xl md:text-2xl font-bold text-white">L'espace de {{ $owner?->name }} est verrouillé</h2>
                    <p class="text-gray-300 mt-1 max-w-3xl">
                        L'abonnement au pack producteur n'est pas actif. Seul {{ $owner?->name }}, titulaire de l'espace, peut l'activer :
                        vos modules seront de nouveau accessibles dès son paiement.
                    </p>
                @endif
            </div>
        </div>
        <div class="mt-5 flex flex-wrap gap-2">
            @foreach(\App\Models\User::MODULES as $module)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium border border-dark-300 bg-dark-50/60 text-gray-400">
                    <i class="fas fa-lock text-[9px] text-amber-400/80"></i> {{ $module['label'] }}
                </span>
            @endforeach
        </div>
    </div>
@else
    <div class="rounded-2xl border border-emerald-500/30 bg-gradient-to-br from-emerald-500/10 via-dark-100 to-dark-100 p-6 mb-6 flex flex-col md:flex-row md:items-center gap-5">
        <div class="w-14 h-14 rounded-2xl bg-emerald-500/15 border border-emerald-500/30 flex items-center justify-center shrink-0">
            <i class="fas fa-circle-check text-2xl text-emerald-300"></i>
        </div>
        <div class="flex-1 min-w-0">
            <h2 class="text-xl font-bold text-white">Votre espace producteur est actif</h2>
            <p class="text-gray-300 mt-1">
                Abonnement valable jusqu'au <strong class="text-white">{{ $accessEndsAt?->format('d/m/Y') }}</strong>.
                @if($canPay) Vous pouvez le prolonger dès maintenant : la nouvelle période démarre à la fin de l'actuelle. @endif
            </p>
        </div>
        <a href="{{ route('admin.dashboard') }}" class="bg-dark-200 hover:bg-dark-300 text-white px-4 py-2 rounded-lg text-sm transition text-center">
            Tableau de bord
        </a>
    </div>
@endif

@if($active)
<div class="grid grid-cols-1 xl:grid-cols-5 gap-6">
    {{-- ===== Le pack ===== --}}
    <x-admin.card class="xl:col-span-2 overflow-hidden" padding="p-0">
        <div class="p-6 border-b border-dark-200 bg-gradient-to-br from-primary-500/15 to-transparent">
            <p class="text-xs font-semibold uppercase tracking-wider text-primary-300">Pack producteur</p>
            <h3 class="text-2xl font-bold text-white mt-1">{{ $plan->name }}</h3>
            <div class="mt-4 flex flex-wrap items-baseline gap-x-2">
                <span class="text-4xl font-extrabold text-white">{{ number_format((float) $plan->price, 0, ',', ' ') }}</span>
                <span class="text-lg font-semibold text-gray-300">FCFA</span>
                <span class="text-gray-400">{{ $plan->periodLabel() }}</span>
            </div>
            @if($plan->description)
                <p class="text-sm text-gray-400 mt-3 leading-relaxed">{{ $plan->description }}</p>
            @endif
        </div>
        <div class="p-6">
            @if(! empty($plan->features))
                <ul class="space-y-3">
                    @foreach($plan->features as $feature)
                        <li class="flex gap-3 text-sm text-gray-200">
                            <i class="fas fa-circle-check text-emerald-400 mt-0.5"></i>
                            <span>{{ $feature }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
            <p class="text-xs text-gray-500 {{ empty($plan->features) ? '' : 'mt-6' }}">
                <i class="fas fa-circle-info mr-1"></i>
                Chaque paiement ouvre {{ $plan->durationLabel() }} d'accès, enchaîné après la période en cours. Pas de prélèvement automatique.
            </p>
        </div>
    </x-admin.card>

    {{-- ===== Paiement ===== --}}
    <div class="xl:col-span-3">
        @if($canPay)
            @php
                $countryCodes = $countries->pluck('code');
                $checkout = [
                    'kpay' => $kpayEnabled,
                    'stripe' => $stripeEnabled,
                    'countries' => $countries,
                    'country' => $countryCodes->contains($defaultCountry) ? $defaultCountry : ($countryCodes->first() ?? ''),
                    'priceLabel' => $priceLabel,
                    'urls' => [
                        'kpay' => route('producer.subscription.kpay'),
                        'stripe' => route('producer.subscription.stripe'),
                    ],
                ];
            @endphp
            <x-admin.card :title="$locked ? 'Activer mon espace' : 'Prolonger mon abonnement'" icon="shield-halved"
                subtitle="Paiement sécurisé — l'espace s'ouvre automatiquement dès confirmation.">
                <div x-data="producerCheckout(@js($checkout))">

                    {{-- Choix du moyen de paiement --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-6" x-show="step === 'form'">
                        <button type="button" @click="choose('kpay')" :disabled="!cfg.kpay"
                                :class="method === 'kpay' ? 'border-primary-500 bg-primary-500/10' : 'border-dark-200 hover:border-dark-300'"
                                class="rounded-xl border p-4 text-left transition disabled:opacity-40 disabled:cursor-not-allowed">
                            <div class="flex items-center justify-between">
                                <i class="fas fa-mobile-screen-button text-lg" :class="method === 'kpay' ? 'text-primary-300' : 'text-gray-400'"></i>
                                <i class="fas fa-circle-check text-primary-400" x-show="method === 'kpay'"></i>
                            </div>
                            <p class="font-semibold text-white mt-2">Mobile Money</p>
                            <p class="text-xs text-gray-400" x-text="cfg.kpay ? 'MTN, Orange, Moov, Airtel…' : 'Indisponible pour le moment'"></p>
                        </button>
                        <button type="button" @click="choose('stripe')" :disabled="!cfg.stripe"
                                :class="method === 'stripe' ? 'border-primary-500 bg-primary-500/10' : 'border-dark-200 hover:border-dark-300'"
                                class="rounded-xl border p-4 text-left transition disabled:opacity-40 disabled:cursor-not-allowed">
                            <div class="flex items-center justify-between">
                                <i class="fas fa-credit-card text-lg" :class="method === 'stripe' ? 'text-primary-300' : 'text-gray-400'"></i>
                                <i class="fas fa-circle-check text-primary-400" x-show="method === 'stripe'"></i>
                            </div>
                            <p class="font-semibold text-white mt-2">Carte bancaire</p>
                            <p class="text-xs text-gray-400" x-text="cfg.stripe ? 'Visa, Mastercard — 3D Secure' : 'Indisponible pour le moment'"></p>
                        </button>
                    </div>

                    <div x-show="error" x-cloak class="mb-4 rounded-lg border border-rose-500/30 bg-rose-500/10 text-rose-300 text-sm px-4 py-3 flex gap-2">
                        <i class="fas fa-circle-exclamation mt-0.5"></i><span x-text="error"></span>
                    </div>

                    @if(! $kpayEnabled && ! $stripeEnabled)
                        <p class="text-sm text-amber-300 bg-amber-500/10 border border-amber-500/30 rounded-lg px-4 py-3">
                            <i class="fas fa-triangle-exclamation mr-1"></i>
                            Aucun moyen de paiement n'est disponible pour le moment. Contactez l'équipe ABBEV.
                        </p>
                    @endif

                    {{-- Mobile Money (KPay). Pas de <form> : le panel intercepte les soumissions pour sa navigation. --}}
                    <div x-show="step === 'form' && method === 'kpay'" x-cloak class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <label class="block text-sm font-medium text-gray-300">Pays</label>
                                <select x-model="country" @change="operator = operators()[0]?.code || ''"
                                        class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition">
                                    <template x-for="c in cfg.countries" :key="c.code">
                                        <option :value="c.code" x-text="c.flag + ' ' + c.name" :selected="c.code === country"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="space-y-1.5">
                                <label class="block text-sm font-medium text-gray-300">Opérateur</label>
                                <select x-model="operator"
                                        class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition">
                                    <template x-for="op in operators()" :key="op.code">
                                        <option :value="op.code" x-text="op.label" :selected="op.code === operator"></option>
                                    </template>
                                </select>
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-gray-300">Numéro Mobile Money</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm pointer-events-none" x-text="'+' + dial()"></span>
                                <input type="tel" inputmode="tel" autocomplete="tel" x-model="phone" @keydown.enter.prevent="payKpay()"
                                       placeholder="6XX XX XX XX"
                                       class="w-full bg-dark-50 border border-dark-200 rounded-lg pl-16 pr-4 py-2.5 text-white placeholder-gray-600 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition">
                            </div>
                            <p class="text-xs text-gray-500">Vous recevrez une demande de validation sur ce téléphone.</p>
                        </div>
                        <button type="button" @click="payKpay()" :disabled="busy"
                                class="w-full bg-primary-500 hover:bg-primary-600 disabled:opacity-60 text-white px-6 py-3 rounded-lg font-medium transition">
                            <i class="fas" :class="busy ? 'fa-spinner fa-spin' : 'fa-lock'"></i>
                            <span class="ml-2" x-text="'Payer ' + cfg.priceLabel"></span>
                        </button>
                    </div>

                    {{-- Carte bancaire (Stripe Payment Element) --}}
                    <div x-show="step === 'form' && method === 'stripe'" x-cloak class="space-y-4">
                        <div x-show="!cardReady" class="text-sm text-gray-400">
                            Saisissez votre carte dans le formulaire sécurisé Stripe : vos données bancaires ne transitent jamais par ABBEV.
                        </div>
                        <div x-ref="cardMount" x-show="cardReady"></div>
                        <button type="button" @click="cardReady ? confirmCard() : startCard()" :disabled="busy"
                                class="w-full bg-primary-500 hover:bg-primary-600 disabled:opacity-60 text-white px-6 py-3 rounded-lg font-medium transition">
                            <i class="fas" :class="busy ? 'fa-spinner fa-spin' : 'fa-lock'"></i>
                            <span class="ml-2" x-text="(cardReady ? 'Confirmer le paiement de ' : 'Payer ') + cfg.priceLabel"></span>
                        </button>
                    </div>

                    {{-- Attente de confirmation --}}
                    <div x-show="step === 'waiting'" x-cloak class="text-center py-10">
                        <div class="w-16 h-16 mx-auto rounded-full border-4 border-dark-300 border-t-primary-500 animate-spin"></div>
                        <p class="text-white font-semibold mt-6" x-text="method === 'kpay' ? 'Validez le paiement sur votre téléphone' : 'Confirmation du paiement…'"></p>
                        <p class="text-sm text-gray-400 mt-1 max-w-md mx-auto" x-text="waitingText"></p>
                        <button type="button" @click="reset()" class="text-sm text-gray-400 hover:text-white underline underline-offset-4 mt-6 transition">
                            Choisir un autre moyen de paiement
                        </button>
                    </div>

                    {{-- Succès --}}
                    <div x-show="step === 'success'" x-cloak class="text-center py-10">
                        <div class="w-16 h-16 mx-auto rounded-full bg-emerald-500/15 border border-emerald-500/30 flex items-center justify-center">
                            <i class="fas fa-check text-2xl text-emerald-300"></i>
                        </div>
                        <p class="text-white font-semibold mt-6">Paiement confirmé — votre espace est ouvert !</p>
                        <p class="text-sm text-gray-400 mt-1">Redirection vers votre tableau de bord…</p>
                    </div>
                </div>
            </x-admin.card>
        @else
            <x-admin.card>
                <div class="flex items-start gap-4">
                    <div class="w-11 h-11 rounded-lg bg-sky-500/15 border border-sky-500/30 flex items-center justify-center shrink-0">
                        <i class="fas fa-user-tie text-sky-300"></i>
                    </div>
                    <div>
                        <p class="text-white font-semibold">Abonnement géré par {{ $owner?->name }}</p>
                        <p class="text-sm text-gray-400 mt-1">Le pack producteur est payé par le titulaire de l'espace. Contactez-le pour activer ou prolonger l'abonnement.</p>
                    </div>
                </div>
            </x-admin.card>
        @endif
    </div>
</div>
@endif

{{-- ===== Historique des paiements (titulaire) ===== --}}
@if($payments->isNotEmpty())
<x-admin.card class="mt-6" title="Mes paiements" icon="receipt" padding="p-0">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-dark-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase">Moyen</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase">Montant</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase">Statut</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-dark-200">
                @foreach($payments as $payment)
                    <tr>
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
                @endforeach
            </tbody>
        </table>
    </div>
</x-admin.card>
@endif
@endsection

@push('scripts')
<script>
function producerCheckout(cfg) {
    const csrf = () => document.querySelector('meta[name="csrf-token"]').content;

    async function post(url, body) {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf(),
            },
            body: JSON.stringify(body || {}),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            const firstError = data.errors ? Object.values(data.errors)[0]?.[0] : null;
            throw new Error(firstError || data.message || 'Une erreur est survenue. Réessayez.');
        }
        return data;
    }

    // Stripe.js chargé à la demande (la page peut arriver par navigation PJAX).
    function loadStripe() {
        if (window.Stripe) return Promise.resolve();
        return new Promise((resolve, reject) => {
            const s = document.createElement('script');
            s.src = 'https://js.stripe.com/v3/';
            s.onload = resolve;
            s.onerror = () => reject(new Error('Impossible de charger le module de paiement par carte.'));
            document.head.appendChild(s);
        });
    }

    const firstCountry = cfg.countries.find((c) => c.code === cfg.country) || cfg.countries[0] || { operators: [] };

    return {
        cfg,
        method: cfg.kpay ? 'kpay' : (cfg.stripe ? 'stripe' : null),
        step: 'form',
        busy: false,
        error: '',
        country: firstCountry.code || '',
        operator: firstCountry.operators[0]?.code || '',
        phone: '',
        cardReady: false,
        stripe: null,
        elements: null,
        statusUrl: null,
        pollTimer: null,
        pollUntil: 0,
        waitingText: '',

        operators() {
            return (this.cfg.countries.find((c) => c.code === this.country) || { operators: [] }).operators;
        },
        dial() {
            return (this.cfg.countries.find((c) => c.code === this.country) || {}).dial || '';
        },
        choose(method) {
            if (this.busy || !this.cfg[method]) return;
            this.method = method;
            this.error = '';
        },

        async payKpay() {
            if (this.busy) return;
            if (!this.phone.trim()) { this.error = 'Saisissez votre numéro Mobile Money.'; return; }
            this.busy = true; this.error = '';
            try {
                const data = await post(this.cfg.urls.kpay, {
                    country_code: this.country,
                    mobile_operator: this.operator,
                    phone_number: this.phone,
                });
                this.waitingText = data.message || 'Composez votre code secret pour confirmer le paiement.';
                this.wait(data.status_url, 10 * 60 * 1000);
            } catch (e) {
                this.error = e.message;
            } finally {
                this.busy = false;
            }
        },

        async startCard() {
            if (this.busy) return;
            this.busy = true; this.error = '';
            try {
                const data = await post(this.cfg.urls.stripe);
                await loadStripe();
                this.statusUrl = data.status_url;
                this.stripe = window.Stripe(data.publishable_key);
                this.elements = this.stripe.elements({
                    clientSecret: data.client_secret,
                    locale: 'fr',
                    appearance: { theme: 'night', variables: { colorPrimary: '#06b6d4', colorBackground: '#18181b', borderRadius: '8px' } },
                });
                this.elements.create('payment').mount(this.$refs.cardMount);
                this.cardReady = true;
            } catch (e) {
                this.error = e.message;
            } finally {
                this.busy = false;
            }
        },

        async confirmCard() {
            if (this.busy || !this.stripe) return;
            this.busy = true; this.error = '';
            const { error } = await this.stripe.confirmPayment({
                elements: this.elements,
                redirect: 'if_required',
                confirmParams: { return_url: window.location.href },
            });
            this.busy = false;
            if (error) {
                this.error = error.message || 'Le paiement par carte a échoué.';
                return;
            }
            this.waitingText = 'Votre banque a validé le paiement, nous ouvrons votre espace.';
            this.wait(this.statusUrl, 3 * 60 * 1000);
        },

        wait(url, maxMs) {
            this.statusUrl = url;
            this.step = 'waiting';
            this.pollUntil = Date.now() + maxMs;
            this.poll();
        },

        async poll() {
            // Page quittée (navigation PJAX) ou paiement abandonné : on arrête.
            if (this.step !== 'waiting' || !document.body.contains(this.$el)) return;
            try {
                const res = await fetch(this.statusUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();
                if (data.status === 'completed') {
                    this.step = 'success';
                    setTimeout(() => { window.location.href = data.redirect; }, 1800);
                    return;
                }
                if (data.status === 'failed' || data.status === 'cancelled') {
                    this.reset();
                    this.error = "Le paiement n'a pas abouti (refusé, annulé ou délai dépassé). Vous pouvez réessayer.";
                    return;
                }
            } catch (e) { /* réseau : on retente au prochain tour */ }

            if (Date.now() > this.pollUntil) {
                this.reset();
                this.error = "Nous n'avons pas encore reçu la confirmation. Si vous avez validé le paiement, votre espace s'ouvrira dans quelques minutes : rechargez cette page.";
                return;
            }
            this.pollTimer = setTimeout(() => this.poll(), 4000);
        },

        reset() {
            clearTimeout(this.pollTimer);
            this.step = 'form';
            this.cardReady = false;
            this.stripe = null;
            this.elements = null;
            if (this.$refs.cardMount) this.$refs.cardMount.innerHTML = '';
        },
    };
}
</script>
@endpush
