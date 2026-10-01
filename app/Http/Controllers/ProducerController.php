<?php

namespace App\Http\Controllers;

use App\Mail\ProducerCredentialsMail;
use App\Models\ProducerPlan;
use App\Models\ProducerSubscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Gestion des comptes "producteur" (réservée aux admins).
 *
 * À la création, un mot de passe fort est généré automatiquement puis ENVOYÉ
 * PAR EMAIL au producteur (il n'est jamais stocké en clair). Si l'envoi échoue,
 * on retombe sur l'affichage unique à l'admin pour qu'il le transmette à la main.
 * Le producteur se connecte ensuite sur /admin/login et gère tout son espace :
 * contenus + upload, œuvres, modération, audience, talents & casting, Awards,
 * formation, appels à projets, billetterie. Il y invite lui-même son équipe
 * (TeamController) ; les membres d'équipe n'apparaissent pas dans cette liste.
 */
class ProducerController extends Controller
{
    public function index()
    {
        $producers = User::where('role', 'producer')
            ->whereNull('producer_id')
            ->withCount(['media', 'teamMembers'])
            // Fin de l'accès payé ou offert (périodes enchaînées) : NULL = verrouillé.
            ->withMax(['producerSubscriptions as access_ends_at' => fn ($q) => $q->unexpired()], 'expires_at')
            ->latest()
            ->paginate(20);

        $paymentRequired = ProducerPlan::paymentRequired();

        return view('producers.index', compact('producers', 'paymentRequired'));
    }

    public function create()
    {
        return view('producers.create');
    }

    /** Fiche détaillée d'un producteur : ses contenus (films/séries) + actions. */
    public function show(User $user)
    {
        if (! $user->isProducerOwner()) {
            return redirect()->route('producers.index')
                ->with('error', "Cet utilisateur n'est pas un producteur.");
        }

        $media = $user->media()->with('category')->latest()->get();
        $team = $user->teamMembers()->orderBy('name')->get();

        $stats = [
            'total'  => $media->count(),
            'movies' => $media->where('type', 'movie')->count(),
            'series' => $media->where('type', 'series')->count(),
            'views'  => (int) $media->sum('views_count'),
        ];

        $access = [
            'payment_required' => ProducerPlan::paymentRequired(),
            'ends_at' => ProducerSubscription::accessEndsAt($user->id),
            'history' => $user->producerSubscriptions()->with(['transaction', 'grantedBy'])
                ->latest('starts_at')->limit(10)->get(),
        ];

        return view('producers.show', compact('user', 'media', 'stats', 'team', 'access'));
    }

    /** Offre (ou prolonge) l'accès à l'espace, sans paiement. */
    public function grantAccess(Request $request, User $user)
    {
        if (! $user->isProducerOwner()) {
            return back()->with('error', "Cet utilisateur n'est pas un producteur.");
        }

        $validated = $request->validate([
            'months' => 'required|integer|min:1|max:36',
        ]);

        $period = ProducerSubscription::grant($user, $validated['months'], $request->user());

        return back()->with('success', sprintf(
            'Accès offert à %s jusqu\'au %s.',
            $user->name,
            $period->expires_at->format('d/m/Y'),
        ));
    }

    /** Coupe l'accès en cours : l'espace est de nouveau verrouillé. */
    public function revokeAccess(User $user)
    {
        if (! $user->isProducerOwner()) {
            return back()->with('error', "Cet utilisateur n'est pas un producteur.");
        }

        ProducerSubscription::revoke($user);

        return back()->with('success', "Accès de {$user->name} coupé : son espace est verrouillé jusqu'au prochain paiement.");
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
        ]);

        $password = $this->generatePassword();

        $producer = User::create([
            'name'              => $validated['name'],
            'email'             => $validated['email'],
            'password'          => Hash::make($password),
            'role'              => 'producer',
            'email_verified_at' => now(),
        ]);

        return $this->afterCredentials($producer, $password, created: true);
    }

    /**
     * Régénère un nouveau mot de passe et le renvoie par email au producteur.
     * Utile si l'email initial a échoué ou si le producteur a perdu son accès.
     * L'ancien mot de passe est immédiatement invalidé.
     */
    public function resend(User $user)
    {
        if (! $user->isProducerOwner()) {
            return back()->with('error', "Cet utilisateur n'est pas un producteur.");
        }

        $password = $this->generatePassword();
        $user->update(['password' => Hash::make($password)]);

        return $this->afterCredentials($user, $password, created: false);
    }

    public function destroy(User $user)
    {
        if (! $user->isProducerOwner()) {
            return back()->with('error', "Cet utilisateur n'est pas un producteur.");
        }

        // Contenus et modules passent à la plateforme (nullOnDelete) ; l'équipe
        // perd l'accès au panel (User::booted).
        $user->delete();

        return redirect()->route('producers.index')
            ->with('success', 'Producteur supprimé. Ses contenus et modules restent sur la plateforme (gérés par l\'admin) et son équipe n\'a plus accès au panel.');
    }

    /**
     * Mot de passe généré : lettres + chiffres, sans symboles pour faciliter la
     * saisie et éviter les soucis d'affichage dans les clients mail.
     */
    private function generatePassword(): string
    {
        return Str::password(14, true, true, false);
    }

    /**
     * Tente d'envoyer les identifiants par email. En cas de succès, l'admin ne
     * voit jamais le mot de passe (message de confirmation neutre). En cas
     * d'échec, on retombe sur l'affichage unique pour ne pas bloquer le compte.
     */
    private function afterCredentials(User $producer, string $password, bool $created)
    {
        $verb = $created ? 'créé' : 'mis à jour';

        try {
            Mail::to($producer->email)->send(new ProducerCredentialsMail(
                name: $producer->name,
                producerEmail: $producer->email,
                password: $password,
                loginUrl: route('admin.login'),
            ));

            return redirect()->route('producers.index')->with('success', sprintf(
                'Producteur « %s » %s. Ses identifiants ont été envoyés à %s.',
                $producer->name,
                $verb,
                $producer->email,
            ));
        } catch (\Throwable $e) {
            Log::error('Envoi des identifiants producteur échoué', [
                'producer_id' => $producer->id,
                'email'       => $producer->email,
                'error'       => $e->getMessage(),
            ]);

            // Fallback : l'email n'est pas parti, on affiche le mot de passe UNE
            // fois pour que l'admin le transmette manuellement.
            return redirect()->route('producers.index')->with('new_producer', [
                'name'         => $producer->name,
                'email'        => $producer->email,
                'password'     => $password,
                'mail_failed'  => true,
            ]);
        }
    }
}
