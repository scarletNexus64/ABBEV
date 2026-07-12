<?php

namespace Tests\Feature;

use App\Jobs\ConvertLocalVideoToMp4;
use App\Models\Category;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * La commande videos:transcode-local doit ENFILER un job de conversion sur la
 * file « bunny » pour chaque vidéo locale non-MP4 (ex. .webm), et ignorer
 * celles déjà en MP4. L'encodage réel est fait par le worker bunny.
 */
class TranscodeLocalVideosCommandTest extends TestCase
{
    use RefreshDatabase;

    private function localMovie(string $videoPath): Media
    {
        $category = Category::firstOrCreate(
            ['slug' => 'action'],
            ['name' => 'Action'],
        );

        return Media::create([
            'category_id' => $category->id,
            'title' => 'Test',
            'slug' => 'test-'.md5($videoPath),
            'type' => 'movie',
            'video_provider' => 'local',
            'video_path' => $videoPath,
        ]);
    }

    public function test_enfile_un_job_pour_un_webm_local(): void
    {
        Queue::fake();
        $movie = $this->localMovie('uploads/1_abc.webm');

        $this->artisan('videos:transcode-local')
            ->assertExitCode(0);

        Queue::assertPushed(ConvertLocalVideoToMp4::class, function ($job) use ($movie) {
            return $job->modelType === 'movie' && $job->modelId === $movie->id;
        });
    }

    public function test_ignore_les_videos_deja_mp4(): void
    {
        Queue::fake();
        $this->localMovie('uploads/2_def.mp4');

        $this->artisan('videos:transcode-local')->assertExitCode(0);

        Queue::assertNotPushed(ConvertLocalVideoToMp4::class);
    }

    public function test_dry_run_n_enfile_rien(): void
    {
        Queue::fake();
        $this->localMovie('uploads/3_ghi.webm');

        $this->artisan('videos:transcode-local', ['--dry-run' => true])
            ->assertExitCode(0);

        Queue::assertNotPushed(ConvertLocalVideoToMp4::class);
    }
}
