<?php

namespace Database\Seeders\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Visuels et documents de DÉMONSTRATION (portraits monogrammes, bannières,
 * PDF de cours), générés localement avec GD pour ne dépendre d'aucune
 * banque d'images.
 *
 * Réservé au `DemoEcosystemSeeder` : en production, chaque visuel est
 * téléversé depuis l'admin.
 */
class DemoAssets
{
    /** Palettes [haut, bas, accent] en RGB. */
    public const PALETTES = [
        'gold' => [[58, 38, 10], [10, 8, 6], [245, 190, 70]],
        'indigo' => [[42, 44, 110], [8, 8, 22], [146, 148, 245]],
        'teal' => [[6, 72, 84], [4, 12, 16], [34, 211, 238]],
        'crimson' => [[96, 16, 32], [14, 4, 8], [248, 113, 113]],
        'emerald' => [[8, 78, 54], [4, 14, 10], [52, 211, 153]],
        'violet' => [[70, 26, 98], [10, 4, 16], [196, 148, 250]],
        'amber' => [[110, 58, 8], [16, 8, 2], [251, 191, 36]],
        'slate' => [[40, 48, 64], [8, 10, 14], [148, 163, 184]],
    ];

    private ?string $font;

    public function __construct()
    {
        $candidates = [
            '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
        ];
        $this->font = collect($candidates)->first(fn ($f) => is_file($f));
    }

    /** Portrait 4:5 avec monogramme — `demo/talents/{slug}.jpg`. */
    public function portrait(string $name, string $slug, string $palette, string $folder = 'talents'): string
    {
        $path = "demo/{$folder}/{$slug}.jpg";
        [$top, $bottom, $accent] = self::PALETTES[$palette] ?? self::PALETTES['indigo'];

        $w = 600;
        $h = 750;
        $img = imagecreatetruecolor($w, $h);
        $this->verticalGradient($img, $w, $h, $top, $bottom);

        // Halo derrière le monogramme.
        for ($r = 260; $r > 0; $r -= 4) {
            $alpha = (int) (127 - (260 - $r) / 260 * 22);
            $color = imagecolorallocatealpha($img, $accent[0], $accent[1], $accent[2], max(0, min(127, $alpha)));
            imagefilledellipse($img, (int) ($w / 2), (int) ($h * 0.42), $r * 2, $r * 2, $color);
        }

        $initials = collect(preg_split('/\s+/', Str::ascii($name)))
            ->filter()
            ->map(fn ($p) => Str::upper(Str::substr($p, 0, 1)))
            ->take(2)
            ->implode('');

        $this->centeredText($img, $initials, 150, $w, (int) ($h * 0.42), [255, 255, 255]);

        // Liseré bas aux couleurs de la palette.
        $line = imagecolorallocate($img, $accent[0], $accent[1], $accent[2]);
        imagefilledrectangle($img, 0, $h - 10, $w, $h, $line);

        return $this->saveJpeg($img, $path);
    }

    /**
     * Bannière 16:9 — `demo/{folder}/{slug}.jpg`.
     *
     * SANS texte lisible : l'app pose déjà le titre sur le visuel, un titre
     * incrusté ferait doublon. Seule l'initiale du titre apparaît, en
     * filigrane géant, pour que deux bannières de même palette se
     * distinguent au premier coup d'œil.
     */
    public function cover(string $title, string $slug, string $folder, string $palette): string
    {
        $path = "demo/{$folder}/{$slug}.jpg";
        [$top, $bottom, $accent] = self::PALETTES[$palette] ?? self::PALETTES['indigo'];

        $w = 1280;
        $h = 720;
        $img = imagecreatetruecolor($w, $h);
        $this->diagonalGradient($img, $w, $h, $top, $bottom);

        // Motif : grands cercles concentriques décentrés, discrets.
        imagesetthickness($img, 2);
        for ($i = 0; $i < 7; $i++) {
            $c = imagecolorallocatealpha($img, $accent[0], $accent[1], $accent[2], 100 + $i * 3);
            imageellipse($img, (int) ($w * 0.82), (int) ($h * 0.30), 220 + $i * 150, 220 + $i * 150, $c);
        }

        // Bande « pellicule » en haut.
        $strip = imagecolorallocatealpha($img, 0, 0, 0, 60);
        imagefilledrectangle($img, 0, 0, $w, 34, $strip);
        $hole = imagecolorallocatealpha($img, 255, 255, 255, 95);
        for ($x = 18; $x < $w; $x += 46) {
            imagefilledrectangle($img, $x, 10, $x + 22, 24, $hole);
        }

        // Initiale du titre (hors articles) en filigrane, à droite.
        $word = collect(preg_split('/[\s«»"\'’—-]+/u', Str::ascii($title)))
            ->filter(fn ($w) => strlen($w) > 2 && ! in_array(Str::lower($w), ['les', 'des', 'the', 'une', 'pour'], true))
            ->first() ?? $title;
        if ($this->font) {
            $glyph = imagecolorallocatealpha($img, $accent[0], $accent[1], $accent[2], 104);
            imagettftext($img, 520, 0, (int) ($w * 0.58), (int) ($h * 0.92), $glyph, $this->font, Str::upper(Str::substr($word, 0, 1)));
        }

        // Voile bas : l'app y pose son titre, il doit rester lisible.
        for ($y = (int) ($h * 0.55); $y < $h; $y++) {
            $t = ($y - $h * 0.55) / ($h * 0.45);
            $veil = imagecolorallocatealpha($img, 0, 0, 0, (int) (127 - 70 * $t));
            imageline($img, 0, $y, $w, $y, $veil);
        }

        return $this->saveJpeg($img, $path);
    }

    /**
     * PDF minimal (texte seul, Helvetica, encodage WinAnsi), écrit à
     * `$path` sur le disque privé.
     *
     * @param  list<array{0: string, 1: string}>  $sections  [intertitre, paragraphe]
     */
    public function pdf(string $path, string $title, array $sections, string $footer = 'ABBEV'): array
    {

        $lines = [['title', $title], ['gap', '']];
        foreach ($sections as [$heading, $body]) {
            $lines[] = ['h', $heading];
            foreach ($this->wrap($body, 88) as $l) {
                $lines[] = ['p', $l];
            }
            $lines[] = ['gap', ''];
        }

        // Pagination : ~44 lignes par page A4.
        $pages = array_chunk($lines, 44);
        $objects = [];
        $kids = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $next = 5;
        foreach ($pages as $index => $pageLines) {
            $stream = "BT\n";
            $y = 790;
            foreach ($pageLines as [$kind, $text]) {
                [$font, $size, $step] = match ($kind) {
                    'title' => ['F2', 20, 30],
                    'h' => ['F2', 13, 22],
                    'gap' => ['F1', 11, 10],
                    default => ['F1', 11, 16],
                };
                if ($kind !== 'gap') {
                    $stream .= sprintf("/%s %d Tf 1 0 0 1 56 %d Tm (%s) Tj\n", $font, $size, $y, $this->pdfEscape($text));
                }
                $y -= $step;
            }
            $stream .= sprintf("/F1 9 Tf 1 0 0 1 56 40 Tm (%s) Tj\n", $this->pdfEscape($footer . ' · page ' . ($index + 1) . '/' . count($pages)));
            $stream .= "ET\n";

            $contentId = $next++;
            $pageId = $next++;
            $objects[$contentId] = "<< /Length " . strlen($stream) . " >>\nstream\n{$stream}endstream";
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] "
                . "/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents {$contentId} 0 R >>";
            $kids[] = "{$pageId} 0 R";
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref' . "\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= 'trailer << /Size ' . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        Storage::disk('local')->put($path, $pdf);

        return ['path' => $path, 'pages' => count($pages)];
    }

    // ------------------------------------------------------------------

    private function verticalGradient($img, int $w, int $h, array $from, array $to): void
    {
        for ($y = 0; $y < $h; $y++) {
            $t = $y / max(1, $h - 1);
            $c = imagecolorallocate($img, ...$this->mix($from, $to, $t));
            imageline($img, 0, $y, $w, $y, $c);
        }
    }

    private function diagonalGradient($img, int $w, int $h, array $from, array $to): void
    {
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x += 4) {
                $t = min(1, ($x / $w) * 0.55 + ($y / $h) * 0.65);
                $c = imagecolorallocate($img, ...$this->mix($from, $to, $t));
                imagefilledrectangle($img, $x, $y, $x + 3, $y, $c);
            }
        }
    }

    private function mix(array $a, array $b, float $t): array
    {
        return [
            (int) round($a[0] + ($b[0] - $a[0]) * $t),
            (int) round($a[1] + ($b[1] - $a[1]) * $t),
            (int) round($a[2] + ($b[2] - $a[2]) * $t),
        ];
    }

    private function centeredText($img, string $text, int $size, int $w, int $cy, array $rgb): void
    {
        $color = imagecolorallocate($img, ...$rgb);
        if (! $this->font) {
            imagestring($img, 5, (int) ($w / 2 - strlen($text) * 4.5), $cy - 8, $text, $color);

            return;
        }
        $box = imagettfbbox($size, 0, $this->font, $text);
        $tw = $box[2] - $box[0];
        $th = $box[1] - $box[7];
        imagettftext($img, $size, 0, (int) (($w - $tw) / 2), (int) ($cy + $th / 2), $color, $this->font, $text);
    }

    private function saveJpeg($img, string $path): string
    {
        ob_start();
        imagejpeg($img, null, 86);
        $bytes = ob_get_clean();
        imagedestroy($img);
        Storage::disk('public')->put($path, $bytes);

        return $path;
    }

    /** @return list<string> */
    private function wrap(string $text, int $width): array
    {
        return explode("\n", wordwrap($text, $width, "\n", true));
    }

    private function pdfEscape(string $text): string
    {
        $latin = mb_convert_encoding($text, 'Windows-1252', 'UTF-8');

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $latin);
    }
}
