<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
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

    public function show(User $user)
    {
        $user->load('subscriptions.plan');
        return view('users.show', compact('user'));
    }

    /**
     * Supprime définitivement un utilisateur (rôle "user" uniquement). Les
     * administrateurs passent par AdminUserController. Garde-fous : pas
     * d'auto-suppression, pas de suppression d'admin par cette route.
     */
    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Vous ne pouvez pas supprimer votre propre compte.');
        }

        if ($user->role !== 'user') {
            return back()->with('error', 'Cet utilisateur n\'est pas un membre standard.');
        }

        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'Utilisateur supprimé avec succès.');
    }
}
