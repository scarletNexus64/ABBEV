@extends('legal.layout')

@section('title', 'Conditions d\'utilisation')

@section('content')

<p>
    Les présentes conditions régissent l'utilisation de l'application et du
    service ABBEV, édités par {{ $company }}. En créant un compte ou en
    souscrivant un abonnement, vous les acceptez sans réserve.
</p>

<h2>1. Le service</h2>
<p>
    ABBEV est un service de vidéo à la demande par abonnement. Il donne accès à
    un catalogue de films, séries et contenus audiovisuels mis à disposition par
    des producteurs et ayants droit partenaires, pour un visionnage en streaming
    à usage strictement personnel et privé.
</p>
<p>
    L'abonnement confère un droit d'accès temporaire, non exclusif et non
    transférable. Il n'emporte aucun transfert de propriété sur les contenus.
</p>

<h2>2. Compte utilisateur</h2>
<ul>
    <li>Vous devez avoir au moins 16 ans, ou disposer de l'autorisation d'un représentant légal.</li>
    <li>Les informations fournies à l'inscription doivent être exactes et tenues à jour.</li>
    <li>Vos identifiants sont personnels : vous répondez de toute activité effectuée depuis votre compte.</li>
    <li>Prévenez-nous sans délai à {{ $contactEmail }} en cas d'utilisation non autorisée.</li>
</ul>

<h2>3. Abonnements et tarifs</h2>
<p>
    ABBEV propose trois formules mensuelles, qui se distinguent par l'étendue du
    catalogue accessible :
</p>

<table>
    <thead>
        <tr>
            <th>Formule</th>
            <th>Durée</th>
            <th>Tarif de référence</th>
        </tr>
    </thead>
    <tbody>
        <tr><td>Classique</td><td>1 mois</td><td>1 000 FCFA</td></tr>
        <tr><td>Standard</td><td>1 mois</td><td>2 500 FCFA</td></tr>
        <tr><td>Premium</td><td>1 mois</td><td>5 000 FCFA</td></tr>
    </tbody>
</table>

<p>
    Les tarifs ci-dessus sont exprimés en francs CFA. Le montant qui vous est
    effectivement facturé est affiché dans votre devise avant toute validation,
    et peut varier selon les taux de change et la fiscalité applicable dans
    votre pays.
</p>

<div class="card">
    <p>
        <strong>Achats effectués sur iOS.</strong> Lorsque vous souscrivez
        depuis un appareil Apple, l'achat est réalisé via votre compte Apple et
        soumis aux conditions de l'App Store. Le prix affiché par Apple au
        moment de l'achat prévaut sur les tarifs de référence indiqués ci-dessus.
    </p>
</div>

<h2>4. Renouvellement automatique</h2>
<p>
    Les abonnements sont reconduits automatiquement pour une durée identique,
    sauf résiliation avant la fin de la période en cours.
</p>
<ul>
    <li>Le paiement est prélevé à la confirmation de la souscription.</li>
    <li>Le renouvellement est facturé dans les 24 heures qui précèdent la fin de la période en cours.</li>
    <li>Le montant du renouvellement est celui de la formule en vigueur à cette date.</li>
</ul>

<h2>5. Résiliation</h2>
<p>
    Vous pouvez résilier à tout moment. La résiliation prend effet à la fin de
    la période déjà payée : l'accès reste ouvert jusqu'à cette échéance, et
    aucun remboursement au prorata n'est effectué pour la période entamée.
</p>
<p>
    Pour un abonnement souscrit sur iOS, la gestion et la résiliation
    s'effectuent depuis <em>Réglages → [votre nom] → Abonnements</em> sur votre
    appareil Apple. Désinstaller l'application ne suffit pas à résilier
    l'abonnement.
</p>
<p>
    Pour un abonnement souscrit par un autre moyen de paiement, la résiliation
    s'effectue depuis votre espace personnel dans l'application, ou sur demande
    à {{ $contactEmail }}.
</p>

<h2>6. Usage autorisé</h2>
<p>Il vous est interdit de :</p>
<ul>
    <li>copier, enregistrer, redistribuer ou diffuser publiquement les contenus ;</li>
    <li>contourner les mesures techniques de protection ou de géolocalisation ;</li>
    <li>partager vos identifiants ou revendre l'accès à votre compte ;</li>
    <li>utiliser des moyens automatisés pour extraire les contenus ou surcharger le service.</li>
</ul>
<p>
    Tout manquement peut entraîner la suspension immédiate du compte, sans
    remboursement.
</p>

<h2>7. Contenus des partenaires</h2>
<p>
    Les contenus sont fournis par des producteurs et ayants droit partenaires,
    qui garantissent détenir les droits nécessaires à leur diffusion. Le
    catalogue est susceptible d'évoluer : des titres peuvent être ajoutés ou
    retirés en cours d'abonnement, notamment à l'expiration d'une licence.
</p>

<h2>8. Disponibilité du service</h2>
<p>
    Nous mettons en œuvre les moyens raisonnables pour assurer la continuité du
    service, sans pouvoir garantir une disponibilité ininterrompue. Des
    interruptions peuvent survenir pour maintenance, mise à jour ou cause
    extérieure. La qualité du streaming dépend par ailleurs de votre connexion
    et de votre appareil.
</p>

<h2>9. Responsabilité</h2>
<p>
    Le service est fourni en l'état. Dans les limites permises par la loi, notre
    responsabilité est plafonnée au montant des sommes que vous avez versées au
    cours des douze derniers mois. Aucune stipulation des présentes ne limite
    les droits que la loi vous reconnaît en tant que consommateur.
</p>

<h2>10. Modification des conditions</h2>
<p>
    Ces conditions peuvent être modifiées. Toute évolution substantielle vous
    sera signalée dans l'application ou par courriel avant son entrée en vigueur.
    Poursuivre l'utilisation du service après cette date vaut acceptation.
</p>

<h2>11. Droit applicable</h2>
<p>
    Les présentes conditions sont régies par le droit camerounais. Tout litige
    sera porté devant les juridictions compétentes de Douala, sous réserve des
    règles impératives protégeant les consommateurs.
</p>

<h2>12. Contact</h2>
<p>
    {{ $company }} — {{ $contactEmail }}
</p>

@endsection
