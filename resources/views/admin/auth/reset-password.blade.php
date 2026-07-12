<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="{{ asset('logo/logo.jpeg') }}">
    <title>Réinitialiser le mot de passe - ABBEV Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('admin.auth._auth-card-styles')
</head>
<body>
    <div class="auth-card">
        <div class="auth-logo">
            <div class="auth-logo-circle">
                <img src="{{ asset('logo/logo.jpeg') }}" alt="ABBEV Logo">
            </div>
            <div>
                <h1>ABBEV</h1>
                <p>Administration</p>
            </div>
        </div>

        <div class="auth-header">
            <h2>Nouveau mot de passe</h2>
            <p>Choisissez un nouveau mot de passe pour votre compte (8 caractères minimum).</p>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    @foreach ($errors->all() as $error)
                        {{ $error }}
                    @endforeach
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="email" value="{{ old('email', $email) }}">

            <div class="form-group">
                <label>Adresse email</label>
                <div class="static-field">
                    <i class="fas fa-envelope"></i>
                    <span>{{ old('email', $email) }}</span>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Nouveau mot de passe</label>
                <div class="input-wrapper">
                    <input type="password" id="password" name="password"
                           placeholder="Au moins 8 caractères" required autofocus>
                    <i class="fas fa-lock input-icon"></i>
                </div>
            </div>

            <div class="form-group">
                <label for="password_confirmation">Confirmer le mot de passe</label>
                <div class="input-wrapper">
                    <input type="password" id="password_confirmation" name="password_confirmation"
                           placeholder="Retapez le mot de passe" required>
                    <i class="fas fa-lock input-icon"></i>
                </div>
            </div>

            <button type="submit" class="btn-primary">
                <i class="fas fa-key"></i>
                Réinitialiser le mot de passe
            </button>
        </form>

        <div class="auth-footer">
            <a href="{{ route('admin.login') }}"><i class="fas fa-arrow-left mr-1"></i> Retour à la connexion</a>
            <p>ABBEV &copy; {{ date('Y') }} - Tous droits réservés</p>
        </div>
    </div>
</body>
</html>
