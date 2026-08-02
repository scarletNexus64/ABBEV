<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * Pages légales publiques : conditions d'utilisation et politique de
 * confidentialité.
 *
 * Elles doivent rester accessibles SANS authentification : elles sont ouvertes
 * depuis l'écran d'abonnement de l'app mobile, et surtout par le reviewer
 * Apple, qui vérifie ces deux liens avant d'approuver un abonnement
 * auto-renouvelable (guideline 3.1.2). Un lien mort = rejet.
 *
 * L'URL de la politique de confidentialité doit également être renseignée à
 * l'identique dans la fiche App Store Connect de l'app.
 */
class LegalController extends Controller
{
    /** Raison sociale de l'éditeur, affichée en pied de page. */
    private const COMPANY = 'ESTUAIRE SERVICES SARL';

    /** Adresse de contact pour les demandes légales et RGPD. */
    private const CONTACT_EMAIL = 'contact@abbev.tv';

    /**
     * Date de dernière révision des textes. À mettre à jour à la main lors
     * d'une modification de fond : les utilisateurs et Apple s'y réfèrent
     * pour savoir quelle version ils ont acceptée.
     */
    private const UPDATED_AT = '2 août 2026';

    public function terms(): View
    {
        return view('legal.terms', $this->sharedData());
    }

    public function privacy(): View
    {
        return view('legal.privacy', $this->sharedData());
    }

    /**
     * @return array<string,string>
     */
    private function sharedData(): array
    {
        return [
            'company'      => self::COMPANY,
            'contactEmail' => self::CONTACT_EMAIL,
            'updatedAt'    => self::UPDATED_AT,
        ];
    }
}
