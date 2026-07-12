<?php

namespace App\Policies;

use App\Models\User;

/**
 * Encadre la modification des comptes utilisateur depuis le dashboard.
 *
 * Décision produit : pas de rôle « gestionnaire » dédié — seuls les admins
 * peuvent modifier un compte, sur N'IMPORTE quelle cible (aucune restriction
 * de rôle cible). On ajoute uniquement des garde-fous de sécurité pour éviter
 * qu'un admin ne se verrouille lui-même (auto-suspension / auto-suppression).
 */
class UserPolicy
{
    /** Créer un compte utilisateur depuis le dashboard. */
    public function create(User $actor): bool
    {
        return $actor->isAdmin();
    }

    /** Éditer les infos de base (nom, email). */
    public function update(User $actor, User $target): bool
    {
        return $actor->isAdmin();
    }

    /** Activer / suspendre un compte. Interdit sur soi-même (anti-lockout). */
    public function updateStatus(User $actor, User $target): bool
    {
        return $actor->isAdmin() && $actor->id !== $target->id;
    }

    /** Réinitialiser (régénérer) le mot de passe d'un compte. */
    public function resetPassword(User $actor, User $target): bool
    {
        return $actor->isAdmin();
    }

    /** Gérer l'abonnement d'un compte (prolonger / annuler). */
    public function manageSubscription(User $actor, User $target): bool
    {
        return $actor->isAdmin();
    }

    /** Supprimer un compte. Interdit sur soi-même. */
    public function delete(User $actor, User $target): bool
    {
        return $actor->isAdmin() && $actor->id !== $target->id;
    }
}
