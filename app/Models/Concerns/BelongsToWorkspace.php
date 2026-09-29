<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Support\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle appartenant à l'espace d'un producteur (colonne `producer_id` par
 * défaut, surchargeable via workspaceColumn()).
 *
 * Tant qu'un espace est actif (voir App\Support\Workspace), toutes les
 * requêtes sont limitées à cet espace et les créations y sont rattachées.
 */
trait BelongsToWorkspace
{
    public static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope('workspace', function (Builder $query) {
            if (($id = Workspace::id()) !== null) {
                $query->where($query->qualifyColumn(static::workspaceColumn()), $id);
            }
        });

        static::creating(function ($model) {
            $column = static::workspaceColumn();
            if ($model->{$column} === null && ($id = Workspace::id()) !== null) {
                $model->{$column} = $id;
            }
        });
    }

    public static function workspaceColumn(): string
    {
        return 'producer_id';
    }

    /** Producteur propriétaire (NULL = donnée de la plateforme). */
    public function workspaceOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, static::workspaceColumn());
    }
}
