<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Vérification des achats In-App Apple (StoreKit 2) via l'App Store Server API.
 *
 * Flux : l'app envoie un `transactionId` (issu de StoreKit). On interroge
 * l'API Apple, authentifiée par un JWT ES256 signé avec notre clé .p8, qui
 * renvoie la transaction SIGNÉE par Apple (JWS). Apple étant la source de
 * vérité, on décode le payload sans avoir à valider nous-mêmes la chaîne x5c.
 *
 * Doc : https://developer.apple.com/documentation/appstoreserverapi
 */
class AppleAppStoreService
{
    private const PROD_BASE = 'https://api.storekit.itunes.apple.com';
    private const SANDBOX_BASE = 'https://api.storekit-sandbox.itunes.apple.com';

    private string $bundleId;
    private ?string $issuerId;
    private ?string $keyId;
    private bool $sandbox;

    public function __construct()
    {
        $cfg = config('services.apple_iap');
        $this->bundleId = $cfg['bundle_id'];
        $this->issuerId = $cfg['issuer_id'] ?? null;
        $this->keyId = $cfg['key_id'] ?? null;
        $this->sandbox = (bool) ($cfg['sandbox'] ?? true);
    }

    /**
     * Récupère et décode une transaction auprès de l'App Store Server API.
     *
     * @return array{success:bool, message?:string, payload?:array}
     */
    public function getTransaction(string $transactionId): array
    {
        try {
            $token = $this->generateToken();
        } catch (\Throwable $e) {
            Log::error('[AppleIAP] Token signing failed', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Configuration Apple IAP invalide'];
        }

        // On essaie d'abord l'environnement configuré, puis on bascule sur
        // l'autre si Apple répond 4xx (cas classique : reçu sandbox sur prod).
        $bases = $this->sandbox
            ? [self::SANDBOX_BASE, self::PROD_BASE]
            : [self::PROD_BASE, self::SANDBOX_BASE];

        foreach ($bases as $base) {
            $response = Http::withToken($token)
                ->acceptJson()
                ->get("{$base}/inApps/v1/transactions/{$transactionId}");

            Log::info('[AppleIAP] GET transaction', [
                'env' => $base === self::SANDBOX_BASE ? 'sandbox' : 'production',
                'transaction_id' => $transactionId,
                'status' => $response->status(),
            ]);

            if ($response->successful()) {
                $signed = $response->json('signedTransactionInfo');

                if (!$signed) {
                    return ['success' => false, 'message' => 'Réponse Apple sans signedTransactionInfo'];
                }

                $payload = $this->decodeJws($signed);

                if ($payload === null) {
                    return ['success' => false, 'message' => 'JWS Apple illisible'];
                }

                return ['success' => true, 'payload' => $payload];
            }

            // 404 = transaction inconnue de cet environnement → on tente l'autre.
            if ($response->status() === 404) {
                Log::info('[AppleIAP] Transaction absente de cet environnement, '
                    . 'bascule vers l\'autre', ['base' => $base]);
            } else {
                // 401 = JWT refusé (issuer_id / key_id / .p8 incohérents).
                Log::warning('[AppleIAP] App Store API error', [
                    'status' => $response->status(),
                    'base' => $base,
                    'body' => $response->body(),
                ]);
            }
        }

        return ['success' => false, 'message' => 'Transaction introuvable côté Apple'];
    }

    /**
     * Décode ET VÉRIFIE le payload d'une notification App Store Server v2.
     *
     * Contrairement à la réponse de l'App Store Server API (canal sortant
     * authentifié par notre JWT), une notification arrive sur une route
     * publique : n'importe qui peut POSTer un JWS forgé. La signature est
     * donc la SEULE preuve d'authenticité — on valide la chaîne x5c contre
     * le certificat racine Apple avant de faire confiance au contenu.
     *
     * Renvoie le contenu déballé (notificationType, data, etc.) ou null si
     * la signature, la chaîne de certificats ou le format est invalide.
     */
    public function decodeNotification(string $signedPayload): ?array
    {
        return $this->verifyJws($signedPayload);
    }

    /**
     * Vérifie un JWS signé par Apple et renvoie son payload.
     *
     * Le header porte `x5c` : la chaîne de certificats DER/base64 [leaf,
     * intermediate, root]. On vérifie que cette chaîne remonte bien au
     * certificat racine Apple (embarqué), puis on valide la signature du
     * JWS avec la clé publique du certificat feuille.
     */
    private function verifyJws(string $jws): ?array
    {
        $parts = explode('.', $jws);

        if (count($parts) !== 3) {
            return null;
        }

        $header = json_decode($this->b64UrlDecode($parts[0]), true);

        if (!is_array($header) || ($header['alg'] ?? null) !== 'ES256') {
            Log::warning('[AppleIAP] JWS header invalide', ['alg' => $header['alg'] ?? null]);

            return null;
        }

        $x5c = $header['x5c'] ?? null;

        if (!is_array($x5c) || count($x5c) < 2) {
            Log::warning('[AppleIAP] JWS sans chaîne x5c exploitable');

            return null;
        }

        $leafPem = $this->derToPem($x5c[0]);

        if (!$this->verifyCertificateChain($x5c)) {
            return null;
        }

        try {
            $publicKey = openssl_pkey_get_public($leafPem);
        } catch (\Throwable $e) {
            $publicKey = false;
        }

        if ($publicKey === false) {
            Log::warning('[AppleIAP] Clé publique du certificat feuille illisible');

            return null;
        }

        try {
            // JWT::decode valide la signature ES256 ET les claims temporels.
            $payload = JWT::decode($jws, new Key($publicKey, 'ES256'));
        } catch (\Throwable $e) {
            Log::warning('[AppleIAP] Signature JWS invalide', ['error' => $e->getMessage()]);

            return null;
        }

        return json_decode(json_encode($payload), true);
    }

    /**
     * Valide la chaîne de certificats x5c : chaque certificat doit être signé
     * par le suivant, et la racine doit être un certificat racine Apple connu.
     */
    private function verifyCertificateChain(array $x5c): bool
    {
        // Laravel convertit les warnings PHP en ErrorException : sur une
        // entrée malformée, openssl_* lèverait au lieu de renvoyer false,
        // transformant un rejet légitime en erreur 500. On isole donc tout
        // le parcours de vérification.
        try {
            return $this->doVerifyCertificateChain($x5c);
        } catch (\Throwable $e) {
            Log::warning('[AppleIAP] Chaîne x5c illisible', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Cœur de la validation de chaîne (voir verifyCertificateChain).
     */
    private function doVerifyCertificateChain(array $x5c): bool
    {
        $rootPath = config('services.apple_iap.root_cert_path');

        if (!$rootPath || !is_readable($rootPath)) {
            Log::error('[AppleIAP] Certificat racine Apple absent — notification rejetée', [
                'path' => $rootPath,
            ]);

            return false;
        }

        $chain = array_map(fn ($der) => $this->derToPem($der), $x5c);
        $rootPem = file_get_contents($rootPath);

        // La racine présentée doit être exactement notre racine de confiance.
        $presentedRoot = openssl_x509_read(end($chain));
        $trustedRoot = openssl_x509_read($rootPem);

        if ($presentedRoot === false || $trustedRoot === false) {
            Log::warning('[AppleIAP] Certificat racine illisible');

            return false;
        }

        if (!hash_equals(
            $this->fingerprint($presentedRoot),
            $this->fingerprint($trustedRoot)
        )) {
            Log::warning('[AppleIAP] Racine x5c ≠ racine Apple de confiance');

            return false;
        }

        // Chaque certificat doit être signé par la clé publique du suivant.
        for ($i = 0; $i < count($chain) - 1; $i++) {
            $cert = openssl_x509_read($chain[$i]);
            $issuerKey = openssl_pkey_get_public($chain[$i + 1]);

            if ($cert === false || $issuerKey === false) {
                Log::warning('[AppleIAP] Certificat de la chaîne illisible', ['index' => $i]);

                return false;
            }

            if (openssl_x509_verify($cert, $issuerKey) !== 1) {
                Log::warning('[AppleIAP] Maillon de chaîne x5c non signé par son émetteur', [
                    'index' => $i,
                ]);

                return false;
            }

            // Un certificat expiré invalide toute la chaîne.
            $parsed = openssl_x509_parse($cert);
            $now = time();

            if (($parsed['validFrom_time_t'] ?? 0) > $now
                || ($parsed['validTo_time_t'] ?? 0) < $now) {
                Log::warning('[AppleIAP] Certificat de la chaîne hors période de validité', [
                    'index' => $i,
                ]);

                return false;
            }
        }

        return true;
    }

    /**
     * Empreinte SHA-256 d'un certificat, pour comparaison d'identité.
     *
     * @param  \OpenSSLCertificate  $cert
     */
    private function fingerprint($cert): string
    {
        return openssl_x509_fingerprint($cert, 'sha256') ?: '';
    }

    /**
     * Convertit un certificat DER base64 (format x5c) en PEM.
     */
    private function derToPem(string $der): string
    {
        return "-----BEGIN CERTIFICATE-----\n"
            . chunk_split($der, 64, "\n")
            . "-----END CERTIFICATE-----\n";
    }

    /**
     * Décodage base64url (les JWS n'utilisent pas le base64 standard).
     */
    private function b64UrlDecode(string $input): string
    {
        return base64_decode(strtr($input, '-_', '+/')) ?: '';
    }

    /**
     * Vérifie qu'un payload de transaction correspond bien à notre app et
     * qu'il n'est pas expiré/révoqué.
     */
    public function isTransactionValid(array $payload): bool
    {
        if (($payload['bundleId'] ?? null) !== $this->bundleId) {
            return false;
        }

        // Remboursée / révoquée : Apple positionne revocationDate.
        if (!empty($payload['revocationDate'])) {
            return false;
        }

        return true;
    }

    /**
     * Génère le JWT ES256 attendu par l'App Store Server API.
     */
    private function generateToken(): string
    {
        $privateKey = $this->resolvePrivateKey();

        if (!$this->issuerId || !$this->keyId || !$privateKey) {
            throw new \RuntimeException('issuer_id / key_id / private_key manquants');
        }

        $now = time();
        $payload = [
            'iss' => $this->issuerId,
            'iat' => $now,
            'exp' => $now + 1500, // < 60 min imposé par Apple
            'aud' => 'appstoreconnect-v1',
            'bid' => $this->bundleId,
        ];

        return JWT::encode($payload, $privateKey, 'ES256', $this->keyId);
    }

    /**
     * Charge la clé privée .p8 depuis l'env (contenu PEM) ou un fichier.
     */
    private function resolvePrivateKey(): ?string
    {
        $cfg = config('services.apple_iap');

        if (!empty($cfg['private_key'])) {
            // Les sauts de ligne sont souvent échappés en \n dans le .env.
            return str_replace('\\n', "\n", $cfg['private_key']);
        }

        if (!empty($cfg['key_path']) && is_readable($cfg['key_path'])) {
            return file_get_contents($cfg['key_path']);
        }

        return null;
    }

    /**
     * Décode le payload d'un JWS sans vérifier la signature.
     *
     * Réservé aux réponses de l'App Store Server API : le canal est sortant
     * et authentifié par notre JWT, donc la provenance est déjà établie.
     * Ne JAMAIS l'utiliser sur une entrée réseau publique — voir verifyJws().
     */
    private function decodeJws(string $jws): ?array
    {
        $parts = explode('.', $jws);

        if (count($parts) !== 3) {
            return null;
        }

        $json = base64_decode(strtr($parts[1], '-_', '+/'));
        $data = json_decode($json, true);

        return is_array($data) ? $data : null;
    }
}
