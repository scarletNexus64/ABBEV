<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Wrapper autour de l'API Bunny Stream.
 *
 * Endpoints utilisés :
 *   GET  https://video.bunnycdn.com/library/{libraryId}/videos      → liste
 *   GET  https://video.bunnycdn.com/library/{libraryId}/videos/{id} → détail
 *   POST https://video.bunnycdn.com/library/{libraryId}/videos      → créer vidéo
 *   DEL  https://video.bunnycdn.com/library/{libraryId}/videos/{id} → supprimer
 *
 * URLs de lecture (sur le CDN, pas l'API) :
 *   HLS    : https://{cdn}/{guid}/playlist.m3u8
 *   MP4    : https://{cdn}/{guid}/play_{height}p.mp4
 *   Thumb  : https://{cdn}/{guid}/thumbnail.jpg
 *   Poster : https://{cdn}/{guid}/preview.webp
 *
 * Token authentication (signed URLs) :
 *   path = "/{guid}/playlist.m3u8"
 *   token = sha256(security_key + path + expires)
 *   URL signée = https://{cdn}{path}?token={token}&expires={epoch}
 *
 * Doc officielle : https://docs.bunny.net/reference/video_list
 */
class BunnyStreamService
{
    public function __construct(
        protected ?string $libraryId = null,
        protected ?string $apiKey = null,
        protected ?string $cdnHostname = null,
        protected ?string $tokenKey = null,
    ) {
        $this->libraryId   ??= (string) config('services.bunny.library_id');
        $this->apiKey      ??= (string) config('services.bunny.api_key');
        $this->cdnHostname ??= (string) config('services.bunny.cdn_hostname');
        $this->tokenKey    ??= (string) config('services.bunny.token_key');
    }

    public function isConfigured(): bool
    {
        return $this->libraryId !== '' && $this->apiKey !== '' && $this->cdnHostname !== '';
    }

    /**
     * Test de connectivité : un appel authentifié léger à la library.
     * Renvoie le code HTTP (200 = OK, 401/403 = clé invalide, 404 = library
     * introuvable) sans lever d'exception, pour la page de configuration.
     *
     * @return array{ok: bool, status: int|null, message: string|null}
     */
    public function ping(): array
    {
        try {
            $resp = $this->api()->get("/library/{$this->libraryId}/videos", ['itemsPerPage' => 1]);

            return ['ok' => $resp->successful(), 'status' => $resp->status(), 'message' => null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'status' => null, 'message' => $e->getMessage()];
        }
    }

    /* ---------------------------------------------------------------
     |  API management (auth via AccessKey header)
     * --------------------------------------------------------------- */
    protected function api(): PendingRequest
    {
        return Http::baseUrl('https://video.bunnycdn.com')
            ->withHeaders([
                'AccessKey' => $this->apiKey,
                'Accept'    => 'application/json',
            ])
            ->acceptJson()
            ->connectTimeout(5) // échoue vite si Bunny est injoignable (évite que l'UI tourne)
            ->timeout(15);
    }

    /**
     * Liste TOUTES les vidéos de la library (paginé en interne).
     *
     * @return array<int, array<string, mixed>>
     */
    public function listAllVideos(int $perPage = 100): array
    {
        $page = 1;
        $all  = [];

        while (true) {
            $resp = $this->api()->get("/library/{$this->libraryId}/videos", [
                'page'     => $page,
                'itemsPerPage' => $perPage,
                'orderBy'  => 'date',
            ]);
            $resp->throw();
            $items = $resp->json('items', []);
            if (empty($items)) {
                break;
            }
            foreach ($items as $v) {
                $all[] = $v;
            }
            if (count($items) < $perPage) {
                break;
            }
            $page++;
            if ($page > 100) { // safety
                break;
            }
        }

        return $all;
    }

    public function getVideo(string $guid): array
    {
        $resp = $this->api()->get("/library/{$this->libraryId}/videos/{$guid}");
        $resp->throw();

        return $resp->json();
    }

    /**
     * Statut d'encodage Bunny d'une vidéo.
     * 0 = created, 1 = uploaded, 2 = processing, 3 = transcoding, 4 = finished/ready, 5 = error.
     */
    public function getVideoStatus(string $guid): int
    {
        return (int) ($this->getVideo($guid)['status'] ?? 0);
    }

    /**
     * Crée une entrée vidéo (sans binaire) dans la library et retourne son guid.
     * POST /library/{libraryId}/videos {"title": ...}
     */
    public function createVideo(string $title): string
    {
        $resp = $this->api()->post("/library/{$this->libraryId}/videos", [
            'title' => $title,
        ]);
        $resp->throw();

        return (string) $resp->json('guid');
    }

    /**
     * Envoie le binaire d'un fichier vidéo vers une vidéo Bunny existante.
     *
     * PUT /library/{libraryId}/videos/{guid} avec le fichier en body.
     *
     * Le fichier est streamé via une resource fopen() : cURL lit le handle au
     * fil de l'eau, donc l'empreinte mémoire reste constante (quelques Mo) même
     * pour un fichier de plusieurs Go. NE PAS remplacer par file_get_contents()
     * ni Http::attach() (qui bufferiseraient tout le fichier en RAM).
     *
     * @param  callable|null  $onProgress  fn(int $bytesSent, int $bytesTotal)
     */
    public function uploadVideoStream(string $guid, string $absolutePath, ?callable $onProgress = null): bool
    {
        $stream = @fopen($absolutePath, 'rb');
        if ($stream === false) {
            throw new \RuntimeException("Impossible d'ouvrir le fichier : {$absolutePath}");
        }

        try {
            $client = new \GuzzleHttp\Client(['http_errors' => true]);

            $response = $client->put(
                "https://video.bunnycdn.com/library/{$this->libraryId}/videos/{$guid}",
                [
                    'headers' => [
                        'AccessKey'    => $this->apiKey,
                        'Content-Type' => 'application/octet-stream',
                        'Accept'       => 'application/json',
                    ],
                    'body'            => $stream, // resource ⇒ streamé chunk par chunk
                    'timeout'         => 0,       // pas de timeout total (gros fichiers)
                    'connect_timeout' => 30,
                    'expect'          => false,   // pas de "Expect: 100-continue"
                    'progress'        => function ($downloadTotal, $downloaded, $uploadTotal, $uploaded) use ($onProgress) {
                        if ($onProgress && $uploadTotal > 0) {
                            $onProgress((int) $uploaded, (int) $uploadTotal);
                        }
                    },
                ]
            );

            return $response->getStatusCode() === 200;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function deleteVideo(string $guid): bool
    {
        $resp = $this->api()->delete("/library/{$this->libraryId}/videos/{$guid}");

        return $resp->successful();
    }

    /* ---------------------------------------------------------------
     |  URLs CDN
     * --------------------------------------------------------------- */
    public function hlsUrl(string $guid, ?int $expiresInSeconds = null): string
    {
        return $this->signed("/{$guid}/playlist.m3u8", $expiresInSeconds);
    }

    public function mp4Url(string $guid, int $height = 720, ?int $expiresInSeconds = null): string
    {
        return $this->signed("/{$guid}/play_{$height}p.mp4", $expiresInSeconds);
    }

    /**
     * URL du lecteur iframe Bunny Stream (gère HLS, qualités, sous-titres…).
     * C'est la façon recommandée d'embarquer une vidéo Bunny.
     */
    public function embedUrl(string $guid, bool $autoplay = false): string
    {
        $url = "https://iframe.mediadelivery.net/embed/{$this->libraryId}/{$guid}";

        return $autoplay ? $url . '?autoplay=true' : $url;
    }

    public function thumbnailUrl(string $guid): string
    {
        // les thumbnails sont publiques (pas signées) — c'est l'usage standard chez Bunny
        return "https://{$this->cdnHostname}/{$guid}/thumbnail.jpg";
    }

    public function previewUrl(string $guid): string
    {
        return "https://{$this->cdnHostname}/{$guid}/preview.webp";
    }

    /**
     * Génère une URL signée si Token Authentication est activé sur la library,
     * sinon retourne l'URL CDN brute.
     */
    protected function signed(string $path, ?int $expiresInSeconds = null): string
    {
        $base = "https://{$this->cdnHostname}{$path}";

        if (! config('services.bunny.signed_urls') || $this->tokenKey === '') {
            return $base;
        }

        $expires = time() + ($expiresInSeconds ?? (int) config('services.bunny.token_ttl', 3600));
        $token   = hash('sha256', $this->tokenKey . $path . $expires);
        // Bunny supporte aussi un encodage particulier (base64 url-safe). On envoie
        // les deux variantes ; la stock standard accepte la version hex.
        $token = strtr(base64_encode(hex2bin($token)), '+/', '-_');
        $token = rtrim($token, '=');

        return $base . '?token=' . $token . '&expires=' . $expires;
    }
}
