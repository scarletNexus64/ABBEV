<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>@yield('title', 'ABBEV')</title>
    <style>
        /* Couleurs par défaut = mode CLAIR (fond blanc). Le bloc @media
           bascule en mode SOMBRE là où le client mail le supporte (Apple Mail…).
           Les styles inline portent la version claire pour les clients qui
           suppriment <style> (Gmail) : fond blanc, carte blanche. */
        .abbev-bg     { background-color: #f4f4f5; }
        .abbev-card   { background-color: #ffffff; border-color: #e4e4e7; }
        .abbev-title  { color: #18181b; }
        .abbev-text   { color: #52525b; }
        .abbev-muted  { color: #71717a; }
        .abbev-box    { background-color: #f6f6f7; }
        .abbev-line   { border-color: #e4e4e7; }
        .abbev-value  { color: #0e7490; }
        .abbev-strong { color: #18181b; }
        .abbev-footer { color: #a1a1aa; }

        @media (prefers-color-scheme: dark) {
            .abbev-bg     { background-color: #000000 !important; }
            .abbev-card   { background-color: #18181b !important; border-color: #27272a !important; }
            .abbev-title  { color: #ffffff !important; }
            .abbev-text   { color: #a1a1aa !important; }
            .abbev-muted  { color: #71717a !important; }
            .abbev-box    { background-color: #09090b !important; }
            .abbev-line   { border-color: #27272a !important; }
            .abbev-value  { color: #22d3ee !important; }
            .abbev-strong { color: #ffffff !important; }
            .abbev-footer { color: #52525b !important; }
        }
    </style>
</head>
<body class="abbev-bg" style="margin:0; padding:0; background-color:#f4f4f5; font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="abbev-bg" style="background-color:#f4f4f5; padding:20px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="abbev-card" style="max-width:520px; background-color:#ffffff; border:1px solid #e4e4e7; border-radius:14px; overflow:hidden;">

                    {{-- Bandeau ABBEV (identique en clair et sombre) --}}
                    <tr>
                        <td style="background:linear-gradient(135deg,#06b6d4,#0891b2); padding:18px 28px; text-align:center;">
                            <span style="display:inline-block; font-size:22px; font-weight:800; letter-spacing:2px; color:#ffffff;">ABBEV</span>
                            <div style="margin-top:3px; font-size:11px; letter-spacing:1px; color:#cffafe; text-transform:uppercase;">@yield('subtitle')</div>
                        </td>
                    </tr>

                    {{-- Contenu --}}
                    <tr>
                        <td style="padding:24px 28px;">
                            @yield('content')
                        </td>
                    </tr>

                    {{-- Pied --}}
                    <tr>
                        <td class="abbev-line" style="padding:14px 28px; border-top:1px solid #e4e4e7; text-align:center;">
                            <p class="abbev-footer" style="margin:0; font-size:11px; color:#a1a1aa; line-height:1.5;">@yield('footer')</p>
                            <p class="abbev-footer" style="margin:8px 0 0; font-size:11px; color:#a1a1aa;">© {{ date('Y') }} ABBEV — Tous droits réservés</p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
