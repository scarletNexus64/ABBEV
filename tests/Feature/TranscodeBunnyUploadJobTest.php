<?php

namespace Tests\Feature;

use App\Jobs\TranscodeBunnyUpload;
use App\Models\BunnyUpload;
use App\Models\Category;
use App\Models\Media;
use App\Services\VideoTranscoder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Le transcodage ffmpeg est DÉCOUPLÉ du transfert Bunny : il tourne sur sa
 * propre file « transcode » pour ne jamais bloquer un transfert (ni les autres
 * uploads). Ces tests vérifient le routage sur la bonne file et la conversion
 * (mise à jour de local_path + réattribution des Media/Episode attachés).
 */
class TranscodeBunnyUploadJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_job_est_route_sur_la_file_transcode(): void
    {
        $job = new TranscodeBunnyUpload(1);

        $this->assertSame('transcode', $job->connection);
        $this->assertSame('transcode', $job->queue);
    }

    public function test_convertit_le_webm_et_reattribue_le_media(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('videos/9_src.webm', 'fake-webm-bytes');
        $srcAbs = Storage::disk('local')->path('videos/9_src.webm');

        $upload = BunnyUpload::create([
            'original_filename' => 'clip.webm',
            'title' => 'Clip',
            'size_bytes' => 100,
            'status' => 'failed',
            'local_path' => 'videos/9_src.webm',
            'temp_path' => $srcAbs,
        ]);

        $category = Category::firstOrCreate(['slug' => 'action'], ['name' => 'Action']);
        $movie = Media::create([
            'category_id' => $category->id,
            'title' => 'Film',
            'slug' => 'film-transcode',
            'type' => 'movie',
            'video_provider' => 'local',
            'video_path' => 'videos/9_src.webm',
        ]);

        // Transcodeur factice : simule un ffmpeg qui produit un .mp4 à côté.
        $fake = new class extends VideoTranscoder {
            public function isAvailable(): bool { return true; }
            public function toMp4(string $source): ?string {
                $dest = preg_replace('/\.[^.]+$/', '.mp4', $source);
                file_put_contents($dest, 'fake-mp4-bytes');
                return $dest;
            }
        };
        $this->app->instance(VideoTranscoder::class, $fake);

        (new TranscodeBunnyUpload($upload->id))->handle($fake);

        $upload->refresh();
        $this->assertSame('videos/9_src.mp4', $upload->local_path);
        $this->assertStringEndsWith('.mp4', (string) $upload->temp_path);
        $this->assertSame('videos/9_src.mp4', $movie->fresh()->video_path);
        $this->assertFalse(Storage::disk('local')->exists('videos/9_src.webm'), 'original webm supprimé');
    }

    public function test_ne_fait_rien_si_deja_mp4(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('videos/8_ok.mp4', 'already-mp4');

        $upload = BunnyUpload::create([
            'original_filename' => 'ok.mp4',
            'title' => 'Ok',
            'size_bytes' => 10,
            'status' => 'ready',
            'local_path' => 'videos/8_ok.mp4',
            'temp_path' => Storage::disk('local')->path('videos/8_ok.mp4'),
        ]);

        $spy = new class extends VideoTranscoder {
            public bool $called = false;
            public function toMp4(string $source): ?string { $this->called = true; return null; }
        };

        (new TranscodeBunnyUpload($upload->id))->handle($spy);

        $this->assertFalse($spy->called, 'ffmpeg ne doit pas être invoqué pour un MP4');
        $this->assertSame('videos/8_ok.mp4', $upload->fresh()->local_path);
    }
}
