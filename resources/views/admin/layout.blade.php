<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $title ?? 'Diago ERP' }}</title>
    <meta name="description" content="Diago ERP" />
    @php
        $browserLogo = data_get(auth()->user()->entreprise?->settings ?? [], 'logo');
        $appTheme = auth()->user()->entreprise
            ? data_get(auth()->user()->entreprise->settings ?? [], 'theme', 'diago')
            : session('console_theme', 'diago');
        $themePalettes = [
            'diago' => ['primary' => '#273772', 'dark' => '#1f2d61', 'accent' => '#fadf2f', 'header' => '#ffffff', 'surface' => '#f4f6fb', 'text' => '#172033', 'muted' => '#5b6478', 'button' => '#ffffff', 'icon' => '#273772', 'sidebar' => '#273772', 'highlight' => '#fadf2f'],
            'ocean' => ['primary' => '#4d68ff', 'dark' => '#293fba', 'accent' => '#8fa5ff', 'header' => '#ffffff', 'surface' => '#f3f6fb', 'text' => '#172b4d', 'muted' => '#8291a8', 'button' => '#ffffff'],
            'emerald' => ['primary' => '#0f9f78', 'dark' => '#08785c', 'accent' => '#62d8b5', 'header' => '#fafffd', 'surface' => '#f1faf7', 'text' => '#123b35', 'muted' => '#6c9189', 'button' => '#ffffff'],
            'royal' => ['primary' => '#7048e8', 'dark' => '#4c2aa6', 'accent' => '#b39bff', 'header' => '#fcfaff', 'surface' => '#f6f3ff', 'text' => '#2d2055', 'muted' => '#887ba9', 'button' => '#ffffff'],
            'amber' => ['primary' => '#d88900', 'dark' => '#a96000', 'accent' => '#ffc65c', 'header' => '#fffdf8', 'surface' => '#fff8e9', 'text' => '#4f3612', 'muted' => '#9c8158', 'button' => '#ffffff'],
            'slate' => ['primary' => '#475569', 'dark' => '#273449', 'accent' => '#91a3bb', 'header' => '#ffffff', 'surface' => '#f4f6f8', 'text' => '#1e293b', 'muted' => '#718096', 'button' => '#ffffff'],
            'midnight' => ['primary' => '#06b6d4', 'dark' => '#164e63', 'accent' => '#67e8f9', 'header' => '#f5fbff', 'surface' => '#eef9fc', 'text' => '#12344a', 'muted' => '#6c8ca0', 'button' => '#ffffff'],
            'coral' => ['primary' => '#f05d5e', 'dark' => '#b9364f', 'accent' => '#ffaaa0', 'header' => '#fffafa', 'surface' => '#fff3f1', 'text' => '#4c2630', 'muted' => '#9d747d', 'button' => '#ffffff'],
            'lavender' => ['primary' => '#a855f7', 'dark' => '#6b21a8', 'accent' => '#d8b4fe', 'header' => '#fefaff', 'surface' => '#faf5ff', 'text' => '#3b1f53', 'muted' => '#987fa8', 'button' => '#ffffff'],
            'teal' => ['primary' => '#0d9488', 'dark' => '#115e59', 'accent' => '#5eead4', 'header' => '#f7fffe', 'surface' => '#effcf9', 'text' => '#133d3b', 'muted' => '#71918e', 'button' => '#ffffff'],
            'graphite' => ['primary' => '#64748b', 'dark' => '#1e293b', 'accent' => '#cbd5e1', 'header' => '#ffffff', 'surface' => '#f1f3f5', 'text' => '#202938', 'muted' => '#788494', 'button' => '#ffffff', 'icon' => '#475569', 'flat' => true],
            'ruby' => ['primary' => '#dc3655', 'dark' => '#8f1836', 'accent' => '#ff9bae', 'header' => '#fffafa', 'surface' => '#fff3f5', 'text' => '#4b1d2b', 'muted' => '#9b7180', 'button' => '#ffffff', 'icon' => '#c02649', 'flat' => true],
            'forest' => ['primary' => '#25804b', 'dark' => '#155d38', 'accent' => '#86d6a3', 'header' => '#f8fff9', 'surface' => '#effaf2', 'text' => '#193b27', 'muted' => '#6d9078', 'button' => '#ffffff', 'icon' => '#207442', 'flat' => true],
            'sand' => ['primary' => '#b7791f', 'dark' => '#805516', 'accent' => '#f0c674', 'header' => '#fffdf8', 'surface' => '#fdf8ed', 'text' => '#4a3519', 'muted' => '#9a815c', 'button' => '#ffffff', 'icon' => '#a56819', 'flat' => true],
            'metronic_black' => ['primary' => '#00a3ff', 'dark' => '#07111f', 'accent' => '#7dd3fc', 'header' => '#15171c', 'surface' => '#0d0f12', 'text' => '#f1f5f9', 'muted' => '#94a3b8', 'button' => '#ffffff', 'icon' => '#7dd3fc', 'flat' => true, 'dark_mode' => true],
        ];
        $activePalette = $themePalettes[$appTheme] ?? $themePalettes['diago'];
        // Barre latérale : couleur dédiée du thème, sinon couleur principale (thèmes « flat ») ou foncée.
        $sidebarBackground = $activePalette['sidebar'] ?? (!empty($activePalette['flat']) ? $activePalette['primary'] : $activePalette['dark']);
        $sidebarAccent = $activePalette['highlight'] ?? $activePalette['accent'];
    @endphp
    <link rel="icon" type="image/png" href="{{ $browserLogo ? asset('storage/' . ltrim($browserLogo, '/')) : asset('assets/media/logos/favicon.ico') }}" />
    <link rel="shortcut icon" href="{{ $browserLogo ? asset('storage/' . ltrim($browserLogo, '/')) : asset('assets/media/logos/favicon.ico') }}" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" />
    <link href="{{ asset('assets/plugins/global/plugins.bundle.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/css/style.bundle.css') }}" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.11/css/dataTables.bootstrap5.min.css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" />
    <style>
        :root {
            --diagoma-primary: {{ $activePalette['primary'] }};
            --diagoma-primary-dark: {{ $activePalette['dark'] }};
            --diagoma-accent: {{ $activePalette['accent'] }};
            --diagoma-header: {{ $activePalette['header'] }};
            --diagoma-surface: {{ $activePalette['surface'] }};
            --diagoma-text: {{ $activePalette['text'] }};
            --diagoma-muted: {{ $activePalette['muted'] }};
            --diagoma-button-text: {{ $activePalette['button'] }};
            --diagoma-icon: {{ $activePalette['icon'] ?? $activePalette['primary'] }};
            --diagoma-flat: {{ !empty($activePalette['flat']) ? '1' : '0' }};
            --dg-sidebar-bg: {{ $sidebarBackground }};
            --dg-sidebar-accent: {{ $sidebarAccent }};
        }
        body { background: var(--diagoma-surface); color: var(--diagoma-text); }
        .app-content h1, .app-content h2, .app-content h3, .app-content h4, .app-content h5, .app-content h6 { color: var(--diagoma-text); }
        .app-content .card, .app-content .modal-content, .app-content .table, .app-content .dropdown-menu { border-color: color-mix(in srgb, var(--diagoma-primary) 12%, #e5ebf3); }
        .app-content .card-header, .app-content .modal-header { background: color-mix(in srgb, var(--diagoma-primary) 4%, #fff); }
        .app-content .btn-primary { color: var(--diagoma-button-text) !important; box-shadow: 0 6px 14px color-mix(in srgb, var(--diagoma-primary) 22%, transparent); }
        .app-content .btn-light-primary { color: var(--diagoma-primary) !important; background: color-mix(in srgb, var(--diagoma-primary) 10%, #fff) !important; border-color: color-mix(in srgb, var(--diagoma-primary) 15%, #fff) !important; }
        .app-content .form-control:focus, .app-content .form-select:focus { border-color: var(--diagoma-primary) !important; box-shadow: 0 0 0 .2rem color-mix(in srgb, var(--diagoma-primary) 15%, transparent) !important; }
        .app-content .text-muted { color: var(--diagoma-muted) !important; }
        .app-content .bi, .app-content .fa, .app-content svg { color: var(--diagoma-icon); }
        .app-content :is(.module-icon, .landing-stat-icon, .rh-icon, .admin-icon, .finance-kpi .icon) > .bi { color: inherit; }
        [data-theme-flat="1"] .btn-primary,
        [data-theme-flat="1"] .app-content .btn-primary {
            background: var(--diagoma-primary) !important;
            background-image: none !important;
        }
    </style>
    <style>
        /* ===== RESET & BASE ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            overflow: hidden;
            height: 100vh;
        }

        .app-root {
            height: 100vh;
            overflow: hidden;
        }

        .app-page {
            height: 100vh;
            overflow: hidden;
            position: relative;
        }

        .app-container {
            padding: 0 32px;
            max-width: 100%;
            width: 100%;
            min-width: 0;
        }

        /* ===== CONTENT ===== */
        .app-content {
            flex: 1;
            overflow-y: auto;
            padding: 28px 0;
        }

        .app-content::-webkit-scrollbar {
            width: 6px;
        }

        .app-content::-webkit-scrollbar-track {
            background: transparent;
        }

        .app-content::-webkit-scrollbar-thumb {
            background: #d0d7e2;
            border-radius: 10px;
        }

        .page-heading {
            font-size: 24px;
            font-weight: 700;
            color: #1a1a2e;
            letter-spacing: -0.03em;
        }

        .page-breadcrumb {
            font-size: 12px;
            color: #8e96a8;
        }

        .page-breadcrumb span {
            color: #4a5068;
        }

        /* ===== CARDS ===== */
        .card {
            border: 1px solid #e4e9f0 !important;
            border-radius: 16px !important;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.03);
            background: #fff;
            transition: box-shadow 0.2s ease;
        }

        .card:hover {
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.06);
        }

        .card-header {
            border-bottom: 1px solid #edf1f7 !important;
            background: transparent;
            padding: 1.25rem 1.5rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* ===== TABLE ===== */
        .table thead th {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #8e96a8;
            font-weight: 700;
            border-bottom: 1px solid #edf1f7;
            padding: 0.85rem 1rem;
        }

        .table tbody td {
            padding: 0.85rem 1rem;
            color: #2d3349;
            border-bottom: 1px solid #f1f4f9;
            vertical-align: middle;
            font-size: 14px;
        }

        .badge {
            border-radius: 6px;
            font-weight: 600;
            font-size: 11px;
            padding: 0.35rem 0.75rem;
        }

        /* ===== BUTTONS ===== */
        .btn {
            border-radius: 10px;
            font-weight: 600;
            font-size: 13px;
            padding: 0.5rem 1.25rem;
            transition: all 0.2s ease;
        }

        .btn-primary {
            border: none;
            color: #fff;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
        }

        .btn-light-primary {
            background: rgba(39, 55, 114, 0.08);
            color: #273772;
            border: 1px solid rgba(39, 55, 114, 0.12);
        }

        .btn-light-primary:hover {
            background: rgba(39, 55, 114, 0.15);
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 991.98px) {
            .app-sidebar-overlay {
                position: fixed;
                inset: 0;
                background: rgba(23, 32, 51, 0.5);
                z-index: 1060;
                display: none;
            }

            .app-sidebar-overlay.open {
                display: block;
            }
        }

        @media (max-width: 576px) {
            .app-container {
                padding: 0 16px;
            }

            .page-heading {
                font-size: 20px;
            }
        }
    </style>
    <style>
        .app-content .table > :not(caption) > * > * {
            border-bottom: 1px solid #edf0f4 !important;
            padding-top: 1.05rem;
            padding-bottom: 1.05rem;
        }

        .app-content .table > tbody > tr:not(:last-child) > * {
            border-bottom: 1px solid #edf0f4 !important;
        }

        .app-content .table > tbody > tr:last-child > * {
            border-bottom: 0;
        }

        .app-content .table > thead > tr > th {
            border-bottom: 1px solid #e5e9ef !important;
            color: #64748b;
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .app-content .table-hover > tbody > tr:hover > * {
            background-color: #f8fafc;
        }

        .app-content .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .app-content .table-sortable {
            cursor: pointer;
            user-select: none;
        }

        .app-content .table-sortable:hover {
            color: #4f46e5;
        }

        .app-content .table-sort-icon {
            display: inline-flex;
            flex-direction: column;
            margin-left: .45rem;
            color: #a7b1c2;
            font-size: .62rem;
            line-height: .55rem;
            vertical-align: middle;
        }

        .app-content .table-sort-icon .active {
            color: #4f46e5;
        }

        .app-content table.dataTable thead th.sorting,
        .app-content table.dataTable thead th.sorting_asc,
        .app-content table.dataTable thead th.sorting_desc {
            cursor: pointer;
            position: relative;
            padding-right: 1.6rem !important;
        }

        .app-content table.dataTable thead th.sorting::after,
        .app-content table.dataTable thead th.sorting_asc::after,
        .app-content table.dataTable thead th.sorting_desc::after {
            position: absolute;
            right: .55rem;
            top: 50%;
            transform: translateY(-50%);
            color: #a7b1c2;
            font-size: .65rem;
            line-height: 1;
        }

        .app-content table.dataTable thead th.sorting::after { content: '↕'; }
        .app-content table.dataTable thead th.sorting_asc::after { content: '↑'; color: #4f46e5; }
        .app-content table.dataTable thead th.sorting_desc::after { content: '↓'; color: #4f46e5; }

        .app-content table.dataTable thead th.sorting::before,
        .app-content table.dataTable thead th.sorting_asc::before,
        .app-content table.dataTable thead th.sorting_desc::before {
            display: none !important;
        }

        .app-content .action-menu-button {
            width: 42px;
            height: 42px;
            padding: 0;
            border: 0;
            border-radius: 12px;
            background: #f7f8fb;
            color: #8994aa;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0;
        }

        .app-content .action-menu-button::before {
            content: '••';
            display: block;
            font-size: 1rem;
            letter-spacing: .55rem;
            line-height: 1.05rem;
            transform: rotate(90deg);
            margin-left: .45rem;
        }

        .app-content .action-menu-button:hover,
        .app-content .action-menu-button:focus {
            background: #eef0f7;
            color: #4f46e5;
        }
    </style>
    <style>
        /* Tenant-selected palette overrides the base layout styles. */
        .btn-primary,
        .app-content .btn-primary {
            background-color: var(--diagoma-primary) !important;
            border-color: var(--diagoma-primary) !important;
        }
        .btn-primary:hover,
        .app-content .btn-primary:hover {
            background-color: var(--diagoma-primary-dark) !important;
            border-color: var(--diagoma-primary-dark) !important;
        }
        .text-primary { color: var(--diagoma-primary) !important; }
        .bg-primary { background-color: var(--diagoma-primary) !important; }
        .border-primary { border-color: var(--diagoma-primary) !important; }
        .app-content a:not(.btn):not([class*="dg-"]):hover,
        .app-content .table-sortable:hover,
        .app-content .table-sort-icon .active {
            color: var(--diagoma-primary) !important;
        }
        .datatable-export-toolbar { display:flex; justify-content:flex-end; gap:.5rem; margin:0 0 1rem; flex-wrap:wrap; }
        .datatable-export-toolbar .dt-button { border:0 !important; border-radius:9px !important; padding:.55rem .9rem !important; font-weight:700 !important; box-shadow:none !important; }
        .datatable-export-toolbar .buttons-excel { background:#e9f8ef !important; color:#16834b !important; }
        .datatable-export-toolbar .buttons-pdf { background:#fff0f0 !important; color:#c03945 !important; }
        .datatable-export-toolbar .buttons-copy { background:#eef3ff !important; color:#3154b7 !important; }
    </style>
    <link rel="stylesheet" href="{{ asset('css/diago.css') }}" />
    <script>
        // Devise de l'entreprise, pour les montants calculés côté navigateur.
        // Définie dans l'en-tête pour être disponible dès les scripts des pages.
        window.APP_CURRENCY = {
            code: @json(currency_symbol() ? company_currency()['code'] : 'XOF'),
            symbol: @json(currency_symbol()),
            decimals: {{ currency_decimals() }}
        };
        window.formatMoney = function (value, decimals) {
            var d = typeof decimals === 'number' ? decimals : window.APP_CURRENCY.decimals;
            return new Intl.NumberFormat('fr-FR', {
                minimumFractionDigits: d, maximumFractionDigits: d
            }).format(Number(value) || 0) + ' ' + window.APP_CURRENCY.symbol;
        };
    </script>
</head>
<body id="kt_app_body" class="dg-app" data-theme-flat="{{ !empty($activePalette['flat']) ? '1' : '0' }}" data-theme-dark="{{ !empty($activePalette['dark_mode']) ? '1' : '0' }}">
    @php
        $currentUser = auth()->user();
        $userInitials = collect(preg_split('/\s+/', trim($currentUser->name ?? '')))
            ->filter()
            ->take(2)
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('') ?: 'U';
        $organisationName = $currentUser->succursale?->name ?? $currentUser->entreprise?->name ?? 'Console Diago';
    @endphp
    <div class="d-flex flex-column flex-root app-root" id="kt_app_root">
        <div class="app-page d-flex" id="kt_app_page">
            <!-- ===== SIDEBAR OVERLAY (mobile) ===== -->
            <div class="app-sidebar-overlay" id="kt_app_sidebar_overlay"></div>

            <!-- ===== SIDEBAR ===== -->
            <aside id="kt_app_sidebar" class="dg-sidebar">
                <div class="dg-sidebar__brand">
                    <a href="{{ route('admin.dashboard') }}" class="dg-brand" aria-label="Diago, by CME Expertises">
                        <span class="dg-brand__name"><i class="bi bi-bar-chart-fill" aria-hidden="true"></i><span class="dg-brand__word">DIAGO</span></span>
                        <span class="dg-brand__tagline">By CME Expertises</span>
                    </a>
                </div>

                <nav class="dg-sidebar__nav" aria-label="Navigation principale">
                    @php
                        $enabledRubriques = array_merge(
                            ['pilotage' => true, 'commercial' => true, 'comptabilite' => true, 'rh' => true, 'administration' => true, 'succursales' => false],
                            data_get($currentUser->entreprise?->settings ?? [], 'enabled_rubriques', [])
                        );
                        $navItems = [];
                        if ($currentUser->hasRole('super_admin')) {
                            $navItems[] = ['route' => 'console.index', 'active' => ['console.index', 'console.dashboard'], 'icon' => 'bi-house-door-fill', 'label' => __('Dashboard')];
                            $navItems[] = ['route' => 'console.account-tracking', 'active' => ['console.account-tracking'], 'icon' => 'bi-clipboard-data-fill', 'label' => __('Account tracking')];
                            $navItems[] = ['route' => 'console.pack-requests', 'active' => ['console.pack-requests'], 'icon' => 'bi-box-seam-fill', 'label' => 'Demandes de packs'];
                            $navItems[] = ['route' => 'admin.demorequests', 'active' => ['admin.demorequests'], 'icon' => 'bi-chat-dots-fill', 'label' => __('Demo requests')];
                        } else {
                            if ($currentUser->hasPermission('dashboard')) {
                                $navItems[] = ['route' => 'admin.dashboard', 'active' => ['admin.dashboard', 'dashboard'], 'icon' => 'bi-house-door-fill', 'label' => __('Dashboard')];
                            }
                            if (data_get($currentUser->entreprise?->settings, 'ai_assistant_enabled', false)) {
                                $navItems[] = ['route' => 'admin.ai-assistant', 'active' => ['admin.ai-assistant*'], 'icon' => 'bi-robot', 'label' => 'Assistant IA'];
                            }
                            if (!empty($enabledRubriques['administration']) && $currentUser->hasPermission('administration')) {
                                $navItems[] = ['route' => 'admin.administration', 'active' => ['admin.administration*'], 'icon' => 'bi-folder-fill', 'label' => __('Administrative management')];
                            }
                            if (!empty($enabledRubriques['comptabilite']) && $currentUser->hasPermission('accounting')) {
                                $navItems[] = ['route' => 'admin.comptabilite', 'active' => ['admin.comptabilite*'], 'icon' => 'bi-calculator-fill', 'label' => __('Accounting')];
                            }
                            if (!empty($enabledRubriques['rh']) && $currentUser->hasPermission('hr')) {
                                $navItems[] = ['route' => 'admin.rh', 'active' => ['admin.rh'], 'icon' => 'bi-people-fill', 'label' => __('HR & Payroll')];
                            }
                            if (!empty($enabledRubriques['commercial']) && $currentUser->hasPermission('commercial')) {
                                $navItems[] = ['route' => 'admin.commercial', 'active' => ['admin.commercial*'], 'icon' => 'bi-bag-fill', 'label' => __('Commercial')];
                            }
                            if (!empty($enabledRubriques['succursales']) && $currentUser->hasPermission('succursales')) {
                                $navItems[] = ['route' => 'admin.succursales.index', 'active' => ['admin.succursales*'], 'icon' => 'bi-buildings-fill', 'label' => __('Branches')];
                            }
                            if ($currentUser->hasPermission('users')) {
                                $navItems[] = ['route' => 'admin.users.index', 'active' => ['admin.users*'], 'icon' => 'bi-person-fill', 'label' => __('Users')];
                            }
                        }
                        if ($currentUser->hasPermission('settings')) {
                            $navItems[] = ['route' => 'admin.settings', 'active' => ['admin.settings*'], 'icon' => 'bi-gear-fill', 'label' => __('Settings')];
                        }
                    @endphp
                    @foreach($navItems as $item)
                        @php($isActive = request()->routeIs(...$item['active']))
                        <a href="{{ route($item['route']) }}" class="dg-nav-link {{ $isActive ? 'is-active' : '' }}" @if($isActive) aria-current="page" @endif title="{{ $item['label'] }}">
                            <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>
                            <span class="dg-nav-link__label">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </nav>

                <div class="dg-sidebar__footer dropup">
                    <button type="button" class="dg-user" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Menu du compte">
                        <span class="dg-avatar">
                            @if($currentUser->avatar_path)
                                <img src="{{ asset('storage/' . $currentUser->avatar_path) }}" alt="">
                            @else
                                {{ $userInitials }}
                            @endif
                        </span>
                        <span class="dg-user__text">
                            <span class="dg-user__name">{{ $currentUser->name ?? 'Administrateur' }}</span>
                            <span class="dg-user__meta">{{ $currentUser->entreprise?->name ?? $currentUser->role?->label ?? 'Super administrateur' }}</span>
                        </span>
                        <i class="bi bi-chevron-up" aria-hidden="true"></i>
                    </button>
                    <ul class="dropdown-menu dg-dropdown">
                        <li class="dg-dropdown__header">
                            {{ $currentUser->name ?? 'Administrateur' }}
                            <span class="d-block fw-normal dg-muted" style="font-size:13px">{{ $currentUser->email ?? '' }}</span>
                        </li>
                        <li><a class="dropdown-item" href="{{ route('admin.profile') }}"><i class="bi bi-person"></i>Profil</a></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right"></i>Se déconnecter</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </aside>
            <button class="dg-sidebar-toggle" id="kt_app_sidebar_edge_toggle" type="button" aria-label="Réduire ou ouvrir le menu" title="Réduire ou ouvrir le menu">
                <i class="bi bi-chevron-left" aria-hidden="true"></i>
            </button>

            <!-- ===== MAIN WRAPPER ===== -->
            <div class="app-wrapper d-flex flex-column flex-fill" id="kt_app_wrapper">
                <!-- ===== HEADER ===== -->
                <header id="kt_app_header" class="dg-topbar">
                    <button class="dg-icon-btn dg-icon-btn--ghost d-lg-none" id="kt_app_sidebar_toggle" type="button" aria-label="Ouvrir ou fermer le menu">
                        <i class="bi bi-list"></i>
                    </button>

                    <div class="dg-topbar__start">
                        {{-- Une page peut remplacer la recherche par son propre contenu (titre, filtres…). --}}
                        @hasSection('topbar')
                            @yield('topbar')
                        @else
                            @php($searchPlaceholder = $currentUser->hasRole('super_admin') ? __('Search a company...') : __('Global search (employees, clients, invoices…)'))
                            <div class="dg-search" id="global-search" role="search">
                                <i class="bi bi-search" aria-hidden="true"></i>
                                <input type="search" id="global-search-input" placeholder="{{ $searchPlaceholder }}" autocomplete="off"
                                       aria-label="{{ $searchPlaceholder }}" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="global-search-results" />
                                <kbd class="dg-search__kbd" aria-hidden="true">Ctrl K</kbd>
                                <div class="dg-search-results" id="global-search-results" role="listbox" hidden></div>
                            </div>
                        @endif
                    </div>

                    <div class="dg-topbar__end">
                        <span class="dg-org-pill" title="{{ $organisationName }}"><span class="dg-tile"><i class="bi bi-buildings" aria-hidden="true"></i></span><span class="dg-org-pill__name">{{ $organisationName }}</span></span>

                        <div class="dropdown">
                            <button class="dg-topbar-btn dg-tone-blue" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('Choose language') }}" title="{{ __('Choose language') }}"><i class="bi bi-globe"></i></button>
                            <ul class="dropdown-menu dropdown-menu-end dg-dropdown" style="min-width:180px">
                                @php($currentLocale = app()->getLocale())
                                <li><form method="POST" action="{{ route('locale.update') }}">@csrf<input type="hidden" name="locale" value="fr"><button class="dropdown-item {{ $currentLocale === 'fr' ? 'active' : '' }}" type="submit"><span class="dg-lang-code">FR</span>Français</button></form></li>
                                <li><form method="POST" action="{{ route('locale.update') }}">@csrf<input type="hidden" name="locale" value="en"><button class="dropdown-item {{ $currentLocale === 'en' ? 'active' : '' }}" type="submit"><span class="dg-lang-code">EN</span>English</button></form></li>
                            </ul>
                        </div>

                        <div class="dropdown">
                            <button class="dg-topbar-btn dg-tone-orange" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications ({{ $notificationCount ?? 0 }})" title="Notifications">
                                <i class="bi bi-bell"></i>
                                @if(($notificationCount ?? 0) > 0)
                                    <span class="dg-count" aria-hidden="true">{{ $notificationCount > 99 ? '99+' : $notificationCount }}</span>
                                @endif
                            </button>
                            <div class="dropdown-menu dropdown-menu-end dg-dropdown dg-dropdown--wide">
                                <div class="dg-dropdown__header d-flex align-items-center gap-2">
                                    <span class="dg-tile dg-tile--sm dg-tone-orange"><i class="bi bi-bell"></i></span>{{ __('Notifications') }}
                                    @if(($notificationCount ?? 0) > 0)<span class="dg-badge dg-badge--danger ms-auto">{{ $notificationCount }}</span>@endif
                                </div>
                                <a href="{{ route('admin.rh.permissions') }}" class="dg-notification">
                                    <span class="dg-tile dg-tile--sm dg-tone-purple"><i class="bi bi-pencil-square"></i></span><span>{{ $pendingPermissions ?? 0 }} {{ __('Pending permission request(s)') }}</span>
                                </a>
                                <a href="{{ route('admin.rh.leaves') }}" class="dg-notification">
                                    <span class="dg-tile dg-tile--sm dg-tone-cyan"><i class="bi bi-calendar-range"></i></span><span>{{ $pendingLeaves ?? 0 }} {{ __('Pending leave request(s)') }}</span>
                                </a>
                                @foreach(($stockAlerts ?? collect()) as $stockAlert)
                                    <a href="{{ route('admin.commercial.module', 'etat-stock') }}" class="dg-notification">
                                        <span class="dg-tile dg-tile--sm {{ $stockAlert['quantity'] <= 0 ? 'dg-tone-red' : 'dg-tone-orange' }}"><i class="bi {{ $stockAlert['quantity'] <= 0 ? 'bi-x-octagon' : 'bi-exclamation-triangle' }}"></i></span>
                                        <span>
                                            <strong>{{ $stockAlert['name'] }}</strong>
                                            <small>{{ $stockAlert['quantity'] <= 0 ? 'Rupture de stock' : 'Stock faible' }} · {{ number_format(max(0, $stockAlert['quantity']), 2, ',', ' ') }} {{ $stockAlert['unit'] }}</small>
                                        </span>
                                    </a>
                                @endforeach
                                @if($pendingAnnualReport ?? null)
                                    <a href="{{ route('admin.bilans.show', $pendingAnnualReport) }}" class="dg-notification">
                                        <span class="dg-tile dg-tile--sm dg-tone-blue"><i class="bi bi-file-earmark-bar-graph"></i></span>
                                        <span>
                                            <strong>{{ $pendingAnnualReport->label() }}</strong>
                                            <small>Exercice clos, bilan non téléchargé</small>
                                        </span>
                                    </a>
                                @endif
                                @if(($notificationCount ?? 0) === 0)
                                    <div class="dg-notification-empty">{{ __('No new requests.') }}</div>
                                @endif
                            </div>
                        </div>

                        @yield('topbar-actions')
                    </div>
                </header>

                <!-- ===== CONTENT ===== -->
                <div id="kt_app_content" class="app-content">
                    <div class="app-container">
                @if(session()->has('impersonator_id'))
                    <div class="alert alert-warning d-flex justify-content-between align-items-center gap-3">
                        <span><strong>Mode assistance actif :</strong> vous consultez l’espace de {{ auth()->user()->entreprise?->name ?? 'l’entreprise' }}. Cette session est journalisée.</span>
                        <form method="POST" action="{{ route('console.impersonation.stop') }}">@csrf<button class="btn btn-sm btn-dark">Revenir à la Console</button></form>
                    </div>
                @endif
                        <!-- Content -->
                        @yield('content')
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== SCRIPTS ===== -->
    <script>
        document.getElementById('global-back-button')?.addEventListener('click', function (event) {
            if (document.referrer && document.referrer.indexOf(window.location.origin) === 0) {
                event.preventDefault();
                window.history.back();
            }
        });

        // Responsive sidebar toggle: overlay on mobile, collapse on desktop.
        (function() {
            const toggles = [
                document.getElementById('kt_app_sidebar_toggle'),
                document.getElementById('kt_app_sidebar_edge_toggle')
            ].filter(Boolean);
            const sidebar = document.getElementById('kt_app_sidebar');
            const overlay = document.getElementById('kt_app_sidebar_overlay');

            if (toggles.length && sidebar && overlay) {
                const open = () => {
                    if (window.innerWidth >= 992) {
                        sidebar.classList.toggle('is-collapsed');
                        localStorage.setItem('diagoma-sidebar-collapsed', sidebar.classList.contains('is-collapsed') ? '1' : '0');
                        return;
                    }
                    sidebar.classList.add('open');
                    overlay.classList.add('open');
                    document.body.style.overflow = 'hidden';
                };

                const close = () => {
                    sidebar.classList.remove('open');
                    overlay.classList.remove('open');
                    document.body.style.overflow = '';
                };

                toggles.forEach(toggle => toggle.addEventListener('click', open));
                const updateArrow = () => {
                    const edge = document.getElementById('kt_app_sidebar_edge_toggle');
                    if (edge) edge.querySelector('i').className = 'bi ' + (sidebar.classList.contains('is-collapsed') ? 'bi-chevron-right' : 'bi-chevron-left');
                };
                document.getElementById('kt_app_sidebar_edge_toggle')?.addEventListener('click', updateArrow);
                overlay.addEventListener('click', close);
                if (window.innerWidth >= 992 && localStorage.getItem('diagoma-sidebar-collapsed') === '1') {
                    sidebar.classList.add('is-collapsed');
                }
                updateArrow();

                // Close on Escape
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') close();
                });

            }
        })();

        // Auto-close sidebar on route change (mobile)
        (function() {
            const sidebar = document.getElementById('kt_app_sidebar');
            const overlay = document.getElementById('kt_app_sidebar_overlay');
            if (window.innerWidth < 992 && sidebar && overlay) {
                document.querySelectorAll('.dg-nav-link').forEach(link => {
                    link.addEventListener('click', () => {
                        sidebar.classList.remove('open');
                        overlay.classList.remove('open');
                        document.body.style.overflow = '';
                    });
                });
            }
        })();

        // Recherche globale de la barre du haut : résultats regroupés par type, navigation au clavier.
        (function () {
            const container = document.getElementById('global-search');
            const input = document.getElementById('global-search-input');
            const panel = document.getElementById('global-search-results');
            if (!container || !input || !panel) return;

            const endpoint = @json(route('admin.search'));
            const minLength = {{ \App\Services\GlobalSearchService::MIN_LENGTH }};
            let timer = null;
            let request = null;
            let activeIndex = -1;

            const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[character]));
            const highlight = (text, term) => {
                const value = String(text ?? '');
                const index = value.toLowerCase().indexOf(term.toLowerCase());
                if (index < 0) return escapeHtml(value);
                return escapeHtml(value.slice(0, index)) + '<mark>' + escapeHtml(value.slice(index, index + term.length)) + '</mark>' + escapeHtml(value.slice(index + term.length));
            };
            const options = () => Array.from(panel.querySelectorAll('.dg-search-result'));
            const show = html => {
                panel.innerHTML = html;
                panel.hidden = false;
                input.setAttribute('aria-expanded', 'true');
                activeIndex = -1;
            };
            const hide = () => {
                panel.hidden = true;
                input.setAttribute('aria-expanded', 'false');
                activeIndex = -1;
            };
            const setActive = index => {
                const items = options();
                if (!items.length) return;
                activeIndex = (index + items.length) % items.length;
                items.forEach((item, position) => {
                    item.classList.toggle('is-active', position === activeIndex);
                    item.setAttribute('aria-selected', position === activeIndex ? 'true' : 'false');
                });
                items[activeIndex].scrollIntoView({ block: 'nearest' });
            };
            const render = (groups, term) => {
                if (!groups.length) {
                    show('<div class="dg-search-state"><i class="bi bi-search"></i>Aucun résultat pour « ' + escapeHtml(term) + ' »</div>');
                    return;
                }
                show(groups.map(group =>
                    '<div class="dg-search-group dg-tone-' + escapeHtml(group.color) + '" role="group" aria-label="' + escapeHtml(group.label) + '">'
                    + '<div class="dg-search-group__label"><span class="dg-tile dg-tile--sm"><i class="bi ' + escapeHtml(group.icon) + '"></i></span>' + escapeHtml(group.label) + '</div>'
                    + group.items.map(item =>
                        '<a class="dg-search-result" role="option" aria-selected="false" href="' + escapeHtml(item.url) + '">'
                        + '<span class="dg-search-result__title">' + highlight(item.title, term) + '</span>'
                        + '<span class="dg-search-result__sub">' + highlight(item.subtitle, term) + '</span></a>'
                    ).join('')
                    + '</div>'
                ).join(''));
            };
            const search = () => {
                const term = input.value.trim();
                clearTimeout(timer);
                if (term.length < minLength) {
                    hide();
                    return;
                }
                timer = setTimeout(() => {
                    if (request) request.abort();
                    request = new AbortController();
                    show('<div class="dg-search-state"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Recherche…</div>');
                    fetch(endpoint + '?q=' + encodeURIComponent(term), { headers: { Accept: 'application/json' }, signal: request.signal })
                        .then(response => response.ok ? response.json() : Promise.reject(response))
                        .then(data => {
                            if (input.value.trim() === term) render(data.groups || [], term);
                        })
                        .catch(error => {
                            if (error && error.name === 'AbortError') return;
                            show('<div class="dg-search-state dg-search-state--error"><i class="bi bi-exclamation-triangle"></i>Recherche indisponible pour le moment.</div>');
                        });
                }, 250);
            };

            input.addEventListener('input', search);
            input.addEventListener('focus', () => {
                if (input.value.trim().length >= minLength) search();
            });
            input.addEventListener('keydown', event => {
                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    setActive(activeIndex + 1);
                } else if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    setActive(activeIndex - 1);
                } else if (event.key === 'Enter') {
                    const item = options()[activeIndex] || options()[0];
                    if (item) {
                        event.preventDefault();
                        window.location.href = item.href;
                    }
                } else if (event.key === 'Escape') {
                    hide();
                    input.blur();
                }
            });
            document.addEventListener('click', event => {
                if (!container.contains(event.target)) hide();
            });

            // Raccourci Ctrl+K (Cmd+K sur Mac) pour placer le curseur dans la recherche.
            document.addEventListener('keydown', event => {
                if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
                    event.preventDefault();
                    input.focus();
                    input.select();
                }
            });
        })();

        // Add ascending/descending arrows to every sortable data table header.
        (function() {
            const normalize = value => value.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            const valueOf = cell => {
                const text = normalize(cell?.textContent || '').replace(/\s/g, '');
                const number = parseFloat(text.replace(/[^\d,.-]/g, '').replace(/\s/g, '').replace(',', '.'));
                return Number.isNaN(number) ? text : number;
            };
            document.querySelectorAll('.app-content table.table').forEach(table => {
                if (table.classList.contains('no-column-sort') || table.classList.contains('dataTable') || !table.tHead || !table.tBodies.length) return;
                const header = table.tHead.querySelector('tr');
                if (!header) return;
                Array.from(header.cells).forEach((cell, index) => {
                    const label = normalize(cell.textContent || '');
                    const isAction = index === 0 && cell.querySelector('input[type="checkbox"]')
                        || label.includes('action') || label.includes('actions');
                    if (isAction || cell.dataset.sortReady === '1') return;
                    cell.classList.add('table-sortable');
                    cell.dataset.sortReady = '1';
                    const icon = document.createElement('span');
                    icon.className = 'table-sort-icon';
                    icon.innerHTML = '<span class="sort-up">▲</span><span class="sort-down">▼</span>';
                    cell.appendChild(icon);
                    cell.addEventListener('click', () => {
                        const ascending = cell.dataset.sortDirection !== 'asc';
                        Array.from(header.cells).forEach(item => {
                            item.dataset.sortDirection = '';
                            item.querySelector('.sort-up')?.classList.remove('active');
                            item.querySelector('.sort-down')?.classList.remove('active');
                        });
                        cell.dataset.sortDirection = ascending ? 'asc' : 'desc';
                        icon.querySelector(ascending ? '.sort-up' : '.sort-down').classList.add('active');
                        const rows = Array.from(table.tBodies[0].rows);
                        rows.sort((a, b) => {
                            const first = valueOf(a.cells[index]);
                            const second = valueOf(b.cells[index]);
                            if (typeof first === 'number' && typeof second === 'number') return ascending ? first - second : second - first;
                            return ascending ? String(first).localeCompare(String(second), 'fr') : String(second).localeCompare(String(first), 'fr');
                        });
                        rows.forEach(row => table.tBodies[0].appendChild(row));
                    });
                });
            });
        })();
    </script>

    @stack('scripts')
    <script src="{{ asset('assets/plugins/global/plugins.bundle.js') }}"></script>
    <script src="{{ asset('assets/js/scripts.bundle.js') }}"></script>
    <script src="https://cdn.datatables.net/1.13.11/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.11/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script>
        (function () {
            const exportOptions = {
                columns: ':not(:last-child)',
                modifier: { search: 'applied', order: 'applied' }
            };
            const companyName = @json(auth()->user()->entreprise?->name ?? 'Diagoma ERP');
            const companyLogoUrl = @json($browserLogo ? asset('storage/' . ltrim($browserLogo, '/')) : '');
            let companyLogoData = '';
            if (companyLogoUrl) {
                fetch(companyLogoUrl)
                    .then(response => response.blob())
                    .then(blob => new Promise(resolve => {
                        const reader = new FileReader();
                        reader.onloadend = () => resolve(reader.result);
                        reader.readAsDataURL(blob);
                    }))
                    .then(data => { companyLogoData = data; })
                    .catch(() => {});
            }
            const customizePdf = doc => {
                const body = doc.content.find(item => item.table);
                const title = doc.content.find(item => item.text && item.text !== 'Export');
                doc.pageMargins = [32, 72, 32, 42];
                doc.defaultStyle = { fontSize: 8, color: '#334155' };
                doc.styles = {
                    tableHeader: { bold: true, color: '#ffffff', fillColor: '#1b4f80', fontSize: 8 },
                    reportTitle: { fontSize: 16, bold: true, color: '#123e68', margin: [0, 0, 0, 4] },
                    reportSubtitle: { fontSize: 9, color: '#64748b' }
                };
                if (title) {
                    title.text = 'Rapport exporté';
                    title.style = 'reportTitle';
                }
                if (body && body.table && body.table.body.length) {
                    body.table.headerRows = 1;
                    body.layout = {
                        hLineColor: () => '#dbe5ef',
                        vLineColor: () => '#dbe5ef',
                        hLineWidth: () => .6,
                        vLineWidth: () => .6,
                        paddingLeft: () => 6,
                        paddingRight: () => 6,
                        paddingTop: () => 6,
                        paddingBottom: () => 6
                    };
                }
                doc.header = () => ({
                    margin: [32, 22, 32, 0],
                    columns: [
                        companyLogoData ? { image: companyLogoData, width: 42, height: 42 } : { text: 'DIAGO', color: '#1b4f80', bold: true, fontSize: 15 },
                        { stack: [{ text: companyName, bold: true, color: '#123e68', fontSize: 12 }, { text: 'Document exporté depuis Diagoma ERP', color: '#64748b', fontSize: 8, margin: [0, 3, 0, 0] }], margin: [10, 5, 0, 0] },
                        { text: new Date().toLocaleDateString('fr-FR'), alignment: 'right', color: '#64748b', fontSize: 8, margin: [0, 8, 0, 0] }
                    ]
                });
                doc.footer = (currentPage, pageCount) => ({
                    margin: [32, 10, 32, 0],
                    columns: [
                        { text: companyName, color: '#94a3b8', fontSize: 8 },
                        { text: `Page ${currentPage} / ${pageCount}`, alignment: 'right', color: '#94a3b8', fontSize: 8 }
                    ]
                });
            };
            const addExportButtons = table => {
                if (!window.jQuery || !jQuery.fn.DataTable || !jQuery.fn.dataTable.Buttons) return;
                const $table = jQuery(table);
                if (!$table.closest('.dataTables_wrapper').find('.datatable-export-toolbar').length) {
                    const api = $table.DataTable();
                    const buttons = new jQuery.fn.dataTable.Buttons(api, {
                        dom: { container: { className: 'datatable-export-toolbar' } },
                        buttons: [
                            { extend: 'excelHtml5', text: '⇩ Exporter en Excel', exportOptions },
                            { extend: 'pdfHtml5', text: '⇩ Exporter en PDF', orientation: 'landscape', pageSize: 'A4', exportOptions, title: 'Rapport exporté', customize: customizePdf }
                        ]
                    });
                    $table.closest('.dataTables_wrapper').prepend(buttons.container());
                }
            };
            const initializeTables = () => {
                if (!window.jQuery || !jQuery.fn.DataTable || !jQuery.fn.dataTable.Buttons) return;
                jQuery('.app-content table.table').each(function () {
                    if (!this.tHead || !this.tBodies.length || this.classList.contains('no-export')) return;
                    if (this.tBodies[0].querySelector('td[colspan], td[rowspan], th[colspan], th[rowspan]')) return;
                    if (jQuery.fn.dataTable.isDataTable(this)) {
                        addExportButtons(this);
                        return;
                    }
                    jQuery(this).DataTable({
                        dom: 't',
                        paging: false,
                        info: false,
                        searching: false,
                        ordering: false,
                        buttons: []
                    });
                    addExportButtons(this);
                });
            };
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => setTimeout(initializeTables, 100));
            } else {
                setTimeout(initializeTables, 100);
            }
        })();
    </script>


@if($pendingAnnualReport ?? null)
@php($ar = $pendingAnnualReport)
@php($arData = $ar->data)
@php($arMoney = fn ($v) => number_format((float) $v, $arData['decimals'] ?? 0, ',', ' ') . ' ' . ($arData['currency_symbol'] ?? ''))
<div class="modal fade" id="annualReportModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">{{ $ar->label() }}</h5>
                    <div class="text-muted small">Exercice du {{ \Carbon\Carbon::parse($arData['from'])->format('d/m/Y') }}
                        au {{ \Carbon\Carbon::parse($arData['to'])->format('d/m/Y') }}</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" id="annualReportClose"></button>
            </div>
            <div class="modal-body">
                <div class="row g-4 mb-4">
                    <div class="col-md-4"><div class="p-3 rounded-3" style="background:#f1f5f9">
                        <div class="text-muted small text-uppercase fw-bold">Total actif</div>
                        <div class="fs-4 fw-bold">{{ $arMoney($arData['totals']['assets']) }}</div>
                    </div></div>
                    <div class="col-md-4"><div class="p-3 rounded-3" style="background:#f1f5f9">
                        <div class="text-muted small text-uppercase fw-bold">Passif et capitaux</div>
                        <div class="fs-4 fw-bold">{{ $arMoney($arData['totals']['liabilities_and_equity']) }}</div>
                    </div></div>
                    <div class="col-md-4"><div class="p-3 rounded-3"
                        style="background:{{ $arData['totals']['net_result'] >= 0 ? '#dcfce7' : '#fee2e2' }}">
                        <div class="text-muted small text-uppercase fw-bold">Résultat de l'exercice</div>
                        <div class="fs-4 fw-bold">{{ $arMoney($arData['totals']['net_result']) }}</div>
                    </div></div>
                </div>

                <h6 class="fw-bold mt-4">Compte de résultat</h6>
                <table class="table table-sm align-middle">
                    <tbody>
                        <tr><td>Produits</td><td class="text-end">{{ $arMoney($arData['totals']['revenue']) }}</td></tr>
                        <tr><td>Charges</td><td class="text-end">{{ $arMoney($arData['totals']['expenses']) }}</td></tr>
                        <tr class="fw-bold border-top"><td>Résultat</td>
                            <td class="text-end">{{ $arMoney($arData['totals']['net_result']) }}</td></tr>
                    </tbody>
                </table>

                <div class="alert alert-warning mt-4 mb-0 small">
                    Si vous fermez cette fenêtre sans télécharger le PDF, le bilan restera
                    signalé comme non lu dans vos notifications.
                </div>
            </div>
            <div class="modal-footer">
                <a href="{{ route('admin.bilans.show', $ar) }}" class="btn btn-light">Voir le détail</a>
                <a href="{{ route('admin.bilans.download', $ar) }}" class="btn btn-primary" id="annualReportDownload">
                    Télécharger le PDF
                </a>
            </div>
        </div>
    </div>
</div>
<script>
    (function () {
        var el = document.getElementById('annualReportModal');
        if (!el || typeof bootstrap === 'undefined') { return; }

        // La fenetre ne s'ouvre qu'une fois par session : le rappel se fait
        // ensuite par la notification, pour ne pas harceler l'utilisateur.
        var key = 'annualReportShown{{ $ar->id }}';
        try {
            if (!sessionStorage.getItem(key)) {
                new bootstrap.Modal(el).show();
                sessionStorage.setItem(key, '1');
            }
        } catch (e) {
            new bootstrap.Modal(el).show();
        }

        el.addEventListener('hidden.bs.modal', function () {
            fetch('{{ route('admin.bilans.acknowledge', $ar) }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                }
            }).catch(function () {});
        });
    })();
</script>
@endif
</body>
</html>