<?php

namespace App\Http\Controllers;

use App\Mail\AdminPasswordResetMail;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $query = User::where('role', 'user')->latest();

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate(20);
        $stats = [
            'total' => User::where('role', 'user')->count(),
            'today' => User::where('role', 'user')->whereDate('created_at', today())->count(),
            'week' => User::where('role', 'user')->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'month' => User::where('role', 'user')->whereMonth('created_at', now()->month)->count(),
        ];

        return view('users.index', compact('users', 'stats'));
    }

    /** Formulaire de création d'un utilisateur (membre standard). */
    public function create()
    {
        $this->authorize('create', User::class);

        return view('users.create');
    }

    /**
     * Crée un membre standard depuis le dashboard. Le mobile se connecte par
     * OTP (email) : aucun mot de passe à transmettre, on en pose un aléatoire.
     */
    public function store(Request $request)
    {
        $this->authorize('create', User::class);

        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
        ]);

        $user = User::create([
            'name'              => $validated['name'],
            'email'             => $validated['email'],
            'password'          => Hash::make(Str::random(32)),
            'role'              => 'user',
            'is_active'         => true,
            'email_verified_at' => now(),
        ]);

        return redirect()->route('users.show', $user)
            ->with('success', 'Utilisateur créé. Il peut se connecter à l\'app via un code OTP envoyé à son email.');
    }

    public function show(User $user)
    {
        $user->load('subscriptions.plan');
        return view('users.show', compact('user'));
    }

    /** Formulaire d'édition des infos de base (nom, email). */
    public function edit(User $user)
    {
        $this->authorize('update', $user);

        return view('users.edit', compact('user'));
    }

    /** Enregistre les infos de base. */
    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update($validated);

        return redirect()->route('users.show', $user)
            ->with('success', 'Informations mises à jour.');
    }

    /** Active ou suspend le compte (is_active). */
    public function updateStatus(Request $request, User $user)
    {
        $this->authorize('updateStatus', $user);

        $validated = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $user->update(['is_active' => $validated['is_active']]);

        $message = $validated['is_active']
            ? 'Compte réactivé. L\'utilisateur peut à nouveau se connecter.'
            : 'Compte suspendu. L\'utilisateur ne peut plus se connecter.';

        return back()->with('success', $message);
    }

    /** Régénère le mot de passe du compte et l'envoie par email à l'utilisateur. */
    public function resetPassword(User $user)
    {
        $this->authorize('resetPassword', $user);

        $password = Str::password(14, true, true, false);
        $user->update(['password' => Hash::make($password)]);

        try {
            Mail::to($user->email)->send(new AdminPasswordResetMail(
                name: $user->name,
                accountEmail: $user->email,
                password: $password,
            ));

            return back()->with('success', "Nouveau mot de passe envoyé à {$user->email}.");
        } catch (\Throwable $e) {
            Log::error('Envoi du reset mot de passe (admin) échoué', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            // Fallback : l'email n'est pas parti, on affiche le mot de passe à l'admin.
            return back()->with('error', "Email non envoyé. Mot de passe temporaire à transmettre : {$password}");
        }
    }

    /** Prolonge l'abonnement actif de X jours. */
    public function extendSubscription(Request $request, User $user)
    {
        $this->authorize('manageSubscription', $user);

        $validated = $request->validate([
            'days' => 'required|integer|min:1|max:3650',
        ]);

        $subscription = $user->subscriptions()
            ->where('status', 'active')
            ->latest('expires_at')
            ->first();

        if (! $subscription) {
            return back()->with('error', "Aucun abonnement actif à prolonger pour cet utilisateur.");
        }

        // Prolonge depuis la date d'expiration (ou maintenant si déjà passée).
        $base = $subscription->expires_at->isFuture() ? $subscription->expires_at : now();
        $subscription->update([
            'expires_at' => $base->copy()->addDays($validated['days']),
        ]);

        return back()->with('success', "Abonnement prolongé de {$validated['days']} jour(s).");
    }

    /** Annule un abonnement (accès révoqué). */
    public function cancelSubscription(User $user, UserSubscription $subscription)
    {
        $this->authorize('manageSubscription', $user);

        if ($subscription->user_id !== $user->id) {
            return back()->with('error', "Cet abonnement n'appartient pas à cet utilisateur.");
        }

        $subscription->update(['status' => 'cancelled']);

        return back()->with('success', 'Abonnement annulé.');
    }

    /**
     * Supprime définitivement un utilisateur (rôle "user" uniquement). Les
     * administrateurs passent par AdminUserController. Garde-fous : pas
     * d'auto-suppression (policy), pas de suppression d'admin par cette route.
     */
    public function destroy(User $user)
    {
        $this->authorize('delete', $user);

        if ($user->role !== 'user') {
            return back()->with('error', 'Cet utilisateur n\'est pas un membre standard.');
        }

        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'Utilisateur supprimé avec succès.');
    }
}
