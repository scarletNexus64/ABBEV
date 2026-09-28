<?php

namespace Database\Seeders;

use App\Models\Media;
use App\Models\Screening;
use App\Models\User;
use App\Support\BusinessTime;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeder de démonstration : ~12 séances cinéma réservables, réparties sur
 * plusieurs cinémas et villes, à des dates à venir, avec 2-3 catégories de
 * places chacune (Standard / VIP / Loge). Idempotent : purge les séances de
 * démo (par référence de cinéma connue) avant réinsertion.
 *
 * Certaines séances pointent vers un film réel du catalogue (media_id),
 * d'autres utilisent un titre libre (films hors-catalogue / avant-premières).
 */
class ScreeningSeeder extends Seeder
{
    use WithoutModelEvents;

    /** Cinémas de démo (sert aussi de marqueur pour la purge idempotente). */
    private const CINEMAS = [
        ['Canal Olympia Bessengue', 'Bessengue, Douala'],
        ['Canal Olympia Yaoundé',   'Warda, Yaoundé'],
        ['Cinéma ABBEV Akwa',       'Boulevard de la Liberté, Douala'],
        ['Ciné Théâtre Bonanjo',    'Bonanjo, Douala'],
        ['Palais des Congrès',      'Tsinga, Yaoundé'],
    ];

    /** Films hors-catalogue (titre libre). */
    private const FREE_TITLES = [
        'Avant-première : Black Panther 3',
        'Festival du film africain',
        'Soirée Classics : Le Roi Lion',
    ];

    public function run(): void
    {
        $cinemaNames = array_column(self::CINEMAS, 0);
        Screening::whereIn('cinema_name', $cinemaNames)->delete();

        $admin   = User::where('role', 'admin')->first();
        $movies  = Media::where('type', 'movie')->inRandomOrder()->limit(9)->pluck('id', 'title')->all();
        $movieList = array_keys($movies);

        // 12 séances : 9 sur des films du catalogue + 3 titres libres.
        $plans = [];

        foreach ($movieList as $i => $title) {
            $plans[] = [
                'media_id'    => $movies[$title],
                'movie_title' => $title,
                'days_ahead'  => 2 + $i,        // étalé sur les prochains jours
                'hour'        => [14, 17, 20][$i % 3],
            ];
        }

        foreach (self::FREE_TITLES as $j => $title) {
            $plans[] = [
                'media_id'    => null,
                'movie_title' => $title,
                'days_ahead'  => 3 + $j * 4,
                'hour'        => [16, 19][$j % 2],
            ];
        }

        foreach ($plans as $k => $plan) {
            [$cinema, $location] = self::CINEMAS[$k % count(self::CINEMAS)];

            // Heure locale de la salle (16h, 19h à Douala), pas 16h UTC.
            $startsAt = BusinessTime::now()
                ->addDays($plan['days_ahead'])
                ->setTime($plan['hour'], 0);

            $screening = Screening::create([
                'media_id'    => $plan['media_id'],
                'movie_title' => $plan['movie_title'],
                'cinema_name' => $cinema,
                'location'    => $location,
                'starts_at'   => $startsAt,
                'status'      => 'published',
                'created_by'  => $admin?->id,
            ]);

            $this->seedTicketTypes($screening, $k);
        }

        $this->command?->info('✅ ' . count($plans) . ' séances cinéma de démo créées.');
    }

    /**
     * 2 ou 3 catégories de places selon la séance, prix réalistes en XAF.
     */
    private function seedTicketTypes(Screening $screening, int $index): void
    {
        $catalogs = [
            // catalogue A : 2 catégories
            [
                ['name' => 'Standard', 'price' => 3000, 'capacity' => 120],
                ['name' => 'VIP',      'price' => 6000, 'capacity' => 30],
            ],
            // catalogue B : 3 catégories
            [
                ['name' => 'Standard', 'price' => 2500, 'capacity' => 150],
                ['name' => 'VIP',      'price' => 5000, 'capacity' => 40],
                ['name' => 'Loge',     'price' => 10000, 'capacity' => 10],
            ],
            // catalogue C : 2 catégories
            [
                ['name' => 'Normal',   'price' => 2000, 'capacity' => 200],
                ['name' => 'Premium',  'price' => 4500, 'capacity' => 50],
            ],
        ];

        foreach ($catalogs[$index % count($catalogs)] as $type) {
            $screening->ticketTypes()->create([
                'name'     => $type['name'],
                'price'    => $type['price'],
                'capacity' => $type['capacity'],
                'currency' => 'XAF',
            ]);
        }
    }
}
