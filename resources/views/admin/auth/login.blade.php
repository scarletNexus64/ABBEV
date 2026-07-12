<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Dashboard Panel Admin ABBEV - Gérez vos films, séries et catégories.">

    <!-- Open Graph / Partage de lien -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="ABBEV - Admin">
    <meta property="og:description" content="Dashboard Panel Admin ABBEV - Gérez vos films, séries et catégories.">
    <meta property="og:image" content="{{ asset('logo/logo.jpeg') }}">

    <!-- Favicon -->
    <link rel="icon" type="image/jpeg" href="{{ asset('logo/logo.jpeg') }}">
    <link rel="apple-touch-icon" href="{{ asset('logo/logo.jpeg') }}">

    <title>Connexion - ABBEV Admin</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #06b6d4;
            --primary-dark: #0e7490;
            --secondary: #22d3ee;
            --tertiary: #0891b2;
            --accent: #67e8f9;
            --dark-50: #18181b;
            --dark-100: #09090b;
            --dark-200: #27272a;
            --dark-300: #3f3f46;
            --dark-400: #52525b;
            --dark-500: #71717a;
            --dark-600: #a1a1aa;
            --dark-700: #d4d4d8;
            --dark-800: #e4e4e7;
            --dark-900: #f4f4f5;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #1a1a1a 0%, #0a0a0a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background:
                radial-gradient(circle at 30% 50%, rgba(6, 182, 212, 0.1), transparent 50%),
                radial-gradient(circle at 70% 50%, rgba(34, 211, 238, 0.08), transparent 50%);
            animation: float 20s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(30px, -30px) rotate(120deg); }
            66% { transform: translate(-20px, 20px) rotate(240deg); }
        }

        .login-container {
            background: rgba(9, 9, 11, 0.95);
            border: 1px solid var(--dark-200);
            border-radius: 24px;
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(20px);
            overflow: hidden;
            max-width: 1100px;
            width: 100%;
            display: grid;
            grid-template-columns: 45% 55%;
            min-height: 650px;
            position: relative;
            z-index: 1;
        }

        .login-left {
            background: linear-gradient(135deg, rgba(6, 182, 212, 0.15) 0%, rgba(8, 145, 178, 0.15) 100%);
            padding: 60px 50px;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
            border-right: 1px solid var(--dark-200);
        }

        .login-left::before {
            content: '';
            position: absolute;
            top: -100px;
            right: -100px;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(6, 182, 212, 0.2) 0%, transparent 70%);
            border-radius: 50%;
        }

        .login-left::after {
            content: '';
            position: absolute;
            bottom: -150px;
            left: -100px;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(34, 211, 238, 0.15) 0%, transparent 70%);
            border-radius: 50%;
        }

        .logo-container {
            position: relative;
            z-index: 1;
            margin-bottom: 40px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .logo-circle {
            width: 90px;
            height: 90px;
            background: white;
            border-radius: 50%;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow:
                0 10px 25px rgba(0, 0, 0, 0.3),
                0 0 0 4px rgba(6, 182, 212, 0.2);
            position: relative;
        }

        .logo-circle::before {
            content: '';
            position: absolute;
            inset: -6px;
            border-radius: 50%;
            padding: 2px;
            background: linear-gradient(135deg, var(--primary), var(--tertiary));
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            opacity: 0.5;
        }

        .logo-circle img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .logo-text h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 4px;
            background: linear-gradient(135deg, #ffffff 0%, #e0e0e0 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .logo-text p {
            font-size: 0.95rem;
            color: var(--dark-700);
            font-weight: 500;
            letter-spacing: 0.5px;
        }

        .welcome-section {
            position: relative;
            z-index: 1;
        }

        .welcome-text {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 16px;
            line-height: 1.3;
            background: linear-gradient(135deg, #ffffff 0%, var(--dark-700) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .welcome-description {
            font-size: 1rem;
            color: var(--dark-600);
            line-height: 1.7;
            margin-bottom: 40px;
        }

        .features {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .feature {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding: 18px;
            background: rgba(39, 39, 42, 0.5);
            border: 1px solid var(--dark-200);
            border-radius: 12px;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        .feature:hover {
            background: rgba(39, 39, 42, 0.8);
            border-color: rgba(6, 182, 212, 0.3);
            transform: translateX(8px);
        }

        .feature-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--tertiary) 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(6, 182, 212, 0.3);
        }

        .feature-icon i {
            font-size: 22px;
            color: white;
        }

        .feature-content h3 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 4px;
            color: var(--dark-900);
        }

        .feature-content p {
            font-size: 0.875rem;
            color: var(--dark-500);
            line-height: 1.5;
        }

        .login-right {
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: rgba(24, 24, 27, 0.5);
        }

        .login-header {
            margin-bottom: 40px;
        }

        .login-header h2 {
            font-size: 2rem;
            color: white;
            margin-bottom: 8px;
            font-weight: 700;
        }

        .login-header p {
            color: var(--dark-600);
            font-size: 0.95rem;
        }

        .alert {
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #6ee7b7;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .alert i {
            font-size: 20px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark-700);
            font-weight: 500;
            font-size: 0.9rem;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--dark-500);
            font-size: 18px;
            z-index: 1;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 14px 16px 14px 50px;
            border: 2px solid var(--dark-200);
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--dark-50);
            color: white;
            font-family: inherit;
        }

        input[type="email"]::placeholder,
        input[type="password"]::placeholder {
            color: var(--dark-500);
        }

        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: var(--primary);
            background: var(--dark-100);
            box-shadow: 0 0 0 4px rgba(6, 182, 212, 0.1);
        }

        input[type="email"]:focus + .input-icon,
        input[type="password"]:focus + .input-icon {
            color: var(--primary);
        }

        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .remember-me input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--primary);
        }

        .remember-me label {
            color: var(--dark-600);
            font-size: 0.9rem;
            cursor: pointer;
            margin: 0;
            user-select: none;
        }

        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--tertiary) 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 16px rgba(6, 182, 212, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-family: inherit;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(6, 182, 212, 0.5);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .btn-login i {
            font-size: 18px;
        }

        .credentials-info {
            margin-top: 24px;
            padding: 16px;
            background: rgba(6, 182, 212, 0.05);
            border-radius: 12px;
            border: 1px solid rgba(6, 182, 212, 0.2);
        }

        .credentials-info p {
            color: var(--dark-600);
            font-size: 0.85rem;
            text-align: center;
        }

        .credentials-info span {
            color: var(--primary);
            font-weight: 600;
        }

        .footer {
            margin-top: 32px;
            text-align: center;
            padding-top: 24px;
            border-top: 1px solid var(--dark-200);
        }

        .footer p {
            color: var(--dark-500);
            font-size: 0.85rem;
        }

        @media (max-width: 968px) {
            .login-container {
                grid-template-columns: 1fr;
                max-width: 500px;
            }

            .login-left {
                padding: 50px 40px;
                min-height: auto;
                border-right: none;
                border-bottom: 1px solid var(--dark-200);
            }

            .login-right {
                padding: 50px 40px;
            }

            .welcome-text {
                font-size: 1.75rem;
            }

            .features {
                display: none;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 10px;
            }

            .login-container {
                border-radius: 16px;
            }

            .login-left,
            .login-right {
                padding: 40px 30px;
            }

            .login-header h2 {
                font-size: 1.75rem;
            }

            .welcome-text {
                font-size: 1.5rem;
            }

            .logo-circle {
                width: 70px;
                height: 70px;
            }

            .logo-text h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-left">
            <div class="logo-container">
                <div class="logo">
                    <div class="logo-circle">
                        <img src="{{ asset('logo/logo.jpeg') }}" alt="ABBEV Logo">
                    </div>
                    <div class="logo-text">
                        <h1>ABBEV</h1>
                        <p>Administration</p>
                    </div>
                </div>
            </div>

            <div class="welcome-section">
                <h1 class="welcome-text">Bienvenue sur votre espace administrateur</h1>
                <p class="welcome-description">
                    Gérez efficacement votre collection de films et séries avec des outils puissants et intuitifs.
                </p>

                <div class="features">
                    <div class="feature">
                        <div class="feature-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="feature-content">
                            <h3>Tableau de bord analytique</h3>
                            <p>Visualisez toutes vos données en temps réel</p>
                        </div>
                    </div>

                    <div class="feature">
                        <div class="feature-icon">
                            <i class="fas fa-film"></i>
                        </div>
                        <div class="feature-content">
                            <h3>Gestion des médias</h3>
                            <p>Contrôlez vos films et séries facilement</p>
                        </div>
                    </div>

                    <div class="feature">
                        <div class="feature-icon">
                            <i class="fas fa-folder"></i>
                        </div>
                        <div class="feature-content">
                            <h3>Gestion des catégories</h3>
                            <p>Organisez votre contenu par genre</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="login-right">
            <div class="login-header">
                <h2>Connexion</h2>
                <p>Accédez au panneau d'administration</p>
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

            @if (session('success'))
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div>{{ session('success') }}</div>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login.submit') }}">
                @csrf

                <div class="form-group">
                    <label for="email">Adresse email</label>
                    <div class="input-wrapper">
                        <input type="email"
                               id="email"
                               name="email"
                               value="{{ old('email') }}"
                               placeholder="admin@example.com"
                               required
                               autofocus>
                        <i class="fas fa-envelope input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <div class="input-wrapper">
                        <input type="password"
                               id="password"
                               name="password"
                               value=""
                               placeholder="Entrez votre mot de passe"
                               required>
                        <i class="fas fa-lock input-icon"></i>
                    </div>
                </div>

                <div class="remember-forgot">
                    <div class="remember-me">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember">Se souvenir de moi</label>
                    </div>
                    <a href="{{ route('admin.password.request') }}"
                       style="color: var(--primary); font-size: 0.9rem; font-weight: 500; text-decoration: none;">
                        Mot de passe oublié ?
                    </a>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i>
                    Se connecter
                </button>

            </form>

            <div class="footer">
                <p>ABBEV &copy; {{ date('Y') }} - Tous droits réservés</p>
            </div>
        </div>
    </div>
</body>
</html>
