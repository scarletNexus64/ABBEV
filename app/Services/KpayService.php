<?php

namespace App\Services;

use App\Models\Configuration;
use App\Models\Transaction;
use App\Models\UserSubscription;
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
     * POST /api/v1/payments/init — mode USSD.
     *
     * @param array{amount:int|float, paymentMethod:string, phoneNumber:string, externalId:string, description?:string, customerName?:string, customerEmail?:string, metadata?:array} $params
     * @return array{success:bool, data?:array, message?:string, http?:int|null, body?:array|null}
     */
    public function initPayment(array $params): array
    {
        Log::info('[KpayService] Init payment', [
            'externalId' => $params['externalId'] ?? null,
            'amount' => $params['amount'] ?? null,
            'method' => $params['paymentMethod'] ?? null,
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

        $message = is_array($decoded) ? ($decoded['error'] ?? ($decoded['message'] ?? 'KPay error')) : 'KPay error';
        if (is_array($message)) {
            $message = implode(', ', array_map('strval', $message));
        }

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
