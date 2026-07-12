<?php

namespace App\Models;

use App\Concerns\HasObfuscatedRouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Screening extends Model
{
    use HasFactory, HasObfuscatedRouteKey;

    protected $fillable = [
        'media_id',
        'movie_title',
        'cinema_name',
        'location',
        'starts_at',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
        ];
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
