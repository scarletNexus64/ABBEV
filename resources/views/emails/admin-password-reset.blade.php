@extends('emails.layout')

@section('title', 'Votre mot de passe a été réinitialisé — ABBEV')
@section('subtitle', 'Sécurité du compte')

@section('content')
    <h1 class="abbev-title" style="margin:0 0 6px; font-size:18px; color:#18181b;">Bonjour {{ $name }},</h1>
    <p class="abbev-text" style="margin:0 0 18px; font-size:14px; line-height:1.5; color:#52525b;">
        Le mot de passe de votre compte ABBEV vient d'être réinitialisé par un administrateur.
        Voici votre nouveau mot de passe :
    </p>

    {{-- Identifiants --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="abbev-box abbev-line" style="background-color:#f6f6f7; border:1px solid #e4e4e7; border-radius:10px;">
        <tr>
            <td class="abbev-line" style="padding:12px 16px; border-bottom:1px solid #e4e4e7;">
                <div class="abbev-muted" style="font-size:10px; text-transform:uppercase; letter-spacing:1px; color:#71717a; margin-bottom:3px;">Compte</div>
                <div class="abbev-strong" style="font-size:14px; color:#18181b; font-family:Consolas,Menlo,monospace; word-break:break-all;">{{ $accountEmail }}</div>
            </td>
        </tr>
        <tr>
            <td style="padding:12px 16px;">
                <div class="abbev-muted" style="font-size:10px; text-transform:uppercase; letter-spacing:1px; color:#71717a; margin-bottom:3px;">Nouveau mot de passe</div>
                <div class="abbev-value" style="font-size:17px; color:#0e7490; font-family:Consolas,Menlo,monospace; letter-spacing:1px; word-break:break-all;">{{ $password }}</div>
            </td>
        </tr>
    </table>

    <p class="abbev-text" style="margin:16px 0 0; font-size:12px; line-height:1.5; color:#52525b;">
        Pour votre sécurité, pensez à
        <strong class="abbev-strong" style="color:#18181b;">changer ce mot de passe</strong> dès votre prochaine connexion.
        Si vous n'êtes pas à l'origine de cette demande, contactez le support.
    </p>
@endsection

@section('footer')
    Vous recevez cet email car le mot de passe de votre compte ABBEV a été réinitialisé. Ne partagez jamais ces identifiants.
@endsection
