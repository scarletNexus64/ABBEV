<?php

namespace Tests\Unit;

use App\Services\VideoTranscoder;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Logique pure du transcodeur : décider quels fichiers doivent être convertis
 * en MP4 (la partie testable sans ffmpeg). On étend le TestCase Laravel car le
 * constructeur du service lit la config (`config('services.ffmpeg.*')`).
 */
class VideoTranscoderTest extends TestCase
{
    #[DataProvider('extensionProvider')]
    public function test_needs_transcode(string $input, bool $expected): void
    {
        $this->assertSame($expected, VideoTranscoder::needsTranscode($input));
    }

    public static function extensionProvider(): array
    {
        return [
            'webm à convertir'        => ['uploads/1_BF6v38EF.webm', true],
            'mkv à convertir'         => ['film.mkv', true],
            'avi à convertir'         => ['film.avi', true],
            'mov à convertir'         => ['film.mov', true],
            'mp4 déjà bon'            => ['uploads/2_abc.mp4', false],
            'm4v déjà bon'           => ['clip.m4v', false],
            'extension brute webm'    => ['webm', true],
            'extension brute mp4'     => ['mp4', false],
            'majuscules WEBM'         => ['FILM.WEBM', true],
        ];
    }

    public function test_to_mp4_retourne_null_si_source_absente(): void
    {
        $t = new VideoTranscoder();
        $this->assertNull($t->toMp4('/chemin/inexistant/video.webm'));
    }

    public function test_to_mp4_renvoie_la_source_si_deja_mp4(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'vt_').'.mp4';
        file_put_contents($tmp, 'fake');

        try {
            $t = new VideoTranscoder();
            // Déjà MP4 → renvoyé tel quel, sans invoquer ffmpeg.
            $this->assertSame($tmp, $t->toMp4($tmp));
        } finally {
            @unlink($tmp);
        }
    }

    public function test_is_available_renvoie_un_bool(): void
    {
        $t = new VideoTranscoder();
        $this->assertIsBool($t->isAvailable());
    }
}
