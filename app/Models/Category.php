<?php

namespace App\Models;

use App\Concerns\HasObfuscatedRouteKey;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasTranslations;

class Category extends Model
{
    use HasTranslations;

    /** Champs exposés à l'app et traduits via la table `translations`. */
    public array $translatable = ['name', 'description'];

    use HasObfuscatedRouteKey;

    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    public function media()
    {
        return $this->hasMany(Media::class);
    }
}
