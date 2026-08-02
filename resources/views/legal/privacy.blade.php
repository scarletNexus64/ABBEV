@extends('legal.layout')

@section('title', 'Politique de confidentialité')

@section('content')

<p>
    Cette politique décrit les données personnelles que {{ $company }} collecte
    dans le cadre du service ABBEV, l'usage qui en est fait, et les droits dont
    vous disposez.
</p>

<h2>1. Données collectées</h2>

<h3 style="font-size:16px;margin:20px 0 6px;">Données que vous fournissez</h3>
<ul>
    <li><strong>Compte</strong> : nom, adresse e-mail, mot de passe (stocké sous forme chiffrée, jamais en clair).</li>
    <li><strong>Coordonnées</strong> : numéro de téléphone, lorsqu'il est utilisé pour la vérification ou le paiement mobile.</li>
    <li><strong>Photo de profil</strong>, si vous choisissez d'en ajouter une.</li>
    <li><strong>Pays et devise</strong>, pour afficher les tarifs et les contenus disponibles chez vous.</li>
</ul>

<h3 style="font-size:16px;margin:20px 0 6px;">Données générées par votre usage</h3>
<ul>
    <li><strong>Historique de visionnage</strong> : titres consultés et progression de lecture, pour vous permettre de reprendre où vous vous étiez arrêté.</li>
    <li><strong>Liste personnelle</strong> : contenus que vous enregistrez pour plus tard.</li>
    <li><strong>Transactions</strong> : montant, devise, moyen de paiement, date et statut de chaque opération.</li>
    <li><strong>Données techniques</strong> : journaux de connexion nécessaires à la sécurité et au diagnostic.</li>
</ul>

<div class="card">
    <p>
        <strong>Nous ne collectons pas vos données bancaires.</strong> Les
        numéros de carte et identifiants de paiement sont traités directement
        par nos prestataires (Apple, Stripe, PayPal, opérateurs de paiement
        mobile) et ne transitent jamais par nos serveurs. Nous ne conservons
        qu'une référence de transaction.
    </p>
</div>

<h2>2. Finalités</h2>
<p>Vos données servent exclusivement à :</p>
<ul>
    <li>créer et gérer votre compte, et vous authentifier ;</li>
    <li>fournir l'accès aux contenus et mémoriser votre progression ;</li>
    <li>traiter les paiements, abonnements et renouvellements ;</li>
    <li>calculer la rémunération due aux producteurs partenaires, à partir de statistiques de visionnage agrégées ;</li>
    <li>vous envoyer les messages nécessaires au service (vérification d'adresse, confirmation de paiement, échéance d'abonnement) ;</li>
    <li>prévenir la fraude et sécuriser le service ;</li>
    <li>respecter nos obligations comptables et légales.</li>
</ul>

<h2>3. Ce que nous ne faisons pas</h2>
<ul>
    <li>Nous ne vendons ni ne louons vos données personnelles.</li>
    <li>Nous ne les transmettons pas à des régies publicitaires.</li>
    <li>Nous ne pratiquons aucun suivi publicitaire inter-applications.</li>
    <li>Les producteurs partenaires reçoivent des statistiques agrégées et anonymes, jamais l'identité des spectateurs.</li>
</ul>

<h2>4. Destinataires</h2>
<p>
    Vos données ne sont communiquées qu'aux prestataires strictement nécessaires
    au fonctionnement du service, et uniquement pour ce qu'ils ont à traiter :
</p>
<ul>
    <li><strong>Prestataires de paiement</strong> — traitement des transactions.</li>
    <li><strong>Hébergeur et service de diffusion vidéo</strong> — stockage et distribution des contenus.</li>
    <li><strong>Service d'envoi d'e-mails</strong> — messages transactionnels.</li>
</ul>
<p>
    Ces prestataires agissent sur nos instructions et sont tenus à la
    confidentialité. Des données peuvent être transférées hors de votre pays de
    résidence ; nous nous assurons alors de l'existence de garanties
    appropriées.
</p>

<h2>5. Durée de conservation</h2>
<ul>
    <li><strong>Données de compte</strong> : conservées tant que le compte est actif.</li>
    <li><strong>Après suppression du compte</strong> : effacées sous 30 jours, à l'exception de ce qui suit.</li>
    <li><strong>Données de facturation</strong> : conservées 10 ans, conformément aux obligations comptables et fiscales.</li>
    <li><strong>Journaux techniques</strong> : 12 mois au plus.</li>
</ul>

<h2>6. Sécurité</h2>
<p>
    Les échanges entre l'application et nos serveurs sont chiffrés (HTTPS). Les
    mots de passe sont stockés sous forme de condensats non réversibles. L'accès
    aux données est restreint aux personnes qui en ont besoin. Aucun système
    n'étant infaillible, nous vous informerons sans délai en cas de violation de
    données susceptible de vous affecter.
</p>

<h2>7. Vos droits</h2>
<p>Vous pouvez à tout moment :</p>
<ul>
    <li><strong>Accéder</strong> à vos données et en obtenir une copie ;</li>
    <li><strong>Rectifier</strong> les informations inexactes, depuis votre profil ou sur demande ;</li>
    <li><strong>Supprimer</strong> votre compte et les données associées ;</li>
    <li><strong>Vous opposer</strong> à certains traitements ou en demander la limitation ;</li>
    <li><strong>Retirer votre consentement</strong>, sans effet rétroactif sur les traitements déjà réalisés.</li>
</ul>
<p>
    Écrivez à {{ $contactEmail }} : nous répondons sous 30 jours. La suppression
    du compte est également accessible directement depuis l'application.
</p>

<h2>8. Mineurs</h2>
<p>
    Le service n'est pas destiné aux enfants de moins de 16 ans. Nous ne
    collectons pas sciemment leurs données. Si vous constatez qu'un mineur nous
    a transmis des informations, signalez-le à {{ $contactEmail }} : nous les
    supprimerons.
</p>

<h2>9. Modifications</h2>
<p>
    Cette politique peut évoluer. Toute modification substantielle vous sera
    signalée dans l'application ou par courriel. La date de dernière mise à jour
    figure en haut de cette page.
</p>

<h2>10. Contact</h2>
<p>
    Pour toute question relative à vos données personnelles :<br>
    {{ $company }} — {{ $contactEmail }}
</p>

@endsection
