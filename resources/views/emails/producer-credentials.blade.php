@extends('emails.layout')

@section('title', 'Vos identifiants producteur — ABBEV')
@section('subtitle', 'Espace Producteur')

@section('content')
    <h1 class="abbev-title" style="margin:0 0 6px; font-size:18px; color:#18181b;">Bienvenue, {{ $name }}</h1>
    <p class="abbev-text" style="margin:0 0 18px; font-size:14px; line-height:1.5; color:#52525b;">
        Votre compte producteur ABBEV vient d'être créé. Voici vos identifiants
        de connexion à l'espace d'administration.
    </p>

    {{-- Identifiants --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="abbev-box abbev-line" style="background-color:#f6f6f7; border:1px solid #e4e4e7; border-radius:10px;">
        <tr>
            <td class="abbev-line" style="padding:12px 16px; border-bottom:1px solid #e4e4e7;">
                <div class="abbev-muted" style="font-size:10px; text-transform:uppercase; letter-spacing:1px; color:#71717a; margin-bottom:3px;">Email de connexion</div>
                <div class="abbev-strong" style="font-size:14px; color:#18181b; font-family:Consolas,Menlo,monospace; word-break:break-all;">{{ $producerEmail }}</div>
            </td>
        </tr>
        <tr>
            <td style="padding:12px 16px;">
                <div class="abbev-muted" style="font-size:10px; text-transform:uppercase; letter-spacing:1px; color:#71717a; margin-bottom:3px;">Mot de passe</div>
                <div class="abbev-value" style="font-size:17px; color:#0e7490; font-family:Consolas,Menlo,monospace; letter-spacing:1px; word-break:break-all;">{{ $password }}</div>
            </td>
        </tr>
    </table>

    {{-- CTA --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0 4px;">
        <tr>
            <td align="center">
                <a href="{{ $loginUrl }}" style="display:inline-block; background-color:#06b6d4; color:#ffffff; text-decoration:none; font-size:14px; font-weight:600; padding:12px 28px; border-radius:8px;">
                    Me connecter à ABBEV
                </a>
            </td>
        </tr>
    </table>

    <p class="abbev-text" style="margin:16px 0 0; font-size:12px; line-height:1.5; color:#52525b;">
        Pour votre sécurité, nous vous recommandons de
        <strong class="abbev-strong" style="color:#18181b;">changer ce mot de passe</strong> après votre première connexion.
        Ne partagez jamais ces identifiants.
    </p>
@endsection

@section('footer')
    Vous recevez cet email car un compte producteur a été créé pour vous sur ABBEV. Si vous n'êtes pas concerné, ignorez ce message.
@endsection
