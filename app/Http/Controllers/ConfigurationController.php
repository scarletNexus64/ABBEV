<?php

namespace App\Http\Controllers;

use App\Models\Configuration;
use Illuminate\Http\Request;

class ConfigurationController extends Controller
{
    public function index()
    {
        $configurations = Configuration::all()->groupBy('group');

        return view('configuration.index', compact('configurations'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'configs' => 'required|array',
            'configs.*' => 'nullable|string',
        ]);

        foreach ($validated['configs'] as $key => $value) {
            Configuration::where('key', $key)->update(['value' => $value]);
        }

        return back()->with('success', 'Configuration mise à jour avec succès.');
    }

    /**
     * Sauvegarder uniquement les paramètres d'un groupe (onglet),
     * sans toucher aux autres catégories de configuration.
     */
    public function updateGroup(Request $request, string $group)
    {
        $validated = $request->validate([
            'configs' => 'required|array',
            'configs.*' => 'nullable|string',
        ]);

        // On ne met à jour que les clés appartenant réellement au
        // groupe ciblé (sécurité : ignore toute clé hors groupe).
        $allowedKeys = Configuration::where('group', $group)->pluck('key')->all();

        $updated = 0;
        foreach ($validated['configs'] as $key => $value) {
            if (in_array($key, $allowedKeys, true)) {
                Configuration::where('key', $key)->update(['value' => $value]);
                $updated++;
            }
        }

        return back()
            ->with('success', "Configuration « {$group} » mise à jour ({$updated} paramètre(s)).")
            ->with('active_tab', $group);
    }
}
