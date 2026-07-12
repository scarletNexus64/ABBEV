<?php

namespace App\Http\Controllers;

use App\Mail\ProducerCredentialsMail;
use App\Models\Media;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Gestion des comptes "assistant" (direction artistique) — réservée aux admins.
 *
 * Un assistant valide/rejette les contenus soumis par les producteurs et leur
 * attribue catégorie + tier (voir ModerationController). À la création, un mot
 * de passe fort est généré et envoyé par email (jamais stocké en clair). Il se
 * connecte sur /admin/login et n'accède qu'au panneau de modération.
 */
class AssistantController extends Controller
{
    public function index()
    {
        $assistants = User::where('role', 'assistant')->latest()->paginate(20);

        // Stat utile : contenus en attente de modération.
        $pending = Media::where('moderation_status', 'pending')->count();

        return view('assistants.index', compact('assistants', 'pending'));
    }

    public function create()
    {
        return view('assistants.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
        ], [
            'email.unique' => 'Cette adresse email est déjà utilisée.',
        ]);

        $password = $this->generatePassword();

        $assistant = User::create([
            'name'              => $validated['name'],
            'email'             => $validated['email'],
            'password'          => Hash::make($password),
            'role'              => 'assistant',
            'email_verified_at' => now(),
        ]);

        return $this->afterCredentials($assistant, $password, created: true);
    }

    public function resend(User $user)
    {
        if ($user->role !== 'assistant') {
            return back()->with('error', "Cet utilisateur n'est pas un assistant.");
        }

        $password = $this->generatePassword();
        $user->update(['password' => Hash::make($password)]);

        return $this->afterCredentials($user, $password, created: false);
    }

    public function destroy(User $user)
    {
        if ($user->role !== 'assistant') {
            return back()->with('error', "Cet utilisateur n'est pas un assistant.");
        }

        $user->delete();

        return redirect()->route('assistants.index')
            ->with('success', 'Assistant supprimé.');
    }

    private function generatePassword(): string
    {
        return Str::password(14, true, true, false);
    }

    private function afterCredentials(User $assistant, string $password, bool $created)
    {
        $verb = $created ? 'créé' : 'mis à jour';

        try {
            Mail::to($assistant->email)->send(new ProducerCredentialsMail(
                name: $assistant->name,
                producerEmail: $assistant->email,
                password: $password,
                loginUrl: route('admin.login'),
            ));

            return redirect()->route('assistants.index')->with('success', sprintf(
                'Assistant « %s » %s. Ses identifiants ont été envoyés à %s.',
                $assistant->name,
                $verb,
                $assistant->email,
            ));
        } catch (\Throwable $e) {
            Log::error('Envoi des identifiants assistant échoué', [
                'assistant_id' => $assistant->id,
                'email'        => $assistant->email,
                'error'        => $e->getMessage(),
            ]);

            return redirect()->route('assistants.index')->with('new_assistant', [
                'name'        => $assistant->name,
                'email'       => $assistant->email,
                'password'    => $password,
                'mail_failed' => true,
            ]);
        }
    }
}
