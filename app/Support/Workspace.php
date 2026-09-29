<?php

namespace App\Support;

/**
 * Espace de travail courant du panel.
 *
 * Quand un producteur (ou un membre de son équipe) navigue dans le panel, le
 * middleware ScopeToWorkspace fixe ici l'id du producteur propriétaire : les
 * modèles cloisonnés (trait BelongsToWorkspace) ne renvoient alors que ses
 * données, y compris pour le route model binding. NULL = aucune restriction
 * (admin, API mobile, console, jobs).
 */
final class Workspace
{
    private static ?int $producerId = null;

    public static function id(): ?int
    {
        return self::$producerId;
    }

    public static function restrictTo(?int $producerId): void
    {
        self::$producerId = $producerId;
    }

    /** Exécute $callback sans cloisonnement (contrôles d'unicité globale…). */
    public static function unrestricted(callable $callback): mixed
    {
        $previous = self::$producerId;
        self::$producerId = null;

        try {
            return $callback();
        } finally {
            self::$producerId = $previous;
        }
    }
}
