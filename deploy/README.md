# Déploiement — worker de transfert Bunny

Le transfert des vidéos uploadées vers Bunny se fait en arrière-plan via un Job
sur la queue **dédiée `bunny`** (isolée pour qu'un upload de plusieurs Go ne
bloque pas les autres jobs, ex. réconciliation des paiements). Ce worker doit
tourner **en permanence** en production, sinon les vidéos restent stockées en
local (lisibles) mais ne partent jamais vers Bunny.

## Commande du worker

```bash
php artisan queue:work bunny --queue=bunny --timeout=0 --tries=3 --sleep=3 --backoff=30
```

- `bunny` (1er arg) = **connexion** (retry_after 7200s, cf. `config/queue.php`).
- `--queue=bunny` = la queue à traiter.
- `--timeout=0` = pas de limite de durée (un PUT de plusieurs Go est légitime).

> `php artisan queue:work` **sans argument** ne traite que la queue `default` :
> il ne verra PAS les transferts Bunny.

## Choisir un superviseur

- **systemd** (recommandé sur la plupart des serveurs Linux) :
  `deploy/systemd/abbev-bunny-worker.service`
- **Supervisor** : `deploy/supervisor/abbev-bunny-worker.conf`

Les deux fichiers contiennent les instructions d'installation en en-tête. Pense
à adapter les chemins (`/var/www/abbev`), l'utilisateur (`www-data`) et le
binaire `php`.

## Important

- **Après chaque déploiement de code**, redémarre le worker pour qu'il recharge
  le code :
  - systemd : `sudo systemctl restart abbev-bunny-worker`
  - supervisor : `sudo supervisorctl restart abbev-bunny-worker:*`
  (ou `php artisan queue:restart`, que les workers prennent en compte au prochain job.)
- **Planificateur** (housekeeping `bunny:uploads:cleanup` + autres tâches) : assure
  le cron Laravel `* * * * * cd /var/www/abbev && php artisan schedule:run >> /dev/null 2>&1`.
- **Nginx / PHP-FPM** : l'upload est chunké (morceaux de 5 Mo), donc
  `client_max_body_size` ≈ 10–20 Mo suffit (PAS besoin de la taille totale du film).
- **En développement**, rien à installer : `composer dev` lance déjà ce worker
  (process `bunny`).
