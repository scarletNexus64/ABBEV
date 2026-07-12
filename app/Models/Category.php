<?php

namespace App\Models;

use App\Concerns\HasObfuscatedRouteKey;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
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
