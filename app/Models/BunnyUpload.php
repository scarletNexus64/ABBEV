<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Suivi d'un upload de vidéo vers la Bunny Library.
 *
 * Le cycle de vie d'un upload :
 *   uploading    → réception des chunks (navigateur → serveur)
 *   queued       → fichier complet reçu, Job en file d'attente
 *   transferring → PUT du binaire vers Bunny en cours
 *   processing   → binaire envoyé, Bunny transcode (status 0..3)
 *   ready        → Bunny a fini d'encoder (status 4)
 *   failed       → erreur
 *
 * La progression est écrite ici par le worker (Job DispatchTransferToBunny),
 * indépendamment du navigateur : c'est ce qui permet de retrouver l'upload
 * « avancé » après avoir fermé l'onglet.
 */
class BunnyUpload extends Model
{
    protected $fillable = [
        'user_id',
        'original_filename',
        'title',
        'size_bytes',
        'bytes_received',
        'bytes_sent',
        'resumable_identifier',
        'temp_path',
        'local_path',
        'status',
        'progress',
        'bunny_status',
        'bunny_guid',
        'error',
        'uploaded_at',
        'transferred_at',
        'ready_at',
    ];

    protected $casts = [
        'size_bytes'     => 'integer',
        'bytes_received' => 'integer',
        'bytes_sent'     => 'integer',
        'progress'       => 'integer',
        'bunny_status'   => 'integer',
        'uploaded_at'    => 'datetime',
        'transferred_at' => 'datetime',
        'ready_at'       => 'datetime',
    ];

    public const TERMINAL = ['ready', 'failed'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Uploads encore en cours (ni prêts, ni échoués). */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', self::TERMINAL);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL, true);
    }

    /**
     * Une copie locale lisible existe-t-elle ? On teste le fichier via le disque
     * « public » (canonique) et pas via public_path()/symlink, qui peut échouer
     * selon l'environnement même quand le fichier existe bel et bien.
     */
    public function hasLocalCopy(): bool
    {
        return (bool) ($this->local_path && Storage::disk('local')->exists($this->local_path));
    }

    /**
     * Chemin absolu du fichier local utilisable (pour download / transfert Bunny).
     * Préfère temp_path s'il existe encore, sinon retombe sur la copie publique
     * (local_path). Retourne null si aucune copie locale n'est disponible.
     */
    public function localFilePath(): ?string
    {
        if ($this->temp_path && is_file($this->temp_path)) {
            return $this->temp_path;
        }
        if ($this->local_path && Storage::disk('local')->exists($this->local_path)) {
            return Storage::disk('local')->path($this->local_path);
        }

        return null;
    }

    public function markFailed(string $message): void
    {
        $this->update([
            'status' => 'failed',
            'error'  => $message,
        ]);
    }
}
