@extends('emails.layout')

@section('title', "Invitation dans l'équipe de {$producerName} — ABBEV")
@section('subtitle', 'Espace Producteur')

@section('content')
    <h1 class="abbev-title" style="margin:0 0 6px; font-size:18px; color:#18181b;">Bienvenue, {{ $name }}</h1>
    <p class="abbev-text" style="margin:0 0 18px; font-size:14px; line-height:1.5; color:#52525b;">
        <strong class="abbev-strong" style="color:#18181b;">{{ $producerName }}</strong> vous a ajouté à son équipe sur
        l'espace producteur ABBEV. Vous avez accès aux modules suivants :
    </p>

    <ul class="abbev-text" style="margin:0 0 18px; padding-left:18px; font-size:14px; line-height:1.7; color:#52525b;">
        @foreach($modules as $module)
            <li>{{ $module }}</li>
        @endforeach
    </ul>

    {{-- Identifiants --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="abbev-box abbev-line" style="background-color:#f6f6f7; border:1px solid #e4e4e7; border-radius:10px;">
        <tr>
            <td class="abbev-line" style="padding:12px 16px; {{ $password ? 'border-bottom:1px solid #e4e4e7;' : '' }}">
                <div class="abbev-muted" style="font-size:10px; text-transform:uppercase; letter-spacing:1px; color:#71717a; margin-bottom:3px;">Email de connexion</div>
                <div class="abbev-strong" style="font-size:14px; color:#18181b; font-family:Consolas,Menlo,monospace; word-break:break-all;">{{ $memberEmail }}</div>
            </td>
        </tr>
        @if($password)
        <tr>
            <td style="padding:12px 16px;">
                <div class="abbev-muted" style="font-size:10px; text-transform:uppercase; letter-spacing:1px; color:#71717a; margin-bottom:3px;">Mot de passe</div>
                <div class="abbev-value" style="font-size:17px; color:#0e7490; font-family:Consolas,Menlo,monospace; letter-spacing:1px; word-break:break-all;">{{ $password }}</div>
            </td>
        </tr>
        @endif
    </table>

    @unless($password)
        <p class="abbev-text" style="margin:12px 0 0; font-size:13px; line-height:1.5; color:#52525b;">
            Connectez-vous avec le mot de passe de votre compte ABBEV.
            Vous ne le connaissez plus ? <a href="{{ $forgotUrl }}" style="color:#0e7490;">Réinitialisez-le ici</a>.
        </p>
    @endunless

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

    @if($password)
        <p class="abbev-text" style="margin:16px 0 0; font-size:12px; line-height:1.5; color:#52525b;">
            Pour votre sécurité, nous vous recommandons de
            <strong class="abbev-strong" style="color:#18181b;">changer ce mot de passe</strong> après votre première connexion.
            Ne partagez jamais ces identifiants.
        </p>
    @endif
@endsection

@section('footer')
    Vous recevez cet email car {{ $producerName }} vous a invité dans son équipe sur ABBEV. Si vous n'êtes pas concerné, ignorez ce message.
@endsection
