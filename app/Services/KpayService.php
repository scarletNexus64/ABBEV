<?php

namespace App\Services;

use App\Models\Configuration;
use App\Models\Transaction;
use App\Models\UserSubscription;
use App\Services\ReservationService;
use Illuminate\Support\Facades\Log;

/**
 * Client HTTP KPay (cURL natif).
 *
 * Périmètre : encaissement Mobile Money USSD pour l'abonnement.
 * Les credentials et la durée max de polling proviennent du
 * dashboard (table configurations), pas du .env.
 */
class KpayService
{
    private const TIMEOUT_SEC = 30;

    public function isConfigured(): bool
    {
        return $this->baseUrl() !== '' && $this->apiKey() !== '' && $this->secretKey() !== '';
    }

    /**
     * Durée max d'attente (secondes) du polling de réconciliation.
     */
    public function maxDuration(): int
    {
        return (int) Configuration::getValue('kpay_max_duration', 300);
    }

    /**
     * Repli legacy (Cameroun) si aucun pays n'est fourni : conserve le
     * comportement d'origine pour ne rien casser.
     */
    private const LEGACY_PROVIDER_MAP = [
        'MTN_MONEY'    => 'MTN_MOMO_CMR',
        'ORANGE_MONEY' => 'ORANGE_CMR',
    ];

    /**
     * Configuration d'un pays KPay (currency, dial, operators) depuis
     * config/kpay.php, ou null si le pays n'est pas supporté.
     *
     * @return array{name:string,currency:string,dial:string,operators:array<string,string>}|null
     */
    public function country(string $iso2): ?array
    {
        return config('kpay.countries.' . strtoupper($iso2));
    }

    /**
     * Opérateurs disponibles pour un pays : liste de
     * `['code' => ..., 'label' => ..., 'currency' => ...]`.
     *
     * @return array<int,array{code:string,label:string,currency:string}>
     */
    public function operatorsFor(string $iso2): array
    {
        return $this->country($iso2)['operators'] ?? [];
    }

    /**
     * Retrouve un opérateur d'un pays par son code provider KPay.
     *
     * @return array{code:string,label:string,currency:string}|null
     */
    public function findOperator(string $iso2, string $code): ?array
    {
        foreach ($this->operatorsFor($iso2) as $op) {
            if (($op['code'] ?? null) === $code) {
                return $op;
            }
        }

        return null;
    }

    /**
     * Résout le code provider KPay EXACT. Le mobile envoie désormais
     * directement le code du catalogue (ex. « ORANGE_CMR », « AIRTEL_COD_CDF ») :
     * si c'est un code valide pour le pays, on le renvoie tel quel. Repli
     * legacy pour les anciens clients qui envoyaient l'enum interne (Cameroun).
     */
    public function providerFor(string $operator, ?string $countryCode = null): string
    {
        if ($countryCode && $this->findOperator($countryCode, $operator)) {
            return $operator;
        }

        return self::LEGACY_PROVIDER_MAP[$operator] ?? $operator;
    }

    /**
     * Liste les providers réellement disponibles sur le compte KPay (pour
     * vérifier/corriger les codes de config/kpay.php via `kpay:providers`).
     *
     * @return array{success:bool, data?:array, message?:string, http?:int|null, body?:array|null}
     */
    public function listProviders(): array
    {
        return $this->request('GET', '/api/v1/providers');
    }

    /**
     * Normalise un numéro Mobile Money au format international attendu par
     * KPay (ex. « 6XXXXXXXX » ou « 06XXXXXXXX » → « 2376XXXXXXXX »). Le
     * dashboard/mobile saisit un numéro local ; KPay exige l'indicatif pays.
     */
    public static function normalizeMsisdn(string $phone, string $dialCode = '237'): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return '';
        }
        // Déjà en international (commence par l'indicatif) : on garde tel quel.
        if (str_starts_with($digits, $dialCode)) {
            return $digits;
        }
        // Retire un éventuel 0 de trunk national puis préfixe l'indicatif.
        return $dialCode . ltrim($digits, '0');
    }

    /**
     * Extrait le message d'erreur le plus utile d'une réponse KPay décodée.
     *
     * KPay renvoie l'enveloppe `{ statusCode, error, code, message, ... }` où
     * `error` est le texte générique du statut HTTP (« Bad Request ») et
     * `message` le détail humain exploitable (« Le montant minimum autorisé
     * pour un paiement est de 100 XAF »). On privilégie donc `message`, puis
     * `error` en repli — c'est CE message qui est affiché à l'utilisateur.
     *
     * @param mixed $decoded corps JSON décodé de la réponse KPay
     */
    public static function extractErrorMessage($decoded): string
    {
        $message = 'KPay error';
        if (is_array($decoded)) {
            $message = $decoded['message'] ?? $decoded['error'] ?? 'KPay error';
        }
        if (is_array($message)) {
            $message = implode(', ', array_map('strval', $message));
        }

        return (string) $message;
    }

    /**
     * POST /api/v1/payments/init — mode USSD.
     *
     * @param array{amount:int|float, phoneNumber:string, externalId:string, provider?:string, description?:string, customerName?:string, customerEmail?:string, metadata?:array} $params
     * @return array{success:bool, data?:array, message?:string, http?:int|null, body?:array|null}
     */
    public function initPayment(array $params): array
    {
        // Mapper l'opérateur interne vers le code provider KPay selon le pays
        // (`country` = code ISO2 optionnel ; repli Cameroun sinon).
        if (isset($params['provider'])) {
            $params['provider'] = $this->providerFor($params['provider'], $params['country'] ?? null);
        }
        // `country` est interne : jamais transmis à KPay.
        unset($params['country']);

        Log::info('[KpayService] Init payment', [
            'externalId' => $params['externalId'] ?? null,
            'amount' => $params['amount'] ?? null,
            'provider' => $params['provider'] ?? null,
        ]);

        return $this->request('POST', '/api/v1/payments/init', $params);
    }

    /**
     * GET /api/v1/payments/:id — récupère l'état d'un paiement.
     *
     * @return array{success:bool, data?:array, message?:string, http?:int|null, body?:array|null}
     */
    public function getPayment(string $id): array
    {
        return $this->request('GET', '/api/v1/payments/' . rawurlencode($id));
    }

    /**
     * Rapproche une transaction KPay selon un statut KPay donné.
     * Idempotent : ne re-finalise pas une transaction déjà complétée.
     *
     * @return string statut local résultant : completed|failed|pending
     */
    public function reconcileTransaction(Transaction $transaction, string $kpayStatus): string
    {
        $status = strtoupper($kpayStatus);

        if ($status === 'COMPLETED') {
            if ($transaction->status !== 'completed') {
                $transaction->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);

                if ($transaction->type === 'subscription') {
                    UserSubscription::provisionFromTransaction($transaction);
                } elseif ($transaction->type === 'purchase') {
                    // Réservation de ticket : confirmer + décompter le stock.
                    app(ReservationService::class)->confirmFromTransaction($transaction);
                }

                Log::info('[KpayService] Transaction reconciled as completed', [
                    'transaction_id' => $transaction->transaction_id,
                    'type' => $transaction->type,
                ]);
            }

            return 'completed';
        }

        if (in_array($status, ['FAILED', 'CANCELLED', 'EXPIRED', 'REFUNDED'], true)) {
            if ($transaction->status !== 'completed') {
                $transaction->update(['status' => 'failed']);
            }

            return 'failed';
        }

        return 'pending';
    }

    private function baseUrl(): string
    {
        return rtrim((string) Configuration::getValue('kpay_base_url', 'https://admin.kpay.site'), '/');
    }

    private function apiKey(): string
    {
        return (string) Configuration::getValue('kpay_api_key', '');
    }

    private function secretKey(): string
    {
        return (string) Configuration::getValue('kpay_secret_key', '');
    }

    /**
     * @return array{success:bool, data?:array, message?:string, http?:int|null, body?:array|null}
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        if (!$this->isConfigured()) {
            Log::error('[KpayService] Credentials KPay manquants (base_url/api_key/secret_key)');

            return [
                'success' => false,
                'message' => 'KPay non configuré (credentials manquants dans le dashboard)',
                'http' => null,
                'body' => null,
            ];
        }

        $url = $this->baseUrl() . $path;

        $ch = curl_init($url);
        $headers = [
            'X-API-Key: ' . $this->apiKey(),
            'X-Secret-Key: ' . $this->secretKey(),
            'Accept: application/json',
        ];

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => self::TIMEOUT_SEC,
            CURLOPT_CONNECTTIMEOUT => 10,
        ];

        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_SLASHES);
        }

        $opts[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $opts);

        $raw = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            Log::error('[KpayService] cURL error', ['error' => $err, 'url' => $url]);

            return [
                'success' => false,
                'message' => 'Erreur réseau KPay: ' . $err,
                'http' => null,
                'body' => null,
            ];
        }

        $decoded = json_decode((string) $raw, true);

        if ($http >= 200 && $http < 300) {
            return [
                'success' => true,
                'data' => is_array($decoded) ? $decoded : [],
                'http' => $http,
            ];
        }

        $message = self::extractErrorMessage($decoded);

        Log::error('[KpayService] HTTP error', [
            'method' => $method,
            'path' => $path,
            'http' => $http,
            'body' => $decoded ?: $raw,
        ]);

        return [
            'success' => false,
            'message' => (string) $message,
            'http' => $http,
            'body' => is_array($decoded) ? $decoded : null,
        ];
    }
}
