<?php

namespace App\Services;

use App\Models\Configuration;
use App\Models\Currency;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service NOWPayments — paiement par crypto (BTC, ETH, USDT…).
 *
 * Porté depuis le backend Winpawa et adapté à l'architecture ABBEV
 * (config via le modèle `Configuration`, montants en XAF/FCFA).
 *
 * NOWPayments fournit une « invoice » hébergée : on crée l'invoice depuis un
 * montant FCFA (converti en USD), on redirige l'utilisateur vers la page de
 * paiement NOWPayments, et la confirmation arrive par IPN (webhook) signé en
 * HMAC-SHA512.
 *
 * Doc : https://documenter.getpostman.com/view/7907941/S1a32n38
 *   Sandbox : https://api-sandbox.nowpayments.io/v1
 *   Live    : https://api.nowpayments.io/v1
 */
class NowPaymentsService
{
    /**
     * Mapping des statuts NOWPayments → statut interne de Transaction.
     */
    public const STATUS_MAP = [
        'waiting'        => 'pending',
        'confirming'     => 'pending',
        'sending'        => 'pending',
        'partially_paid' => 'pending',
        'finished'       => 'completed',
        'confirmed'      => 'completed',
        'failed'         => 'failed',
        'expired'        => 'failed',
        'refunded'       => 'cancelled',
    ];

    private bool $enabled;
    private ?string $apiKey;
    private ?string $ipnSecret;
    private string $mode;
    private float $exchangeRate;
    private float $minAmountXaf;
    private ?string $callbackBaseUrl;

    public function __construct()
    {
        $this->enabled         = (bool) Configuration::getValue('nowpayments_enabled', '0');
        $this->apiKey          = Configuration::getValue('nowpayments_api_key');
        $this->ipnSecret       = Configuration::getValue('nowpayments_ipn_secret');
        $this->mode            = Configuration::getValue('nowpayments_mode', 'sandbox');
        $this->exchangeRate    = (float) Configuration::getValue('nowpayments_exchange_rate', 655);
        $this->minAmountXaf    = (float) Configuration::getValue('nowpayments_min_amount_xaf', 30000);
        $this->callbackBaseUrl = Configuration::getValue('nowpayments_callback_url');

        Log::debug('[NowPaymentsService] Service initialized', [
            'mode'       => $this->mode,
            'enabled'    => $this->enabled,
            'hasApiKey'  => ! empty($this->apiKey),
            'hasIpnSec'  => ! empty($this->ipnSecret),
        ]);
    }

    /**
     * URL publique complète de réception de l'IPN.
     *
     * Si `nowpayments_callback_url` est renseigné (ex : URL ngrok en dev), on
     * l'utilise comme base ; sinon on retombe sur APP_URL. On ajoute le chemin
     * du webhook seulement s'il n'est pas déjà présent.
     */
    private function ipnCallbackUrl(): string
    {
        $base = trim((string) ($this->callbackBaseUrl ?: config('app.url')));
        $base = rtrim($base, '/');
        $path = '/api/webhooks/nowpayments';

        return str_ends_with($base, $path) ? $base : $base . $path;
    }

    /**
     * Le paiement crypto est-il activé ET configuré ?
     */
    public function isConfigured(): bool
    {
        return $this->enabled && ! empty($this->apiKey);
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function getExchangeRate(): float
    {
        return $this->exchangeRate;
    }

    public function getMinAmountXaf(): float
    {
        return $this->minAmountXaf;
    }

    private function baseUrl(): string
    {
        return $this->mode === 'live'
            ? 'https://api.nowpayments.io/v1'
            : 'https://api-sandbox.nowpayments.io/v1';
    }

    /**
     * Crée une invoice hébergée NOWPayments à partir d'une transaction ABBEV.
     *
     * @param array{return_url?:string, cancel_url?:string} $urls
     * @return array{success:bool, message?:string, invoice_id?:string, payment_url?:string, amount_usd?:float}
     */
    public function createInvoice(Transaction $transaction, array $urls = []): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => 'Le paiement crypto n\'est pas disponible pour le moment.'];
        }

        $amountXaf = (float) $transaction->amount;

        if ($amountXaf < $this->minAmountXaf) {
            $min = number_format($this->minAmountXaf, 0, '.', ' ');
            return [
                'success' => false,
                'message' => "Montant minimum pour un paiement crypto : {$min} FCFA. "
                    . 'Certaines cryptos (BTC, ETH) ont des frais réseau élevés qui imposent un minimum plus haut.',
            ];
        }

        // Conversion au taux LIVE (currencies.rate_from_xof) ; repli sur le
        // taux fixe configurable si USD absent de la table.
        $usdRate = Currency::rateFromXof('USD');
        $amountUsd = ($usdRate !== null && $usdRate > 0)
            ? round($amountXaf * $usdRate, 2)
            : round($amountXaf / max($this->exchangeRate, 1), 2);

        $callbackUrl = $this->ipnCallbackUrl();

        $payload = [
            'price_amount'     => $amountUsd,
            'price_currency'   => 'usd',
            'order_id'         => $transaction->transaction_id,
            'order_description' => $transaction->description ?: 'Paiement ABBEV',
            'ipn_callback_url' => $callbackUrl,
            'success_url'      => $urls['return_url'] ?? rtrim(config('app.url'), '/') . '/',
            'cancel_url'       => $urls['cancel_url'] ?? rtrim(config('app.url'), '/') . '/',
        ];

        try {
            $response = Http::withHeaders([
                'x-api-key'    => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($this->baseUrl() . '/invoice', $payload);

            Log::info('[NowPaymentsService] createInvoice', [
                'transaction_id' => $transaction->transaction_id,
                'amount_xaf'     => $amountXaf,
                'amount_usd'     => $amountUsd,
                'status'         => $response->status(),
            ]);

            if ($response->failed()) {
                $body   = $response->json() ?? [];
                $rawMsg = (string) ($body['message'] ?? '');

                Log::error('[NowPaymentsService] createInvoice failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                // NOWPayments impose un montant minimum qui dépend de la crypto
                // (frais réseau BTC/ETH élevés). On traduit l'erreur brute en
                // message clair plutôt que de remonter « amount is less than
                // minimal value » à l'utilisateur.
                if (stripos($rawMsg, 'minimal') !== false ||
                    stripos($rawMsg, 'minimum') !== false) {
                    $min = number_format($this->minAmountXaf, 0, '.', ' ');

                    return [
                        'success' => false,
                        'message' => "Ce montant est trop faible pour un paiement crypto. "
                            . "Le minimum est d'environ {$min} FCFA (les frais réseau Bitcoin/Ethereum "
                            . 'imposent un seuil élevé). Choisissez un montant plus important.',
                    ];
                }

                return [
                    'success' => false,
                    'message' => $rawMsg !== '' ? $rawMsg : 'Erreur NOWPayments : ' . $response->status(),
                ];
            }

            $data       = $response->json();
            $invoiceId  = $data['id'] ?? null;
            $invoiceUrl = $data['invoice_url'] ?? null;

            if (! $invoiceId || ! $invoiceUrl) {
                return ['success' => false, 'message' => 'Réponse NOWPayments invalide.'];
            }

            return [
                'success'     => true,
                'invoice_id'  => (string) $invoiceId,
                'payment_url' => $invoiceUrl,
                'amount_usd'  => $amountUsd,
                'data'        => $data,
            ];
        } catch (\Exception $e) {
            Log::error('[NowPaymentsService] createInvoice exception', [
                'transaction_id' => $transaction->transaction_id,
                'error'          => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Récupère le statut d'un paiement lié à une invoice.
     *
     * ⚠️ NON utilisé par le flux nominal : l'endpoint NOWPayments de liste des
     * payments (`GET /payment/`) exige un **login JWT** (POST /auth avec
     * email + mot de passe du compte), PAS la simple `x-api-key` — il renvoie
     * 401 AUTH_REQUIRED sinon. La source de vérité est donc l'IPN webhook
     * (voir CryptoPaymentController::webhook). Cette méthode est conservée pour
     * une éventuelle implémentation JWT future ; ne pas l'appeler avec seulement
     * l'API key.
     *
     * @return array{success:bool, message?:string, raw_status?:string, mapped_status?:string, crypto_currency?:?string, crypto_amount?:mixed}
     */
    public function getPaymentStatus(string $invoiceId): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => 'NOWPayments non configuré.'];
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
            ])->timeout(20)->get($this->baseUrl() . '/payment/', [
                'invoice_id' => $invoiceId,
                'limit'      => 1,
            ]);

            if ($response->successful()) {
                $items = $response->json()['data'] ?? [];
                if (! empty($items)) {
                    $payment   = $items[0];
                    $rawStatus = strtolower($payment['payment_status'] ?? 'waiting');

                    return [
                        'success'         => true,
                        'raw_status'      => $rawStatus,
                        'mapped_status'   => self::STATUS_MAP[$rawStatus] ?? 'pending',
                        'crypto_currency' => $payment['pay_currency'] ?? null,
                        'crypto_amount'   => $payment['actually_paid'] ?? $payment['pay_amount'] ?? null,
                        'data'            => $payment,
                    ];
                }
            }

            // Aucun paiement encore associé à l'invoice = toujours en attente.
            return [
                'success'       => true,
                'raw_status'    => 'waiting',
                'mapped_status' => 'pending',
                'data'          => null,
            ];
        } catch (\Exception $e) {
            Log::error('[NowPaymentsService] getPaymentStatus failed', [
                'invoice_id' => $invoiceId,
                'error'      => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Vérifie la signature HMAC-SHA512 d'un IPN NOWPayments.
     *
     * NOWPayments signe le JSON body trié alphabétiquement par clé, de façon
     * RÉCURSIVE (sous-objets triés aussi), puis encodé sans échappement de
     * slashes ni d'unicode (équivalent de JSON.stringify côté JS).
     */
    public function verifyIpnSignature(string $rawBody, ?string $signature): bool
    {
        if (empty($signature) || empty($this->ipnSecret)) {
            return false;
        }

        $expected = $this->computeIpnSignature($rawBody);
        if ($expected === null) {
            return false;
        }

        return hash_equals($expected, $signature);
    }

    /**
     * Calcule la signature attendue pour un body brut. Retourne null si le body
     * n'est pas un JSON valide.
     */
    private function computeIpnSignature(string $rawBody): ?string
    {
        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            return null;
        }

        $this->ksortRecursive($payload);
        $sorted = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return hash_hmac('sha512', $sorted, (string) $this->ipnSecret);
    }

    /**
     * Tri récursif des clés (comme le sortObject de NOWPayments).
     *
     * @param array<mixed> $array
     */
    private function ksortRecursive(array &$array): void
    {
        ksort($array);
        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->ksortRecursive($value);
            }
        }
    }

    /**
     * DEBUG : signature attendue pour un body (diagnostic d'IPN). À ne pas
     * utiliser en production hors logs temporaires.
     */
    public function debugExpectedSignature(string $rawBody): ?string
    {
        return $this->computeIpnSignature($rawBody);
    }

    /**
     * L'IPN est-il signé (secret configuré) ? Permet de décider si on doit
     * imposer la vérification de signature.
     */
    public function hasIpnSecret(): bool
    {
        return ! empty($this->ipnSecret);
    }
}
