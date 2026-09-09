@extends('admin.layout')

@section('content')
<style>
    .landing-admin-shell {
        position: relative;
        min-height: 100%;
    }

    .landing-admin-shell::before {
        content: "";
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at top left, rgba(110,120,255,.18), transparent 32%),
                    radial-gradient(circle at bottom right, rgba(30,168,211,.18), transparent 30%);
        pointer-events: none;
    }

    .landing-topbar {
        position: relative;
        z-index: 1;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        background: rgba(255,255,255,.72);
        border: 1px solid rgba(148,163,184,.18);
        box-shadow: 0 12px 30px rgba(15, 23, 42, .04);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        border-radius: 22px;
        padding: 20px 24px;
        margin-bottom: 24px;
    }

    .landing-brand {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .landing-brand-badge {
        width: 44px;
        height: 44px;
        display: grid;
        place-items: center;
        border-radius: 14px;
        background: linear-gradient(135deg, #3b82f6, #7c3aed);
        color: #fff;
        font-size: 1.2rem;
        font-weight: 700;
        box-shadow: 0 12px 24px rgba(59, 130, 246, .25);
    }

    .landing-brand-title {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 700;
        color: #1f2937;
    }

    .landing-brand-subtitle {
        margin: 0;
        font-size: .8rem;
        color: #6b7280;
    }

    .landing-stat {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 14px;
        border-radius: 14px;
        background: rgba(248,250,252,.9);
        border: 1px solid rgba(148,163,184,.14);
    }

    .landing-stat-icon {
        width: 36px;
        height: 36px;
        border-radius: 12px;
        display: grid;
        place-items: center;
        background: linear-gradient(135deg, rgba(59,130,246,.12), rgba(124,58,237,.12));
        color: #4f46e5;
    }

    .landing-stat strong {
        display: block;
        font-size: 1.05rem;
        color: #111827;
    }

    .landing-stat span {
        font-size: .72rem;
        color: #64748b;
    }

    .landing-hero {
        position: relative;
        z-index: 1;
        display: grid;
        grid-template-columns: 1.3fr .7fr;
        gap: 24px;
        margin-bottom: 26px;
    }

    .landing-hero-card,
    .landing-panel {
        background: rgba(255,255,255,.8);
        border: 1px solid rgba(148,163,184,.18);
        border-radius: 28px;
        box-shadow: 0 20px 45px rgba(15, 23, 42, .06);
        backdrop-filter: blur(12px);
    }

    .landing-hero-card {
        padding: 30px 30px 24px;
    }

    .landing-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-radius: 999px;
        background: rgba(59,130,246,.08);
        color: #2563eb;
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .landing-title {
        margin: 18px 0 14px;
        font-size: clamp(2.1rem, 3vw, 3.6rem);
        line-height: 1.1;
        font-weight: 800;
        letter-spacing: -.06em;
        color: #0f172a;
    }

    .landing-title .muted {
        display: block;
        color: #4f46e5;
    }

    .landing-subtitle {
        max-width: 620px;
        color: #64748b;
        font-size: 1.02rem;
        line-height: 1.75;
    }

    .landing-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 24px;
    }

    .landing-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px 18px;
        border-radius: 12px;
        font-weight: 700;
        text-decoration: none;
        transition: .2s ease;
    }

    .landing-btn.primary {
        background: linear-gradient(135deg, #2563eb, #7c3aed);
        color: #fff;
        box-shadow: 0 16px 30px rgba(37, 99, 235, .25);
    }

    .landing-btn.secondary {
        background: #f8fafc;
        color: #0f172a;
        border: 1px solid rgba(148,163,184,.2);
    }

    .landing-hero-list {
        display: grid;
        grid-template-columns: repeat(2, minmax(0,1fr));
        gap: 12px;
        margin-top: 22px;
    }

    .landing-check {
        display: flex;
        align-items: center;
        gap: 10px;
        background: rgba(248,250,252,.9);
        border: 1px solid rgba(148,163,184,.12);
        border-radius: 12px;
        padding: 10px 12px;
        color: #334155;
        font-weight: 600;
        font-size: .85rem;
    }

    .landing-check .icon {
        width: 24px;
        height: 24px;
        border-radius: 8px;
        background: rgba(34,197,94,.12);
        color: #16a34a;
        display: grid;
        place-items: center;
        font-size: .8rem;
    }

    .landing-side {
        display: grid;
        gap: 16px;
    }

    .landing-panel {
        padding: 20px;
    }

    .landing-panel h4 {
        margin: 0 0 6px;
        font-size: 1rem;
        color: #0f172a;
    }

    .landing-panel p {
        margin: 0;
        color: #64748b;
        font-size: .82rem;
    }

    .mini-chart {
        margin-top: 18px;
        height: 150px;
        border-radius: 18px;
        background: linear-gradient(180deg, rgba(59,130,246,.08), rgba(14,165,233,.02));
        position: relative;
        overflow: hidden;
    }

    .mini-chart::before {
        content: "";
        position: absolute;
        inset: 18px 12px 12px 12px;
        border-radius: 14px;
        background: linear-gradient(180deg, rgba(14,165,233,.10), rgba(59,130,246,.02));
        clip-path: polygon(0 78%, 18% 70%, 34% 62%, 52% 55%, 70% 42%, 86% 28%, 100% 10%, 100% 100%, 0 100%);
    }

    .landing-grid {
        position: relative;
        z-index: 1;
        display: grid;
        grid-template-columns: repeat(3, minmax(0,1fr));
        gap: 20px;
    }

    .module-card {
        display: block;
        height: 100%;
        text-decoration: none;
        background: rgba(255,255,255,.8);
        border: 1px solid rgba(148,163,184,.18);
        border-radius: 22px;
        padding: 22px 20px;
        box-shadow: 0 15px 35px rgba(15,23,42,.04);
        transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease;
    }

    .module-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 20px 40px rgba(15,23,42,.08);
        border-color: rgba(59,130,246,.25);
        text-decoration: none;
    }

    .card-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 18px;
    }

    .module-icon {
        width: 46px;
        height: 46px;
        display: grid;
        place-items: center;
        border-radius: 14px;
        background: linear-gradient(135deg, rgba(59,130,246,.12), rgba(124,58,237,.12));
        color: #3b82f6;
        font-size: 1.2rem;
    }

    .module-tag {
        display: inline-flex;
        padding: 6px 9px;
        border-radius: 999px;
        font-size: .68rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        background: rgba(59,130,246,.08);
        color: #2563eb;
    }

    .module-card h3 {
        margin: 0 0 9px;
        font-size: 1.1rem;
        color: #111827;
        font-weight: 800;
    }

    .module-card p {
        margin: 0;
        color: #64748b;
        font-size: .88rem;
        line-height: 1.7;
    }

    .module-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-top: 18px;
        font-size: .82rem;
        font-weight: 700;
        color: #2563eb;
    }

    @media (max-width: 991.98px) {
        .landing-hero {
            grid-template-columns: 1fr;
        }

        .landing-grid {
            grid-template-columns: repeat(2, minmax(0,1fr));
        }
    }

    @media (max-width: 767.98px) {
        .landing-grid {
            grid-template-columns: 1fr;
        }

        .landing-topbar {
            flex-direction: column;
            align-items: flex-start;
        }

        .landing-hero-card,
        .landing-panel {
            padding: 20px 18px;
        }
    }
</style>

<div class="landing-admin-shell">
    <div class="landing-topbar">
        <div class="landing-brand">
            <div class="landing-brand-badge">D</div>
            <div>
                <p class="landing-brand-title">Diagoma ERP</p>
                <p class="landing-brand-subtitle">Centre d’administration</p>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-3">
            <div class="landing-stat">
                <div class="landing-stat-icon">📊</div>
                <div>
                    <strong>{{ count($cards) }}</strong>
                    <span>Modules</span>
                </div>
            </div>
            <div class="landing-stat">
                <div class="landing-stat-icon">⚡</div>
                <div>
                    <strong>24/7</strong>
                    <span>Monitoring</span>
                </div>
            </div>
        </div>
    </div>

    <div class="landing-hero">
        <div class="landing-hero-card">
            <span class="landing-eyebrow">Administration</span>
            <h1 class="landing-title">
                Contrôlez votre organisation
                <span class="muted">en un seul espace</span>
            </h1>
            <p class="landing-subtitle">
                Gérez les comptes, permissions, entreprises, visiteurs, logs et modules de votre plateforme avec une interface inspirée de Metronic, pensée pour la performance et la simplicité.
            </p>

            <div class="landing-actions">
                <a href="{{ $cards[0]['link'] ?? route('admin.settings') }}" class="landing-btn primary">Accéder au module</a>
                <a href="{{ route('admin.settings') }}" class="landing-btn secondary">Paramètres</a>
            </div>

            <div class="landing-hero-list">
                <div class="landing-check"><span class="icon">✓</span> Gestion centralisée</div>
                <div class="landing-check"><span class="icon">✓</span> Sécurité renforcée</div>
                <div class="landing-check"><span class="icon">✓</span> Multi-tenant</div>
                <div class="landing-check"><span class="icon">✓</span> Accès rapide</div>
            </div>
        </div>

        <div class="landing-side">
            <div class="landing-panel">
                <h4>Performance</h4>
                <p>Vue d’ensemble operative</p>
                <div class="mini-chart"></div>
            </div>

            <div class="landing-panel">
                <h4>Activité</h4>
                <p>Opérations récentes</p>
                <div class="mt-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small">Utilisateurs</span>
                        <strong>96%</strong>
                    </div>
                    <div class="progress" style="height: 10px; border-radius: 999px;">
                        <div class="progress-bar" style="width: 96%; background: linear-gradient(90deg, #22c55e, #4ade80);"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="landing-grid">
        @foreach($cards as $card)
            <a href="{{ $card['link'] }}" class="module-card">
                <div class="card-head">
                    <div class="module-icon">{{ $card['icon'] ?? '⚙️' }}</div>
                    <span class="module-tag">Admin</span>
                </div>
                <h3>{{ $card['title'] }}</h3>
                <p>{{ $card['description'] }}</p>
                <span class="module-link">Ouvrir <i class="ki-duotone ki-arrow-right fs-5"></i></span>
            </a>
        @endforeach
    </div>
</div>
@endsection
