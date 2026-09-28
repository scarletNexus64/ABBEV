<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesUploads;
use App\Http\Controllers\Admin\Concerns\SavesTranslations;
use App\Http\Controllers\Controller;
use App\Models\AwardCategory;
use App\Models\AwardEdition;
use App\Models\AwardNominee;
use App\Models\Media;
use App\Models\Talent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Prix d'une édition et leurs nommés. */
class AwardCategoryController extends Controller
{
    use HandlesUploads, SavesTranslations;

    public function store(Request $request, AwardEdition $edition)
    {
        $data = $this->validated($request);

        $category = $edition->categories()->create($data + [
            'slug' => $this->uniqueSlug($edition, $data['name'] . '-' . $data['scope']),
            // Hors relation : son ORDER BY est refusé par PostgreSQL dans un agrégat.
            'sort_order' => (int) AwardCategory::where('award_edition_id', $edition->id)->max('sort_order') + 10,
        ]);
        $this->saveEnglish($category, $request, ['name', 'description']);

        return redirect()->route('awards.show', [$edition, 'tab' => 'prix'])
            ->withFragment('prix-' . $category->id)
            ->with('success', "Prix « {$category->name} » ajouté.");
    }

    public function update(Request $request, AwardCategory $category)
    {
        $category->update($this->validated($request));
        $this->saveEnglish($category, $request, ['name', 'description']);

        return redirect()->route('awards.show', [$category->award_edition_id, 'tab' => 'prix'])
            ->withFragment('prix-' . $category->id)
            ->with('success', "Prix « {$category->name} » enregistré.");
    }

    public function destroy(AwardCategory $category)
    {
        $editionId = $category->award_edition_id;
        $name = $category->name;
        $category->translations()->delete();
        $category->delete();

        return redirect()->route('awards.show', [$editionId, 'tab' => 'prix'])
            ->with('success', "Prix « {$name} » supprimé, avec ses nommés et leurs votes.");
    }

    /**
     * Ajoute un nommé : un talent de l'annuaire, une œuvre du catalogue, ou
     * une entrée libre. Nom et visuel sont repris de la source quand ils ne
     * sont pas saisis.
     */
    public function storeNominee(Request $request, AwardCategory $category)
    {
        $data = $request->validate([
            'source' => ['required', Rule::in(['talent', 'media', 'free'])],
            'talent_id' => 'nullable|required_if:source,talent|integer|exists:talents,id',
            'media_id' => 'nullable|required_if:source,media|integer|exists:media,id',
            'name' => 'nullable|required_if:source,free|string|max:160',
            'subtitle' => 'nullable|string|max:190',
            'photo' => 'nullable|image|max:4096',
        ]);

        $talent = $data['source'] === 'talent' ? Talent::find($data['talent_id']) : null;
        $media = $data['source'] === 'media' ? Media::find($data['media_id']) : null;

        $category->nominees()->create([
            'talent_id' => $talent?->id,
            'media_id' => $media?->id,
            'name' => trim((string) ($data['name'] ?? '')) ?: ($talent?->displayName() ?? $media?->title ?? ''),
            'subtitle' => $data['subtitle'] ?? ($media?->release_year ? (string) $media->release_year : null),
            'photo_path' => $request->hasFile('photo') ? $request->file('photo')->store('awards/nominees', 'public') : null,
            'sort_order' => (int) AwardNominee::where('award_category_id', $category->id)->max('sort_order') + 1,
        ]);

        return redirect()->route('awards.show', [$category->award_edition_id, 'tab' => 'prix'])
            ->withFragment('prix-' . $category->id)
            ->with('success', 'Nommé ajouté à « ' . $category->name . ' ».');
    }

    public function destroyNominee(AwardNominee $nominee)
    {
        $category = $nominee->category;
        $this->forgetPublic($nominee->photo_path);
        $nominee->delete();

        return redirect()->route('awards.show', [$category->award_edition_id, 'tab' => 'prix'])
            ->withFragment('prix-' . $category->id)
            ->with('success', 'Nommé retiré (et ses votes).');
    }

    /** Désigne (ou retire) un lauréat — choix du jury, prioritaire sur le vote. */
    public function toggleWinner(AwardNominee $nominee)
    {
        $nominee->update(['is_winner' => ! $nominee->is_winner]);

        return back()->withFragment('resultats-' . $nominee->award_category_id)
            ->with('success', $nominee->is_winner ? "{$nominee->name} est désigné(e) lauréat(e)." : 'Lauréat retiré.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:120',
            'scope' => ['required', Rule::in(array_keys(AwardCategory::SCOPES))],
            'nominee_type' => ['required', Rule::in(array_keys(AwardCategory::NOMINEE_TYPES))],
            'description' => 'nullable|string|max:190',
        ] + $this->translationRules(['name', 'description']));
    }

    private function uniqueSlug(AwardEdition $edition, string $source): string
    {
        $base = Str::slug($source) ?: 'prix';
        $slug = $base;
        $n = 1;
        while ($edition->categories()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . (++$n);
        }

        return $slug;
    }
}
