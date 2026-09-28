<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserSubscription;

/**
 * Règle d'accès par forfait, partagée par les rubriques et les cours.
 *
 * Les tiers vont du moins au plus permissif : un abonnement « premium »
 * ouvre aussi ce qui est réservé au « standard » et au « classique ».
 * Un contenu sans tier requis est ouvert à tout compte (ou à tout visiteur,
 * selon ce que l'appelant exige par ailleurs).
 */
class TierAccess
{
    public const TIERS = ['classique', 'standard', 'premium'];

    public const LABELS = [
        'classique' => 'Classique',
        'standard' => 'Standard',
        'premium' => 'Premium',
    ];

    /** Tier de l'abonnement ACTIF de l'utilisateur, ou null. */
    public static function tierOf(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        $subscription = UserSubscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->with('plan')
            ->orderByDesc('expires_at')
            ->first();

        return $subscription?->plan?->tier;
    }

    /** L'utilisateur atteint-il le tier requis ? */
    public static function allows(?User $user, ?string $requiredTier): bool
    {
        if ($requiredTier === null || $requiredTier === '') {
            return true;
        }

        $userTier = self::tierOf($user);
        if (! $userTier) {
            return false;
        }

        return array_search($userTier, self::TIERS, true)
            >= array_search($requiredTier, self::TIERS, true);
    }
}
