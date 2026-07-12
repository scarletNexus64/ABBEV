<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Currency;
use App\Models\EmailVerification;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WatchHistory;
use App\Services\KpayService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthApiController extends Controller
{
    /**
     * Inscription d'un nouvel utilisateur (rôle 'user' par défaut).
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            // Numéro (avec indicatif) — saisi à l'inscription pour l'OTP futur.
            'phone' => 'nullable|string|max:32',
            'country_code' => 'required|string|size:2|exists:countries,code',
            'currency_code' => 'nullable|string|size:3|exists:currencies,code',
        ], [
            'name.required'      => 'Le nom est requis.',
            'email.required'     => "L'adresse email est requise.",
            'email.email'        => "L'adresse email n'est pas valide.",
            'email.unique'       => 'Cette adresse email est déjà utilisée.',
            'password.required'  => 'Le mot de passe est requis.',
            'password.min'       => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed' => 'Les mots de passe ne correspondent pas.',
            'country_code.required' => 'Veuillez sélectionner votre pays.',
            'country_code.exists'   => 'Pays invalide.',
        ]);

        $locale = $this->resolveLocale($data['country_code'], $data['currency_code'] ?? null);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone' => $data['phone'] ?? null,
            'role' => 'user',
            'country_code' => $locale['country_code'],
            'currency_code' => $locale['currency_code'],
        ]);

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    /**
     * Vérifie qu'un couple pays/devise est cohérent et retourne les codes
     * normalisés. Si `currency_code` est null, on prend la devise du pays.
     * Si fournie, elle doit correspondre à la devise officielle du pays
     * (évite qu'un utilisateur en France choisisse XOF par exemple).
     */
    private function resolveLocale(?string $countryCode, ?string $currencyCode): array
    {
        $countryCode = $countryCode ? strtoupper($countryCode) : null;
        $currencyCode = $currencyCode ? strtoupper($currencyCode) : null;

        if (! $countryCode) {
            return ['country_code' => null, 'currency_code' => null];
        }

        $country = Country::where('code', $countryCode)->first();
        if (! $country) {
            throw ValidationException::withMessages([
                'country_code' => ['Pays inconnu.'],
            ]);
        }

        $finalCurrency = $currencyCode ?: $country->currency_code;

        if ($currencyCode && $currencyCode !== $country->currency_code) {
            throw ValidationException::withMessages([
                'currency_code' => ['Cette devise ne correspond pas au pays sélectionné.'],
            ]);
        }

        return [
            'country_code' => $country->code,
            'currency_code' => $finalCurrency,
        ];
    }

    /**
     * Connexion. Retourne un Sanctum token utilisable en `Authorization: Bearer <token>`.
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:255',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants invalides.'],
            ]);
        }

        $deviceName = $data['device_name'] ?? ($request->userAgent() ?: 'api');
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Envoie un code OTP à 6 chiffres par email (login = register).
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
        ]);

        $email = $data['email'];

        // Comptes de test (review Play Store / App Store) : on enregistre le
        // code fixe en base sans envoyer d'email, pour que les testeurs
        // puissent se connecter sans accès à la boîte mail. La vérification
        // dans verifyOtp() reste réelle (le code doit matcher en base).
        if ($this->isOtpTestEmail($email)) {
            EmailVerification::updateOrCreate(
                ['email' => $email],
                [
                    'code' => (string) config('auth.otp_test_code'),
                    'expires_at' => Carbon::now()->addMinutes(10),
                    'verified' => false,
                ]
            );

            return response()->json([
                'message' => 'Code envoyé par email',
            ]);
        }

        // Code à 6 chiffres
        $code = (string) random_int(100000, 999999);

        EmailVerification::updateOrCreate(
            ['email' => $email],
            [
                'code' => $code,
                'expires_at' => Carbon::now()->addMinutes(10),
                'verified' => false,
            ]
        );

        try {
            Mail::raw(
                "Bonjour,\n\nVotre code de vérification ABBEV est : $code\n\n".
                "Ce code expire dans 10 minutes.\n\n".
                "Si vous n'avez pas demandé ce code, ignorez cet email.\n\nL'équipe ABBEV",
                function ($message) use ($email) {
                    $message->to($email)
                        ->subject('Code de vérification - ABBEV');
                }
            );

            return response()->json([
                'message' => 'Code envoyé par email',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => "Erreur lors de l'envoi de l'email. Veuillez réessayer.",
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Indique si l'email fait partie des comptes de test OTP (review stores).
     */
    private function isOtpTestEmail(string $email): bool
    {
        $testEmails = config('auth.otp_test_emails', []);

        return in_array(strtolower(trim($email)), $testEmails, true);
    }

    /**
     * Vérifie l'OTP. Crée le compte s'il n'existe pas, puis retourne un token Sanctum.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
            'name' => 'nullable|string|max:255',
            'device_name' => 'nullable|string|max:255',
            'country_code' => 'nullable|string|size:2|exists:countries,code',
            'currency_code' => 'nullable|string|size:3|exists:currencies,code',
        ]);

        $record = EmailVerification::where('email', $data['email'])
            ->where('code', $data['code'])
            ->where('expires_at', '>', now())
            ->where('verified', false)
            ->first();

        if (! $record) {
            throw ValidationException::withMessages([
                'code' => ['Code invalide ou expiré.'],
            ]);
        }

        $record->update(['verified' => true]);

        // Login = register : on crée le compte s'il n'existe pas encore.
        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            // À la 1re connexion (= inscription) on exige le pays — c'est
            // ce qui détermine la devise pour la conversion des prix.
            if (empty($data['country_code'])) {
                throw ValidationException::withMessages([
                    'country_code' => ['Le pays est requis à l\'inscription.'],
                ]);
            }

            $locale = $this->resolveLocale(
                $data['country_code'],
                $data['currency_code'] ?? null,
            );

            $user = User::create([
                'name' => ($data['name'] ?? null) ?: Str::before($data['email'], '@'),
                'email' => $data['email'],
                'password' => Hash::make(Str::random(32)),
                'role' => 'user',
                'country_code' => $locale['country_code'],
                'currency_code' => $locale['currency_code'],
            ]);
        } elseif (! empty($data['country_code']) && empty($user->country_code)) {
            // Compte préexistant sans localisation → on la complète à la
            // volée si l'app l'envoie (cas d'upgrade après migration).
            $locale = $this->resolveLocale(
                $data['country_code'],
                $data['currency_code'] ?? null,
            );
            $user->forceFill([
                'country_code' => $locale['country_code'],
                'currency_code' => $locale['currency_code'],
            ])->save();
        }

        // Compte suspendu par un admin → accès refusé (aucun token émis).
        if (! $user->is_active) {
            return response()->json([
                'message' => 'Ce compte a été suspendu. Contactez le support.',
            ], 403);
        }

        if (is_null($user->email_verified_at)) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $deviceName = $data['device_name'] ?? ($request->userAgent() ?: 'api');
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Mot de passe oublié (#6) — envoie un code à 6 chiffres par email, sur le
     * même mécanisme que l'OTP d'inscription (table EmailVerification).
     *
     * Anti-énumération : on répond TOUJOURS le même message, qu'un compte
     * existe ou non (empêche de deviner les emails inscrits). Le code n'est
     * réellement envoyé que si le compte existe.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
        ]);

        $email = $data['email'];
        $user = User::where('email', $email)->first();

        if ($user) {
            $code = $this->isOtpTestEmail($email)
                ? (string) config('auth.otp_test_code')
                : (string) random_int(100000, 999999);

            EmailVerification::updateOrCreate(
                ['email' => $email],
                [
                    'code' => $code,
                    'expires_at' => Carbon::now()->addMinutes(15),
                    'verified' => false,
                ]
            );

            if (! $this->isOtpTestEmail($email)) {
                try {
                    Mail::raw(
                        "Bonjour,\n\nVotre code de réinitialisation de mot de passe ABBEV est : $code\n\n".
                        "Ce code expire dans 15 minutes.\n\n".
                        "Si vous n'avez pas demandé cette réinitialisation, ignorez cet email.\n\nL'équipe ABBEV",
                        function ($message) use ($email) {
                            $message->to($email)
                                ->subject('Réinitialisation de mot de passe - ABBEV');
                        }
                    );
                } catch (\Throwable $e) {
                    Log::error('[forgotPassword] envoi email échoué', [
                        'error' => $e->getMessage(),
                    ]);
                    // Message générique quoi qu'il arrive (anti-énumération).
                }
            }
        }

        return response()->json([
            'message' => 'Si un compte existe pour cet email, un code de réinitialisation a été envoyé.',
        ]);
    }

    /**
     * Vérifie le code de réinitialisation SANS le consommer : permet à l'app de
     * débloquer l'écran « nouveau mot de passe » après saisie du bon code.
     */
    public function verifyResetCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        $record = EmailVerification::where('email', $data['email'])
            ->where('code', $data['code'])
            ->where('expires_at', '>', now())
            ->where('verified', false)
            ->first();

        if (! $record) {
            throw ValidationException::withMessages([
                'code' => ['Code invalide ou expiré.'],
            ]);
        }

        return response()->json(['message' => 'Code valide.', 'valid' => true]);
    }

    /**
     * Réinitialise le mot de passe : email + code + nouveau mot de passe.
     * Consomme le code, révoque les anciens tokens (sécurité) et retourne un
     * nouveau token (connexion automatique après réinitialisation).
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
            'device_name' => 'nullable|string|max:255',
        ]);

        $record = EmailVerification::where('email', $data['email'])
            ->where('code', $data['code'])
            ->where('expires_at', '>', now())
            ->where('verified', false)
            ->first();

        if (! $record) {
            throw ValidationException::withMessages([
                'code' => ['Code invalide ou expiré.'],
            ]);
        }

        $user = User::where('email', $data['email'])->first();
        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['Aucun compte associé à cet email.'],
            ]);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'Ce compte a été suspendu. Contactez le support.',
            ], 403);
        }

        $user->forceFill(['password' => Hash::make($data['password'])])->save();
        $record->update(['verified' => true]);

        // Révoque toutes les sessions existantes : après un reset, on déconnecte
        // les autres appareils par sécurité.
        $user->tokens()->delete();

        $deviceName = $data['device_name'] ?? ($request->userAgent() ?: 'api');
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
            'message' => 'Mot de passe réinitialisé avec succès.',
        ]);
    }

    /**
     * Profil de l'utilisateur courant. On inclut pays + devise (avec leurs
     * métadonnées) pour que l'app sache quelle devise utiliser pour
     * l'affichage des prix.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['country', 'currency']);

        return response()->json([
            'user' => $this->serializeUser($user),
        ]);
    }

    /**
     * Mise à jour partielle du profil : nom, photo (avatar), pays et devise.
     * Tous les champs sont optionnels — on ne met à jour que ce qui est fourni.
     */
    public function updateMe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|min:2|max:255',
            'avatar' => 'sometimes|image|mimes:jpeg,jpg,png,webp|max:4096',
            'country_code' => 'sometimes|string|size:2|exists:countries,code',
            'currency_code' => 'nullable|string|size:3|exists:currencies,code',
        ]);

        $user = $request->user();

        if (array_key_exists('name', $data)) {
            $user->name = $data['name'];
        }

        // Photo de profil : stockée sur le disque public (servie avec CORS via
        // /media/img/...). On supprime l'ancienne pour ne pas accumuler.
        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($user->avatar_path);
            }
            $user->avatar_path = $request->file('avatar')->store('avatars', 'public');
        }

        // Pays/devise : on ne réaligne que si un pays est fourni.
        if (! empty($data['country_code'])) {
            $locale = $this->resolveLocale(
                $data['country_code'],
                $data['currency_code'] ?? null,
            );
            $user->country_code = $locale['country_code'];
            $user->currency_code = $locale['currency_code'];
        }

        $user->save();

        return response()->json([
            'user' => $this->serializeUser($user->load(['country', 'currency'])),
        ]);
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatarUrl' => $user->avatar_path
                ? rtrim(config('app.url'), '/').'/media/img/'.ltrim($user->avatar_path, '/')
                : null,
            'role' => $user->role,
            'country_code' => $user->country_code,
            'currency_code' => $user->currency_code,
            'country' => $user->country ? [
                'code' => $user->country->code,
                'name' => $user->country->name,
                'flag' => $user->country->flag_emoji,
                'phoneCode' => $user->country->phone_code,
            ] : null,
            'currency' => $user->currency ? [
                'code' => $user->currency->code,
                'name' => $user->currency->name,
                'symbol' => $user->currency->symbol,
                'decimals' => (int) $user->currency->decimals,
                'rateFromXof' => (float) $user->currency->rate_from_xof,
            ] : null,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
        ];
    }

    /**
     * Statistiques de visionnage de l'utilisateur courant.
     *
     * - movies_watched : nombre de films distincts regardés
     * - series_watched : nombre de séries distinctes regardées
     * - hours_watched  : total des secondes regardées, converti en heures
     */
    public function stats(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $moviesWatched = WatchHistory::query()
            ->where('watch_history.user_id', $userId)
            ->join('media', 'media.id', '=', 'watch_history.media_id')
            ->where('media.type', 'movie')
            ->distinct('watch_history.media_id')
            ->count('watch_history.media_id');

        $seriesWatched = WatchHistory::query()
            ->where('watch_history.user_id', $userId)
            ->join('media', 'media.id', '=', 'watch_history.media_id')
            ->where('media.type', 'series')
            ->distinct('watch_history.media_id')
            ->count('watch_history.media_id');

        $totalSeconds = (int) WatchHistory::where('user_id', $userId)
            ->sum('watched_seconds');

        return response()->json([
            'movies_watched' => $moviesWatched,
            'series_watched' => $seriesWatched,
            'hours_watched' => (int) floor($totalSeconds / 3600),
        ]);
    }

    /**
     * Abonnement payant actif de l'utilisateur courant (le plus récent),
     * ou null s'il n'en a pas. Sert à l'app pour afficher les infos de
     * l'abonnement (expiration, jours restants) au lieu des offres.
     *
     * GET /api/v1/auth/me/subscription
     */
    public function currentSubscription(Request $request): JsonResponse
    {
        // Avant de chercher un abonnement actif, on tente de réconcilier
        // les transactions KPay laissées 'pending' pour cet utilisateur.
        // Pourquoi : KPay ne notifie pas le backend (pas de webhook) ;
        // si le polling client (5 min) et le job Laravel (5 min) ratent
        // tous les deux la confirmation, la transaction reste 'pending'
        // à vie et aucune UserSubscription n'est créée — l'app affiche
        // alors les offres alors que l'utilisateur a déjà payé.
        $this->reconcilePendingKpayPayments($request->user());

        $subscription = $request->user()
            ->subscriptions()
            ->with('plan')
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->whereHas('plan', fn ($q) => $q->where('price', '>', 0))
            ->latest('expires_at')
            ->first();

        if (! $subscription) {
            return response()->json(['data' => null]);
        }

        $now = now();
        $expiresAt = $subscription->expires_at;
        // Jours restants, arrondi au supérieur (un jour entamé compte).
        $daysRemaining = max(0, (int) ceil($now->floatDiffInDays($expiresAt)));

        // Affiche le prix dans la devise de l'utilisateur (fallback XOF).
        $currencyCode = $request->user()->currency_code ?: 'XOF';
        $currency = Currency::where('code', $currencyCode)->first()
            ?? Currency::where('code', 'XOF')->first();

        $priceXof = (float) $subscription->plan->price;
        $convertedPrice = $currency ? $currency->convertFromXof($priceXof) : $priceXof;

        return response()->json([
            'data' => [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'starts_at' => $subscription->starts_at?->toIso8601String(),
                'expires_at' => $expiresAt?->toIso8601String(),
                'days_remaining' => $daysRemaining,
                // Renouvellement manuel : le backend ne gère pas (encore)
                // de paiement récurrent — l'app proposera "Renouveler".
                'auto_renew' => false,
                'plan' => [
                    'id' => $subscription->plan->id,
                    'name' => $subscription->plan->name,
                    'description' => $subscription->plan->description,
                    'price' => $convertedPrice,
                    'currency' => $currency?->code ?? 'XOF',
                    'currency_symbol' => $currency?->symbol,
                    'currency_decimals' => (int) ($currency?->decimals ?? 0),
                    'price_xof' => $priceXof,
                    'duration_days' => (int) $subscription->plan->duration_days,
                    'features' => $subscription->plan->features ?? [],
                ],
            ],
        ]);
    }

    /**
     * Réconciliation paresseuse des transactions KPay 'pending' de
     * l'utilisateur en interrogeant l'API KPay. Sert de filet pour les
     * cas où ni le polling client ni le Job de réconciliation n'ont
     * capté la confirmation (KPay confirme tardivement, pas de webhook).
     *
     * Borné : max 3 transactions par appel, fenêtre 7 jours, erreurs
     * KPay avalées (la route doit toujours répondre).
     */
    private function reconcilePendingKpayPayments(User $user): void
    {
        $pendings = Transaction::query()
            ->where('user_id', $user->id)
            ->where('type', 'subscription')
            ->where('payment_method', 'kpay')
            ->where('status', 'pending')
            ->whereNotNull('external_reference')
            ->where('created_at', '>=', now()->subDays(7))
            ->orderByDesc('id')
            ->limit(3)
            ->get();

        if ($pendings->isEmpty()) {
            return;
        }

        try {
            $kpay = app(KpayService::class);
        } catch (\Throwable $e) {
            Log::warning('[currentSubscription] KpayService indisponible', [
                'error' => $e->getMessage(),
            ]);

            return;
        }

        foreach ($pendings as $transaction) {
            try {
                $result = $kpay->getPayment((string) $transaction->external_reference);
                if (! ($result['success'] ?? false)) {
                    continue;
                }
                $kpayStatus = strtoupper((string) ($result['data']['status'] ?? 'PENDING'));
                $kpay->reconcileTransaction($transaction, $kpayStatus);
            } catch (\Throwable $e) {
                // Ne jamais faire planter la requête de subscription :
                // au pire on retombe sur l'ancien comportement.
                Log::warning('[currentSubscription] reconcile KPay tx échec', [
                    'transaction_id' => $transaction->transaction_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Enregistre (ou met à jour) une session de visionnage pour l'utilisateur
     * courant. Appelé par l'app au fil de la lecture.
     */
    public function recordWatch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'media_id' => 'required|integer|exists:media,id',
            'episode_id' => 'nullable|integer|exists:episodes,id',
            'watched_seconds' => 'required|integer|min:0',
        ]);

        $history = WatchHistory::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'media_id' => $data['media_id'],
                'episode_id' => $data['episode_id'] ?? null,
            ],
            [
                'watched_seconds' => $data['watched_seconds'],
            ]
        );

        return response()->json([
            'message' => 'Historique enregistré.',
            'watch_history' => $history,
        ]);
    }

    /**
     * Déconnexion : révoque le token courant.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Déconnexion réussie.',
        ]);
    }
}
