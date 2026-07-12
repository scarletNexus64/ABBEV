@extends('admin.layouts.app')

@section('title', 'Configuration - ABBEV')
@section('header', 'Configuration du Système')

@section('content')
<!-- Info Banner -->
<div class="mb-6 bg-blue-500/10 border border-blue-500/30 rounded-lg p-4">
    <div class="flex items-start">
        <i class="fas fa-info-circle text-blue-400 text-xl mr-3 mt-1"></i>
        <div>
            <p class="text-blue-300 font-medium mb-1">Information importante</p>
            <p class="text-blue-200 text-sm">
                Ces paramètres contrôlent les systèmes de paiement et de notification de votre plateforme.
                Modifiez-les avec précaution. Les clés secrètes sont masquées par défaut.
            </p>
        </div>
    </div>
</div>

{{-- KPay test form (hidden) --}}
<form id="kpay-test-form" action="{{ route('configuration.testKpay') }}" method="POST" class="hidden">@csrf</form>

{{-- Email test form (hidden) : envoie un email de test à l'admin connecté --}}
<form id="mail-test-form" action="{{ route('configuration.testMail') }}" method="POST" class="hidden">@csrf</form>

{{-- Bunny test form (hidden) : teste la connectivité Bunny Stream --}}
<form id="bunny-test-form" action="{{ route('configuration.testBunny') }}" method="POST" class="hidden">@csrf</form>

{{-- Les messages flash (succès/erreur) sont affichés par le layout admin :
     on ne les duplique pas ici. --}}

@php
    $groupMeta = [
        'general'       => ['icon' => 'fas fa-cog',          'color' => 'text-primary-400', 'label' => 'Général'],
        'maintenance'   => ['icon' => 'fas fa-tools',        'color' => 'text-orange-400',  'label' => 'Maintenance'],
        'system'        => ['icon' => 'fas fa-server',       'color' => 'text-cyan-400',    'label' => 'Système'],
        'video_mode'    => ['icon' => 'fas fa-video',        'color' => 'text-fuchsia-400', 'label' => 'Mode Vidéo'],
        'paypal'        => ['icon' => 'fab fa-paypal',       'color' => 'text-blue-400',    'label' => 'PayPal'],
        'stripe'        => ['icon' => 'fab fa-cc-stripe',    'color' => 'text-violet-400',  'label' => 'Stripe (Carte)'],
        'fedapay'       => ['icon' => 'fas fa-credit-card',  'color' => 'text-indigo-400',  'label' => 'FedaPay'],
        'freemopay'     => ['icon' => 'fas fa-wallet',       'color' => 'text-green-400',   'label' => 'FreeMoPay'],
        'kpay'          => ['icon' => 'fas fa-mobile-alt',   'color' => 'text-emerald-400', 'label' => 'KPay'],
        'nowpayments'   => ['icon' => 'fab fa-bitcoin',      'color' => 'text-amber-400',   'label' => 'Crypto (NOWPayments)'],
        'nexah_sms'     => ['icon' => 'fas fa-sms',          'color' => 'text-purple-400',  'label' => 'Nexah SMS'],
        'whatsapp'      => ['icon' => 'fab fa-whatsapp',     'color' => 'text-green-400',   'label' => 'WhatsApp Business'],
        'promo'         => ['icon' => 'fas fa-tag',          'color' => 'text-yellow-400',  'label' => 'Code Promo'],
        'email'         => ['icon' => 'fas fa-envelope',     'color' => 'text-sky-400',     'label' => 'Email (SMTP)'],
        'bunny'         => ['icon' => 'fas fa-cloud',        'color' => 'text-orange-400',  'label' => 'Bunny Stream (vidéo)'],
        'exchange_rate' => ['icon' => 'fas fa-money-bill-transfer', 'color' => 'text-lime-400', 'label' => 'Taux de change'],
        'notifications' => ['icon' => 'fas fa-bell',         'color' => 'text-pink-400',    'label' => 'Notifications'],
        'security'      => ['icon' => 'fas fa-shield-alt',   'color' => 'text-red-400',     'label' => 'Sécurité'],
    ];
    $defaultTab = session('active_tab', $configurations->keys()->first());
@endphp

<div x-data="{ activeTab: '{{ $defaultTab }}' }">

    <!-- Tabs Navigation -->
    <div class="mb-6 border-b border-dark-200 flex flex-wrap gap-1">
        @foreach($configurations as $group => $configs)
        @php $meta = $groupMeta[$group] ?? ['icon' => 'fas fa-cog', 'color' => 'text-gray-400', 'label' => ucfirst(str_replace('_', ' ', $group))]; @endphp
        <button type="button"
                @click="activeTab = '{{ $group }}'"
                :class="activeTab === '{{ $group }}' ? 'border-primary-500 text-white bg-dark-100' : 'border-transparent text-gray-400 hover:text-white'"
                class="px-4 py-3 border-b-2 rounded-t-lg transition flex items-center text-sm font-medium">
            <i class="{{ $meta['icon'] }} {{ $meta['color'] }} mr-2"></i>
            {{ $meta['label'] }}
        </button>
        @endforeach
    </div>

    <!-- Tab Panels : un formulaire indépendant par groupe -->
    @foreach($configurations as $group => $configs)
    @php $meta = $groupMeta[$group] ?? ['icon' => 'fas fa-cog', 'color' => 'text-gray-400', 'label' => ucfirst(str_replace('_', ' ', $group))]; @endphp
    <div x-show="activeTab === '{{ $group }}'" x-transition>
        <form action="{{ route('configuration.updateGroup', $group) }}" method="POST">
            @csrf

            <!-- Configuration Group Card -->
            <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 overflow-hidden">
                <!-- Group Header -->
                <div class="bg-dark-50 px-6 py-4 border-b border-dark-200 flex items-center">
                    <i class="{{ $meta['icon'] }} text-2xl {{ $meta['color'] }} mr-3"></i>
                    <h3 class="text-xl font-bold text-white">{{ $meta['label'] }}</h3>
                </div>

                <!-- Group Content -->
                <div class="p-6">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    @foreach($configs as $config)
                    <div>
                        <label for="config_{{ $config->key }}" class="block text-sm font-medium text-gray-300 mb-2">
                            {{ $config->description ?? $config->key }}
                            @if($config->key === 'enabled' || str_ends_with($config->key, '_enabled'))
                            @else
                            <span class="text-red-400">*</span>
                            @endif
                        </label>

                        @if($config->key === 'enabled' || str_ends_with($config->key, '_enabled'))
                        <!-- Toggle Switch for Enable/Disable -->
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox"
                                   name="configs[{{ $config->key }}]"
                                   id="config_{{ $config->key }}"
                                   value="1"
                                   {{ old('configs.' . $config->key, $config->value) == '1' ? 'checked' : '' }}
                                   class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-800 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-600"></div>
                            <span class="ml-3 text-sm font-medium text-gray-300">
                                {{ old('configs.' . $config->key, $config->value) == '1' ? 'Activé' : 'Désactivé' }}
                            </span>
                        </label>

                        @elseif($config->is_secret)
                        <!-- Secret Input (Password) -->
                        <div class="relative" x-data="{ show: false }">
                            <input :type="show ? 'text' : 'password'"
                                   name="configs[{{ $config->key }}]"
                                   id="config_{{ $config->key }}"
                                   value="{{ old('configs.' . $config->key, $config->value) }}"
                                   placeholder="{{ $config->is_secret ? '••••••••••••' : '' }}"
                                   class="w-full bg-dark-50 border @error('configs.' . $config->key) border-red-500 @else border-dark-200 @enderror rounded-lg px-4 py-3 pr-12 text-white focus:outline-none focus:border-primary-500 transition">
                            <button type="button"
                                    @click="show = !show"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white transition">
                                <i class="fas" :class="show ? 'fa-eye-slash' : 'fa-eye'"></i>
                            </button>
                        </div>

                        @elseif($config->key === 'video_mode')
                        <!-- Mode Vidéo : Production (Bunny) ou Test/Dev (sample public) -->
                        <select name="configs[{{ $config->key }}]"
                                id="config_{{ $config->key }}"
                                class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500 transition">
                            <option value="production" {{ old('configs.' . $config->key, $config->value) === 'production' ? 'selected' : '' }}>🟢 Production — Bunny Stream</option>
                            <option value="test" {{ old('configs.' . $config->key, $config->value) === 'test' ? 'selected' : '' }}>🧪 Test / Dev — vidéo d'échantillon</option>
                        </select>

                        @elseif($config->key === 'mail_mailer')
                        <!-- Choix du mailer -->
                        <select name="configs[{{ $config->key }}]"
                                id="config_{{ $config->key }}"
                                class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500 transition">
                            <option value="log" {{ old('configs.' . $config->key, $config->value) === 'log' ? 'selected' : '' }}>📝 Log (dev — emails écrits dans les logs)</option>
                            <option value="smtp" {{ old('configs.' . $config->key, $config->value) === 'smtp' ? 'selected' : '' }}>📤 SMTP (serveur d'envoi)</option>
                            <option value="resend" {{ old('configs.' . $config->key, $config->value) === 'resend' ? 'selected' : '' }}>⚡ Resend (API)</option>
                        </select>

                        @elseif($config->key === 'mail_encryption')
                        <!-- Chiffrement SMTP -->
                        <select name="configs[{{ $config->key }}]"
                                id="config_{{ $config->key }}"
                                class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500 transition">
                            <option value="tls" {{ old('configs.' . $config->key, $config->value) === 'tls' ? 'selected' : '' }}>TLS (port 587)</option>
                            <option value="ssl" {{ old('configs.' . $config->key, $config->value) === 'ssl' ? 'selected' : '' }}>SSL (port 465)</option>
                            <option value="none" {{ in_array(old('configs.' . $config->key, $config->value), ['none', 'aucun', ''], true) ? 'selected' : '' }}>Aucun</option>
                        </select>

                        @elseif(str_contains($config->key, 'mode'))
                        <!-- Mode Selector (Sandbox/Live) -->
                        <select name="configs[{{ $config->key }}]"
                                id="config_{{ $config->key }}"
                                class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500 transition">
                            <option value="sandbox" {{ old('configs.' . $config->key, $config->value) === 'sandbox' ? 'selected' : '' }}>Sandbox (Test)</option>
                            <option value="live" {{ old('configs.' . $config->key, $config->value) === 'live' ? 'selected' : '' }}>Live (Production)</option>
                        </select>

                        @elseif(is_numeric($config->value))
                        <!-- Number Input -->
                        <input type="number"
                               name="configs[{{ $config->key }}]"
                               id="config_{{ $config->key }}"
                               value="{{ old('configs.' . $config->key, $config->value) }}"
                               step="any"
                               class="w-full bg-dark-50 border @error('configs.' . $config->key) border-red-500 @else border-dark-200 @enderror rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500 transition">

                        @elseif(str_contains($config->key, 'url') || str_contains($config->key, 'endpoint'))
                        <!-- URL Input -->
                        <input type="url"
                               name="configs[{{ $config->key }}]"
                               id="config_{{ $config->key }}"
                               value="{{ old('configs.' . $config->key, $config->value) }}"
                               placeholder="https://..."
                               class="w-full bg-dark-50 border @error('configs.' . $config->key) border-red-500 @else border-dark-200 @enderror rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500 transition">

                        @elseif(strlen($config->value ?? '') > 100)
                        <!-- Textarea for long text -->
                        <textarea name="configs[{{ $config->key }}]"
                                  id="config_{{ $config->key }}"
                                  rows="3"
                                  class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500 transition">{{ old('configs.' . $config->key, $config->value) }}</textarea>

                        @else
                        <!-- Text Input -->
                        <input type="text"
                               name="configs[{{ $config->key }}]"
                               id="config_{{ $config->key }}"
                               value="{{ old('configs.' . $config->key, $config->value) }}"
                               class="w-full bg-dark-50 border @error('configs.' . $config->key) border-red-500 @else border-dark-200 @enderror rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500 transition">
                        @endif

                        @error('configs.' . $config->key)
                        <p class="mt-2 text-sm text-red-400">
                            <i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}
                        </p>
                        @enderror
                        </div>
                        @endforeach
                    </div>
                </div>

                @if($group === 'video_mode')
                <div class="px-6 pb-2 -mt-2">
                    <div class="bg-fuchsia-500/10 border border-fuchsia-500/30 rounded-lg p-4 text-sm text-fuchsia-200">
                        <p class="font-medium text-fuchsia-300 mb-1"><i class="fas fa-circle-info mr-1"></i> Comment ça marche</p>
                        <p class="mb-1"><strong>Production</strong> : chaque film/épisode est lu via sa vidéo Bunny Stream (configuration <code>.env</code>, inchangée).</p>
                        <p><strong>Test / Dev</strong> : tout le catalogue lit la vidéo d'échantillon publique ci-dessus, sans toucher à Bunny — idéal pour présenter le catalogue (affiches, synopsis) sans consommer de quota.</p>
                    </div>
                </div>
                @endif

                @if($group === 'bunny')
                <div class="px-6 pb-2 -mt-2">
                    <div class="bg-orange-500/10 border border-orange-500/30 rounded-lg p-4 text-sm text-orange-200">
                        <p class="font-medium text-orange-300 mb-1"><i class="fas fa-circle-info mr-1"></i> Hébergement vidéo Bunny Stream</p>
                        <p class="mb-1">Ces paramètres pilotent l'envoi des vidéos vers Bunny (transcodage HLS, lecture CDN). Une <strong>clé API invalide</strong> provoque l'erreur <strong>401</strong> à l'upload — les vidéos restent alors « disponibles en local ».</p>
                        <p class="mb-1"><strong>Library ID</strong> + <strong>clé API</strong> (AccessKey) + <strong>CDN Hostname</strong> viennent du dashboard Bunny.tv. Laisser un champ vide conserve la valeur du <code>.env</code>.</p>
                        <p class="text-orange-300/90 mt-2"><i class="fas fa-plug mr-1"></i> Après enregistrement, cliquez sur <strong>« Tester la connexion Bunny »</strong>, puis « Relancer » sur les uploads en attente.</p>
                    </div>
                </div>
                @endif

                @if($group === 'email')
                <div class="px-6 pb-2 -mt-2">
                    <div class="bg-sky-500/10 border border-sky-500/30 rounded-lg p-4 text-sm text-sky-200">
                        <p class="font-medium text-sky-300 mb-1"><i class="fas fa-circle-info mr-1"></i> Envoi réel des emails</p>
                        <p class="mb-1"><strong>Log</strong> : les emails (identifiants producteur, OTP…) sont écrits dans les logs, <strong>pas</strong> livrés — pour le dev uniquement.</p>
                        <p class="mb-1"><strong>SMTP</strong> : renseignez hôte, port, utilisateur, mot de passe et chiffrement de votre serveur d'envoi.</p>
                        <p class="mb-1"><strong>Resend</strong> : indiquez seulement la clé API Resend (le reste est ignoré).</p>
                        <p class="text-sky-300/90 mt-2"><i class="fas fa-paper-plane mr-1"></i> Après enregistrement, cliquez sur <strong>« Envoyer un email test »</strong> pour vérifier la livraison.</p>
                    </div>
                </div>
                @endif

                <!-- Action Buttons (par groupe) -->
                <div class="bg-dark-50 px-6 py-4 border-t border-dark-200 flex gap-4">
                    <button type="submit"
                            class="flex-1 bg-primary-500 hover:bg-primary-600 text-white px-6 py-3 rounded-lg transition">
                        <i class="fas fa-save mr-2"></i> Enregistrer « {{ $meta['label'] }} »
                    </button>
                    @if($group === 'kpay')
                    <button type="button"
                            onclick="document.getElementById('kpay-test-form').submit()"
                            class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-3 rounded-lg transition">
                        <i class="fas fa-plug mr-2"></i> Tester la connexion
                    </button>
                    @endif
                    @if($group === 'email')
                    <button type="button"
                            onclick="document.getElementById('mail-test-form').submit()"
                            class="bg-sky-600 hover:bg-sky-700 text-white px-6 py-3 rounded-lg transition whitespace-nowrap">
                        <i class="fas fa-paper-plane mr-2"></i> Envoyer un email test
                    </button>
                    @endif
                    @if($group === 'bunny')
                    <button type="button"
                            onclick="document.getElementById('bunny-test-form').submit()"
                            class="bg-orange-600 hover:bg-orange-700 text-white px-6 py-3 rounded-lg transition whitespace-nowrap">
                        <i class="fas fa-plug mr-2"></i> Tester la connexion Bunny
                    </button>
                    @endif
                    <button type="button"
                            @click="window.location.reload()"
                            class="bg-dark-200 hover:bg-dark-300 text-white px-6 py-3 rounded-lg transition">
                        <i class="fas fa-undo mr-2"></i> Annuler
                    </button>
                </div>
            </div>
        </form>
    </div>
    @endforeach

    <!-- Warning Box -->
    <div class="mt-6 bg-yellow-500/10 border border-yellow-500/30 rounded-lg p-4">
        <div class="flex items-start">
            <i class="fas fa-exclamation-triangle text-yellow-400 text-xl mr-3 mt-1"></i>
            <div>
                <p class="text-yellow-300 font-medium mb-1">Attention</p>
                <p class="text-yellow-200 text-sm">
                    Chaque catégorie se sauvegarde indépendamment : enregistrer KPay ne touche pas
                    aux autres configurations. Testez en mode sandbox avant la production.
                </p>
            </div>
        </div>
    </div>
</div>

{{-- L'état des onglets est désormais défini INLINE dans x-data
     (« { activeTab: '…' } ») : plus aucune dépendance à une fonction JS
     externe. Cela évite le bug de course PJAX où Alpine s'initialisait avant
     que le script « configForm » ne soit (re)défini, figeant les onglets sur
     « Général » après une navigation interne. --}}
@endsection
