<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Enregistre la version ANGLAISE saisie dans le bloc « Version anglaise »
 * des formulaires (champs `en[champ]`). Un champ vidé supprime la
 * traduction : l'app retombe alors sur le français.
 */
trait SavesTranslations
{
    /** Règles de validation des champs `en.*`. */
    protected function translationRules(array $fields): array
    {
        $rules = [];
        foreach ($fields as $field) {
            $rules["en.{$field}"] = 'nullable|string|max:10000';
        }

        return $rules;
    }

    protected function saveEnglish(Model $model, Request $request, array $fields): void
    {
        foreach ($fields as $field) {
            $value = trim((string) $request->input("en.{$field}", ''));

            if ($value === '') {
                $model->translations()->where('locale', 'en')->where('field', $field)->delete();
                continue;
            }

            $model->setTranslation($field, 'en', $value);
        }
    }
}
