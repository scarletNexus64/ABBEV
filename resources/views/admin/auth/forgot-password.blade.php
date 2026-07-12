<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="{{ asset('logo/logo.jpeg') }}">
    <title>Mot de passe oublié - ABBEV Admin</title>
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
            <h2>Mot de passe oublié ?</h2>
            <p>Saisissez l'adresse email de votre compte. Nous vous enverrons un lien pour réinitialiser votre mot de passe.</p>
        </div>

        @if (session('status'))
            <div class="alert alert-info">
                <i class="fas fa-circle-info"></i>
                <div>{{ session('status') }}</div>
            </div>
        @endif

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

        <form method="POST" action="{{ route('admin.password.email') }}">
            @csrf
            <div class="form-group">
                <label for="email">Adresse email</label>
                <div class="input-wrapper">
                    <input type="email" id="email" name="email" value="{{ old('email') }}"
                           placeholder="admin@example.com" required autofocus>
                    <i class="fas fa-envelope input-icon"></i>
                </div>
            </div>

            <button type="submit" class="btn-primary">
                <i class="fas fa-paper-plane"></i>
                Envoyer le lien de réinitialisation
            </button>
        </form>

        <div class="auth-footer">
            <a href="{{ route('admin.login') }}"><i class="fas fa-arrow-left mr-1"></i> Retour à la connexion</a>
            <p>ABBEV &copy; {{ date('Y') }} - Tous droits réservés</p>
        </div>
    </div>
</body>
</html>
