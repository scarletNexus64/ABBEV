<?php

namespace App\Models\Concerns;

use App\Support\Workspace;
use Illuminate\Database\Eloquent\Builder;

/**
 * Modèle enfant d'un modèle cloisonné (candidature → annonce, leçon → cours…).
 * Il hérite du cloisonnement de son parent : tant qu'un espace est actif, seules
 * les lignes dont le parent appartient à cet espace sont visibles.
 *
 * Le modèle déclare la relation parente via workspaceParent().
 */
trait BelongsToWorkspaceThrough
{
    public static function bootBelongsToWorkspaceThrough(): void
    {
        static::addGlobalScope('workspace', function (Builder $query) {
            if (Workspace::id() !== null) {
                $query->whereHas(static::workspaceParent());
            }
        });
    }

    /** Nom de la relation BelongsTo vers le parent cloisonné. */
    abstract public static function workspaceParent(): string;
}
