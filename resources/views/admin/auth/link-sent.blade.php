<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="{{ asset('logo/logo.jpeg') }}">
    <title>Lien envoyé - ABBEV Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('admin.auth._auth-card-styles')
    <style>
        .sent-icon {
            width: 72px; height: 72px;
            margin: 0 auto 22px;
            border-radius: 50%;
            background: rgba(6, 182, 212, 0.12);
            border: 1px solid rgba(6, 182, 212, 0.35);
            display: flex; align-items: center; justify-content: center;
        }
        .sent-icon i { font-size: 30px; color: var(--primary); }
        .sent-email {
            display: inline-block;
            margin-top: 4px;
            padding: 4px 12px;
            border-radius: 8px;
            background: rgba(6, 182, 212, 0.08);
            color: var(--primary);
            font-weight: 600;
            word-break: break-all;
        }
        .sent-steps {
            text-align: left;
            margin: 26px 0 8px;
            padding: 18px 20px;
            border: 1px solid var(--dark-200);
            border-radius: 12px;
            background: var(--dark-50);
        }
        .sent-step { display: flex; align-items: flex-start; gap: 12px; color: var(--dark-600); font-size: 0.9rem; line-height: 1.5; }
        .sent-step + .sent-step { margin-top: 14px; }
        .sent-step i { color: var(--primary); font-size: 15px; margin-top: 3px; flex-shrink: 0; }
        .sent-expire {
            margin: 18px 0 0;
            font-size: 0.85rem;
            color: var(--dark-500);
        }
        .sent-expire strong { color: var(--dark-700); }
    </style>
</head>
<body>
    <div class="auth-card" style="text-align: center;">
        <div class="auth-logo" style="justify-content: center; margin-bottom: 26px;">
            <div class="auth-logo-circle">
                <img src="{{ asset('logo/logo.jpeg') }}" alt="ABBEV Logo">
            </div>
            <div style="text-align: left;">
                <h1>ABBEV</h1>
                <p>Administration</p>
            </div>
        </div>

        <div class="auth-header" style="text-align: center;">
            <h2>Vérifiez votre boîte mail</h2>
            <p style="margin-bottom: 4px;">
                Si un compte existe pour cette adresse, un lien de réinitialisation vient d'être envoyé à :
            </p>
            <span class="sent-email">{{ $email }}</span>
        </div>

        <div class="sent-steps">
            <div class="sent-step">
                <i class="fas fa-inbox"></i>
                <span>Ouvrez l'email <strong style="color: var(--dark-700);">« Réinitialisation de votre mot de passe — ABBEV »</strong> (pensez aux spams).</span>
            </div>
            <div class="sent-step">
                <i class="fas fa-hand-pointer"></i>
                <span>Cliquez sur <strong style="color: var(--dark-700);">« Réinitialiser mon mot de passe »</strong>.</span>
            </div>
            <div class="sent-step">
                <i class="fas fa-key"></i>
                <span>Choisissez votre nouveau mot de passe, puis reconnectez-vous.</span>
            </div>
        </div>

        <p class="sent-expire">
            <i class="fas fa-clock" style="color: var(--primary); margin-right: 4px;"></i>
            Ce lien expire dans <strong>{{ $expire }} minutes</strong>.
        </p>

        <div class="auth-footer">
            <a href="{{ route('admin.password.request') }}"><i class="fas fa-rotate-right mr-1"></i> Je n'ai rien reçu, renvoyer un lien</a>
            <p style="margin-top: 14px;">
                <a href="{{ route('admin.login') }}" style="color: var(--dark-500);">Retour à la connexion</a>
            </p>
        </div>
    </div>
</body>
</html>
