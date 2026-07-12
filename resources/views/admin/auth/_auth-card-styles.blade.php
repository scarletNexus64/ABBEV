<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    :root {
        --primary: #06b6d4;
        --tertiary: #0891b2;
        --dark-50: #18181b;
        --dark-100: #09090b;
        --dark-200: #27272a;
        --dark-500: #71717a;
        --dark-600: #a1a1aa;
        --dark-700: #d4d4d8;
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
        top: -50%; left: -50%;
        width: 200%; height: 200%;
        background:
            radial-gradient(circle at 30% 50%, rgba(6, 182, 212, 0.1), transparent 50%),
            radial-gradient(circle at 70% 50%, rgba(34, 211, 238, 0.08), transparent 50%);
    }

    .auth-card {
        position: relative;
        z-index: 1;
        width: 100%;
        max-width: 440px;
        background: rgba(9, 9, 11, 0.95);
        border: 1px solid var(--dark-200);
        border-radius: 24px;
        box-shadow: 0 24px 48px rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(20px);
        padding: 48px 40px;
    }

    .auth-logo {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-bottom: 32px;
    }

    .auth-logo-circle {
        width: 56px; height: 56px;
        background: white;
        border-radius: 50%;
        padding: 3px;
        box-shadow: 0 6px 16px rgba(0,0,0,0.3), 0 0 0 3px rgba(6,182,212,0.2);
        overflow: hidden;
        flex-shrink: 0;
    }
    .auth-logo-circle img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
    .auth-logo h1 { font-size: 1.5rem; color: #fff; font-weight: 700; line-height: 1; }
    .auth-logo p { font-size: 0.85rem; color: var(--dark-600); margin-top: 4px; }

    .auth-header h2 { font-size: 1.6rem; color: #fff; margin-bottom: 8px; font-weight: 700; }
    .auth-header p { color: var(--dark-600); font-size: 0.92rem; margin-bottom: 28px; line-height: 1.5; }

    .alert {
        padding: 14px 16px;
        border-radius: 12px;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 0.9rem;
        line-height: 1.4;
    }
    .alert-danger { background: rgba(239,68,68,0.1); color: #fca5a5; border: 1px solid rgba(239,68,68,0.3); }
    .alert-success { background: rgba(16,185,129,0.1); color: #6ee7b7; border: 1px solid rgba(16,185,129,0.3); }
    .alert-info { background: rgba(6,182,212,0.08); color: #67e8f9; border: 1px solid rgba(6,182,212,0.3); }
    .alert i { font-size: 20px; flex-shrink: 0; }

    .form-group { margin-bottom: 22px; }
    .form-group label { display: block; margin-bottom: 8px; color: var(--dark-700); font-weight: 500; font-size: 0.9rem; }

    .input-wrapper { position: relative; }
    .input-icon { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--dark-500); font-size: 18px; }

    /* Champ en lecture seule (non modifiable) : email pré-rempli sur la page reset */
    .static-field {
        display: flex;
        align-items: center;
        gap: 12px;
        width: 100%;
        padding: 14px 16px;
        border: 2px solid var(--dark-200);
        border-radius: 12px;
        background: rgba(6, 182, 212, 0.05);
        color: var(--dark-700);
        font-size: 1rem;
        word-break: break-all;
    }
    .static-field i { color: var(--primary); font-size: 18px; flex-shrink: 0; }

    input[type="email"], input[type="password"], input[type="text"] {
        width: 100%;
        padding: 14px 16px 14px 50px;
        border: 2px solid var(--dark-200);
        border-radius: 12px;
        font-size: 1rem;
        background: var(--dark-50);
        color: white;
        font-family: inherit;
        transition: all 0.3s ease;
    }
    input::placeholder { color: var(--dark-500); }
    input:focus { outline: none; border-color: var(--primary); background: var(--dark-100); box-shadow: 0 0 0 4px rgba(6,182,212,0.1); }

    .btn-primary {
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
        box-shadow: 0 4px 16px rgba(6,182,212,0.4);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-family: inherit;
    }
    .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 24px rgba(6,182,212,0.5); }

    .auth-footer { margin-top: 28px; text-align: center; padding-top: 22px; border-top: 1px solid var(--dark-200); }
    .auth-footer a { color: var(--primary); text-decoration: none; font-size: 0.9rem; font-weight: 500; }
    .auth-footer a:hover { text-decoration: underline; }
    .auth-footer p { color: var(--dark-500); font-size: 0.82rem; margin-top: 12px; }

    @media (max-width: 480px) {
        .auth-card { padding: 36px 26px; border-radius: 16px; }
    }
</style>
