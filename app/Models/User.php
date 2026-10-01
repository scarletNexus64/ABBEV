<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Concerns\HasObfuscatedRouteKey;
use App\Notifications\AdminResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasObfuscatedRouteKey, Notifiable;

    /** Fichiers personnels à effacer une fois la suppression du compte confirmée. */
    private array $personalFilesToForget = [];

    protected static function booted(): void
    {
        // Suppression d'un compte (depuis l'app ou l'admin) : candidatures et
        // propositions partent en cascade avec lui, mais pas les fichiers
        // qu'elles référencent. Photos de candidats et scénarios sont des
        // données personnelles : on les efface avec le compte — après la
        // suppression en base, pour ne rien perdre si elle échoue.
        static::deleting(function (User $user) {
            // Producteur supprimé : son équipe perd l'accès au panel mais les
            // comptes restent (certains sont des abonnés de l'app).
            if ($user->isProducerOwner()) {
                $user->teamMembers()->get()->each->leaveTeam();
            }

            $user->personalFilesToForget = CastingApplication::where('user_id', $user->id)
                ->whereNotNull('photo_path')->pluck('photo_path')
                ->merge(ProjectSubmission::where('user_id', $user->id)->whereNotNull('file_path')->pluck('file_path'))
                ->all();
        });

        static::deleted(function (User $user) {
            if ($user->personalFilesToForget !== []) {
                Storage::disk('local')->delete($user->personalFilesToForget);
            }
        });
    }

    /**
     * Email de réinitialisation de mot de passe : version française brandée
     * ABBEV, pointant vers le dashboard web (admin.password.reset).
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new AdminResetPasswordNotification($token));
    }

    /**
     * Modules d'un espace producteur. Le producteur les a tous ; il en délègue
     * une sélection à chaque membre de son équipe (colonne `permissions`).
     */
    public const MODULES = [
        'contents'   => ['label' => 'Films, séries & upload vidéos', 'icon' => 'film'],
        'oeuvres'    => ['label' => 'Œuvres adaptables', 'icon' => 'book-open'],
        'moderation' => ['label' => 'Modération des contenus', 'icon' => 'clipboard-check'],
        'audience'   => ['label' => 'Audience des contenus', 'icon' => 'chart-line'],
        'talents'    => ['label' => 'Talents, agents & casting', 'icon' => 'id-badge'],
        'awards'     => ['label' => 'Lions Head Awards', 'icon' => 'trophy'],
        'courses'    => ['label' => 'Formation (cours de cinéma)', 'icon' => 'graduation-cap'],
        'calls'      => ['label' => 'Appels à projets', 'icon' => 'lightbulb'],
        'ticketing'  => ['label' => 'Billetterie (séances & codes cinéma)', 'icon' => 'ticket'],
        'tickets'    => ['label' => 'Contrôle des billets', 'icon' => 'qrcode'],
    ];

    /**
     * Rôles disponibles : 'admin' | 'producer' | 'user'.
     * Un rôle `producer` avec `producer_id` renseigné est un membre de
     * l'équipe de ce producteur.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /** Producteur ou membre de son équipe : limité à l'espace du producteur. */
    public function isProducer(): bool
    {
        return $this->role === 'producer';
    }

    /** Titulaire d'un espace producteur (tous les modules + gestion de l'équipe). */
    public function isProducerOwner(): bool
    {
        return $this->isProducer() && $this->producer_id === null;
    }

    /** Membre invité par un producteur (modules limités à ses permissions). */
    public function isTeamMember(): bool
    {
        return $this->isProducer() && $this->producer_id !== null;
    }

    /** Membre du panel (admin, producteur ou équipe) : a accès au dashboard. */
    public function isStaff(): bool
    {
        return in_array($this->role, ['admin', 'producer'], true);
    }

    /** Id du producteur dont on gère l'espace (NULL pour l'admin et les abonnés). */
    public function workspaceId(): ?int
    {
        if (! $this->isProducer()) {
            return null;
        }

        return $this->producer_id ?? $this->id;
    }

    /** Accès à un module du panel (voir MODULES). */
    public function canAccessModule(string $module): bool
    {
        if ($this->isAdmin() || $this->isProducerOwner()) {
            return true;
        }

        return $this->isTeamMember() && in_array($module, $this->permissions ?? [], true);
    }

    /** Producteur dont ce compte est membre de l'équipe. */
    public function producer()
    {
        return $this->belongsTo(User::class, 'producer_id');
    }

    /** Équipe invitée par ce producteur. */
    public function teamMembers()
    {
        return $this->hasMany(User::class, 'producer_id');
    }

    /** Périodes d'accès à l'espace (payées ou offertes), pour le producteur titulaire. */
    public function producerSubscriptions()
    {
        return $this->hasMany(ProducerSubscription::class, 'producer_id');
    }

    /**
     * Espace producteur verrouillé : le pack producteur est actif et le
     * titulaire n'a aucune période payée (ou offerte) en cours. Tout l'espace
     * est alors fermé, équipe comprise, jusqu'au paiement.
     */
    public function isWorkspaceLocked(): bool
    {
        if (! $this->isProducer()) {
            return false;
        }

        return ProducerPlan::paymentRequired()
            && ! ProducerSubscription::coversNow($this->workspaceId());
    }

    /** Retire le compte de son équipe : il redevient un simple abonné de l'app. */
    public function leaveTeam(): void
    {
        $this->forceFill(['role' => 'user', 'producer_id' => null, 'permissions' => null])->save();
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
            'permissions' => 'array',
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
