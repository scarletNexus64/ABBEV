<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\TeamInvitationMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Équipe d'un producteur : il invite des membres sur son espace et choisit,
 * module par module, ce que chacun peut gérer (User::MODULES).
 *
 * Réservé au producteur titulaire de l'espace : un membre ne gère jamais
 * l'équipe, ce qui l'empêche de s'attribuer des droits.
 *
 * Invitation :
 *  - adresse inconnue → compte créé, mot de passe généré envoyé par email ;
 *  - abonné existant de l'app → son compte est rattaché à l'équipe, il garde
 *    son mot de passe ;
 *  - compte déjà dans le panel (admin, producteur, autre équipe) → refusé.
 * Retirer un membre le rend à son statut d'abonné : le compte n'est pas supprimé.
 */
class TeamController extends Controller
{
    public function index(Request $request)
    {
        $producer = $this->owner($request);

        $members = $producer->teamMembers()->orderBy('name')->get();

        return view('team.index', [
            'members' => $members,
            'modules' => User::MODULES,
        ]);
    }

    public function create(Request $request)
    {
        $this->owner($request);

        return view('team.create', [
            'member' => new User(),
            'modules' => User::MODULES,
        ]);
    }

    public function store(Request $request)
    {
        $producer = $this->owner($request);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
        ] + $this->permissionRules());

        $email = Str::lower(trim($data['email']));
        $existing = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($existing && $existing->role !== 'user') {
            return back()->withInput()->withErrors([
                'email' => $existing->producer_id === $producer->id
                    ? 'Cette personne fait déjà partie de votre équipe.'
                    : 'Cette adresse est déjà utilisée par un compte du panel ABBEV (administrateur, producteur ou autre équipe).',
            ]);
        }

        // Abonné de l'app : rattaché tel quel, son mot de passe ne change pas.
        if ($existing) {
            $existing->forceFill([
                'role' => 'producer',
                'producer_id' => $producer->id,
                'permissions' => $data['permissions'],
            ])->save();

            return $this->sendInvitation($producer, $existing, null, 'ajouté(e) à votre équipe');
        }

        $password = $this->generatePassword();

        $member = new User([
            'name' => $data['name'],
            'email' => $email,
            'password' => Hash::make($password),
        ]);
        $member->forceFill([
            'role' => 'producer',
            'producer_id' => $producer->id,
            'permissions' => $data['permissions'],
            'email_verified_at' => now(),
        ])->save();

        return $this->sendInvitation($producer, $member, $password, 'invité(e)');
    }

    public function edit(Request $request, User $member)
    {
        $this->authorizeMember($request, $member);

        return view('team.edit', [
            'member' => $member,
            'modules' => User::MODULES,
        ]);
    }

    public function update(Request $request, User $member)
    {
        $this->authorizeMember($request, $member);

        $data = $request->validate($this->permissionRules());
        $member->forceFill(['permissions' => $data['permissions']])->save();

        return redirect()->route('team.index')
            ->with('success', "Permissions de « {$member->name} » mises à jour.");
    }

    /** Régénère un mot de passe et renvoie l'invitation (l'ancien est invalidé). */
    public function resend(Request $request, User $member)
    {
        $producer = $this->authorizeMember($request, $member);

        $password = $this->generatePassword();
        $member->update(['password' => Hash::make($password)]);

        return $this->sendInvitation($producer, $member, $password, 'mis(e) à jour');
    }

    public function destroy(Request $request, User $member)
    {
        $this->authorizeMember($request, $member);

        $member->leaveTeam();

        return redirect()->route('team.index')->with('success', sprintf(
            "« %s » a été retiré(e) de l'équipe et n'a plus accès à votre espace.",
            $member->name,
        ));
    }

    /** Le producteur titulaire de l'espace (pas un membre d'équipe). */
    private function owner(Request $request): User
    {
        $user = $request->user();
        abort_unless($user->isProducerOwner(), 403, "Seul le producteur titulaire de l'espace gère son équipe.");

        return $user;
    }

    /** Un membre d'une autre équipe n'existe pas pour ce producteur (404). */
    private function authorizeMember(Request $request, User $member): User
    {
        $producer = $this->owner($request);
        abort_unless($member->producer_id === $producer->id, 404);

        return $producer;
    }

    private function permissionRules(): array
    {
        return [
            'permissions' => 'required|array|min:1',
            'permissions.*' => ['string', Rule::in(array_keys(User::MODULES))],
        ];
    }

    private function generatePassword(): string
    {
        return Str::password(14, true, true, false);
    }

    /**
     * Envoie l'invitation. Si l'email échoue et qu'un mot de passe a été
     * généré, il est affiché une seule fois au producteur pour qu'il le
     * transmette lui-même.
     */
    private function sendInvitation(User $producer, User $member, ?string $password, string $verb)
    {
        $modules = collect($member->permissions ?? [])
            ->map(fn (string $key) => User::MODULES[$key]['label'] ?? $key)
            ->values()
            ->all();

        try {
            Mail::to($member->email)->send(new TeamInvitationMail(
                name: $member->name,
                producerName: $producer->name,
                memberEmail: $member->email,
                password: $password,
                modules: $modules,
                loginUrl: route('admin.login'),
                forgotUrl: route('admin.password.request'),
            ));

            return redirect()->route('team.index')->with('success', sprintf(
                '« %s » %s. Ses accès ont été envoyés à %s.',
                $member->name,
                $verb,
                $member->email,
            ));
        } catch (\Throwable $e) {
            Log::error("Envoi de l'invitation d'équipe échoué", [
                'producer_id' => $producer->id,
                'member_id' => $member->id,
                'error' => $e->getMessage(),
            ]);

            if (! $password) {
                return redirect()->route('team.index')->with('error', sprintf(
                    "« %s » a bien été ajouté(e) à l'équipe, mais l'email n'a pas pu partir. Prévenez cette personne : elle se connecte avec le mot de passe de son compte ABBEV.",
                    $member->name,
                ));
            }

            return redirect()->route('team.index')->with('new_member', [
                'name' => $member->name,
                'email' => $member->email,
                'password' => $password,
            ]);
        }
    }
}
