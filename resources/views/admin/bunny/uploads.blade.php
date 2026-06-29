@extends('admin.layouts.app')

@section('title', 'Upload vidéos - ABBEV')
@section('header', 'Bunny Stream — Upload vidéos')

@section('content')
@php
    $statusMeta = [
        'uploading'    => ['Réception',  'bg-blue-500/15 text-blue-300 border-blue-500/30',     'fa-arrow-up-from-bracket'],
        'queued'       => ['En file',    'bg-amber-500/15 text-amber-300 border-amber-500/30',  'fa-clock'],
        'transferring' => ['Vers Bunny', 'bg-indigo-500/15 text-indigo-300 border-indigo-500/30','fa-cloud-arrow-up'],
        'processing'   => ['Encodage',   'bg-purple-500/15 text-purple-300 border-purple-500/30','fa-gears'],
        'ready'        => ['Prête',      'bg-green-500/15 text-green-300 border-green-500/30',   'fa-circle-check'],
        'failed'       => ['Échec',      'bg-red-500/15 text-red-300 border-red-500/30',         'fa-circle-exclamation'],
    ];
    $human = function ($b) { $u=['o','Ko','Mo','Go','To']; $i=0; $b=(float)$b; while($b>=1024 && $i<count($u)-1){$b/=1024;$i++;} return round($b, $i?1:0).' '.$u[$i]; };
@endphp

<div class="space-y-6" id="bunny-upload-app"
     x-data="{ help: false }">

    {{-- En-tête compact --}}
    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-white flex items-center gap-2">
                    <i class="fas fa-cloud-arrow-up text-primary-400"></i>
                    Uploader des vidéos
                </h2>
                <p class="text-gray-400 text-sm mt-1">
                    Library #{{ config('services.bunny.library_id') }} —
                    transfert vers Bunny en arrière-plan, lecture locale immédiate.
                </p>
            </div>
            <div class="flex items-center gap-2">
                @if($configured)
                    <span class="inline-flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-full bg-green-500/15 text-green-300 border border-green-500/30">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-400"></span> Bunny configuré
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-full bg-amber-500/15 text-amber-300 border border-amber-500/30">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span> Bunny non configuré
                    </span>
                @endif
                <button type="button" @click="help = !help"
                        class="text-xs px-3 py-1.5 rounded-full bg-dark-200 hover:bg-dark-300 text-gray-300 transition">
                    <i class="fas fa-circle-info mr-1"></i> Comment ça marche
                </button>
            </div>
        </div>

        <div x-show="help" x-collapse x-cloak class="mt-4 text-gray-400 text-sm bg-dark-50 border border-dark-200 rounded-lg p-4 space-y-1.5">
            <p><i class="fas fa-server w-4 text-primary-400"></i> La vidéo est <span class="text-white">stockée sur le serveur et lisible immédiatement</span>, même si Bunny est indisponible.</p>
            <p><i class="fas fa-cloud-arrow-up w-4 text-primary-400"></i> En arrière-plan, dès que Bunny répond, elle y est transférée puis <span class="text-white">supprimée du serveur</span> — le transfert continue même onglet fermé.</p>
            <p><i class="fas fa-layer-group w-4 text-primary-400"></i> Plusieurs fichiers à la fois (ex. 5 épisodes). Coupure ou fermeture : re-déposez le <span class="text-white">même fichier</span>, ça reprend où ça s'était arrêté.</p>
        </div>

        @unless($configured)
            <div class="mt-4 bg-amber-500/10 border border-amber-500/30 text-amber-200 rounded-lg p-3 text-sm">
                <i class="fas fa-triangle-exclamation mr-1"></i>
                Bunny n'est pas configuré. Les uploads restent stockés en local et lisibles ; ils partiront vers Bunny une fois configuré (bouton « Relancer »).
            </div>
        @endunless
    </div>

    {{-- Dropzone --}}
    <div id="bunny-dropzone"
         class="bg-dark-100 hover:bg-dark-100/70 rounded-xl shadow-lg border-2 border-dashed border-dark-200 hover:border-primary-500/60 p-10 text-center transition-all cursor-pointer">
        <i class="fas fa-film text-4xl text-primary-400 mb-3"></i>
        <p class="text-white font-medium">Glissez-déposez vos vidéos ici</p>
        <p class="text-gray-500 text-sm mt-1">ou</p>
        <button type="button" id="bunny-browse"
                class="inline-block mt-3 bg-primary-500 hover:bg-primary-600 text-white px-5 py-2 rounded-lg font-medium transition-all">
            <i class="fas fa-folder-open mr-2"></i> Choisir des fichiers
        </button>
        <p class="text-gray-600 text-xs mt-4">mp4, mkv, mov, webm, avi, m4v, ts — aucune limite de taille · plusieurs fichiers possibles</p>
    </div>

    {{-- Uploads en cours (seulement ceux qui bougent réellement) --}}
    <div id="bunny-active" class="space-y-2"></div>

    {{-- Bibliothèque des uploads --}}
    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-dark-200 flex flex-col lg:flex-row lg:items-center gap-3 lg:justify-between">
            <div class="flex items-center gap-3">
                <h3 class="text-white font-semibold whitespace-nowrap"><i class="fas fa-photo-film mr-2 text-gray-400"></i>Mes vidéos</h3>
                <span class="text-xs text-gray-500">{{ $uploads->total() }} au total</span>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                {{-- Recherche + filtre côté serveur (GET, auto-submit). --}}
                <form method="GET" action="{{ route('admin.bunny.uploads.index') }}" id="bunny-filter-form" class="flex flex-wrap items-center gap-2">
                    <div class="relative">
                        <i class="fas fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-xs"></i>
                        <input type="text" name="q" value="{{ $q }}" placeholder="Rechercher par nom…"
                               class="bg-dark-50 border border-dark-200 rounded-lg pl-8 pr-3 py-2 text-sm text-white w-52 focus:outline-none focus:border-primary-500">
                    </div>
                    <select name="status" onchange="this.form.submit()" class="bg-dark-50 border border-dark-200 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-primary-500">
                        <option value="" {{ $status === '' ? 'selected' : '' }}>Tous les statuts</option>
                        <option value="progress" {{ $status === 'progress' ? 'selected' : '' }}>En cours</option>
                        <option value="queued" {{ $status === 'queued' ? 'selected' : '' }}>En file</option>
                        <option value="ready" {{ $status === 'ready' ? 'selected' : '' }}>Prête</option>
                        <option value="failed" {{ $status === 'failed' ? 'selected' : '' }}>Échec / local</option>
                    </select>
                    @if($q !== '' || $status !== '')
                        <a href="{{ route('admin.bunny.uploads.index') }}" class="text-xs text-gray-400 hover:text-white px-2 py-2" title="Réinitialiser"><i class="fas fa-xmark"></i></a>
                    @endif
                </form>
                <span id="bunny-sel-count" class="text-xs text-gray-400"></span>
                <button type="button" id="bunny-delete-btn" disabled
                        class="bg-red-500/80 hover:bg-red-600 disabled:opacity-30 disabled:cursor-not-allowed text-white px-3 py-2 rounded-lg text-sm font-medium transition-all whitespace-nowrap">
                    <i class="fas fa-trash mr-1.5"></i>Supprimer
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm" id="bunny-table">
                <thead class="bg-dark-200/40 text-gray-400 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3 w-10"><input type="checkbox" id="bunny-check-all" class="w-4 h-4 accent-red-500 align-middle"></th>
                        <th class="text-left px-3 py-3">Vidéo</th>
                        <th class="text-left px-3 py-3 w-40">Statut</th>
                        <th class="text-left px-3 py-3 w-24">Taille</th>
                        <th class="text-left px-3 py-3 w-28">Ajoutée</th>
                        <th class="text-right px-4 py-3 w-44">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-dark-200/70">
                    @forelse($uploads as $u)
                        @php
                            $meta       = $statusMeta[$u->status] ?? ['—', 'bg-gray-500/15 text-gray-300 border-gray-500/30', 'fa-circle'];
                            $hasFile    = $u->temp_path && is_file($u->temp_path);
                            $localReady = $u->hasLocalCopy();
                            $inProgress = in_array($u->status, ['uploading','transferring','processing'], true);

                            // Statut AFFICHÉ : une vidéo dispo en local n'est jamais « Échec » rouge.
                            $note = null;
                            if ($u->status === 'ready') {
                                $disp = ['Sur Bunny', 'bg-green-500/15 text-green-300 border-green-500/30', 'fa-circle-check'];
                            } elseif ($localReady && $u->status === 'failed') {
                                $disp = ['Disponible en local', 'bg-sky-500/15 text-sky-300 border-sky-500/30', 'fa-hard-drive'];
                                $note = 'Bunny indisponible — relançable quand il revient';
                            } elseif ($localReady && $u->status === 'queued') {
                                $disp = ['En local · attente Bunny', 'bg-sky-500/15 text-sky-300 border-sky-500/30', 'fa-hard-drive'];
                            } elseif ($u->status === 'failed') {
                                $disp = ['Échec', 'bg-red-500/15 text-red-300 border-red-500/30', 'fa-circle-exclamation'];
                                $note = $u->error;
                            } else {
                                $disp = $meta;
                            }
                        @endphp
                        <tr data-upload-id="{{ $u->id }}" data-status="{{ $u->status }}" data-title="{{ \Illuminate\Support\Str::lower($u->title) }}"
                            class="hover:bg-dark-200/30 transition-colors">
                            <td class="px-4 py-3"><input type="checkbox" class="bunny-row-check w-4 h-4 accent-red-500 align-middle" value="{{ $u->id }}"></td>

                            <td class="px-3 py-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-12 h-9 rounded bg-dark-300 flex items-center justify-center flex-shrink-0 text-gray-500">
                                        <i class="fas fa-film"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-white truncate max-w-xs" title="{{ $u->title }}">{{ $u->title }}</p>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            @if($localReady)
                                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-green-500/15 text-green-300 border border-green-500/25">LOCAL · dispo picker</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td class="px-3 py-3" data-cell="status">
                                <span class="inline-flex items-center gap-1.5 text-xs px-2 py-1 rounded-full border {{ $disp[1] }}">
                                    <i class="fas {{ $disp[2] }} text-[10px]"></i>{{ $disp[0] }}
                                </span>
                                <div data-cell="progress" class="mt-1.5 {{ $inProgress ? '' : 'hidden' }}">
                                    <div class="w-28 bg-dark-300 rounded-full h-1.5 overflow-hidden">
                                        <div class="bg-primary-500 h-1.5 rounded-full transition-all" style="width:{{ $u->progress }}%"></div>
                                    </div>
                                </div>
                                @if($note)
                                    <p class="text-[11px] text-gray-500 mt-1 max-w-xs truncate" title="{{ $note }}">{{ $note }}</p>
                                @endif
                            </td>

                            <td class="px-3 py-3 text-gray-400" data-cell="size">{{ $human($u->size_bytes) }}</td>
                            <td class="px-3 py-3 text-gray-500 whitespace-nowrap">{{ $u->created_at?->diffForHumans(null, true) }}</td>

                            <td class="px-4 py-3" data-cell="actions">
                                <div class="flex items-center justify-end gap-1.5">
                                    @if($localReady)
                                        <a href="{{ asset('storage/' . $u->local_path) }}" target="_blank" title="Lire en local"
                                           class="w-8 h-8 inline-flex items-center justify-center rounded-lg bg-dark-200 hover:bg-dark-300 text-green-300"><i class="fas fa-play text-xs"></i></a>
                                    @endif
                                    @if($hasFile)
                                        <a href="{{ route('admin.bunny.uploads.download', $u->id) }}" title="Télécharger l'original"
                                           class="w-8 h-8 inline-flex items-center justify-center rounded-lg bg-dark-200 hover:bg-dark-300 text-gray-300"><i class="fas fa-download text-xs"></i></a>
                                    @endif
                                    @if($hasFile && $u->status === 'failed')
                                        <button type="button" data-retry-row="{{ $u->id }}" title="Relancer vers Bunny"
                                                class="w-8 h-8 inline-flex items-center justify-center rounded-lg bg-dark-200 hover:bg-dark-300 text-amber-300"><i class="fas fa-rotate-right text-xs"></i></button>
                                    @endif
                                    <button type="button" data-del-row="{{ $u->id }}" title="Supprimer"
                                            class="w-8 h-8 inline-flex items-center justify-center rounded-lg bg-dark-200 hover:bg-red-500/30 text-red-300"><i class="fas fa-trash text-xs"></i></button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr id="bunny-empty"><td colspan="6" class="px-6 py-10 text-center text-gray-500">
                            <i class="fas fa-inbox text-3xl mb-2 block opacity-50"></i>
                            @if($q !== '' || $status !== '')
                                Aucun résultat pour ce filtre.
                            @else
                                Aucune vidéo pour l'instant. Glissez vos premiers fichiers ci-dessus.
                            @endif
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination côté serveur --}}
        @if($uploads->hasPages())
            <div class="px-5 py-3 border-t border-dark-200 flex items-center justify-between gap-3">
                <span class="text-xs text-gray-500">
                    Affichage {{ $uploads->firstItem() }}–{{ $uploads->lastItem() }} sur {{ $uploads->total() }}
                </span>
                <div class="flex items-center gap-1.5">
                    @if($uploads->onFirstPage())
                        <span class="px-3 py-1.5 rounded-lg bg-dark-200/40 text-gray-600 text-sm cursor-not-allowed"><i class="fas fa-chevron-left mr-1 text-xs"></i>Précédent</span>
                    @else
                        <a href="{{ $uploads->previousPageUrl() }}" class="px-3 py-1.5 rounded-lg bg-dark-200 hover:bg-dark-300 text-gray-300 text-sm"><i class="fas fa-chevron-left mr-1 text-xs"></i>Précédent</a>
                    @endif
                    <span class="px-3 py-1.5 text-gray-400 text-xs">Page {{ $uploads->currentPage() }} / {{ $uploads->lastPage() }}</span>
                    @if($uploads->hasMorePages())
                        <a href="{{ $uploads->nextPageUrl() }}" class="px-3 py-1.5 rounded-lg bg-dark-200 hover:bg-dark-300 text-gray-300 text-sm">Suivant<i class="fas fa-chevron-right ml-1 text-xs"></i></a>
                    @else
                        <span class="px-3 py-1.5 rounded-lg bg-dark-200/40 text-gray-600 text-sm cursor-not-allowed">Suivant<i class="fas fa-chevron-right ml-1 text-xs"></i></span>
                    @endif
                </div>
            </div>
        @endif
    </div>

    {{-- Modale de confirmation de suppression (personnalisée) --}}
    <div id="bunny-del-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" data-modal-close></div>
        <div class="relative bg-dark-100 border border-dark-200 rounded-2xl shadow-2xl w-full max-w-md p-6">
            <div class="flex items-start gap-4">
                <div class="w-11 h-11 rounded-full bg-red-500/15 text-red-400 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-trash-can"></i>
                </div>
                <div class="min-w-0">
                    <h3 class="text-white font-semibold text-lg">Supprimer <span id="bunny-del-count">cette vidéo</span> ?</h3>
                    <p class="text-gray-400 text-sm mt-1">
                        Le(s) fichier(s) local(aux) seront définitivement effacés.
                        Les vidéos rattachées à un film ou un épisode seront <span class="text-gray-200">ignorées</span>.
                    </p>
                </div>
            </div>
            <div class="flex justify-end gap-2 mt-6">
                <button type="button" data-modal-close
                        class="px-4 py-2 rounded-lg bg-dark-200 hover:bg-dark-300 text-gray-200 text-sm font-medium transition">Annuler</button>
                <button type="button" id="bunny-del-confirm"
                        class="px-4 py-2 rounded-lg bg-red-500 hover:bg-red-600 text-white text-sm font-medium transition">
                    <i class="fas fa-trash mr-1.5"></i>Supprimer
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/resumablejs@1.1.0/resumable.min.js"></script>
<script>
(function () {
    const CSRF       = '{{ csrf_token() }}';
    const URL_START  = '{{ route('admin.bunny.upload.start') }}';
    const URL_CHUNK  = '{{ route('admin.bunny.upload.chunk') }}';
    const URL_ACTIVE = '{{ route('admin.bunny.uploads.active') }}';
    const URL_BASE   = '{{ url('admin/bunny/uploads') }}';
    const URL_BULK   = '{{ route('admin.bunny.uploads.bulk-delete') }}';

    const activeEl = document.getElementById('bunny-active');
    const tableBody = document.querySelector('#bunny-table tbody');
    let pollTimer = null;

    const META = {
        uploading:    ['Réception','bg-blue-500/15 text-blue-300 border-blue-500/30','fa-arrow-up-from-bracket'],
        queued:       ['En file','bg-amber-500/15 text-amber-300 border-amber-500/30','fa-clock'],
        transferring: ['Vers Bunny','bg-indigo-500/15 text-indigo-300 border-indigo-500/30','fa-cloud-arrow-up'],
        processing:   ['Encodage','bg-purple-500/15 text-purple-300 border-purple-500/30','fa-gears'],
        ready:        ['Prête','bg-green-500/15 text-green-300 border-green-500/30','fa-circle-check'],
        failed:       ['Échec','bg-red-500/15 text-red-300 border-red-500/30','fa-circle-exclamation'],
    };
    const IN_PROGRESS = ['uploading','transferring','processing'];
    const TERMINAL = ['ready','failed'];

    function human(b){ b=Number(b)||0; const u=['o','Ko','Mo','Go','To']; let i=0; while(b>=1024&&i<u.length-1){b/=1024;i++;} return b.toFixed(i?1:0)+' '+u[i]; }
    function pill(s){ const m=META[s]||['—','bg-gray-500/15 text-gray-300 border-gray-500/30','fa-circle']; return `<span class="inline-flex items-center gap-1.5 text-xs px-2 py-1 rounded-full border ${m[1]}"><i class="fas ${m[2]} text-[10px]"></i>${m[0]}</span>`; }

    /* ---------- Zone « en cours » (compacte) ---------- */
    function progressRow(d){
        let el = document.getElementById('act-'+d.id);
        if (TERMINAL.includes(d.status) || d.status==='queued'){ if(el) el.remove(); return; }
        if(!el){
            el=document.createElement('div'); el.id='act-'+d.id;
            el.className='bg-dark-100 rounded-xl border border-dark-200 px-4 py-3';
            activeEl.appendChild(el);
        }
        const label = d.status==='uploading'?'Envoi vers le serveur':(d.status==='transferring'?'Transfert vers Bunny':'Encodage Bunny');
        el.innerHTML=`
            <div class="flex items-center justify-between mb-1.5 gap-3">
                <span class="text-white text-sm truncate">${d.title||d.filename||('#'+d.id)}</span>
                ${pill(d.status)}
            </div>
            <div class="w-full bg-dark-300 rounded-full h-1.5 overflow-hidden">
                <div class="bg-primary-500 h-1.5 rounded-full transition-all" style="width:${d.progress||0}%"></div>
            </div>
            <div class="text-[11px] text-gray-500 mt-1">${label} · ${d.progress||0}%${d.size_bytes?' · '+human(d.size_bytes):''}</div>`;
    }

    /* ---------- Mise à jour live d'une ligne du tableau (jamais de reload ici) ---------- */
    function upsertRow(d){
        const row = tableBody.querySelector(`tr[data-upload-id="${d.id}"]`);
        if(!row) return; // hors page/filtre courant → on ne touche à rien
        row.dataset.status = d.status;
        const stCell = row.querySelector('[data-cell="status"]');
        if(stCell){
            const span = stCell.querySelector('span'); if(span) span.outerHTML = pill(d.status);
            const bar = row.querySelector('[data-cell="progress"]');
            if(bar){
                if(IN_PROGRESS.includes(d.status)){ bar.classList.remove('hidden'); const f=bar.querySelector('div>div'); if(f) f.style.width=(d.progress||0)+'%'; }
                else bar.classList.add('hidden');
            }
        }
    }

    let reloadT=null;
    function scheduleReload(){ if(reloadT) return; reloadT=setTimeout(()=>window.location.reload(), 1500); }

    /* ---------- Polling via /uploads/active (uniquement les uploads EN COURS) ---------- */
    let prevActive=new Set();
    async function sync(){
        try{
            const r=await fetch(URL_ACTIVE,{headers:{'Accept':'application/json'}});
            if(!r.ok){ pollTimer=setTimeout(sync,5000); return; }
            const {data}=await r.json();
            const seen=new Set();
            (data||[]).forEach(d=>{ seen.add(String(d.id)); progressRow(d); upsertRow(d); });
            // retirer les cartes « en cours » devenues inactives
            Array.from(activeEl.children).forEach(c=>{ const id=c.id.replace('act-',''); if(!seen.has(id)) c.remove(); });
            // un upload qui ÉTAIT en cours et ne l'est plus = terminé → un seul reload
            let finished=false;
            prevActive.forEach(id=>{ if(!seen.has(id)) finished=true; });
            prevActive=seen;
            if(finished){ scheduleReload(); return; }
            // on continue de poller tant qu'il reste des uploads en cours
            pollTimer = seen.size>0 ? setTimeout(sync, 3000) : null;
        }catch(e){ pollTimer=setTimeout(sync, 5000); }
    }
    function ensurePolling(){ if(!pollTimer) sync(); }

    /* ---------- Resumable.js ---------- */
    if (window.Resumable) {
        const r = new Resumable({
            target: URL_CHUNK, chunkSize: 5*1024*1024, simultaneousUploads: 3,
            testChunks: true, fileParameterName: 'file', maxChunkRetries: 5, chunkRetryInterval: 2000,
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            query: (file) => ({ upload_id: file.uploadId }),
        });
        r.assignDrop(document.getElementById('bunny-dropzone'));
        r.assignBrowse(document.getElementById('bunny-browse'), false, false);

        r.on('fileAdded', async (file) => {
            try{
                const res=await fetch(URL_START,{method:'POST',
                    headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
                    body:JSON.stringify({filename:file.fileName||file.file.name, size:file.size, identifier:file.uniqueIdentifier})});
                const data=await res.json();
                if(!res.ok){ alert(data.error||'Démarrage impossible.'); r.removeFile(file); return; }
                file.uploadId=data.upload_id;
                progressRow({id:data.upload_id,title:file.fileName,status:'uploading',progress:0,size_bytes:file.size});
                r.upload();
            }catch(e){ alert('Erreur réseau au démarrage.'); r.removeFile(file); }
        });
        r.on('fileProgress', (file)=>{ if(file.uploadId) progressRow({id:file.uploadId,title:file.fileName,status:'uploading',progress:Math.floor(file.progress()*100),size_bytes:file.size}); });
        r.on('fileSuccess', (file)=>{ const el=document.getElementById('act-'+file.uploadId); if(el) el.remove(); scheduleReload(); });
        r.on('fileError', (file)=>{ if(file.uploadId){ const el=document.getElementById('act-'+file.uploadId); if(el) el.querySelector('div:last-child').textContent='Échec de la réception.'; } });
    }

    /* ---------- Relancer ---------- */
    async function retryUpload(id){
        try{
            const r=await fetch(`${URL_BASE}/${id}/retry`,{method:'POST',headers:{'X-CSRF-TOKEN':CSRF,'Accept':'application/json'}});
            const d=await r.json();
            if(!r.ok){ alert(d.error||'Relance impossible.'); return; }
            ensurePolling(); scheduleReload();
        }catch(e){ alert('Erreur réseau à la relance.'); }
    }
    document.addEventListener('click',(e)=>{ const b=e.target.closest('[data-retry-row]'); if(b) retryUpload(b.dataset.retryRow); });

    /* ---------- Suppression via modale personnalisée ---------- */
    const modal=document.getElementById('bunny-del-modal');
    const delCountEl=document.getElementById('bunny-del-count');
    const delConfirm=document.getElementById('bunny-del-confirm');
    let pendingIds=[];

    function openDelModal(ids){
        if(!ids.length) return;
        pendingIds=ids;
        if(delCountEl) delCountEl.textContent = ids.length>1 ? `ces ${ids.length} vidéos` : 'cette vidéo';
        if(delConfirm){ delConfirm.disabled=false; delConfirm.innerHTML='<i class="fas fa-trash mr-1.5"></i>Supprimer'; }
        modal.classList.remove('hidden'); modal.classList.add('flex');
    }
    function closeDelModal(){ if(!modal) return; modal.classList.add('hidden'); modal.classList.remove('flex'); pendingIds=[]; }
    if(modal){
        modal.querySelectorAll('[data-modal-close]').forEach(el=>el.addEventListener('click', closeDelModal));
        document.addEventListener('keydown',(e)=>{ if(e.key==='Escape' && !modal.classList.contains('hidden')) closeDelModal(); });
    }

    async function deleteIds(ids){
        const r=await fetch(URL_BULK,{method:'POST',
            headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
            body:JSON.stringify({ids})});
        return r.ok ? r.json() : Promise.reject(await r.json().catch(()=>({})));
    }
    function afterDelete(d){
        if(d.skipped && d.skipped.length){
            const lines = d.skipped.map(s=>`• ${s.title} — ${s.reason||'rattachée à un film/épisode'}`).join('\n');
            sessionStorage.setItem('bunny-del-msg', `${d.deleted} supprimée(s).\nNon supprimée(s) :\n${lines}`);
        }
        window.location.reload();
    }
    if(delConfirm) delConfirm.addEventListener('click', async ()=>{
        if(!pendingIds.length) return;
        delConfirm.disabled=true; delConfirm.innerHTML='<i class="fas fa-spinner fa-spin mr-1.5"></i>Suppression…';
        try{ afterDelete(await deleteIds(pendingIds)); }
        catch(d){ closeDelModal(); alert(d.message||'Suppression impossible.'); }
    });

    // Suppression par ligne → ouvre la modale.
    document.addEventListener('click',(e)=>{ const b=e.target.closest('[data-del-row]'); if(b) openDelModal([Number(b.dataset.delRow)]); });

    /* ---------- Sélection multiple ---------- */
    const checkAll=document.getElementById('bunny-check-all');
    const delBtn=document.getElementById('bunny-delete-btn');
    const selCount=document.getElementById('bunny-sel-count');
    const rowChecks=()=>Array.from(document.querySelectorAll('.bunny-row-check'));
    const selectedIds=()=>rowChecks().filter(c=>c.checked).map(c=>Number(c.value));
    function refreshSel(){
        const n=selectedIds().length;
        if(delBtn) delBtn.disabled=n===0;
        if(selCount) selCount.textContent=n?`${n} sélectionnée(s)`:'';
        if(checkAll){ const all=rowChecks(); checkAll.checked=all.length>0&&all.every(c=>c.checked); checkAll.indeterminate=all.some(c=>c.checked)&&!checkAll.checked; }
    }
    if(checkAll) checkAll.addEventListener('change',()=>{ rowChecks().forEach(c=>{ c.checked=checkAll.checked; }); refreshSel(); });
    document.addEventListener('change',(e)=>{ if(e.target.classList.contains('bunny-row-check')) refreshSel(); });
    if(delBtn) delBtn.addEventListener('click', ()=> openDelModal(selectedIds()));

    /* ---------- Recherche serveur : auto-submit débauncé ---------- */
    const fForm=document.getElementById('bunny-filter-form');
    const fSearch=fForm?.querySelector('input[name="q"]');
    let sT=null;
    if(fSearch) fSearch.addEventListener('input', ()=>{ clearTimeout(sT); sT=setTimeout(()=>fForm.submit(), 600); });

    // Message après rechargement (suppressions ignorées).
    const delMsg=sessionStorage.getItem('bunny-del-msg');
    if(delMsg){ sessionStorage.removeItem('bunny-del-msg'); setTimeout(()=>alert(delMsg), 150); }

    refreshSel();
    ensurePolling();
})();
</script>
@endpush
