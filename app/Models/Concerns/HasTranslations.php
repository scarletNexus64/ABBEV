<?php

namespace App\Models\Concerns;

use App\Models\Translation;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\App;

/**
 * Rend traduisibles les champs texte d'un modèle.
 *
 * Usage :
 *
 *     class Category extends Model
 *     {
 *         use HasTranslations;
 *         public array $translatable = ['name', 'description'];
 *     }
 *
 *     $category->t('name');   // langue courante, repli sur la valeur d'origine
 *
 * Principe de repli : la table métier contient la valeur **française** (source
 * de vérité historique). La table `translations` ne contient QUE les autres
 * langues. Conséquence : un contenu jamais traduit continue de s'afficher en
 * français au lieu de disparaître — jamais de champ vide à l'écran.
 */
trait HasTranslations
{
    /**
     * Charge systématiquement les traductions avec le modèle.
     *
     * Sans ça, chaque `t()` déclenche sa propre requête : l'écran d'accueil
     * (149 médias × 2 champs) partirait en centaines de requêtes. Avec, c'est
     * une requête supplémentaire par lot, quel que soit le nombre de lignes.
     *
     * Le surcoût en `fr` est nul : `t()` court-circuite avant toute lecture
     * de la relation quand la locale est celle de repli.
     */
    public static function bootHasTranslations(): void
    {
        static::addGlobalScope('withTranslations', function ($query) {
            if (App::getLocale() !== config('app.fallback_locale')) {
                $query->with('translations');
            }
        });
    }

    public function translations(): MorphMany
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    /**
     * Valeur traduite de [$field] pour la locale courante (ou [$locale]).
     *
     * Ordre : traduction demandée → valeur d'origine du modèle.
     */
    public function t(string $field, ?string $locale = null): mixed
    {
        $locale ??= App::getLocale();
        $original = $this->getAttribute($field);

        // La locale par défaut EST la valeur stockée sur le modèle : inutile
        // d'interroger la table de traduction.
        if ($locale === config('app.fallback_locale')) {
            return $original;
        }

        if (! in_array($field, $this->translatableFields(), true)) {
            return $original;
        }

        $translated = $this->translationFor($field, $locale);

        // `null` ET chaîne vide retombent sur l'original : une traduction vide
        // afficherait un libellé blanc, pire que du français.
        return ($translated === null || $translated === '') ? $original : $translated;
    }

    /**
     * Variante pour les champs stockés en JSON (ex. `features` d'un plan).
     * Renvoie toujours un tableau.
     */
    public function tArray(string $field, ?string $locale = null): array
    {
        $value = $this->t($field, $locale);

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /** Écrit (ou met à jour) une traduction. Utilisé par les seeders et l'admin. */
    public function setTranslation(string $field, string $locale, ?string $value): void
    {
        $this->translations()->updateOrCreate(
            ['locale' => $locale, 'field' => $field],
            ['value' => $value],
        );
    }

    /** Champs déclarés traduisibles sur le modèle. */
    public function translatableFields(): array
    {
        return property_exists($this, 'translatable') && is_array($this->translatable)
            ? $this->translatable
            : [];
    }

    /**
     * Lit une traduction en privilégiant la relation déjà chargée.
     *
     * Important pour les listes : avec `->with('translations')`, le catalogue
     * (149 médias) coûte 1 requête au lieu de 149 — sans ça, on retomberait
     * sur un N+1 sur l'écran d'accueil.
     */
    private function translationFor(string $field, string $locale): ?string
    {
        if ($this->relationLoaded('translations')) {
            $hit = $this->translations
                ->firstWhere(fn ($t) => $t->field === $field && $t->locale === $locale);

            return $hit?->value;
        }

        return $this->translations()
            ->where('field', $field)
            ->where('locale', $locale)
            ->value('value');
    }
}
