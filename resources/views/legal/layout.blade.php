{{--
    Layout des pages légales publiques (CGU, confidentialité).

    Volontairement autonome : pas de sidebar admin, pas d'authentification, pas
    de dépendance à Vite. Ces pages sont ouvertes depuis l'app mobile ET par le
    reviewer Apple, qui doit pouvoir les lire sans compte. Le CSS est inline
    pour qu'elles restent lisibles même si le build front n'est pas déployé.
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — {{ config('app.name') }}</title>
    <style>
        :root {
            --bg: #0f1115;
            --card: #171a21;
            --text: #e7e9ee;
            --muted: #9aa1ad;
            --accent: #e50914;
            --border: #262a33;
        }

        @media (prefers-color-scheme: light) {
            :root {
                --bg: #f6f7f9;
                --card: #ffffff;
                --text: #1a1d23;
                --muted: #5c636e;
                --border: #e2e5ea;
            }
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 0 16px 64px;
            background: var(--bg);
            color: var(--text);
            font: 16px/1.65 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        .wrap { max-width: 760px; margin: 0 auto; }

        header {
            padding: 40px 0 24px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 32px;
        }

        .brand {
            display: inline-block;
            font-weight: 700;
            letter-spacing: .12em;
            color: var(--accent);
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 12px;
        }

        h1 { font-size: 28px; line-height: 1.25; margin: 0 0 8px; }
        h2 { font-size: 19px; margin: 36px 0 10px; }

        .updated { color: var(--muted); font-size: 14px; margin: 0; }

        p, li { color: var(--text); }
        ul { padding-left: 22px; }
        li { margin-bottom: 6px; }

        a { color: var(--accent); }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 18px 20px;
            margin: 24px 0;
        }

        .card p:first-child { margin-top: 0; }
        .card p:last-child { margin-bottom: 0; }

        footer {
            margin-top: 48px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
            color: var(--muted);
            font-size: 14px;
        }

        table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid var(--border); }
        th { color: var(--muted); font-weight: 600; font-size: 14px; }
    </style>
</head>
<body>
    <div class="wrap">
        <header>
            <a class="brand" href="{{ url('/') }}">{{ strtoupper(config('app.name')) }}</a>
            <h1>@yield('title')</h1>
            <p class="updated">Dernière mise à jour : {{ $updatedAt }}</p>
        </header>

        @yield('content')

        <footer>
            <p>
                {{ $company }} — {{ $contactEmail }}<br>
                <a href="{{ route('legal.terms') }}">Conditions d'utilisation</a> ·
                <a href="{{ route('legal.privacy') }}">Politique de confidentialité</a>
            </p>
        </footer>
    </div>
</body>
</html>
