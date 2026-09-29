<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Casts\BusinessDateTime;
use App\Concerns\HasObfuscatedRouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Offre de billetterie : une SÉANCE datée dans une salle, ou un CODE CINÉMA
 * prépayé, valable jusqu'à `valid_until` sans séance fixe (cat.md :
 * « Réservation ticket — salle cinéma, achat code »).
 */
class Screening extends Model
{
    use BelongsToWorkspace, HasFactory, HasObfuscatedRouteKey;

    public const KINDS = [
        'seance' => 'Séance en salle',
        'code' => 'Code cinéma',
    ];

    protected $fillable = [
        'media_id',
        'kind',
        'movie_title',
        'cinema_name',
        'location',
        'country_code',
        'starts_at',
        'valid_until',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            // Heures locales saisies dans l'admin, stockées en UTC.
            'starts_at' => BusinessDateTime::class,
            'valid_until' => BusinessDateTime::class,
        ];
    }

    public function isCode(): bool
    {
        return $this->kind === 'code';
    }

    /**
     * Offres encore en vente : séances à venir, codes non expirés. Pour un
     * code, `starts_at` est la date d'ouverture de la vente.
     */
    public function scopeOnSale($query)
    {
        return $query->where('status', 'published')->where(function ($q) {
            $q->where(fn ($s) => $s->where('kind', 'seance')->where('starts_at', '>=', now()))
                ->orWhere(fn ($c) => $c->where('kind', 'code')
                    ->where('starts_at', '<=', now())
                    ->where(fn ($v) => $v->whereNull('valid_until')->orWhere('valid_until', '>', now())));
        });
    }

    public function media()
    {
        return $this->belongsTo(Media::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function ticketTypes()
    {
        return $this->hasMany(TicketType::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
    }

    /**
     * Séances dont la notification doit partir maintenant :
     * planifiées, dont l'heure d'envoi est atteinte, et pas encore notifiées.
     */
    public function scopeDueForNotification($query)
    {
        return $query->where('status', 'scheduled')
            ->whereNull('notified_at')
            ->whereNotNull('notify_at')
            ->where('notify_at', '<=', now());
    }
}
