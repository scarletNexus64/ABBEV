@extends('admin.layouts.app')

@section('title', 'Upload vidéos - ABBEV')
@section('header', 'Bunny Stream — Upload vidéos')

@section('content')
<div class="space-y-6" id="bunny-upload-app">

    {{-- En-tête --}}
    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
        <h2 class="text-xl font-bold text-white flex items-center gap-2">
            <i class="fas fa-cloud-arrow-up text-primary-400"></i>
            Uploader des vidéos vers la Bunny Library
        </h2>
        <p class="text-gray-400 text-sm mt-1">
            Library #{{ config('services.bunny.library_id') }} — les fichiers sont envoyés au serveur par
            morceaux, puis transférés vers Bunny en arrière-plan.
        </p>
        <p class="text-gray-500 text-xs mt-2">
            <i class="fas fa-info-circle"></i>
            La vidéo est <span class="text-white font-semibold">stockée sur le serveur et lisible immédiatement</span>,
            même si Bunny est indisponible. En arrière-plan, dès que Bunny est joignable, la vidéo y est transférée
            puis <span class="text-white font-semibold">supprimée du serveur</span> — le transfert se poursuit même si
            vous fermez l'onglet. Tu peux uploader <span class="text-white font-semibold">plusieurs vidéos à la fois</span>
            (ex. 5 épisodes). Les vidéos sont attribuables à un film ou un épisode dès l'upload.
        </p>
    </div>

    @unless($configured)
        <div class="bg-yellow-500/10 border border-yellow-500/30 text-yellow-200 rounded-xl p-4 text-sm">
            <i class="fas fa-triangle-exclamation mr-2"></i>
            Bunny Stream n'est pas configuré (BUNNY_STREAM_LIBRARY_ID / BUNNY_STREAM_API_KEY /
            BUNNY_STREAM_CDN_HOSTNAME). Les uploads restent <span class="font-semibold">stockés en local</span> et
            lisibles ; ils seront transférés vers Bunny une fois configuré (bouton « Relancer »).
        </div>
    @endunless

    {{-- Dropzone --}}
    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
        <div id="bunny-dropzone"
             class="border-2 border-dashed border-dark-200 hover:border-primary-500/60 rounded-xl p-10 text-center transition-all cursor-pointer">
            <i class="fas fa-film text-4xl text-primary-400 mb-3"></i>
            <p class="text-white font-medium">Glissez-déposez vos vidéos ici</p>
            <p class="text-gray-500 text-sm mt-1">ou</p>
            <button type="button" id="bunny-browse"
                    class="mt-3 bg-primary-500 hover:bg-primary-600 text-white px-5 py-2 rounded-lg font-medium transition-all">
                <i class="fas fa-folder-open mr-2"></i> Choisir des fichiers
            </button>
            <p class="text-gray-600 text-xs mt-4">Formats : mp4, mkv, mov, webm, avi, m4v, ts — aucune limite de taille.</p>
        </div>
    </div>

    {{-- Uploads en cours (alimenté par JS) --}}
    <div id="bunny-active" class="space-y-3"></div>

    {{-- Uploads récents (rendu serveur) --}}
    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-dark-200">
            <h3 class="text-white font-semibold"><i class="fas fa-clock-rotate-left mr-2 text-gray-400"></i>Uploads récents</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm" id="bunny-recent-table">
                @php
                    $statusMeta = [
                        'uploading'    => ['Réception…',  'bg-blue-500/20 text-blue-300'],
                        'queued'       => ['En file',     'bg-yellow-500/20 text-yellow-300'],
                        'transferring' => ['Vers Bunny…', 'bg-indigo-500/20 text-indigo-300'],
                        'processing'   => ['Encodage…',   'bg-purple-500/20 text-purple-300'],
                        'ready'        => ['Prête',       'bg-green-500/20 text-green-300'],
                        'failed'       => ['Échec',       'bg-red-500/20 text-red-300'],
                    ];
                    $human = function ($b) { $u=['o','Ko','Mo','Go','To']; $i=0; $b=(float)$b; while($b>=1024 && $i<count($u)-1){$b/=1024;$i++;} return round($b, $i?1:0).' '.$u[$i]; };
                @endphp
                <thead class="bg-dark-200/50 text-gray-400 text-xs uppercase">
                    <tr>
                        <th class="text-left px-6 py-3">Titre</th>
                        <th class="text-left px-6 py-3">Statut</th>
                        <th class="text-left px-6 py-3">Progression</th>
                        <th class="text-left px-6 py-3">Taille</th>
                        <th class="text-left px-6 py-3">Date</th>
                        <th class="text-left px-6 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-dark-200">
                    @forelse($uploads as $u)
                        @php $meta = $statusMeta[$u->status] ?? ['—', 'bg-gray-500/20 text-gray-300']; $hasFile = $u->temp_path && is_file($u->temp_path); $localReady = $u->hasLocalCopy(); @endphp
                        <tr data-upload-id="{{ $u->id }}">
                            <td class="px-6 py-3 text-white">{{ $u->title }}</td>
                            <td class="px-6 py-3" data-cell="status">
                                <span class="px-2 py-1 rounded text-xs font-medium {{ $meta[1] }}">{{ $meta[0] }}</span>
                            </td>
                            <td class="px-6 py-3 w-64" data-cell="progress">
                                <div class="w-full bg-dark-300 rounded-full h-2 overflow-hidden">
                                    <div class="{{ $u->status === 'failed' ? 'bg-red-500' : ($u->status === 'ready' ? 'bg-green-500' : 'bg-primary-500') }} h-2 rounded-full" style="width:{{ $u->progress }}%"></div>
                                </div>
                                <span class="text-xs text-gray-500">{{ $u->progress }}%</span>
                            </td>
                            <td class="px-6 py-3 text-gray-400" data-cell="size">{{ $human($u->size_bytes) }}</td>
                            <td class="px-6 py-3 text-gray-500">{{ $u->created_at?->diffForHumans() }}</td>
                            <td class="px-6 py-3 text-xs whitespace-nowrap">
                                @if($hasFile)
                                    <a href="{{ route('admin.bunny.uploads.download', $u->id) }}" class="text-primary-300 hover:underline mr-3"><i class="fas fa-download mr-1"></i>Original</a>
                                @endif
                                @if($hasFile && $u->status === 'failed')
                                    <button type="button" data-retry-row="{{ $u->id }}" class="text-yellow-300 hover:underline mr-3"><i class="fas fa-rotate-right mr-1"></i>Relancer</button>
                                @endif
                                @if($localReady)
                                    <a href="{{ asset('storage/' . $u->local_path) }}" target="_blank" class="text-green-300 hover:underline mr-3"><i class="fas fa-play mr-1"></i>Lire local</a>
                                    <span class="text-green-400"><i class="fas fa-circle-check mr-1"></i>Dispo picker (local)</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-6 text-center text-gray-500">Aucun upload pour l'instant.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/resumablejs@1.1.0/resumable.min.js"></script>
<script>
(function () {
    const CSRF      = '{{ csrf_token() }}';
    const URL_START = '{{ route('admin.bunny.upload.start') }}';
    const URL_CHUNK = '{{ route('admin.bunny.upload.chunk') }}';
    const URL_STATUS = (id) => `{{ url('admin/bunny/uploads') }}/${id}/status`;
    const URL_ACTIVE = '{{ route('admin.bunny.uploads.active') }}';
    const CONFIGURED = @json($configured);

    const activeEl = document.getElementById('bunny-active');
    const pollers  = {}; // upload_id -> intervalId

    /* ---------- Helpers ---------- */
    function humanSize(bytes) {
        bytes = Number(bytes) || 0;
        const u = ['o', 'Ko', 'Mo', 'Go', 'To'];
        let i = 0;
        while (bytes >= 1024 && i < u.length - 1) { bytes /= 1024; i++; }
        return bytes.toFixed(i ? 1 : 0) + ' ' + u[i];
    }

    const STATUS_LABELS = {
        uploading:    { txt: 'Réception…',  cls: 'bg-blue-500/20 text-blue-300' },
        queued:       { txt: 'En file',     cls: 'bg-yellow-500/20 text-yellow-300' },
        transferring: { txt: 'Vers Bunny…', cls: 'bg-indigo-500/20 text-indigo-300' },
        processing:   { txt: 'Encodage…',   cls: 'bg-purple-500/20 text-purple-300' },
        ready:        { txt: 'Prête',       cls: 'bg-green-500/20 text-green-300' },
        failed:       { txt: 'Échec',       cls: 'bg-red-500/20 text-red-300' },
    };

    function statusBadge(status) {
        const s = STATUS_LABELS[status] || { txt: status, cls: 'bg-gray-500/20 text-gray-300' };
        return `<span class="px-2 py-1 rounded text-xs font-medium ${s.cls}">${s.txt}</span>`;
    }

    function progressBar(progress, status) {
        const color = status === 'failed' ? 'bg-red-500'
                    : status === 'ready'  ? 'bg-green-500'
                    : 'bg-primary-500';
        return `<div class="w-full bg-dark-300 rounded-full h-2 overflow-hidden">
                    <div class="${color} h-2 rounded-full transition-all" style="width:${progress || 0}%"></div>
                </div>
                <span class="text-xs text-gray-500">${progress || 0}%</span>`;
    }

    /* ---------- Carte d'upload actif ---------- */
    function cardFor(id) {
        let el = document.getElementById('upload-card-' + id);
        if (!el) {
            el = document.createElement('div');
            el.id = 'upload-card-' + id;
            el.className = 'bg-dark-100 rounded-xl border border-dark-200 p-4';
            activeEl.appendChild(el);
        }
        return el;
    }

    function renderCard(d) {
        const el = cardFor(d.id);
        const hint = d.status === 'uploading' ? 'Envoi vers le serveur'
                   : d.status === 'transferring' ? 'Transfert vers Bunny'
                   : d.status === 'processing' ? 'Bunny encode la vidéo'
                   : d.status === 'queued' ? 'En attente de transfert'
                   : '';
        const actions = [];
        if (d.download_url) {
            actions.push(`<a href="${d.download_url}" class="text-primary-300 hover:underline">
                <i class="fas fa-download mr-1"></i>Télécharger l'original</a>`);
        }
        if (d.can_retry) {
            actions.push(`<button type="button" data-retry="${d.id}" class="text-yellow-300 hover:underline">
                <i class="fas fa-rotate-right mr-1"></i>Relancer vers Bunny</button>`);
        }
        if (d.local_ready) {
            actions.push(`<a href="${d.local_url}" target="_blank" class="text-green-300 hover:underline">
                <i class="fas fa-play mr-1"></i>Lire en local</a>`);
            actions.push(`<span class="text-green-400"><i class="fas fa-circle-check mr-1"></i>Dispo dans le picker (local)</span>`);
        }
        el.innerHTML = `
            <div class="flex items-center justify-between mb-2">
                <div class="text-white font-medium truncate pr-4">${d.title || d.filename || ('#' + d.id)}</div>
                ${statusBadge(d.status)}
            </div>
            <div class="flex items-center gap-3">${progressBar(d.progress, d.status)}</div>
            <div class="text-xs text-gray-500 mt-1">${hint}${d.size_bytes ? ' · ' + humanSize(d.size_bytes) : ''}
                ${d.error ? '<span class="text-red-400"> · ' + d.error + '</span>' : ''}</div>
            ${actions.length ? '<div class="flex gap-4 mt-2 text-xs">' + actions.join('') + '</div>' : ''}`;
        const retryBtn = el.querySelector('[data-retry]');
        if (retryBtn) retryBtn.addEventListener('click', () => retryUpload(d.id));
        // On masque seulement les réussites ; les échecs restent (boutons récupérer/relancer).
        if (d.status === 'ready') {
            setTimeout(() => { el.remove(); }, 4000);
        }
    }

    async function retryUpload(id) {
        try {
            const r = await fetch(`{{ url('admin/bunny/uploads') }}/${id}/retry`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            });
            const d = await r.json();
            if (!r.ok) { alert(d.error || 'Relance impossible.'); return; }
            renderCard(d);
            updateRecentRow(d);
            startPolling(id);
        } catch (e) { alert('Erreur réseau à la relance.'); }
    }

    /* ---------- Suivi serveur (polling) ---------- */
    function startPolling(id) {
        if (pollers[id]) return;
        pollers[id] = setInterval(() => pollOnce(id), 2500);
        pollOnce(id);
    }
    function stopPolling(id) {
        if (pollers[id]) { clearInterval(pollers[id]); delete pollers[id]; }
    }
    async function pollOnce(id) {
        try {
            const r = await fetch(URL_STATUS(id), { headers: { 'Accept': 'application/json' } });
            if (!r.ok) return;
            const d = await r.json();
            renderCard(d);
            updateRecentRow(d);
            if (d.status === 'ready' || d.status === 'failed') stopPolling(id);
        } catch (e) { /* on réessaie au tick suivant */ }
    }

    function updateRecentRow(d) {
        const row = document.querySelector(`#bunny-recent-table tr[data-upload-id="${d.id}"]`);
        if (!row) return;
        row.querySelector('[data-cell="status"]').innerHTML = statusBadge(d.status);
        row.querySelector('[data-cell="progress"]').innerHTML = progressBar(d.progress, d.status);
        row.querySelector('[data-cell="size"]').textContent = humanSize(d.size_bytes);
    }

    /* ---------- Resumable.js ---------- */
    // L'upload fonctionne même si Bunny est indisponible : la vidéo est stockée
    // sur le serveur (lisible en local) et poussée vers Bunny en arrière-plan.
    if (window.Resumable) {
        const r = new Resumable({
            target: URL_CHUNK,
            chunkSize: 5 * 1024 * 1024,      // 5 Mo
            simultaneousUploads: 3,          // chunks en parallèle (débit gros fichiers + multi-upload)
            testChunks: false,
            fileParameterName: 'file',
            maxChunkRetries: 5,
            chunkRetryInterval: 2000,
            maxFiles: undefined,             // multi-upload illimité (ex. 5 épisodes d'un coup)
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            query: (file) => ({ upload_id: file.uploadId }),
        });

        // assignBrowse(node, isDirectory=false, singleFile=false) → sélection multiple permise.
        r.assignDrop(document.getElementById('bunny-dropzone'));
        r.assignBrowse(document.getElementById('bunny-browse'), false, false);

        // On crée la ligne de suivi avant d'uploader, pour récupérer l'upload_id.
        r.on('fileAdded', async (file) => {
            try {
                const res = await fetch(URL_START, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        filename: file.fileName || file.file.name,
                        size: file.size,
                        identifier: file.uniqueIdentifier,
                    }),
                });
                const data = await res.json();
                if (!res.ok) { alert(data.error || 'Démarrage de l\'upload impossible.'); r.removeFile(file); return; }
                file.uploadId = data.upload_id;
                renderCard({ id: data.upload_id, title: file.fileName, status: 'uploading', progress: 0, size_bytes: file.size });
                r.upload();
            } catch (e) {
                alert('Erreur réseau au démarrage de l\'upload.');
                r.removeFile(file);
            }
        });

        r.on('fileProgress', (file) => {
            if (!file.uploadId) return;
            renderCard({
                id: file.uploadId, title: file.fileName, status: 'uploading',
                progress: Math.floor(file.progress() * 100), size_bytes: file.size,
            });
        });

        // Dernier chunk reçu : le serveur a dispatché le Job. On passe au suivi serveur.
        r.on('fileSuccess', (file) => {
            if (file.uploadId) startPolling(file.uploadId);
        });

        r.on('fileError', (file, message) => {
            if (file.uploadId) {
                renderCard({ id: file.uploadId, title: file.fileName, status: 'failed', progress: 0,
                             error: 'Échec de la réception côté serveur.' });
            }
        });
    }

    /* ---------- Au chargement : reprendre le suivi des uploads serveur en cours ---------- */
    async function loadActive() {
        try {
            const r = await fetch(URL_ACTIVE, { headers: { 'Accept': 'application/json' } });
            if (!r.ok) return;
            const { data } = await r.json();
            (data || []).forEach((d) => {
                // Échecs récupérables OU phase serveur (queued/transferring/processing) qui avancent seuls.
                if (d.status !== 'uploading') { renderCard(d); }
                if (d.status !== 'uploading' && d.status !== 'failed') { startPolling(d.id); }
                updateRecentRow(d);
            });
        } catch (e) { /* ignore */ }
    }
    // Boutons rendus côté serveur dans le tableau.
    document.querySelectorAll('[data-retry-row]').forEach((btn) => {
        btn.addEventListener('click', () => retryUpload(btn.getAttribute('data-retry-row')));
    });

    loadActive();
})();
</script>
@endpush
