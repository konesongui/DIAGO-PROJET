<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Diagoma | Espace démo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link href="{{ asset('assets/plugins/global/plugins.bundle.css') }}" rel="stylesheet" />
    <link href="{{ asset('assets/css/style.bundle.css') }}" rel="stylesheet" />
    <style>
        :root {
            --dk-primary: #00a3ff;
            --dk-primary-2: #4d68ff;
            --dk-dark: #0d1424;
            --dk-surface: #f5f8ff;
            --dk-muted: #65748a;
            --dk-border: rgba(112, 128, 176, 0.18);
        }
        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: linear-gradient(180deg, #eef6ff 0%, #f6f9ff 100%);
            color: var(--dk-dark);
        }
        .container { width: min(1180px, calc(100% - 28px)); margin: 0 auto; }
        .topbar {
            border-bottom: 1px solid rgba(17,24,39,.06);
            background: rgba(255,255,255,.85);
            backdrop-filter: blur(12px);
        }
        .topbar-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 0;
            gap: 14px;
        }
        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-weight: 800;
            color: var(--dk-dark);
        }
        .brand-mark {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--dk-primary), var(--dk-primary-2));
            color: white;
        }
        .demo-shell {
            padding: 48px 0 80px;
        }
        .hero {
            display: grid;
            grid-template-columns: 1.1fr .9fr;
            gap: 24px;
            align-items: stretch;
        }
        .card {
            background: rgba(255,255,255,.88);
            border: 1px solid var(--dk-border);
            border-radius: 28px;
            box-shadow: 0 18px 40px rgba(15,23,42,.06);
        }
        .content-card {
            padding: 28px;
        }
        .eyebrow {
            display: inline-block;
            background: rgba(0,163,255,.08);
            color: #0f69ff;
            border-radius: 999px;
            padding: 9px 14px;
            font-size: 0.73rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 16px;
        }
        h1 {
            margin: 0 0 16px;
            font-size: clamp(2rem, 3vw, 3.3rem);
            letter-spacing: -0.06em;
            color: var(--dk-dark);
        }
        .muted { color: var(--dk-muted); line-height: 1.8; }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(93,211,158,.12);
            color: #166534;
            border-radius: 999px;
            padding: 9px 12px;
            font-weight: 700;
            margin-top: 18px;
        }
        .module-list {
            margin-top: 22px;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }
        .module-item {
            border: 1px solid var(--dk-border);
            border-radius: 16px;
            padding: 16px;
            background: #f9fbff;
        }
        .module-name {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 8px;
            font-weight: 700;
        }
        .status {
            display: inline-flex;
            padding: 5px 8px;
            border-radius: 999px;
            font-size: .67rem;
            font-weight: 800;
            background: rgba(93,211,158,.12);
            color: #158a62;
        }
        .side-card {
            padding: 22px;
        }
        .mail-box {
            border-radius: 18px;
            background: linear-gradient(135deg, rgba(77,104,255,.08), rgba(0,163,255,.08));
            border: 1px solid rgba(77,104,255,.14);
            padding: 18px;
            margin-bottom: 18px;
        }
        .mini-label {
            color: var(--dk-muted);
            font-size: .75rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            font-weight: 800;
        }
        .email-value {
            margin-top: 8px;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--dk-dark);
            word-break: break-all;
        }
        .action-row {
            display: flex;
            gap: 12px;
            margin-top: 22px;
            flex-wrap: wrap;
        }
        .btn {
            border: none;
            border-radius: 12px;
            padding: 12px 18px;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--dk-primary), var(--dk-primary-2));
            color: white;
        }
        .btn-secondary {
            background: white;
            border: 1px solid var(--dk-border);
            color: var(--dk-dark);
        }
        @media (max-width: 820px) {
            .hero, .module-list { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="container topbar-inner">
            <a href="{{ route('landing') }}" class="brand">
                <span class="brand-mark">D</span>
                <span>Diagoma</span>
            </a>
            <form method="POST" action="{{ route('demo.logout') }}">
                @csrf
                <button type="submit" class="btn btn-secondary">Fermer la démo</button>
            </form>
        </div>
    </header>

    <main class="demo-shell">
        <div class="container hero">
            <div class="card content-card">
                <div class="eyebrow">Espace démo</div>
                <h1>Bienvenue dans votre environnement de démonstration</h1>
                <p class="muted">Vous avez un accès temporaire à une version fonctionnelle de l’ERP. Explorez les modules clés du système avant de passer à un plan définitif.</p>
                <div class="badge"><i class="bi bi-check-circle me-1"></i>Accès activé pour {{ $demoEmail }}</div>

                <div class="module-list">
                    @foreach($modules as $module)
                        <div class="module-item">
                            <div class="module-name">
                                <span>{{ $module['name'] }}</span>
                                <span class="status">{{ $module['status'] }}</span>
                            </div>
                            <div class="muted" style="font-size: .92rem;">{{ $module['description'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            <aside class="card side-card">
                <div class="mail-box">
                    <div class="mini-label">Email d’accès</div>
                    <div class="email-value">{{ $demoEmail }}</div>
                </div>

                <div class="mini-label">Informations</div>
                <p class="muted">Cette session est disponible pour tester la navigation, les modules et la logique de gestion sans effet sur les données réelles.</p>

                <div class="action-row">
                    <a href="{{ route('login') }}" class="btn btn-primary">Se connecter</a>
                    <a href="{{ route('landing') }}" class="btn btn-secondary">Retour au site</a>
                </div>
            </aside>
        </div>
    </main>
</body>
</html>
