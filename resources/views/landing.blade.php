<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $page_title ?? 'Diagoma | ERP multi-entreprises' }}</title>
    <meta name="description" content="{{ $meta_description ?? 'Diagoma ERP - modules, packs et accès démo.' }}" />
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
            --dk-card: rgba(255,255,255,0.92);
            --dk-text: #1c2540;
            --dk-muted: #65748a;
            --dk-border: rgba(112, 128, 176, 0.18);
            --dk-shadow: 0 20px 50px rgba(17, 24, 39, 0.12);
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            color: var(--dk-text);
            background:
                radial-gradient(circle at top left, rgba(77,104,255,.12), transparent 28%),
                radial-gradient(circle at bottom right, rgba(0,163,255,.15), transparent 30%),
                linear-gradient(180deg, #f5f8ff 0%, #edf4ff 100%);
        }

        a { text-decoration: none; }
        .container { width: min(1200px, calc(100% - 32px)); margin: 0 auto; }
        .topbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 30;
            background: rgba(245, 248, 255, 0.79);
            backdrop-filter: blur(18px);
            border-bottom: 1px solid rgba(17, 24, 39, 0.05);
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.02);
        }
        body { padding-top: 82px; }
        .topbar-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            min-height: 82px;
        }
        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-weight: 800;
            color: var(--dk-dark);
            letter-spacing: -0.04em;
        }
        .brand-mark {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--dk-primary), var(--dk-primary-2));
            color: white;
            box-shadow: 0 12px 22px rgba(77,104,255,.26);
            font-size: 1.2rem;
        }
        .brand-sub {
            font-size: 0.68rem;
            color: var(--dk-muted);
            letter-spacing: 0.12em;
            text-transform: uppercase;
            font-weight: 700;
        }
        .nav-menu {
            display: flex;
            align-items: center;
            gap: 26px;
            color: var(--dk-muted);
            font-size: 0.9rem;
            font-weight: 700;
        }
        .nav-menu a {
            color: var(--dk-muted);
            transition: .2s ease;
        }
        .nav-menu a:hover { color: var(--dk-dark); }
        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .btn-link {
            background: transparent;
            border: 1px solid transparent;
            color: var(--dk-dark);
            font-weight: 700;
        }
        .btn-link:hover {
            border-color: rgba(17, 24, 39, 0.08);
            background: rgba(255,255,255,.55);
        }
        .btn {
            border: none;
            border-radius: 12px;
            padding: 12px 18px;
            font-weight: 700;
            cursor: pointer;
            transition: .2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--dk-primary), var(--dk-primary-2));
            color: #fff;
            box-shadow: 0 16px 28px rgba(77,104,255,.28);
        }
        .btn-secondary {
            background: rgba(255,255,255,.8);
            border: 1px solid var(--dk-border);
            color: var(--dk-dark);
        }

        .header-carousel-wrap {
            background: rgba(255,255,255,0.12);
            border-bottom: 1px solid rgba(17, 24, 39, 0.04);
            padding: 0;
        }
        .header-carousel {
            position: relative;
            overflow: hidden;
            width: 100%;
            height: 700px;
            background: rgba(255,255,255,0.75);
            border: none;
            border-radius: 0;
            box-shadow: none;
            margin: 0;
        }
        .header-carousel-track {
            display: flex;
            transition: transform 0.6s ease;
            width: 100%;
            height: 100%;
        }
        .header-slide {
            min-width: 100%;
            position: relative;
            height: 100%;
            overflow: hidden;
            background-size: cover;
            background-position: center;
        }
        .header-slide::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, rgba(9,15,30,.55), rgba(9,15,30,.28));
        }
        .header-slide-content {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 0 90px;
            color: white;
            z-index: 1;
        }
        .header-slide-content .label {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
            padding: 10px 22px;
            border: 1px solid rgba(255,255,255,0.48);
            border-radius: 999px;
            background: rgba(255,255,255,0.12);
            backdrop-filter: blur(4px);
            font-size: 0.76rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: rgba(255,255,255,0.92);
        }
        .header-slide-content h3 {
            margin: 0;
            font-size: clamp(3rem, 5vw, 7rem);
            line-height: 0.98;
            letter-spacing: -0.07em;
            font-weight: 800;
            max-width: 1400px;
            text-shadow: 0 3px 12px rgba(0,0,0,0.18);
        }
        .header-slide-content p {
            margin: 28px 0 0;
            font-size: clamp(1rem, 1.7vw, 1.6rem);
            color: rgba(255,255,255,0.9);
            max-width: 980px;
            line-height: 1.6;
        }
        .header-carousel-dots {
            position: absolute;
            left: 50%;
            bottom: 26px;
            transform: translateX(-50%);
            display: flex;
            gap: 8px;
            z-index: 2;
        }
        .header-carousel-dots button {
            width: 10px;
            height: 10px;
            border: none;
            border-radius: 50%;
            background: rgba(255,255,255,0.5);
            cursor: pointer;
            padding: 0;
        }
        .header-carousel-dots button.active {
            background: white;
            box-shadow: 0 0 0 3px rgba(255,255,255,.25);
        }
        .hero {
            padding: 88px 0 36px;
        }
        .hero-grid {
            display: grid;
            grid-template-columns: 1.2fr .8fr;
            gap: 42px;
            align-items: center;
        }
        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(0,163,255,.08);
            color: #0f69ff;
            border-radius: 999px;
            padding: 10px 14px;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        h1 {
            margin: 18px 0 18px;
            font-size: clamp(2.7rem, 4vw, 4.7rem);
            line-height: 1.04;
            letter-spacing: -0.06em;
            color: var(--dk-dark);
        }
        .hero h1 .gradient-text {
            background: linear-gradient(135deg, var(--dk-primary), var(--dk-primary-2));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .lead {
            color: var(--dk-muted);
            font-size: 1.08rem;
            line-height: 1.8;
            max-width: 650px;
        }
        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 30px;
        }
        .trust-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 28px;
            color: var(--dk-muted);
            font-weight: 600;
            font-size: 0.82rem;
        }
        .trust-row span {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .hero-card {
            position: relative;
            border-radius: 28px;
            background: rgba(255,255,255,0.85);
            border: 1px solid rgba(17,24,39,.06);
            box-shadow: var(--dk-shadow);
            padding: 22px;
        }
        .dashboard-surface {
            border-radius: 22px;
            background: linear-gradient(180deg, #f8fbff 0%, #edf4ff 100%);
            border: 1px solid rgba(61,90,255,.08);
            padding: 18px;
        }
        .mini-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
        }
        .mini-dots {
            display: flex;
            gap: 8px;
        }
        .mini-dots span {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            background: #edf2f7;
        }
        .mini-dots span:nth-child(1) { background: #ff6f61; }
        .mini-dots span:nth-child(2) { background: #ffbf69; }
        .mini-dots span:nth-child(3) { background: #5dd39e; }
        .mini-graph {
            height: 180px;
            border-radius: 16px;
            position: relative;
            overflow: hidden;
            background: linear-gradient(180deg, rgba(77,104,255,.08), rgba(0,163,255,.04));
            border: 1px solid rgba(77,104,255,.08);
        }
        .mini-graph::before {
            content: "";
            position: absolute;
            inset: 18px 16px 16px 16px;
            background: linear-gradient(180deg, rgba(77,104,255,.14), rgba(0,163,255,.02));
            clip-path: polygon(0 76%, 16% 70%, 32% 62%, 44% 56%, 60% 45%, 74% 30%, 88% 24%, 100% 12%, 100% 100%, 0 100%);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0,1fr));
            gap: 14px;
            margin-top: 18px;
        }
        .mini-stat {
            border: 1px solid rgba(17,24,39,.05);
            background: white;
            border-radius: 15px;
            padding: 14px;
        }
        .mini-stat .value {
            display: block;
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--dk-dark);
        }
        .mini-stat .label {
            color: var(--dk-muted);
            font-size: 0.72rem;
            margin-top: 4px;
        }
        section { padding: 40px 0; }
        .section-title {
            text-align: center;
            margin-bottom: 26px;
        }
        .section-title h2 {
            margin: 0;
            font-size: clamp(2rem, 3vw, 3rem);
            color: var(--dk-dark);
            letter-spacing: -0.05em;
        }
        .section-title p {
            margin: 12px auto 0;
            max-width: 680px;
            color: var(--dk-muted);
            line-height: 1.8;
        }
        .module-grid, .pack-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0,1fr));
            gap: 20px;
        }
        .module-card, .pack-card {
            background: rgba(255,255,255,0.87);
            border: 1px solid var(--dk-border);
            border-radius: 24px;
            padding: 24px;
            box-shadow: 0 10px 26px rgba(15,23,42,.04);
        }
        .module-icon {
            width: 52px;
            height: 52px;
            display: grid;
            place-items: center;
            border-radius: 16px;
            background: linear-gradient(135deg, rgba(0,163,255,.10), rgba(77,104,255,.12));
            font-size: 1.5rem;
            margin-bottom: 18px;
        }
        .module-card h3, .pack-card h3 {
            margin: 0 0 10px;
            font-size: 1.28rem;
            color: var(--dk-dark);
        }
        .module-card p, .pack-card p {
            margin: 0;
            color: var(--dk-muted);
            line-height: 1.8;
        }
        .pack-card {
            position: relative;
        }
        .pack-card.featured {
            border-color: rgba(77,104,255,.28);
            background: linear-gradient(180deg, rgba(77,104,255,.05), rgba(255,255,255,.95));
            transform: translateY(-6px);
        }
        .badge-popular {
            position: absolute;
            top: 16px;
            right: 16px;
            background: rgba(77,104,255,.12);
            color: #3145ff;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 800;
            padding: 8px 10px;
            text-transform: uppercase;
        }
        .pack-price {
            display: flex;
            align-items: baseline;
            gap: 6px;
            margin: 18px 0 14px;
        }
        .pack-price .price {
            font-size: 2.4rem;
            font-weight: 800;
            color: var(--dk-dark);
            letter-spacing: -0.05em;
        }
        .pack-price .period {
            color: var(--dk-muted);
            font-weight: 600;
        }
        .feature-list {
            list-style: none;
            padding: 0;
            margin: 16px 0 22px;
            display: grid;
            gap: 10px;
        }
        .feature-list li {
            color: var(--dk-text);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .feature-list li::before {
            content: "✓";
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: inline-grid;
            place-items: center;
            background: rgba(93,211,158,.12);
            color: #158a62;
            font-weight: 800;
            font-size: 0.72rem;
        }
        .cta-panel {
            background: linear-gradient(135deg, rgba(77,104,255,.95), rgba(0,163,255,.92));
            border-radius: 30px;
            padding: 42px 38px;
            color: white;
            box-shadow: 0 26px 50px rgba(77,104,255,.25);
        }
        .cta-panel h3 {
            margin: 0 0 10px;
            font-size: clamp(2rem, 3vw, 3rem);
            letter-spacing: -0.05em;
        }
        .cta-panel p {
            margin: 0;
            color: rgba(255,255,255,.8);
            line-height: 1.8;
            max-width: 760px;
        }
        .demo-form {
            margin-top: 24px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            max-width: 740px;
        }
        .demo-form input {
            flex: 1 1 300px;
            height: 52px;
            border: 1px solid rgba(255,255,255,.3);
            border-radius: 12px;
            background: rgba(255,255,255,.14);
            color: white;
            padding: 0 16px;
            font-size: 1rem;
            outline: none;
        }
        .demo-form input::placeholder { color: rgba(255,255,255,.75); }
        .demo-form button {
            height: 52px;
            border: none;
            border-radius: 12px;
            background: white;
            color: var(--dk-dark);
            padding: 0 22px;
            font-weight: 800;
        }
        .alert {
            border-radius: 14px;
            padding: 12px 14px;
            font-weight: 600;
            margin: 18px 0 0;
        }
        .alert-success { background: rgba(93,211,158,.12); color: #166534; }
        .alert-danger { background: rgba(255, 111, 97, .10); color: #b42318; }
        .pack-modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 100;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(13, 20, 36, .62);
        }
        .pack-modal.is-open { display: flex; }
        .pack-modal-card {
            width: min(560px, 100%);
            max-height: calc(100vh - 40px);
            overflow: auto;
            border-radius: 22px;
            background: #fff;
            padding: 28px;
            box-shadow: 0 24px 70px rgba(13, 20, 36, .28);
        }
        .pack-modal-close {
            border: 0;
            background: #eef2f8;
            border-radius: 50%;
            width: 34px;
            height: 34px;
            font-size: 1.2rem;
            cursor: pointer;
        }
        .pack-modal-form { display: grid; gap: 14px; margin-top: 20px; }
        .pack-modal-form input, .pack-modal-form textarea {
            width: 100%;
            border: 1px solid var(--dk-border);
            border-radius: 12px;
            padding: 12px 14px;
            font: inherit;
        }
        .landing-footer {
            padding: 72px 0 26px;
            background: linear-gradient(180deg, rgba(11, 18, 32, 0.98), rgba(11, 18, 32, 1));
            color: rgba(255,255,255,0.76);
        }
        .footer-grid {
            display: grid;
            grid-template-columns: 1.3fr .7fr .7fr .9fr;
            gap: 28px;
            padding-bottom: 26px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .footer-brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-weight: 800;
            color: white;
            letter-spacing: -0.04em;
            margin-bottom: 14px;
        }
        .footer-brand .brand-mark {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--dk-primary), var(--dk-primary-2));
        }
        .footer-copy {
            max-width: 360px;
            line-height: 1.8;
            color: rgba(255,255,255,0.68);
        }
        .footer-title {
            color: rgba(255,255,255,0.94);
            font-size: 0.78rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            margin-bottom: 16px;
        }
        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            gap: 10px;
        }
        .footer-links a {
            color: rgba(255,255,255,0.72);
            transition: .2s ease;
        }
        .footer-links a:hover { color: white; }
        .footer-meta {
            padding-top: 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            color: rgba(255,255,255,0.62);
            font-size: 0.88rem;
        }
        @media (max-width: 980px) {
            .hero-grid, .module-grid, .pack-grid, .footer-grid { grid-template-columns: 1fr; }
            .nav-menu { display: none; }
        }
    </style>
</head>
<body style="--dk-primary: {{ $primary_color ?? '#00a3ff' }}; --dk-primary-2: {{ $secondary_color ?? '#4d68ff' }};">
    <header class="topbar">
        <div class="container topbar-inner">
            <a href="{{ route('landing') }}" class="brand" aria-label="{{ $brand_name ?? 'Diagoma' }} home">
                <span class="brand-mark">D</span>
                <span>
                    <span>{{ $brand_name ?? 'Diagoma' }}</span>
                    <span class="brand-sub">ERP</span>
                </span>
            </a>

            <nav class="nav-menu" aria-label="Main navigation">
                <a href="#overview">Overview</a>
                <a href="#modules">Modules</a>
                <a href="#packs">Packs</a>
                <a href="#demo">Démonstration</a>
                <a href="#contact">Contact</a>
            </nav>

            <div class="topbar-actions">
                <a href="{{ route('login') }}" class="btn btn-link">Connexion</a>
                <a href="#demo" class="btn btn-secondary">{{ $primary_cta_label ?? 'Essai gratuit' }}</a>
                <a href="{{ route('login') }}" class="btn btn-primary">Commencer</a>
            </div>
        </div>
    </header>

    <div class="header-carousel-wrap">
        <div class="header-carousel" aria-label="Carousel de démonstration">
            <div class="header-carousel-track" id="headerCarouselTrack">
                @foreach(($slides ?? []) as $slide)
                    <div class="header-slide" style="background-image:url('{{ $slide['image'] ?? 'https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=1600&q=80' }}');">
                        <div class="header-slide-content">
                            <span class="label">{{ $slide['label'] ?? 'Présentation' }}</span>
                            <h3>{{ $slide['title'] ?? 'Le meilleur de votre organisation' }}</h3>
                            <p>{{ $slide['text'] ?? '' }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="header-carousel-dots" aria-label="Pagination du carousel">
                @foreach(($slides ?? []) as $index => $slide)
                    <button type="button" class="{{ $loop->first ? 'active' : '' }}" data-index="{{ $index }}" aria-label="Afficher le slide {{ $index + 1 }}"></button>
                @endforeach
            </div>
        </div>
    </div>

    <main>
        <section class="hero" id="overview">
            <div class="container hero-grid">
                <div>
                    <div class="eyebrow">{{ $hero_badge ?? 'ERP intelligent • multi-tenant' }}</div>
                    <h1>{!! $hero_title ?? 'Un ERP qui <span class="gradient-text">simplifie</span> la gestion de votre entreprise.' !!}</h1>
                    <p class="lead">
                        {{ $hero_subtitle ?? 'Centralisez votre finance, votre RH, votre commercial, votre administration et la supervision de vos opérations, dans un espace prêt à l’emploi et prêt à être déployé rapidement.' }}
                    </p>
                    <div class="hero-actions">
                        <a href="{{ $primary_cta_url ?? '#demo' }}" class="btn btn-primary">{{ $primary_cta_label ?? 'Obtenir un accès démo' }}</a>
                        <a href="{{ $secondary_cta_url ?? '#modules' }}" class="btn btn-secondary">{{ $secondary_cta_label ?? 'Découvrir les modules' }}</a>
                    </div>
                    <div class="trust-row">
                        @foreach(($stats ?? []) as $stat)
                            <span><i class="bi bi-check-circle me-1"></i>{{ $stat['value'] ?? '' }} {{ $stat['label'] ?? '' }}</span>
                        @endforeach
                    </div>
                </div>

                <div class="hero-card">
                    <div class="dashboard-surface">
                        <div class="mini-head">
                            <strong>Tableau de bord</strong>
                            <div class="mini-dots"><span></span><span></span><span></span></div>
                        </div>
                        <div class="mini-graph" aria-hidden="true"></div>
                        <div class="stats-grid">
                            @foreach(($dashboard_stats ?? []) as $metric)
                                <div class="mini-stat">
                                    <span class="value">{{ $metric['value'] ?? '' }}</span>
                                    <div class="label">{{ $metric['label'] ?? '' }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="modules">
            <div class="container">
                <div class="section-title">
                    <h2>Des modules conçus pour piloter votre organisation</h2>
                    <p>Chaque module est pensé pour être utile, lisible et directement exploitable par les équipes de terrain, la direction et l’administration.</p>
                </div>

                <div class="module-grid">
                    @foreach($modules as $module)
                        <article class="module-card">
                            <div class="module-icon"><i class="bi {{ $module['icon'] }}"></i></div>
                            <h3>{{ $module['title'] }}</h3>
                            <p>{{ $module['description'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="packs">
            <div class="container">
                <div class="section-title">
                    <h2>Choisissez le pack adapté à votre structure</h2>
                    <p>Que vous soyez une petite structure ou un groupe multi-sites, Diagoma s’adapte à votre croissance.</p>
                </div>

                <div class="pack-grid">
                    @foreach($packs as $pack)
                        <article class="pack-card {{ $pack['featured'] ?? false ? 'featured' : '' }}">
                            @if($pack['featured'] ?? false)
                                <span class="badge-popular">Populaire</span>
                            @endif
                            <h3>{{ $pack['name'] }}</h3>
                            <p>{{ $pack['description'] }}</p>
                            <div class="pack-price">
                                <span class="price">{{ $pack['price'] }}</span>
                                <span class="period">{{ $pack['currency'] }}/mois</span>
                            </div>
                            <ul class="feature-list">
                                @foreach($pack['features'] as $feature)
                                    <li>{{ $feature }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="btn btn-primary js-pack-request" data-pack="{{ $pack['name'] }}" style="width:100%">Choisir ce pack</button>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <div class="pack-modal" id="packRequestModal" aria-hidden="true">
            <div class="pack-modal-card" role="dialog" aria-modal="true" aria-labelledby="packRequestTitle">
                <div class="d-flex justify-content-between align-items-center gap-3">
                    <div>
                        <div class="eyebrow">Demande de pack</div>
                        <h3 id="packRequestTitle" class="mb-0">Être recontacté</h3>
                    </div>
                    <button type="button" class="pack-modal-close" data-close-pack-modal aria-label="Fermer">&times;</button>
                </div>
                <p class="text-muted mt-3 mb-0">Remplissez ce formulaire et notre équipe vous répondra par email ou WhatsApp.</p>
                <form method="POST" action="{{ route('pack.request') }}" class="pack-modal-form">
                    @csrf
                    <input type="hidden" name="pack_name" id="selectedPackName">
                    <input type="text" name="name" placeholder="Nom complet" required>
                    <input type="email" name="email" placeholder="Adresse email" required>
                    <input type="tel" name="phone" placeholder="Téléphone / WhatsApp">
                    <textarea name="message" rows="4" placeholder="Votre besoin ou votre question"></textarea>
                    <button type="submit" class="btn btn-primary">Envoyer ma demande</button>
                </form>
            </div>
        </div>

        <section id="demo">
            <div class="container">
                <div class="cta-panel">
                    <h3>{{ $demo_title ?? 'Essayez notre espace démo' }}</h3>
                    <p>{{ $demo_description ?? 'Renseignez votre adresse email pour obtenir un accès immédiat à une version de démonstration de l’ERP, avec les modules et interfaces les plus utilisés.' }}</p>

                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    <form method="POST" action="{{ route('demo.request') }}" class="demo-form">
                        @csrf
                        <input type="email" name="email" value="{{ old('email', session('demo_email') ?? '') }}" placeholder="Votre adresse email" required />
                        <button type="submit">{{ $demo_submit_label ?? 'Accéder à la démo' }}</button>
                    </form>

                    @if($demoGranted)
                        <div class="alert alert-success" style="margin-top:18px;">{{ $demo_success_label ?? 'Accès démo activé pour' }} <strong>{{ $demoEmail }}</strong> — <a href="{{ route('demo') }}" style="color:#0d1424;font-weight:800;">ouvrir l’espace démo</a></div>
                    @endif
                </div>
            </div>
        </section>
    </main>

    <footer class="landing-footer" id="contact">
        <div class="container">
            <div class="footer-grid">
                <div>
                    <a href="{{ route('landing') }}" class="footer-brand">
                        <span class="brand-mark">D</span>
                        <span>{{ $brand_name ?? 'Diagoma' }}</span>
                    </a>
                    <p class="footer-copy">{{ $footer_text ?? 'Une plateforme ERP moderne...' }}</p>
                </div>

                <div>
                    <div class="footer-title">Produit</div>
                    <ul class="footer-links">
                        <li><a href="#overview">Overview</a></li>
                        <li><a href="#modules">Modules</a></li>
                        <li><a href="#packs">Packs</a></li>
                        <li><a href="#demo">Démonstration</a></li>
                    </ul>
                </div>

                <div>
                    <div class="footer-title">Entreprise</div>
                    <ul class="footer-links">
                        <li><a href="{{ route('login') }}">Connexion</a></li>
                        <li><a href="#demo">Essayez la démo</a></li>
                        <li><a href="mailto:{{ $contact_email ?? 'contact@diagoma.com' }}">Support</a></li>
                        <li><a href="tel:{{ preg_replace('/[^0-9+]/', '', $contact_phone ?? '+22500000000') }}">{{ $contact_phone ?? '+225 00 00 00 00' }}</a></li>
                    </ul>
                </div>

                <div>
                    <div class="footer-title">Réseaux</div>
                    <ul class="footer-links">
                        <li><a href="#">LinkedIn</a></li>
                        <li><a href="#">Twitter</a></li>
                        <li><a href="#">Facebook</a></li>
                        <li><a href="mailto:{{ $contact_email ?? 'contact@diagoma.com' }}">{{ $contact_email ?? 'contact@diagoma.com' }}</a></li>
                    </ul>
                </div>
            </div>

            <div class="footer-meta">
                <div>© {{ date('Y') }} {{ $brand_name ?? 'Diagoma' }}. Tous droits réservés.</div>
                <div>Politique de confidentialité • Conditions d’utilisation</div>
            </div>
        </div>
    </footer>

    <script>
        (function () {
            const track = document.getElementById('headerCarouselTrack');
            if (!track) return;
            const slides = Array.from(track.children);
            const dots = Array.from(document.querySelectorAll('.header-carousel-dots button'));
            let index = 0;

            function render() {
                track.style.transform = 'translateX(-' + (index * 100) + '%)';
                dots.forEach((dot, i) => dot.classList.toggle('active', i === index));
            }

            dots.forEach((dot) => {
                dot.addEventListener('click', () => {
                    index = Number(dot.dataset.index || 0);
                    render();
                });
            });

            setInterval(() => {
                index = (index + 1) % slides.length;
                render();
            }, 4500);

            render();
        })();

        (function () {
            const modal = document.getElementById('packRequestModal');
            const packInput = document.getElementById('selectedPackName');
            if (!modal || !packInput) return;
            document.querySelectorAll('.js-pack-request').forEach((button) => {
                button.addEventListener('click', () => {
                    packInput.value = button.dataset.pack || '';
                    modal.classList.add('is-open');
                    modal.setAttribute('aria-hidden', 'false');
                });
            });
            document.querySelectorAll('[data-close-pack-modal]').forEach((button) => {
                button.addEventListener('click', () => {
                    modal.classList.remove('is-open');
                    modal.setAttribute('aria-hidden', 'true');
                });
            });
            modal.addEventListener('click', (event) => {
                if (event.target === modal) {
                    modal.classList.remove('is-open');
                    modal.setAttribute('aria-hidden', 'true');
                }
            });
        })();
    </script>
</body>
</html>
