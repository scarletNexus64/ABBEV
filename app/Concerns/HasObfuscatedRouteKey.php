<?php

namespace App\Concerns;

use App\Support\Hashid;
use Illuminate\Database\Eloquent\Model;

/**
 * Fait apparaître les modèles avec un identifiant ENCODÉ dans les URLs générées
 * (dashboard web) au lieu de l'id séquentiel brut. La résolution accepte :
 *   - un id encodé (préfixe « k… ») → décodé  → utilisé côté web ;
 *   - un id brut décimal            → tel quel → conservé pour l'API/mobile ;
 *   - un champ explicite ({model:slug}) → délégué au comportement par défaut.
 *
 * Le préfixe distingue sans ambiguïté encodé vs brut : l'API mobile qui envoie
 * des ids bruts n'est donc pas impactée.
 *
 * @mixin Model
 */
trait HasObfuscatedRouteKey
{
    /** Valeur utilisée dans les URLs générées par route()/link (id encodé). */
    public function getRouteKey(): string
    {
        return Hashid::encode((int) $this->getKey());
    }

    public function resolveRouteBinding($value, $field = null)
    {
        // Liaison explicite par champ (ex: {model:slug}) → comportement natif.
        if ($field !== null) {
            return $this->where($field, $value)->first();
        }

        $value = (string) $value;

        // Id encodé (web).
        $decoded = Hashid::decode($value);
        if ($decoded !== null) {
            return $this->where($this->getKeyName(), $decoded)->first();
        }

        // Id brut décimal (API/mobile).
        if (ctype_digit($value)) {
            return $this->where($this->getKeyName(), (int) $value)->first();
        }

        return null;
    }
}
