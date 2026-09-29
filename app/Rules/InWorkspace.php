<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Équivalent de `exists:` qui respecte le cloisonnement producteur : la
 * recherche passe par Eloquent, donc par le scope `workspace`. Un producteur
 * ne peut pas rattacher un talent, un agent ou un film d'un autre espace.
 */
class InWorkspace implements ValidationRule
{
    /** @param class-string<\Illuminate\Database\Eloquent\Model> $model */
    public function __construct(private string $model)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->model::query()->whereKey($value)->exists()) {
            $fail('La valeur sélectionnée pour :attribute est invalide.');
        }
    }
}
