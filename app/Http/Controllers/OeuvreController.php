<?php

namespace App\Http\Controllers;

use App\Models\Oeuvre;
use App\Models\Rubrique;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class OeuvreController extends Controller
{
    public function index()
    {
        $rubrique = Rubrique::where('slug', 'oeuvre-adaptable')->firstOrFail();

        $oeuvres = Oeuvre::where('rubrique_id', $rubrique->id)
            ->orderBy('sort_order')
            ->paginate(20);

        $stats = [
            'total'     => Oeuvre::where('rubrique_id', $rubrique->id)->count(),
            'active'    => Oeuvre::where('rubrique_id', $rubrique->id)->where('is_active', true)->count(),
            'inactive'  => Oeuvre::where('rubrique_id', $rubrique->id)->where('is_active', false)->count(),
        ];

        return view('oeuvres.index', compact('oeuvres', 'stats'));
    }

    public function create()
    {
        return view('oeuvres.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'author'      => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'cover'       => 'nullable|image|max:2048',
            'file'        => 'required|mimes:pdf|max:20480',
            'is_active'   => 'boolean',
        ]);

        $rubrique = Rubrique::where('slug', 'oeuvre-adaptable')->firstOrFail();

        $filePath = $request->file('file')->store('oeuvres', 'local');

        $coverPath = null;
        if ($request->hasFile('cover')) {
            $coverPath = $request->file('cover')->store('oeuvres/covers', 'public');
        }

        Oeuvre::create([
            'rubrique_id'  => $rubrique->id,
            'title'        => $validated['title'],
            'slug'         => Str::slug($validated['title']),
            'author'       => $validated['author'],
            'description'  => $validated['description'] ?? null,
            'pages'        => $this->countPdfPages($request->file('file')),
            'cover_path'   => $coverPath,
            'file_path'    => $filePath,
            'is_active'    => $request->boolean('is_active', true),
            'sort_order'   => Oeuvre::where('rubrique_id', $rubrique->id)->max('sort_order') + 1,
            'published_at' => $request->boolean('is_active', true) ? now() : null,
        ]);

        return redirect()->route('oeuvres.index')
            ->with('success', 'Oeuvre ajoutee avec succes.');
    }

    public function edit(Oeuvre $oeuvre)
    {
        return view('oeuvres.edit', compact('oeuvre'));
    }

    public function update(Request $request, Oeuvre $oeuvre)
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'author'      => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'cover'       => 'nullable|image|max:2048',
            'file'        => 'nullable|mimes:pdf|max:20480',
            'is_active'   => 'boolean',
        ]);

        $data = [
            'title'       => $validated['title'],
            'slug'        => Str::slug($validated['title']),
            'author'      => $validated['author'],
            'description' => $validated['description'] ?? null,
            'is_active'   => $request->boolean('is_active', true),
        ];

        if ($request->hasFile('file')) {
            if ($oeuvre->file_path) {
                Storage::disk('local')->delete($oeuvre->file_path);
            }
            $data['file_path'] = $request->file('file')->store('oeuvres', 'local');
            $data['pages'] = $this->countPdfPages($request->file('file'));
        }

        if ($request->hasFile('cover')) {
            if ($oeuvre->cover_path) {
                Storage::disk('public')->delete($oeuvre->cover_path);
            }
            $data['cover_path'] = $request->file('cover')->store('oeuvres/covers', 'public');
        }

        if ($data['is_active'] && !$oeuvre->published_at) {
            $data['published_at'] = now();
        }

        $oeuvre->update($data);

        return redirect()->route('oeuvres.index')
            ->with('success', 'Oeuvre mise a jour avec succes.');
    }

    public function destroy(Oeuvre $oeuvre)
    {
        if ($oeuvre->file_path) {
            Storage::disk('local')->delete($oeuvre->file_path);
        }
        if ($oeuvre->cover_path) {
            Storage::disk('public')->delete($oeuvre->cover_path);
        }

        $oeuvre->delete();

        return redirect()->route('oeuvres.index')
            ->with('success', 'Oeuvre supprimee avec succes.');
    }

    private function countPdfPages($file): ?int
    {
        try {
            $content = file_get_contents($file->getRealPath());
            // Simple regex count of page objects in PDF
            preg_match_all('/\/Type\s*\/Page[^s]/i', $content, $matches);
            $count = count($matches[0]);
            return $count > 0 ? $count : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
