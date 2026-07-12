<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Media;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Sécurité des vidéos LOCALES : le watch renvoie une URL SIGNÉE et expirante
 * (plus de lien public permanent), et la route de streaming refuse tout accès
 * sans signature valide.
 */
class LocalVideoStreamTest extends TestCase
{
    use RefreshDatabase;

    private function subscriber(): User
    {
        $user = User::factory()->create(['role' => 'user']);
        $plan = SubscriptionPlan::create(['name' => 'Mensuel', 'price' => 1000, 'duration_days' => 30]);
        UserSubscription::create([
            'user_id'              => $user->id,
            'subscription_plan_id' => $plan->id,
            'starts_at'            => now(),
            'expires_at'           => now()->addDays(20),
            'status'               => 'active',
        ]);

        return $user;
    }

    private function localMovie(): Media
    {
        // Vidéos locales : disque PRIVÉ (jamais servi directement par le web).
        Storage::disk('local')->put('videos/test_movie.mp4', 'FAKE-MP4-BYTES');
        $category = Category::firstOrCreate(['slug' => 'action'], ['name' => 'Action']);

        return Media::create([
            'category_id'    => $category->id,
            'title'          => 'Film Local',
            'slug'           => 'film-local',
            'type'           => 'movie',
            'video_provider' => 'local',
            'video_path'     => 'videos/test_movie.mp4',
            'published_at'   => now(),
        ]);
    }

    public function test_le_watch_renvoie_une_url_signee_pas_le_chemin_public(): void
    {
        $user  = $this->subscriber();
        $movie = $this->localMovie();

        $res = $this->actingAs($user, 'sanctum')->getJson("/api/v1/watch/movie/{$movie->id}");

        $res->assertOk();
        $url = $res->json('data.videoUrl') ?? $res->json('videoUrl');

        $this->assertStringContainsString('/watch/local/movie/'.$movie->id, $url);
        $this->assertStringContainsString('signature=', $url);
        // L'ancien chemin public permanent ne doit PLUS être exposé.
        $this->assertStringNotContainsString('storage/uploads/test_movie.mp4', $url);
    }

    public function test_la_route_signee_sert_la_video(): void
    {
        $user  = $this->subscriber();
        $movie = $this->localMovie();

        $url = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/watch/movie/{$movie->id}")
            ->json('data.videoUrl');

        // L'URL signée (sans en-tête d'auth : la signature suffit) sert le fichier.
        $this->get($url)->assertOk();
    }

    public function test_acces_sans_signature_est_refuse(): void
    {
        $movie = $this->localMovie();

        $this->get("/api/v1/watch/local/movie/{$movie->id}")->assertForbidden();
    }
}
