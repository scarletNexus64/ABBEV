<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * Récupération de mot de passe pour le DASHBOARD WEB uniquement (admin +
 * producteur). Le mobile utilise l'OTP par email, ce flux ne le concerne pas.
 *
 * On s'appuie sur le broker natif Laravel (table password_reset_tokens). L'accès
 * est restreint au staff : une demande pour un email non-staff (ou inexistant)
 * renvoie le même message générique afin de ne pas révéler quels comptes existent.
 */
class PasswordResetController extends Controller
{
    public function showLinkRequest()
    {
        return view('admin.auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        // On n'envoie réellement le lien qu'au staff (admin/producteur). Pour un
        // email non-staff ou inexistant, on n'envoie rien mais on affiche le
        // MÊME écran de confirmation (anti-énumération : ne pas révéler qui existe).
        if ($user && $user->isStaff()) {
            $status = Password::sendResetLink($request->only('email'));

            // Throttle : on renvoie l'erreur réelle (utile) sur le formulaire.
            if ($status === Password::RESET_THROTTLED) {
                return back()->withErrors([
                    'email' => 'Trop de tentatives. Patientez avant de redemander un lien.',
                ])->onlyInput('email');
            }
        }

        return redirect()->route('admin.password.sent')->with('reset_email', $request->email);
    }

    /**
     * Écran de confirmation : « le lien a été envoyé à …, il expire dans … ».
     * Nécessite le flash reset_email (posé par sendResetLink), sinon on renvoie
     * vers le formulaire (empêche l'accès direct sans avoir fait de demande).
     */
    public function linkSent()
    {
        $email = session('reset_email');

        if (! $email) {
            return redirect()->route('admin.password.request');
        }

        $expire = config('auth.passwords.'.config('auth.defaults.passwords', 'users').'.expire', 60);

        return view('admin.auth.link-sent', compact('email', 'expire'));
    }

    public function showReset(Request $request, string $token)
    {
        return view('admin.auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password'       => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('admin.login')
                ->with('success', 'Mot de passe réinitialisé. Vous pouvez vous connecter.');
        }

        return back()->withErrors([
            'email' => $this->messageFor($status),
        ])->onlyInput('email');
    }

    private function messageFor(string $status): string
    {
        return match ($status) {
            Password::INVALID_TOKEN => 'Ce lien de réinitialisation est invalide ou a expiré. Redemandez-en un.',
            Password::INVALID_USER  => "Aucun compte ne correspond à cette adresse.",
            default                 => 'Impossible de réinitialiser le mot de passe. Réessayez.',
        };
    }
}
