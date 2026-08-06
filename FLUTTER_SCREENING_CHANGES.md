# Changements API Screenings - Impact Flutter

## Contexte
Les screenings (seances) ont maintenant un pays associe. L'API filtre automatiquement par pays du user et les tickets portent la devise reelle du pays.

## Changements API

### GET /api/screenings (liste des seances)
- **Filtre automatique par pays** : l'API ne renvoie que les seances du pays du user (`user.country_code`). Aucun param supplementaire a envoyer.
- **Nouveau champ dans la reponse** : `country_code` (string, ISO 2 lettres)

Exemple de reponse :
```json
{
  "id": 25,
  "movie_title": "The Dark Knight",
  "cinema_name": "AMC Theater",
  "location": "Los Angeles, CA",
  "country_code": "US",
  "starts_at": "2026-08-10T19:00:00+00:00",
  "ticket_types": [
    {
      "id": 57,
      "name": "Standard",
      "price": 12.99,
      "currency": "USD",
      "currency_symbol": "$",
      "currency_decimals": 2,
      "capacity": 150,
      "available_seats": 150,
      "sold_out": false
    }
  ]
}
```

### Ce qui change pour Flutter

1. **Affichage des prix** : utiliser `currency`, `currency_symbol` et `currency_decimals` de chaque `ticket_type` (deja present dans l'API, mais maintenant la devise varie reellement : USD, XAF, EUR, etc. au lieu d'etre toujours XAF).

2. **Aucune seance visible ?** Si le user n'a pas de `country_code` ou qu'aucune seance n'existe pour son pays, la liste sera vide. Prevoir un message adapte (ex: "Aucune seance disponible dans votre pays").

3. **Paiement Stripe** : aucun changement cote Flutter. Le backend passe maintenant la bonne devise au PaymentIntent automatiquement.

4. **Modele Screening** : ajouter le champ optionnel `countryCode` au modele Dart si vous parsez la reponse :
```dart
final String? countryCode;
```

## Resume
| Avant | Apres |
|-------|-------|
| Toutes les seances visibles par tous | Seances filtrees par pays du user |
| Prix toujours en XAF | Prix dans la devise du pays de la seance |
| Stripe recevait XAF par defaut | Stripe recoit la devise reelle du ticket |
