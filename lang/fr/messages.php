<?php

/**
 * Messages d'API renvoyés à l'app mobile (champ `message` des réponses JSON).
 *
 * L'app affiche ce champ TEL QUEL quand il est présent : toute chaîne ajoutée
 * ici doit donc être rédigée pour un utilisateur final, pas pour un
 * développeur. Les messages purement machine (webhooks Stripe/Apple) restent
 * en dur dans les contrôleurs — ils ne sont jamais montrés à personne.
 *
 * Toute clé ajoutée ici DOIT l'être aussi dans `lang/en/messages.php`
 * (le test `LocalizationTest` échoue sinon).
 */
return [

    // ---- Authentification / compte ------------------------------------
    'auth' => [
        'otp_sent'            => 'Code envoyé par email',
        'otp_send_failed'     => "Erreur lors de l'envoi de l'email. Veuillez réessayer.",
        'account_suspended'   => 'Ce compte a été suspendu. Contactez le support.',
        'reset_code_sent'     => 'Si un compte existe pour cet email, un code de réinitialisation a été envoyé.',
        'code_valid'          => 'Code valide.',
        'password_reset'      => 'Mot de passe réinitialisé avec succès.',
        'logged_out'          => 'Déconnexion réussie.',
        'unauthenticated'     => 'Non authentifié.',
        'forbidden_admin'     => 'Accès interdit : rôle administrateur requis.',
        'history_saved'       => 'Historique enregistré.',
    ],

    // ---- Catalogue / lecture ------------------------------------------
    'content' => [
        'not_found'            => 'Contenu introuvable.',
        'subscription_required' => 'Un abonnement actif est requis pour visionner ce contenu.',
        'rubrique_upgrade'     => 'Cette rubrique nécessite un abonnement supérieur.',
        'rubrique_not_found'   => 'Rubrique introuvable.',
        'download_unavailable' => "Ce contenu n'est pas disponible en téléchargement.",
        'download_disabled'    => "Le téléchargement n'est pas disponible pour le moment.",
        'download_misconfigured' => "Le téléchargement n'est pas disponible (configuration serveur incomplète).",
    ],

    // ---- Ma liste ------------------------------------------------------
    'list' => [
        'added'   => 'Ajouté à votre liste.',
        'removed' => 'Retiré de votre liste.',
    ],

    // ---- Paiements / abonnement ---------------------------------------
    'payment' => [
        'succeeded'          => 'Paiement effectué avec succès',
        'failed'             => 'Le paiement a échoué.',
        'processing'         => 'Paiement en cours de traitement.',
        'crypto_processing'  => 'Paiement crypto en cours de traitement.',
        'validate_on_phone'  => 'Veuillez valider le paiement sur votre téléphone.',
        'dial_ussd'          => 'Veuillez composer le code USSD affiché sur votre téléphone',
        'init_failed'        => "Erreur lors de l'initialisation du paiement",
        'capture_failed'     => 'Erreur lors de la capture du paiement',
        'confirm_failed'     => 'Erreur lors de la confirmation du paiement',
        'status_check_failed' => 'Erreur lors de la vérification du statut',
        'paypal_unavailable' => "Le paiement PayPal n'est pas disponible pour le moment. Choisissez Mobile Money ou réessayez plus tard.",
        'card_unavailable'   => "Le paiement par carte n'est pas disponible pour le moment.",
        'crypto_unavailable' => "Le paiement crypto n'est pas disponible pour le moment.",
        'transaction_not_found' => 'Transaction introuvable.',
        'subscription_activated' => 'Abonnement activé',
        'apple_verify_failed' => 'Erreur lors de la vérification Apple',
        'apple_invalid'      => 'Transaction Apple invalide ou révoquée',
        'apple_plan_missing' => 'Plan introuvable pour ce produit Apple',
        'stripe_init_failed' => "Échec de l'initialisation Stripe.",
        'stripe_verify_failed' => 'Vérification Stripe impossible.',
        'kpay_init_failed'   => "Échec de l'initialisation KPay.",
        'kpay_country_unavailable' => "KPay n'est pas disponible dans votre pays (:country).",
        'operator_unavailable' => "Cet opérateur n'est pas disponible pour :country.",
        'card_init_failed'   => "Impossible d'initier le paiement par carte.",
        'paypal_init_failed' => "Impossible d'initier le paiement PayPal.",
        'crypto_init_failed' => "Impossible d'initier le paiement crypto.",
        'crypto_min_amount'  => 'Montant minimum pour un paiement crypto : :min FCFA. Certaines cryptos (BTC, ETH) ont des frais réseau élevés qui imposent un minimum plus haut.',
        'crypto_amount_too_low' => "Ce montant est trop faible pour un paiement crypto. Le minimum est d'environ :min FCFA (les frais réseau Bitcoin/Ethereum imposent un seuil élevé). Choisissez un montant plus important.",
        'apple_verify_rejected' => 'Vérification Apple échouée.',
        'verify_failed'      => 'Erreur lors de la vérification.',
    ],

    // ---- Réservations cinéma ------------------------------------------
    'reservation' => [
        'created'   => 'Réservation créée. Procédez au paiement pour la confirmer.',
        'confirmed' => 'Réservation confirmée.',
        'cancelled' => 'Réservation annulée.',
        'not_found' => 'Réservation introuvable.',
        'quantity_min'      => 'La quantité doit être au moins 1.',
        'not_enough_seats'  => 'Plus assez de places disponibles dans cette catégorie.',
        'cancelled_cannot_confirm' => 'Réservation annulée, impossible de confirmer.',
        'not_enough_to_confirm' => 'Plus assez de places pour confirmer cette réservation.',
    ],

    // ---- Administration (panel) ----------------------------------------
    'admin' => [
        'media_deleted'    => 'Média supprimé.',
        'category_deleted' => 'Catégorie supprimée.',
        'season_deleted'   => 'Saison supprimée.',
        'episode_deleted'  => 'Épisode supprimé.',
    ],

    // ---- Validation (messages personnalisés) ---------------------------
    'validation' => [
        'name_required'      => 'Le nom est requis.',
        'email_required'     => "L'adresse email est requise.",
        'email_invalid'      => "L'adresse email n'est pas valide.",
        'email_taken'        => 'Cette adresse email est déjà utilisée.',
        'password_required'  => 'Le mot de passe est requis.',
        'password_min'       => 'Le mot de passe doit contenir au moins 8 caractères.',
        'password_confirmed' => 'Les mots de passe ne correspondent pas.',
        'country_required'   => 'Veuillez sélectionner votre pays.',
        'country_invalid'    => 'Pays invalide.',
    ],
];
