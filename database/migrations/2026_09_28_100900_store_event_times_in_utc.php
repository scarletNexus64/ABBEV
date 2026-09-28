<?php

use App\Support\BusinessTime;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Horaires d'événements : les valeurs existantes ont été saisies en heure
 * locale (Douala) mais stockées telles quelles, donc lues comme de l'UTC —
 * une séance saisie à 20h s'affichait 21h sur les téléphones du Cameroun.
 *
 * On les convertit une fois en vrai UTC ; le cast BusinessDateTime fait
 * ensuite la conversion à chaque saisie et à chaque lecture.
 */
return new class extends Migration
{
    /** table => colonnes d'horaires saisies à la main. */
    private const COLUMNS = [
        'screenings' => ['starts_at', 'valid_until'],
        'award_editions' => ['voting_starts_at', 'voting_ends_at', 'ceremony_at'],
        'casting_calls' => ['deadline_at'],
        'project_calls' => ['opens_at', 'closes_at'],
    ];

    public function up(): void
    {
        $this->shift(fn (string $value) => Carbon::parse($value, BusinessTime::zone())->utc());
    }

    public function down(): void
    {
        $this->shift(fn (string $value) => Carbon::parse($value, 'UTC')->setTimezone(BusinessTime::zone()));
    }

    private function shift(callable $convert): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)->orderBy('id')->select(['id', ...$columns])
                ->chunkById(200, function ($rows) use ($table, $columns, $convert) {
                    foreach ($rows as $row) {
                        $changes = [];
                        foreach ($columns as $column) {
                            if ($row->{$column} !== null) {
                                $changes[$column] = $convert((string) $row->{$column})->format('Y-m-d H:i:s');
                            }
                        }
                        if ($changes) {
                            DB::table($table)->where('id', $row->id)->update($changes);
                        }
                    }
                });
        }
    }
};
