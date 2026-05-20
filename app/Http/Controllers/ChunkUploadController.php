<?php

namespace App\Http\Controllers;

/**
 * @deprecated L'upload de vidéo a été supprimé.
 * Toutes les vidéos sont uploadées directement sur Bunny Stream
 * depuis le dashboard https://dash.bunny.net puis référencées en base
 * via video_id (cf. BunnySyncController et MediaController).
 *
 * Cette classe ne sert plus à rien et n'est plus routée. Conservée pour
 * éviter de casser un import résiduel.
 */
class ChunkUploadController extends Controller
{
}
