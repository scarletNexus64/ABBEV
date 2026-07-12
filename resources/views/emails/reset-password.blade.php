@extends('emails.layout')

@section('title', 'Réinitialisation de mot de passe — ABBEV')
@section('subtitle', 'Sécurité du compte')

@section('content')
    <h1 class="abbev-title" style="margin:0 0 6px; font-size:18px; color:#18181b;">Réinitialisation du mot de passe</h1>
    <p class="abbev-text" style="margin:0 0 18px; font-size:14px; line-height:1.5; color:#52525b;">
        Bonjour,<br>
        Une réinitialisation du mot de passe de votre compte ABBEV a été demandée.
        Cliquez sur le bouton ci-dessous pour en choisir un nouveau.
    </p>

    {{-- CTA --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:4px 0 4px;">
        <tr>
            <td align="center">
                <a href="{{ $url }}" style="display:inline-block; background-color:#06b6d4; color:#ffffff; text-decoration:none; font-size:14px; font-weight:600; padding:12px 28px; border-radius:8px;">
                    Réinitialiser mon mot de passe
                </a>
            </td>
        </tr>
    </table>

    <p class="abbev-text" style="margin:18px 0 0; font-size:13px; line-height:1.5; color:#52525b;">
        Ce lien expire dans <strong class="abbev-strong" style="color:#18181b;">{{ $expire }} minutes</strong>.
        Si vous n'êtes pas à l'origine de cette demande, aucune action n'est requise.
    </p>

    <p class="abbev-muted" style="margin:14px 0 0; font-size:11px; line-height:1.5; color:#71717a; word-break:break-all;">
        Si le bouton ne fonctionne pas, copiez-collez ce lien dans votre navigateur :<br>
        <a href="{{ $url }}" style="color:#0891b2; text-decoration:underline;">{{ $url }}</a>
    </p>
@endsection

@section('footer')
    Vous recevez cet email suite à une demande de réinitialisation sur ABBEV. Si vous n'êtes pas concerné, ignorez ce message.
@endsection
