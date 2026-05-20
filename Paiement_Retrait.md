Paiements & Retrait

Créer, suivre et encaisser des paiements Mobile Money en mode USSD ou via la passerelle hébergée.

L'objet Paiement
Objet Paiement
Copier
{
  "id": "pay_abc123",
  "reference": "KPAY-20260514-ABC123",
  "providerReference": "ref_a1b2c3",
  "status": "COMPLETED",
  "amount": 5000,
  "netAmount": 4900,
  "feeAmount": 100,
  "currency": "XAF",
  "externalId": "ORDER-12345",
  "paymentMethod": "MTN_MONEY",
  "phoneNumber": "237670000001",
  "description": "Paiement commande #12345",
  "metadata": { "orderId": "12345" },
  "createdAt": "2026-05-14T10:00:00.000Z",
  "completedAt": "2026-05-14T10:02:30.000Z",
  "failedAt": null,
  "failureReason": null
}
Propriétés

id
string
optionnel
Identifiant unique du paiement.

reference
string
optionnel
Référence interne KPAY (source de vérité).

status
string
optionnel
Statut courant du paiement.

PENDING
PROCESSING
COMPLETED
FAILED
CANCELLED
amount
number
optionnel
Montant brut demandé, en XAF.

netAmount
number
optionnel
Montant net crédité après commission (paiement).

feeAmount
number
optionnel
Commission prélevée.

externalId
string
optionnel
Votre identifiant de transaction (idempotence).

metadata
object
optionnel
Données libres renvoyées telles quelles.

completedAt
string | null
optionnel
Horodatage de complétion (si COMPLETED).

failureReason
string | null
optionnel
Motif d'échec (si FAILED).

Cycle de vie & statuts
PENDING — en attente de validation par le client.
PROCESSING — traitement par le fournisseur en cours.
COMPLETED — réussi (montant net disponible dans le wallet).
FAILED — échec (fonds insuffisants, timeout…).
CANCELLED — annulé par le client.
Mode USSD — Initier un paiement
POST
/api/v1/payments/init

paymentMethod obligatoire (USSD)

L'auto-détection de l'opérateur a été supprimée. En mode USSD, paymentMethod (MTN_MONEY ou ORANGE_MONEY) est requis ; son absence renvoie un 400.
Corps de la requête

amount
number
requis
Montant en XAF, minimum 50. Une commission est prélevée.

externalId
string
requis
Votre identifiant unique de transaction. 409 si déjà actif.

phoneNumber
string
requis
Numéro Mobile Money. Formats : 6XXXXXXXX, 06XXXXXXXX, 237XXXXXXXXX (ou 6XXXXXX en sandbox).

paymentMethod
enum
requis
Opérateur Mobile Money.

MTN_MONEY
ORANGE_MONEY
description
string
optionnel
Description du paiement présentée au client.

customerName
string
optionnel
Nom complet du client.

customerEmail
string
optionnel
Email du client.

metadata
object
optionnel
Métadonnées JSON libres, renvoyées dans le statut.

Exemple de requête
Node.js
PHP
Python
Go
Dart
PHP
Copier
$ch = curl_init("https://admin.kpay.site/api/v1/payments/init");
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST => true,
  CURLOPT_HTTPHEADER => [
    "X-API-Key: " . getenv("KPAY_API_KEY"),
    "X-Secret-Key: " . getenv("KPAY_SECRET_KEY"),
    "Content-Type: application/json",
  ],
  CURLOPT_POSTFIELDS => json_encode([
    "amount" => 5000,
    "paymentMethod" => "MTN_MONEY",
    "phoneNumber" => "6770001",
    "externalId" => "ORDER-12345",
  ]),
]);
$data = json_decode(curl_exec($ch), true);
Réponse (201)
201 Created
Copier
{
  "id": "pay_abc123",
  "reference": "KPAY-20260514-ABC123",
  "status": "PENDING",
  "amount": 5000,
  "currency": "XAF",
  "externalId": "ORDER-12345",
  "isTest": true,
  "message": "Sandbox payment received. Will complete automatically in ~3 seconds."
}
Mode passerelle hébergée (GATEWAY)
En mode GATEWAY, KPay héberge la page de paiement : le client y saisit lui-même son opérateur et son numéro. Appelez /api/v1/payments/init sans phoneNumber / paymentMethod / customerName, avec returnUrl (requis) et cancelUrl (optionnel).

Exemple de requête
Node.js
PHP
Python
Go
Dart
PHP
Copier
$ch = curl_init("https://admin.kpay.site/api/v1/payments/init");
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST => true,
  CURLOPT_HTTPHEADER => [
    "X-API-Key: " . getenv("KPAY_API_KEY"),
    "X-Secret-Key: " . getenv("KPAY_SECRET_KEY"),
    "Content-Type: application/json",
  ],
  CURLOPT_POSTFIELDS => json_encode([
    "amount" => 5000,
    "externalId" => "ORDER-12346",
    "returnUrl" => "https://monsite.com/return",
    "cancelUrl" => "https://monsite.com/cancel",
  ]),
]);
$data = json_decode(curl_exec($ch), true);
Réponse en mode GATEWAY
201 Created
Copier
{
  "id": "pay_xyz789",
  "reference": "KPAY-20260514-XYZ789",
  "externalId": "ORDER-12346",
  "status": "PENDING",
  "mode": "GATEWAY",
  "amount": 5000,
  "currency": "XAF",
  "gatewayUrl": "https://admin.kpay.site/gateway/gw_8sJ2...",
  "expiresAt": "2026-05-16T10:30:00.000Z",
  "isTest": false,
  "message": "Redirect the customer to gatewayUrl to complete the payment."
}
Redirection de retour (query signée)
text
Copier
{returnUrl}?status=COMPLETED&reference=KPAY-20260514-ABC123&externalId=ORDER-12345&ts=1747245600000&sig=<hmac-sha256-hex>
Vérification de la signature (côté serveur)
Règle d'or

Ne marquez la commande payée qu'après signature valide ET statut COMPLETED confirmé via GET /api/v1/payments/:id. Rejetez si ts a plus de 10 minutes (anti-rejeu).
Node.js
PHP
Python
Go
Dart
PHP
Copier
function verifyReturn(array $query, string $gatewaySecret): bool
{
    $stringToSign = ($query['status'] ?? '') . '|' . ($query['reference'] ?? '')
        . '|' . ($query['externalId'] ?? '') . '|' . ($query['ts'] ?? '');
    $expected = hash_hmac('sha256', $stringToSign, $gatewaySecret);

    return hash_equals($expected, $query['sig'] ?? '')
        && (round(microtime(true) * 1000) - (int) ($query['ts'] ?? 0)) < 10 * 60 * 1000;
}
Suivi du statut (polling)
GET
/api/v1/payments/:id

Interrogez ce endpoint pour connaître le statut courant. Espacez les appels (ex. toutes les 3 s) avec un délai croissant, et arrêtez-vous sur un statut terminal (COMPLETED, FAILED, CANCELLED). Le webhook reste la source d'autorité.

Node.js
PHP
Python
Go
Dart
PHP
Copier
$ch = curl_init("https://admin.kpay.site/api/v1/payments/pay_abc123");
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => [
    "X-API-Key: " . getenv("KPAY_API_KEY"),
    "X-Secret-Key: " . getenv("KPAY_SECRET_KEY"),
  ],
]);
$data = json_decode(curl_exec($ch), true);
Ressources liées

Ressources principales

Retraits
Envoyer des fonds depuis votre wallet vers un compte Mobile Money, en mode USSD ou via la passerelle hébergée.

L'objet Retrait
Objet Retrait
Copier
{
  "id": "wdr_xyz456",
  "reference": "KPAY-WD-20260514-XYZ456",
  "providerReference": "FMP-WD-AAA999",
  "status": "COMPLETED",
  "amount": 5000,
  "netAmount": 4750,
  "feeAmount": 250,
  "currency": "XAF",
  "externalId": "WD-ORDER-98765",
  "paymentMethod": "MOBILE_MONEY",
  "phoneNumber": "237670000001",
  "description": "Retrait commission — mai 2026",
  "metadata": { "payoutCycle": "2026-05" },
  "createdAt": "2026-05-14T10:00:00.000Z",
  "completedAt": "2026-05-14T10:01:45.000Z",
  "failedAt": null,
  "failureReason": null
}
Propriétés

id
string
optionnel
Identifiant unique du retrait.

reference
string
optionnel
Référence interne KPAY.

status
string
optionnel
Statut courant.

PENDING
PROCESSING
COMPLETED
FAILED
CANCELLED
amount
number
optionnel
Montant brut demandé, en XAF.

netAmount
number
optionnel
Montant net envoyé au bénéficiaire après commission.

feeAmount
number
optionnel
Commission de retrait prélevée.

externalId
string
optionnel
Votre identifiant (idempotence, retry-safe).

Mode USSD — Initier un retrait
POST
/api/v1/payments/withdraw

Corps de la requête

amount
number
requis
Montant en XAF, minimum 100. Une commission est prélevée.

phoneNumber
string
requis
Numéro Mobile Money du bénéficiaire (mode USSD).

paymentMethod
enum
requis
Opérateur du bénéficiaire (mode USSD).

MTN_MONEY
ORANGE_MONEY
externalId
string
optionnel
Identifiant unique — active l'idempotence (réessai sûr).

description
string
optionnel
Description pour réconciliation.

metadata
object
optionnel
Métadonnées JSON libres.

Exemple de requête
Node.js
PHP
Python
Go
Dart
PHP
Copier
$ch = curl_init("https://admin.kpay.site/api/v1/payments/withdraw");
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST => true,
  CURLOPT_HTTPHEADER => [
    "X-API-Key: " . getenv("KPAY_API_KEY"),
    "X-Secret-Key: " . getenv("KPAY_SECRET_KEY"),
    "Content-Type: application/json",
  ],
  CURLOPT_POSTFIELDS => json_encode([
    "amount" => 5000,
    "paymentMethod" => "MTN_MONEY",
    "phoneNumber" => "6770001",
    "externalId" => "WD-ORDER-98765",
  ]),
]);
$data = json_decode(curl_exec($ch), true);
Réponse (201)
201 Created
Copier
{
  "id": "wdr_xyz456",
  "reference": "KPAY-WD-20260514-XYZ456",
  "status": "PENDING",
  "amount": 5000,
  "netAmount": 4750,
  "feeAmount": 250,
  "currency": "XAF",
  "externalId": "WD-ORDER-98765",
  "isTest": true,
  "message": "Sandbox withdrawal received. Will complete automatically in ~3 seconds."
}
Mode passerelle hébergée (GATEWAY)
Appelez /api/v1/payments/withdraw sans phoneNumber / paymentMethod, avec returnUrl (requis) et cancelUrl (optionnel). Le bénéficiaire saisit ses informations sur la page hébergée KPay.

Exemple de requête
Node.js
PHP
Python
Go
Dart
PHP
Copier
$ch = curl_init("https://admin.kpay.site/api/v1/payments/withdraw");
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST => true,
  CURLOPT_HTTPHEADER => [
    "X-API-Key: " . getenv("KPAY_API_KEY"),
    "X-Secret-Key: " . getenv("KPAY_SECRET_KEY"),
    "Content-Type: application/json",
  ],
  CURLOPT_POSTFIELDS => json_encode([
    "amount" => 5000,
    "externalId" => "WD-ORD-001",
    "returnUrl" => "https://monsite.com/return",
  ]),
]);
$data = json_decode(curl_exec($ch), true);
Réponse withdraw en mode GATEWAY
Copier
{
  "id": "wdr_abc123",
  "reference": "KPAY-WD-20260514-ABC123",
  "externalId": "WD-ORD-001",
  "status": "PENDING",
  "mode": "GATEWAY",
  "amount": 5000,
  "netAmount": 4750,
  "feeAmount": 250,
  "currency": "XAF",
  "gatewayUrl": "https://admin.kpay.site/gateway/gw_9aB3...",
  "expiresAt": "2026-05-16T10:45:00.000Z",
  "isTest": false,
  "message": "Redirect the beneficiary to gatewayUrl to enter withdrawal details."
}
Signature de retour

Le retour passerelle d'un retrait utilise le même schéma de signature que les paiements (status|reference|externalId|ts). Voir Vérification de la signature.
Suivi du statut
GET
/api/v1/payments/withdraw/:id

Node.js
PHP
Python
Go
Dart
PHP
Copier
$ch = curl_init("https://admin.kpay.site/api/v1/payments/withdraw/wdr_xyz456");
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => [
    "X-API-Key: " . getenv("KPAY_API_KEY"),
    "X-Secret-Key: " . getenv("KPAY_SECRET_KEY"),
  ],
]);
$data = json_decode(curl_exec($ch), true);
Solde insuffisant

Si le solde du wallet ne couvre pas le montant (commission incluse), l'initialisation renvoie 422 Unprocessable Entity.