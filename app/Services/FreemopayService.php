<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Configuration;

class FreemopayService
{
    private string $baseUrl;
    private ?string $appKey;
    private ?string $secretKey;
    private ?string $token = null;

    public function __construct()
    {
        $this->baseUrl = Configuration::getValue('freemopay_base_url', 'https://api-v2.freemopay.com');
        $this->appKey = Configuration::getValue('freemopay_app_key');
        $this->secretKey = Configuration::getValue('freemopay_secret_key');
    }

    /**
     * Generate authentication token
     */
    public function generateToken(): ?string
    {
        try {
            // FreeMoPay attend un JSON body
            $response = Http::asJson()
                ->post("{$this->baseUrl}/api/v2/payment/token", [
                    'appKey' => $this->appKey,
                    'secretKey' => $this->secretKey,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $this->token = $data['access_token'] ?? null;
                return $this->token;
            }

            Log::error('FreemoPay token error', ['response' => $response->body()]);
            return null;
        } catch (\Exception $e) {
            Log::error('FreemoPay token exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Initialize a payment
     */
    public function initializePayment(array $params): array
    {
        if (!$this->token) {
            $this->generateToken();
        }

        if (!$this->token) {
            return ['success' => false, 'message' => 'Impossible de générer le token FreemoPay'];
        }

        try {
            $response = Http::withToken($this->token)
                ->asJson()
                ->post("{$this->baseUrl}/api/v2/payment", [
                    'payer' => $params['phone_number'],
                    'amount' => (string) $params['amount'],
                    'externalId' => $params['external_reference'] ?? null,
                    'description' => $params['description'] ?? 'Paiement ABBEV',
                    'callback' => $params['callback_url'] ?? url('/api/webhooks/freemopay'),
                ]);

            $data = $response->json();

            if ($response->successful()) {
                return [
                    'success' => true,
                    'reference' => $data['reference'] ?? null,
                    'status' => $data['status'] ?? 'PENDING',
                    'message' => $data['message'] ?? 'Paiement initié',
                    'data' => $data ?? [],
                ];
            }

            return [
                'success' => false,
                'message' => $data['message'] ?? 'Erreur lors du paiement',
            ];
        } catch (\Exception $e) {
            Log::error('FreemoPay payment exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Erreur de connexion au service de paiement'];
        }
    }

    /**
     * Check payment status
     */
    public function checkStatus(string $reference): array
    {
        if (!$this->token) {
            $this->generateToken();
        }

        try {
            $response = Http::withToken($this->token)
                ->get("{$this->baseUrl}/api/v2/payment/{$reference}");

            $data = $response->json();

            return [
                'success' => $response->successful(),
                'reference' => $reference,
                'status' => $data['status'] ?? 'UNKNOWN',
                'data' => $data ?? [],
                'reason' => $data['reason'] ?? null,
                'message' => $data['message'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('FreemoPay status exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'status' => 'ERROR'];
        }
    }

    /**
     * Check payment status (alias for job compatibility)
     */
    public function checkPaymentStatus(string $reference): array
    {
        $result = $this->checkStatus($reference);

        return [
            'status' => $result['status'],
            'reference' => $reference,
            'data' => $result['data'] ?? [],
            'message' => $result['data']['message'] ?? null,
            'reason' => $result['data']['reason'] ?? null,
        ];
    }

    /**
     * Check withdrawal (disbursement) status
     */
    public function checkDisbursementStatus(string $reference): array
    {
        if (!$this->appKey || !$this->secretKey) {
            Log::error('FreemoPay: Missing credentials for disbursement status check');
            return [
                'status' => 'ERROR',
                'message' => 'Service configuration error',
            ];
        }

        try {
            $response = Http::withBasicAuth($this->appKey, $this->secretKey)
                ->timeout(30)
                ->get("{$this->baseUrl}/api/v2/payment/{$reference}");

            if (!$response->successful()) {
                Log::error('FreemoPay disbursement status error', [
                    'reference' => $reference,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'status' => 'ERROR',
                    'message' => 'Failed to check withdrawal status',
                ];
            }

            $data = $response->json();

            return [
                'status' => $data['status'] ?? 'UNKNOWN',
                'reference' => $reference,
                'data' => $data ?? [],
                'message' => $data['message'] ?? null,
                'reason' => $data['reason'] ?? null,
            ];

        } catch (\Exception $e) {
            Log::error('FreemoPay disbursement status exception', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'ERROR',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Initiate a disbursement (withdrawal) to a mobile money account
     *
     * @param array $params ['amount', 'phone_number', 'description', 'external_reference']
     * @return array
     */
    public function initiateDisbursement(array $params): array
    {
        if (!$this->appKey || !$this->secretKey) {
            Log::error('FreemoPay: Missing credentials for disbursement');
            return [
                'success' => false,
                'message' => 'Service configuration error',
            ];
        }

        try {
            $response = Http::withBasicAuth($this->appKey, $this->secretKey)
                ->asJson()
                ->timeout(30)
                ->post("{$this->baseUrl}/api/v2/payment/direct-withdraw", [
                    'receiver' => $params['phone_number'],
                    'amount' => (string) $params['amount'],
                    'externalId' => $params['external_reference'] ?? 'WITHDRAW-' . time(),
                    'callback' => $params['callback_url'] ?? url('/api/webhooks/freemopay-disbursement'),
                ]);

            if (!$response->successful()) {
                Log::error('FreemoPay disbursement error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                $errorData = $response->json();
                return [
                    'success' => false,
                    'message' => $errorData['message'] ?? 'Failed to initiate withdrawal',
                    'data' => $errorData ?? [],
                ];
            }

            $data = $response->json();

            return [
                'success' => true,
                'reference' => $data['reference'] ?? null,
                'status' => $data['status'] ?? 'CREATED',
                'message' => $data['message'] ?? 'Disbursement initiated',
                'data' => $data ?? [],
            ];

        } catch (\Exception $e) {
            Log::error('FreemoPay disbursement exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage(),
                'data' => [],
            ];
        }
    }
}
