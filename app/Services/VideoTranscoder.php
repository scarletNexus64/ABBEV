<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Transcode une vidéo locale vers un MP4 universellement lisible sur mobile
 * (H.264 + AAC, `+faststart`).
 *
 * Pourquoi : les vidéos uploadées peuvent arriver en `.webm` (VP8/VP9), qui
 * n'est PAS lu par iOS (AVPlayer) ni Safari — ni en streaming ni en
 * téléchargement hors-ligne. On les convertit donc en `.mp4` H.264/AAC, qui
 * fonctionne partout (iOS, Android, Web).
 *
 * Nécessite le binaire `ffmpeg` installé sur le serveur (chemin configurable
 * via `FFMPEG_BIN`). En son absence, les méthodes dégradent proprement (log +
 * retour null) sans casser l'upload.
 */
class VideoTranscoder
{
    private string $bin;
    private int $timeout;

    public function __construct()
    {
        $this->bin = (string) config('services.ffmpeg.bin', 'ffmpeg');
        // Un long-métrage peut être long à encoder : timeout généreux (2 h).
        $this->timeout = (int) config('services.ffmpeg.timeout', 7200);
    }

    /**
     * Extensions déjà sûres sur mobile (pas de transcodage nécessaire).
     */
    private const SAFE_EXTENSIONS = ['mp4', 'm4v'];

    /**
     * Une extension de fichier nécessite-t-elle une conversion vers MP4 ?
     * (`.webm`, `.mkv`, `.avi`, `.mov`… → oui ; `.mp4`/`.m4v` → non).
     */
    public static function needsTranscode(string $pathOrExtension): bool
    {
        $ext = strtolower(pathinfo($pathOrExtension, PATHINFO_EXTENSION));
        if ($ext === '') {
            // Pas d'extension dans le chemin : on suppose que c'est déjà
            // l'extension brute passée directement.
            $ext = strtolower($pathOrExtension);
        }

        return $ext !== '' && ! in_array($ext, self::SAFE_EXTENSIONS, true);
    }

    /**
     * `ffmpeg` est-il disponible sur ce serveur ?
     */
    public function isAvailable(): bool
    {
        try {
            $process = new Process([$this->bin, '-version']);
            $process->setTimeout(10);
            $process->run();

            return $process->isSuccessful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Convertit un fichier vidéo en MP4 H.264/AAC.
     *
     * @param string $source chemin ABSOLU du fichier source
     * @return string|null chemin absolu du .mp4 produit, ou null en cas
     *                     d'échec (ffmpeg absent / erreur). Si le source est
     *                     déjà un MP4, il est renvoyé tel quel.
     */
    public function toMp4(string $source): ?string
    {
        if (! is_file($source)) {
            Log::warning('[VideoTranscoder] Source introuvable', ['source' => $source]);

            return null;
        }

        if (! self::needsTranscode($source)) {
            return $source; // déjà mp4/m4v
        }

        if (! $this->isAvailable()) {
            Log::error('[VideoTranscoder] ffmpeg indisponible — conversion ignorée', [
                'bin' => $this->bin,
                'source' => $source,
            ]);

            return null;
        }

        $dest = $this->destinationPath($source);

        $process = new Process([
            $this->bin,
            '-y',
            '-i', $source,
            '-c:v', 'libx264',
            '-preset', 'veryfast',
            '-crf', '23',
            // libx264 + yuv420p exigent des dimensions PAIRES : on arrondit
            // largeur/hauteur au multiple de 2 inférieur (ex: 1867x950 → 1866x950),
            // sinon « width not divisible by 2 » et la conversion échoue.
            '-vf', 'scale=trunc(iw/2)*2:trunc(ih/2)*2',
            // Chroma 4:2:0 : indispensable pour la compatibilité iOS/Safari.
            '-pix_fmt', 'yuv420p',
            '-c:a', 'aac',
            '-b:a', '128k',
            // Déplace l'atome moov en tête : lecture/téléchargement progressif.
            '-movflags', '+faststart',
            $dest,
        ]);
        $process->setTimeout($this->timeout);

        Log::info('[VideoTranscoder] Début conversion → MP4', [
            'source' => $source,
            'dest' => $dest,
        ]);

        try {
            $process->run();
        } catch (\Throwable $e) {
            Log::error('[VideoTranscoder] Exception ffmpeg', [
                'source' => $source,
                'error' => $e->getMessage(),
            ]);
            @unlink($dest);

            return null;
        }

        if (! $process->isSuccessful() || ! is_file($dest) || filesize($dest) === 0) {
            Log::error('[VideoTranscoder] Échec conversion ffmpeg', [
                'source' => $source,
                'exit' => $process->getExitCode(),
                'stderr' => mb_substr($process->getErrorOutput(), -500),
            ]);
            @unlink($dest);

            return null;
        }

        Log::info('[VideoTranscoder] Conversion réussie', [
            'dest' => $dest,
            'size' => filesize($dest),
        ]);

        return $dest;
    }

    /**
     * Chemin de sortie `.mp4` à côté du source (sans écraser un fichier
     * existant).
     */
    private function destinationPath(string $source): string
    {
        $base = preg_replace('/\.[^.\/\\\\]+$/', '', $source);
        $dest = $base . '.mp4';

        // Évite d'écraser un mp4 homonyme déjà présent.
        $i = 1;
        while (is_file($dest) && $dest !== $source) {
            $dest = $base . '_' . $i . '.mp4';
            $i++;
        }

        return $dest;
    }
}
