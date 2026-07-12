# Déploiement — workers du pipeline vidéo

Le traitement d'un upload est **entièrement en arrière-plan** (le navigateur de
l'utilisateur rend la main dès le dernier morceau reçu) et réparti sur **deux
files séparées** pour que rien ne bloque personne :

| File | Rôle | Nature | Worker |
|------|------|--------|--------|
| **`bunny`** | assemblage + transfert réseau vers Bunny | I/O réseau (peut durer, gros fichiers) | `queue:work bunny --queue=bunny` |
| **`transcode`** | conversion ffmpeg webm/mkv… → MP4 iPhone | CPU, long (plusieurs minutes) | `queue:work transcode --queue=transcode` |

**Pourquoi deux files ?** Un encodage ffmpeg de 15 min ne doit pas bloquer un
transfert (ni l'inverse), et **plusieurs uploads simultanés doivent avancer en
parallèle**. La concurrence = le **nombre de processus worker** (`numprocs` sous
Supervisor). Avec une seule file et un seul worker, l'utilisateur B attendait
derrière l'utilisateur A (head-of-line blocking).

> La **réception** (navigateur → serveur) n'est PAS sur une file : c'est du HTTP
> servi par PHP-FPM. Plusieurs utilisateurs uploadent déjà en parallèle, et la
> requête qui finalise l'upload répond immédiatement (elle ne fait que
> `dispatch`). Côté utilisateur, chacun a donc « l'impression d'être seul ».

## Commandes des workers

```bash
# Transferts vers Bunny (lance-en plusieurs pour des transferts simultanés)
php artisan queue:work bunny --queue=bunny --timeout=0 --tries=3 --sleep=3 --backoff=30

# Transcodage ffmpeg (file séparée ; 1 à 2 suffisent, ffmpeg sature le CPU)
php artisan queue:work transcode --queue=transcode --timeout=0 --tries=1 --sleep=3
```

- 1er arg = **connexion** (retry_after 7200s, cf. `config/queue.php`).
- `--queue=…` = la queue à traiter.
- `--timeout=0` = pas de limite de durée (PUT de plusieurs Go / encodage long).

> `php artisan queue:work` **sans argument** ne traite que la queue `default` :
> il ne verra NI les transferts Bunny NI les transcodages.

### En développement (une seule commande, deux files)

```bash
php artisan queue:work bunny --queue=bunny,transcode --timeout=0
```

Pratique en local (un seul terminal), mais **séquentiel** : pour de la vraie
concurrence, lance plusieurs processus (voir Supervisor ci-dessous, `numprocs`).

## Choisir un superviseur

- **Supervisor** (recommandé pour la concurrence via `numprocs`) :
  `deploy/supervisor/abbev-bunny-worker.conf` — définit **les deux** programmes
  (`abbev-bunny-worker`, `numprocs=3` + `abbev-transcode-worker`, `numprocs=2`).
- **systemd** :
  - `deploy/systemd/abbev-bunny-worker.service` (transferts)
  - `deploy/systemd/abbev-transcode-worker.service` (transcodage)

Les fichiers contiennent les instructions d'installation en en-tête. Pense à
adapter les chemins (`/var/www/abbev`), l'utilisateur (`www-data`) et le binaire
`php`. Ajuste `numprocs` selon ta RAM/CPU (pour `transcode`, ≤ nombre de cœurs).

## Important

- **Après chaque déploiement de code**, redémarre les workers pour qu'ils
  rechargent le code (un worker garde en mémoire la version chargée à son
  démarrage — il n'assemble/transfère plus au bon endroit sinon) :
  - systemd : `sudo systemctl restart abbev-bunny-worker abbev-transcode-worker`
  - supervisor : `sudo supervisorctl restart abbev-bunny-worker:* abbev-transcode-worker:*`
  (ou `php artisan queue:restart`, que les workers prennent en compte au prochain job.)
- **Planificateur** (housekeeping `bunny:uploads:cleanup` + autres tâches) : assure
  le cron Laravel `* * * * * cd /var/www/abbev && php artisan schedule:run >> /dev/null 2>&1`.
- **Nginx / PHP-FPM** : l'upload est chunké (morceaux de 5 Mo), donc
  `client_max_body_size` ≈ 10–20 Mo suffit (PAS besoin de la taille totale du film).
- **En développement**, rien à installer : `composer dev` lance déjà ce worker
  (process `bunny`).
