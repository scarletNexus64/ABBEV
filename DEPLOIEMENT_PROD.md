# Déploiement production — abonnements Apple IAP

Procédure de mise en production du backend ABBEV pour la soumission App Store.
À exécuter sur le serveur qui sert `https://admin.abbev.tv`.

> **Contexte.** L'app iOS vend trois abonnements auto-renouvelables via
> StoreKit. Apple vérifie chaque achat côté serveur : sans la configuration
> ci-dessous, tout achat est encaissé par Apple **sans** que l'abonnement soit
> activé côté ABBEV. Plusieurs points sont également des motifs de rejet
> directs lors de la review.

---

## 0. Ce qui doit être vrai avant de soumettre

Le reviewer Apple teste l'app **en conditions réelles** contre ce serveur.
Ces cinq points sont bloquants :

| # | Vérification | Motif de rejet si absent |
|---|---|---|
| 1 | `https://admin.abbev.tv/conditions-utilisation` → 200 | Guideline 3.1.2 (lien mort) |
| 2 | `https://admin.abbev.tv/confidentialite` → 200 | Guideline 3.1.2 + fiche App Store |
| 3 | `/api/v1/subscription-plans` renvoie 3 plans avec `appleProductId` | Paywall vide → 2.1 |
| 4 | Vérification des reçus Apple fonctionnelle (clé `.p8`) | Achat encaissé sans accès |
| 5 | L'app pointe sur `https://admin.abbev.tv` (pas une IP LAN) | Guideline 2.1 |

---

## 1. Déployer le code

```bash
cd /chemin/vers/ABBEV
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
```

Nouveautés de cette version :

- `LegalController` + vues `resources/views/legal/` → pages CGU et confidentialité
- routes publiques `/conditions-utilisation` et `/confidentialite`
- `AppleInAppPurchasePlanSeeder` → les trois forfaits et leurs Product IDs
- `appleProductId` exposé par `/api/v1/subscription-plans`
- journalisation détaillée du parcours IAP (`[AppleIAP]`, `[SubscriptionPayment]`)

## 2. Créer les trois forfaits

```bash
php artisan db:seed --class=AppleInAppPurchasePlanSeeder --force
```

Idempotent : relançable sans créer de doublon. Il désactive aussi l'ancienne
offre unique `com.abbev.sub.monthly` sans la supprimer (des `UserSubscription`
y font référence).

Vérification :

```bash
php artisan tinker --execute="
foreach (\App\Models\SubscriptionPlan::where('is_active',true)->orderBy('order')->get() as \$p)
  printf(\"%-10s %6s F  %s\n\", \$p->name, number_format(\$p->price,0), \$p->apple_product_id ?: 'AUCUN');
"
```

Attendu — les Product IDs doivent correspondre **au caractère près** à App Store Connect :

```
Classique   1,000 F  com.abbev.sub.classique.monthly
Standard    2,500 F  com.abbev.sub.standard.monthly
Premium     5,000 F  com.abbev.sub.premium.monthly
```

## 3. Installer les clés Apple

Les clés ne sont **pas** dans le dépôt (`storage/app/private/.gitignore`) : une
clé privée committée reste dans l'historique git indéfiniment. Transfert direct
depuis le poste de développement :

```bash
# Depuis le Mac
scp abbev-apple-keys.tar.gz user@serveur:/tmp/

# Sur le serveur
cd /chemin/vers/ABBEV/storage/app/private
tar -xzf /tmp/abbev-apple-keys.tar.gz
chmod 600 apple/SubscriptionKey_T43F9LUGCZ.p8
chown www-data:www-data apple/*     # adapter à l'utilisateur PHP
rm /tmp/abbev-apple-keys.tar.gz
```

Contrôle d'intégrité (doit correspondre au poste de dev) :

```bash
shasum -a 256 apple/*
# b1747ca3114e0c4691c392ce3f00087d1912037df6d94b758c431e0a6f50f98c  SubscriptionKey_T43F9LUGCZ.p8
# 9a30e66217898cbc3fff23756803bb135c486b62b5d102e89338f39a3043c54f  AppleRootCA-G3.pem
```

## 4. Configurer le `.env`

```dotenv
APPLE_IAP_BUNDLE_ID=com.abbev.abbev
APPLE_IAP_ISSUER_ID=24116d70-a3a5-427a-a334-4ca45f2c8654
APPLE_IAP_KEY_ID=T43F9LUGCZ
APPLE_IAP_KEY_PATH=/chemin/absolu/vers/ABBEV/storage/app/private/apple/SubscriptionKey_T43F9LUGCZ.p8
APPLE_IAP_ROOT_CERT_PATH=/chemin/absolu/vers/ABBEV/storage/app/private/apple/AppleRootCA-G3.pem
APPLE_IAP_SANDBOX=false

EXCHANGERATE_API_KEY=<clé ExchangeRate-API>
EXCHANGERATE_BASE=XOF
```

Points d'attention :

- **Chemins absolus obligatoires** — `resolvePrivateKey()` fait un
  `is_readable()` direct, sans résolution relative.
- **`APPLE_IAP_SANDBOX=false`** en production. Le code bascule automatiquement
  sur l'autre environnement en cas de 4xx, mais partir du bon côté évite un
  aller-retour réseau à chaque vérification.
- Laisser `APPLE_IAP_PRIVATE_KEY` **vide** : le chemin suffit. (Alternative sur
  hébergement mutualisé sans accès disque : y coller le contenu du `.p8`, sauts
  de ligne échappés en `\n`.)
- La clé ExchangeRate-API a été exposée en clair pendant le développement :
  **la régénérer** sur exchangerate-api.com avant la mise en production.

Puis :

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 5. Vérifier la configuration Apple

```bash
php artisan tinker --execute="new \App\Services\AppleAppStoreService();"
tail -1 storage/logs/laravel.log
```

Attendu — aucune valeur ne doit être `MANQUANT` :

```
[AppleIAP] Configuration chargée {"bundle_id":"com.abbev.abbev","issuer_id":"défini",
"key_id":"T43F9LUGCZ","private_key":"lisible","environnement":"production (...)"}
```

Test d'authentification réelle auprès d'Apple :

```bash
php artisan tinker --execute="
\$r = (new \App\Services\AppleAppStoreService())->getTransaction('0000000000000000');
print(\$r['message'] ?? 'ok');
"
grep 'GET transaction' storage/logs/laravel.log | tail -2
```

Lecture du résultat :

- **HTTP 400** (`Invalid transaction id`) → **le JWT est accepté**, la clé
  fonctionne. C'est le résultat recherché : seul l'identifiant bidon est rejeté.
- **HTTP 401 partout** → clé, Issuer ID ou Key ID invalides. Bloquant.

## 6. Vérifier les pages légales

```bash
curl -o /dev/null -w "%{http_code}\n" https://admin.abbev.tv/conditions-utilisation
curl -o /dev/null -w "%{http_code}\n" https://admin.abbev.tv/confidentialite
```

Les deux doivent renvoyer **200**, sans authentification. Apple ouvre ces liens
pendant la review ; un 404 est un rejet.

Vérifier aussi le contenu de `app/Http/Controllers/LegalController.php` :
`CONTACT_EMAIL` (`contact@abbev.tv`) et la juridiction (Douala) doivent
correspondre à la réalité juridique d'ESTUAIRE SERVICES SARL.

## 7. Vérifier l'API des plans

```bash
curl -s https://admin.abbev.tv/api/v1/subscription-plans | python3 -m json.tool | head -30
```

Chaque plan doit porter un `appleProductId` non nul.

## 8. Taux de change

```bash
php artisan tinker --execute="
print((new \App\Services\ExchangeRateService())->updateCurrencies() . ' devises mises à jour');
"
```

Vérifier que la tâche planifiée tourne (`routes/console.php`) et que le cron
Laravel est actif :

```bash
crontab -l | grep schedule:run
# * * * * * cd /chemin/vers/ABBEV && php artisan schedule:run >> /dev/null 2>&1
```

Sans ce cron, les taux se figent : les prix affichés sur Android dérivent
progressivement de la réalité.

## 9. Webhook Apple

Déjà déclaré dans App Store Connect (App Information → App Store Server
Notifications), **Version 2**, Production et Sandbox :

```
https://admin.abbev.tv/api/webhooks/apple
```

Prérequis côté serveur :

- HTTPS avec certificat valide (Apple refuse HTTP et l'auto-signé)
- route publique, **sans** `auth:sanctum` — Apple ne présente aucun token,
  c'est la signature JWS qui authentifie
- `APPLE_IAP_ROOT_CERT_PATH` renseigné (étape 4), sinon toute notification est
  rejetée à la vérification de signature

Test : bouton **Test** dans App Store Connect, puis

```bash
tail -f storage/logs/laravel.log | grep "Apple notification"
```

Sans ce webhook, les renouvellements mensuels ne prolongent pas l'accès : les
premiers abonnés perdent leur accès au mois 2 tout en étant débités.

---

## Côté application mobile (avant `flutter build ipa`)

1. **`lib/app/data/services/api_client.dart`** — basculer sur la ligne PROD :
   ```dart
   return override.isNotEmpty ? override : 'https://admin.abbev.tv/api/v1';
   ```
   Une IP LAN rend l'app inutilisable chez le reviewer → rejet Guideline 2.1.
   Cette base conditionne aussi les URLs des pages légales.

2. **`ios/Runner/Info.plist`** — retirer les exceptions ATS de développement
   (`192.168.1.152`, `localhost`) sous `NSExceptionDomains`. Elles ne sont plus
   utiles en HTTPS et signalent une configuration de développement.

3. **App Store Connect** — pour chacun des trois abonnements : prix,
   localisations FR/EN, et le screenshot du paywall. Le groupe « ABBEV Access »
   part obligatoirement avec un nouveau build (premier groupe d'abonnements).

---

## Diagnostic

Tout le parcours est journalisé.

```bash
tail -f storage/logs/laravel.log | grep -E "AppleIAP|SubscriptionPayment"
```

Parcours nominal d'un achat :

```
[SubscriptionPayment] Apple verify — requête reçue
[AppleIAP] GET transaction {"env":"production","status":200}
[AppleIAP] ✅ Transaction valide
[SubscriptionPayment] Apple — provisionnement d'un nouvel abonnement
[SubscriptionPayment] 🎟️ Abonnement provisionné
```

| Symptôme | Cause |
|---|---|
| `Configuration Apple IAP invalide` | `.p8`, Issuer ID ou Key ID absent — étape 4 |
| `401` sur les deux environnements | clé révoquée ou Issuer ID erroné |
| `❌ bundleId différent` | `APPLE_IAP_BUNDLE_ID` ≠ bundle de l'app installée |
| `Apple product not mapped` | seeder non lancé (étape 2), ou Product ID divergent |
| `payload incomplet` | notification v1 au lieu de v2 dans App Store Connect |
| Achat validé, accès absent | vérifier `🎟️ Abonnement provisionné` et `expires_at` |

---

## Points non testés en développement

Le parcours d'achat a été validé sur simulateur avec une configuration StoreKit
locale : prix, sélection, feuille Apple et `purchaseStream` fonctionnent. En
revanche, **la vérification du reçu auprès d'Apple et le provisionnement de
l'abonnement n'ont jamais tourné** — StoreKit local produit des identifiants de
transaction fictifs (`"1"`, `"2"`…) qu'Apple ne reconnaît pas.

La configuration est vérifiée (JWT accepté en sandbox) et le code est en place,
mais ce maillon sera exercé pour la première fois en conditions réelles.
Idéalement, valider avec un compte Sandbox sur un iPhone physique avant
soumission — sinon, surveiller de près les logs des premiers achats.
