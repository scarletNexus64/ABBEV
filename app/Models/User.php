<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Concerns\HasObfuscatedRouteKey;
use App\Notifications\AdminResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasObfuscatedRouteKey, Notifiable;

    /**
     * Email de réinitialisation de mot de passe : version française brandée
     * ABBEV, pointant vers le dashboard web (admin.password.reset).
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new AdminResetPasswordNotification($token));
    }

    /**
     * Rôles disponibles : 'admin' | 'producer' | 'assistant' | 'user'.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /** Producteur : gère uniquement ses propres contenus. */
    public function isProducer(): bool
    {
        return $this->role === 'producer';
    }

    /**
     * Assistant (direction artistique) : valide/rejette les contenus et leur
     * attribue catégorie + tier. Accès au panel de modération, mais pas à la
     * gestion (users, forfaits, config…) réservée à l'admin.
     */
    public function isAssistant(): bool
    {
        return $this->role === 'assistant';
    }

    /** Membre du panel (admin, producteur ou assistant) : a accès au dashboard. */
    public function isStaff(): bool
    {
        return in_array($this->role, ['admin', 'producer', 'assistant'], true);
    }

    /** Contenus (films/séries) dont cet utilisateur est propriétaire. */
    public function media()
    {
        return $this->hasMany(Media::class);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'phone_verified_at',
        'avatar_path',
        'password',
        'role',
        'is_active',
        'country_code',
        'currency_code',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions()
    {
        return $this->hasMany(UserSubscription::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    /**
     * L'utilisateur a-t-il un abonnement actif lui donnant accès à la
     * lecture des contenus ?
     *
     * Conditions : une UserSubscription `active`, non expirée, liée à un
     * plan PAYANT (price > 0). Le plan Gratuit ne débloque pas le visionnage.
     */
    public function hasActiveSubscription(): bool
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->whereHas('plan', fn ($q) => $q->where('price', '>', 0))
            ->exists();
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * "Ma liste" : médias (films + séries) ajoutés par l'utilisateur.
     */
    public function listItems()
    {
        return $this->hasMany(UserListItem::class);
    }
}
