<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $title ?? 'Diagoma ERP' }}</title>
    <meta name="description" content="Diagoma ERP" />
    @php
        $browserLogo = data_get(auth()->user()->entreprise?->settings ?? [], 'logo');
        $appTheme = auth()->user()->entreprise
            ? data_get(auth()->user()->entreprise->settings ?? [], 'theme', 'ocean')
            : session('console_theme', 'ocean');
        $themePalettes = [
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
        $activePalette = $themePalettes[$appTheme] ?? $themePalettes['ocean'];
    @endphp
    <link rel="icon" type="image/png" href="{{ $browserLogo ? asset('storage/' . ltrim($browserLogo, '/')) : asset('assets/media/logos/favicon.ico') }}" />
    <link rel="shortcut icon" href="{{ $browserLogo ? asset('storage/' . ltrim($browserLogo, '/')) : asset('assets/media/logos/favicon.ico') }}" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700" />
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
        }
        .app-sidebar-header .logo-icon,
        .app-sidebar-menu .menu-link.active::before,
        .app-header .header-profile-avatar,
        .sidebar-edge-toggle:hover,
        .app-header .topbar-search:focus-within {
            border-color: var(--diagoma-primary);
        }
        .app-sidebar-header .logo-icon,
        .app-sidebar-menu .menu-link.active::before,
        .app-header .header-profile-avatar {
            background: linear-gradient(135deg, var(--diagoma-primary), var(--diagoma-primary-dark)) !important;
        }
        .app-sidebar-menu .menu-link.active,
        .app-sidebar-menu .menu-link:hover,
        .app-header .topbar-search:focus-within,
        .sidebar-edge-toggle:hover { color: var(--diagoma-primary) !important; }
        .app-sidebar { background: linear-gradient(180deg, color-mix(in srgb, var(--diagoma-primary-dark) 35%, #0f0f1a) 0%, #1a1a2e 100%); }
        body { background: var(--diagoma-surface); color: var(--diagoma-text); }
        .app-header { background: color-mix(in srgb, var(--diagoma-header) 94%, transparent) !important; border-bottom-color: color-mix(in srgb, var(--diagoma-primary) 12%, #e4e9f0) !important; }
        .app-content h1, .app-content h2, .app-content h3, .app-content h4, .app-content h5, .app-content h6 { color: var(--diagoma-text); }
        .app-content .card, .app-content .modal-content, .app-content .table, .app-content .dropdown-menu { border-color: color-mix(in srgb, var(--diagoma-primary) 12%, #e5ebf3); }
        .app-content .card-header, .app-content .modal-header { background: color-mix(in srgb, var(--diagoma-primary) 4%, #fff); }
        .app-content .btn-primary { color: var(--diagoma-button-text) !important; box-shadow: 0 6px 14px color-mix(in srgb, var(--diagoma-primary) 22%, transparent); }
        .app-content .btn-light-primary { color: var(--diagoma-primary) !important; background: color-mix(in srgb, var(--diagoma-primary) 10%, #fff) !important; border-color: color-mix(in srgb, var(--diagoma-primary) 15%, #fff) !important; }
        .app-content .form-control:focus, .app-content .form-select:focus { border-color: var(--diagoma-primary) !important; box-shadow: 0 0 0 .2rem color-mix(in srgb, var(--diagoma-primary) 15%, transparent) !important; }
        .app-content .text-muted { color: var(--diagoma-muted) !important; }
        .menu-icon, .header-actions .btn-icon, .app-content .bi, .app-content .fa,
        .app-content svg { color: var(--diagoma-icon); }
        [data-theme-flat="1"] .app-sidebar,
        [data-theme-flat="1"] .app-sidebar-header .logo-icon,
        [data-theme-flat="1"] .user-avatar,
        [data-theme-flat="1"] .app-sidebar-menu .menu-link.active::before,
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

        .sidebar-edge-toggle {
            position: absolute;
            top: 14px;
            left: 264px;
            width: 44px;
            height: 44px;
            z-index: 1060;
            border: 1px solid #e4e9f0;
            border-radius: 10px;
            background: #fff;
            color: #9aa6bd;
            box-shadow: 0 4px 12px rgba(26, 26, 46, 0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            line-height: 1;
            transition: left 0.25s ease, color 0.2s ease, box-shadow 0.2s ease;
        }

        .sidebar-edge-toggle:hover {
            color: #4d68ff;
            box-shadow: 0 6px 18px rgba(26, 26, 46, 0.14);
        }

        .app-sidebar.collapsed + .sidebar-edge-toggle {
            left: 60px;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f0f4f9;
            overflow: hidden;
            height: 100vh;
        }

        @media (max-width: 991.98px) {
            .sidebar-edge-toggle {
                display: none;
            }
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

        /* ===== SIDEBAR ===== */
        .app-sidebar {
            background: linear-gradient(180deg, #0f0f1a 0%, #1a1a2e 100%);
            width: 280px;
            height: 100vh;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            box-shadow: 4px 0 30px rgba(0, 0, 0, 0.3);
            position: relative;
            z-index: 100;
            border-right: 1px solid rgba(255, 255, 255, 0.06);
            transition: width 0.25s ease, left 0.3s ease;
        }

        /* Sidebar Header */
        .app-sidebar-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            flex-shrink: 0;
            background: rgba(255, 255, 255, 0.02);
        }

        .app-sidebar-header .logo-icon {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, #4d68ff, #6d4aff);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 800;
            color: #fff;
            box-shadow: 0 8px 20px rgba(77, 104, 255, 0.25);
        }

        .app-sidebar-header .company-logo {
            display: block;
            width: auto;
            max-width: 180px;
            height: 44px;
            object-fit: contain;
            object-position: left center;
        }

        .app-sidebar-header .brand-text {
            color: #fff;
            font-weight: 700;
            font-size: 18px;
            letter-spacing: -0.02em;
        }

        .app-sidebar-header .brand-sub {
            color: rgba(255, 255, 255, 0.4);
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.12em;
        }

        .app-sidebar-header .version-badge {
            background: rgba(77, 104, 255, 0.2);
            color: #6d8aff;
            font-size: 10px;
            font-weight: 700;
            padding: 0.3rem 0.7rem;
            border-radius: 20px;
            border: 1px solid rgba(77, 104, 255, 0.15);
        }

        /* ===== SIDEBAR MENU (SCROLLABLE) ===== */
        .app-sidebar-menu {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 1rem 0.75rem 0.5rem;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.12) transparent;
            min-height: 0;
        }

        .app-sidebar-menu::-webkit-scrollbar {
            width: 4px;
        }

        .app-sidebar-menu::-webkit-scrollbar-track {
            background: transparent;
        }

        .app-sidebar-menu::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 10px;
        }

        .app-sidebar-menu::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.25);
        }

        /* ===== MENU ITEMS ===== */
        .menu {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .menu-section {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: rgba(255, 255, 255, 0.25);
            padding: 1.25rem 1rem 0.6rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .menu-section::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(255, 255, 255, 0.06);
        }

        .menu-item {
            list-style: none;
        }

        .menu-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.65rem 1rem;
            border-radius: 10px;
            color: rgba(255, 255, 255, 0.6);
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
            position: relative;
            font-size: 14px;
            font-weight: 500;
        }

        .menu-link:hover {
            background: rgba(255, 255, 255, 0.06);
            color: #fff;
        }

        .menu-item.active > .menu-link {
            background: rgba(77, 104, 255, 0.18);
            color: #fff;
            box-shadow: inset 0 0 0 1px rgba(77, 104, 255, 0.15);
        }

        .menu-item.active > .menu-link::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 24px;
            background: linear-gradient(180deg, #4d68ff, #6d4aff);
            border-radius: 0 4px 4px 0;
        }

        .menu-icon {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
            color: inherit;
        }

        .menu-title {
            flex: 1;
            white-space: nowrap;
        }

        .menu-badge {
            background: rgba(77, 104, 255, 0.2);
            color: #6d8aff;
            font-size: 10px;
            font-weight: 700;
            padding: 0.15rem 0.55rem;
            border-radius: 20px;
        }

        /* Sub-menu */
        .menu-sub {
            padding-left: 1.75rem;
            display: flex;
            flex-direction: column;
            gap: 1px;
        }

        .menu-sub .menu-link {
            padding: 0.4rem 1rem;
            font-size: 13px;
            color: rgba(255, 255, 255, 0.45);
        }

        .menu-sub .menu-link:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.04);
        }

        .menu-sub .menu-item.active > .menu-link {
            color: #fff;
            background: rgba(77, 104, 255, 0.12);
        }

        /* ===== SIDEBAR FOOTER ===== */
        .app-sidebar-footer {
            padding: 1rem 1.25rem 1.25rem;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            flex-shrink: 0;
            background: rgba(255, 255, 255, 0.02);
        }

        .app-sidebar-footer .btn-logout {
            width: 100%;
            padding: 0.65rem;
            border-radius: 10px;
            background: rgba(255, 70, 70, 0.12);
            color: #ff6b6b;
            border: 1px solid rgba(255, 70, 70, 0.1);
            font-weight: 600;
            font-size: 13px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .app-sidebar-footer .btn-logout:hover {
            background: rgba(255, 70, 70, 0.2);
            border-color: rgba(255, 70, 70, 0.2);
        }

        @media (min-width: 992px) {
            .app-sidebar.collapsed {
                width: 76px;
            }

            .app-sidebar.collapsed .app-sidebar-header {
                padding-left: 1rem;
                padding-right: 1rem;
            }

            .app-sidebar.collapsed .brand-text,
            .app-sidebar.collapsed .brand-sub,
            .app-sidebar.collapsed .version-badge,
            .app-sidebar.collapsed .menu-section,
            .app-sidebar.collapsed .menu-title,
            .app-sidebar.collapsed .menu-badge,
            .app-sidebar.collapsed .app-sidebar-footer span {
                display: none;
            }

            .app-sidebar.collapsed .menu-link {
                justify-content: center;
                padding-left: 0.65rem;
                padding-right: 0.65rem;
            }

            .app-sidebar.collapsed .menu-icon {
                margin: 0;
            }

            .app-sidebar.collapsed .app-sidebar-footer .btn-logout {
                padding-left: 0;
                padding-right: 0;
            }
        }

        /* ===== HEADER ===== */
        .app-header {
            height: 72px;
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.04);
            flex-shrink: 0;
            position: relative;
            z-index: 1050;
            overflow: visible;
        }

        .app-container {
            padding: 0 2rem;
            max-width: 100%;
            width: 100%;
            min-width: 0;
        }

        .app-header .app-container {
            padding-right: 0;
        }

        .topbar-search {
            background: #f1f4f9;
            border: 1px solid #e4e9f0;
            border-radius: 10px;
            padding: 0.25rem 0.75rem;
            min-width: 240px;
        }

        .app-header .topbar-search {
            position: absolute;
            left: calc(50% - 45px);
            transform: translateX(-50%);
            width: min(360px, 34vw);
        }

        .topbar-search input {
            border: none;
            background: transparent;
            padding: 0.5rem 0.25rem;
            font-size: 13px;
            width: 100%;
            outline: none;
            color: #1a1a2e;
        }

        .topbar-search input::placeholder {
            color: #8e96a8;
        }

        .employee-search-results {
           position: absolute;
           top: calc(100% + 8px);
           left: 0;
           right: 0;
           z-index: 1050;
           display: none;
           max-height: 320px;
           overflow-y: auto;
           background: #fff;
           border: 1px solid #e4e9f0;
           border-radius: 10px;
           box-shadow: 0 12px 28px rgba(15, 23, 42, .14);
        }

        .employee-search-result {
           display: block;
           padding: .7rem .85rem;
           color: #1a1a2e;
           text-decoration: none;
           border-bottom: 1px solid #f0f2f5;
        }

        .employee-search-result:hover { background: #f5f8fc; }
        .employee-search-empty { padding: .8rem .85rem; color: #8e96a8; }

        .header-actions .btn-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: #f1f4f9;
            border: 1px solid #e4e9f0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4a5068;
            transition: all 0.2s ease;
        }

        .header-actions .btn-icon:hover {
            background: #e4e9f0;
            color: #1a1a2e;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.25rem 0.75rem 0.25rem 0.5rem;
            background: #f1f4f9;
            border-radius: 30px;
            border: 1px solid #e4e9f0;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .user-profile:hover {
            background: #e8ecf5;
            border-color: #d5dceb;
        }

        .user-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4d68ff, #6d4aff);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 14px;
            overflow: hidden;
            flex-shrink: 0;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-name {
            font-weight: 600;
            font-size: 13px;
            color: #1a1a2e;
        }

        .header-user-menu {
            min-width: 210px;
            padding: 0.5rem;
            border: 1px solid #e4e9f0;
            box-shadow: 0 12px 30px rgba(26, 26, 46, 0.12);
            z-index: 1100;
        }

        .app-header .dropdown:last-child {
            margin-right: 0;
        }

        .app-header .header-profile-dropdown {
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            z-index: 1100;
        }

        .app-header .header-actions {
            padding-left: 32px;
            padding-right: 220px;
        }

        .header-language-menu {
            min-width: 130px;
        }

        .header-notification-menu {
            min-width: 300px;
            padding: .5rem;
        }

        .header-notification-menu .notification-item {
            display: block;
            padding: .65rem .75rem;
            border-radius: 8px;
            text-decoration: none;
            color: #1a1a2e;
        }

        .header-notification-menu .notification-item:hover {
            background: #f1f4f9;
        }

        .header-user-menu .dropdown-item {
            border-radius: 8px;
            padding: 0.65rem 0.75rem;
        }

        .header-user-menu .dropdown-item:hover {
            background: #f1f4f9;
        }

        .user-role {
            font-size: 11px;
            color: #8e96a8;
        }

        /* ===== CONTENT ===== */
        .app-content {
            background: #f0f4f9;
            flex: 1;
            overflow-y: auto;
            padding: 1.5rem 0;
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
            background: linear-gradient(135deg, #4d68ff, #6d4aff);
            border: none;
            color: #fff;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(77, 104, 255, 0.25);
        }

        .btn-light-primary {
            background: rgba(77, 104, 255, 0.08);
            color: #4d68ff;
            border: 1px solid rgba(77, 104, 255, 0.12);
        }

        .btn-light-primary:hover {
            background: rgba(77, 104, 255, 0.15);
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 992px) {
            .app-sidebar {
                position: fixed;
                top: 0;
                left: -100%;
                transition: left 0.3s ease;
                z-index: 1050;
                height: 100vh;
                width: 280px;
            }

            .app-sidebar.open {
                left: 0;
            }

            .app-sidebar-overlay {
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 1040;
                display: none;
            }

            .app-sidebar-overlay.open {
                display: block;
            }

            .topbar-search {
                min-width: 140px;
            }

            .user-profile .user-details {
                display: none;
            }

        }

        @media (max-width: 576px) {
            .app-container {
                padding: 0 1rem;
            }

            .topbar-search {
                min-width: 100px;
            }

            .topbar-search input {
                font-size: 12px;
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
        .app-sidebar {
            background: linear-gradient(180deg, var(--diagoma-primary-dark) 0%, #151b2c 100%) !important;
        }
        .app-sidebar-header .logo-icon,
        .user-avatar {
            background: linear-gradient(135deg, var(--diagoma-primary), var(--diagoma-primary-dark)) !important;
        }
        .app-sidebar-header .version-badge {
            background: color-mix(in srgb, var(--diagoma-primary) 20%, transparent) !important;
            color: var(--diagoma-primary) !important;
            border-color: color-mix(in srgb, var(--diagoma-primary) 35%, transparent) !important;
        }
        .menu-item.active > .menu-link {
            background: color-mix(in srgb, var(--diagoma-primary) 22%, transparent) !important;
            box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--diagoma-primary) 35%, transparent) !important;
        }
        .menu-item.active > .menu-link::before {
            background: linear-gradient(180deg, var(--diagoma-primary), var(--diagoma-primary-dark)) !important;
        }
        .menu-link:hover,
        .menu-link:focus,
        .menu-item.active > .menu-link {
            color: #fff !important;
        }
        .app-header .topbar-search:focus-within,
        .header-actions .btn-icon:hover,
        .user-profile:hover {
            border-color: color-mix(in srgb, var(--diagoma-primary) 40%, #e4e9f0) !important;
            color: var(--diagoma-primary) !important;
        }
        .user-profile .user-name,
        .header-actions .btn-icon:hover {
            color: var(--diagoma-primary) !important;
        }
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
        .app-content a:not(.btn):hover,
        .app-content .table-sortable:hover,
        .app-content .table-sort-icon .active {
            color: var(--diagoma-primary) !important;
        }
        .datatable-export-toolbar { display:flex; justify-content:flex-end; gap:.5rem; margin:0 0 1rem; flex-wrap:wrap; }
        .datatable-export-toolbar .dt-button { border:0 !important; border-radius:9px !important; padding:.55rem .9rem !important; font-weight:700 !important; box-shadow:none !important; }
        .datatable-export-toolbar .buttons-excel { background:#e9f8ef !important; color:#16834b !important; }
        .datatable-export-toolbar .buttons-pdf { background:#fff0f0 !important; color:#c03945 !important; }
        .datatable-export-toolbar .buttons-copy { background:#eef3ff !important; color:#3154b7 !important; }
        body[data-theme-dark="1"] { background: #f0f4f9; color: #172b4d; }
        body[data-theme-dark="1"] .app-sidebar { background: #101114 !important; }
        body[data-theme-dark="1"] .app-header { background: rgba(255,255,255,.92) !important; border-bottom-color: rgba(0,0,0,.04) !important; }
        body[data-theme-dark="1"] .app-header .topbar-search,
        body[data-theme-dark="1"] .app-header .header-actions .btn-icon,
        body[data-theme-dark="1"] .app-header .user-profile {
            background: #f1f4f9 !important;
            border-color: #e4e9f0 !important;
            color: #4a5068 !important;
        }
        body[data-theme-dark="1"] .app-header .topbar-search input,
        body[data-theme-dark="1"] .app-header .user-name { color: #1a1a2e !important; }
    </style>
</head>
<body id="kt_app_body" data-theme-flat="{{ !empty($activePalette['flat']) ? '1' : '0' }}" data-theme-dark="{{ !empty($activePalette['dark_mode']) ? '1' : '0' }}">
    <div class="d-flex flex-column flex-root app-root" id="kt_app_root">
        <div class="app-page d-flex" id="kt_app_page">
            <!-- ===== SIDEBAR OVERLAY (mobile) ===== -->
            <div class="app-sidebar-overlay" id="kt_app_sidebar_overlay"></div>

            <!-- ===== SIDEBAR ===== -->
            <aside id="kt_app_sidebar" class="app-sidebar">
                <!-- Header -->
                <div class="app-sidebar-header">
                    <div class="d-flex align-items-center justify-content-between">
                        <a href="{{ route('admin.dashboard') }}" class="d-flex align-items-center text-decoration-none gap-3">
                            @php
                                $companyLogo = data_get(auth()->user()->entreprise?->settings ?? [], 'logo');
                            @endphp
                            @if($companyLogo)
                                <img src="{{ asset('storage/' . ltrim($companyLogo, '/')) }}" alt="{{ auth()->user()->entreprise?->name ?? 'Logo de l’entreprise' }}" class="company-logo">
                            @else
                                <div class="logo-icon">D</div>
                                <div>
                                    <div class="brand-text">{{ auth()->user()->entreprise?->name ?? 'Diagoma' }}</div>
                                    <div class="brand-sub">ERP</div>
                                </div>
                            @endif
                        </a>
                        <span class="version-badge">v1.0</span>
                    </div>
                </div>

                <!-- Scrollable Menu -->
                <div class="app-sidebar-menu">
                    <div class="menu">
                        @php
                            $enabledRubriques = array_merge(
                                ['pilotage' => true, 'commercial' => true, 'comptabilite' => true, 'rh' => true, 'administration' => true, 'succursales' => false],
                                data_get(auth()->user()->entreprise?->settings ?? [], 'enabled_rubriques', [])
                            );
                        @endphp
                        <!-- Section: Navigation -->
                        <div class="menu-section">{{ __('Navigation') }}</div>

                        <!--<div class="menu-item {{ request()->routeIs('admin.hub') ? 'active' : '' }}">
                            <a href="{{ route('admin.hub') }}" class="menu-link">
                                <span class="menu-icon">⚙️</span>
                                <span class="menu-title">Administration</span>
                            </a>
                        </div>-->

                        @if(auth()->user()->hasRole('super_admin'))
                        <div class="menu-section">{{ __('Console') }}</div>
                        <div class="menu-item {{ request()->routeIs('console.index', 'console.dashboard') ? 'active' : '' }}">
                            <a href="{{ route('console.index') }}" class="menu-link">
                                <span class="menu-icon">📊</span>
                                <span class="menu-title">Dashboard</span>
                            </a>
                        </div>
                        <div class="menu-item {{ request()->routeIs('console.account-tracking') ? 'active' : '' }}">
                            <a href="{{ route('console.account-tracking') }}" class="menu-link">
                                <span class="menu-icon">📋</span>
                                <span class="menu-title">{{ __('Account tracking') }}</span>
                            </a>
                        </div>
                        <div class="menu-item {{ request()->routeIs('console.pack-requests') ? 'active' : '' }}">
                            <a href="{{ route('console.pack-requests') }}" class="menu-link">
                                <span class="menu-icon">📦</span>
                                <span class="menu-title">Demandes de packs</span>
                            </a>
                        </div>
                        @else
                        <!-- Section: Modules -->
                        <div class="menu-section">{{ __('Modules') }}</div>
                        @if(auth()->user()->hasPermission('dashboard'))
                        <div class="menu-item {{ request()->routeIs('admin.dashboard', 'dashboard') ? 'active' : '' }}">
                            <a href="{{ route('admin.dashboard') }}" class="menu-link">
                                <span class="menu-icon">📊</span>
                                <span class="menu-title">Dashboard</span>
                            </a>
                        </div>
                        @endif
                        @if(data_get(auth()->user()->entreprise?->settings, 'ai_assistant_enabled', false))
                        <div class="menu-item {{ request()->routeIs('admin.ai-assistant*') ? 'active' : '' }}">
                            <a href="{{ route('admin.ai-assistant') }}" class="menu-link">
                                <span class="menu-icon">🤖</span>
                                <span class="menu-title">Assistant IA</span>
                            </a>
                        </div>
                        @endif
                        @if(!empty($enabledRubriques['administration']) && auth()->user()->hasPermission('administration'))
                        <div class="menu-item {{ request()->routeIs('admin.administration*') ? 'active' : '' }}">
                            <a href="{{ route('admin.administration') }}" class="menu-link">
                                <span class="menu-icon">🗂️</span>
                                <span class="menu-title">{{ __('Administrative management') }}</span>
                            </a>
                        </div>
                        @endif


                        @if(!empty($enabledRubriques['comptabilite']) && auth()->user()->hasPermission('accounting'))
                        <div class="menu-item {{ request()->routeIs('admin.comptabilite*') ? 'active' : '' }}">
                            <a href="{{ route('admin.comptabilite') }}" class="menu-link">
                                <span class="menu-icon">💳</span>
                                <span class="menu-title">{{ __('Accounting') }}</span>
                                <span class="menu-badge"></span>
                            </a>
                        </div>
                        @endif


                        @if(!empty($enabledRubriques['rh']) && auth()->user()->hasPermission('hr'))
                        <div class="menu-item {{ request()->routeIs('admin.rh') ? 'active' : '' }}">
                            <a href="{{ route('admin.rh') }}" class="menu-link">
                                <span class="menu-icon">👥</span>
                                <span class="menu-title">{{ __('HR & Paie') }}</span>
                            </a>
                        </div>
                        @endif

                        @if(!empty($enabledRubriques['commercial']) && auth()->user()->hasPermission('commercial'))
                        <div class="menu-item {{ request()->routeIs('admin.commercial*') ? 'active' : '' }}">
                            <a href="{{ route('admin.commercial') }}" class="menu-link">
                                <span class="menu-icon">📊</span>
                                <span class="menu-title">{{ __('Commercial') }}</span>
                                <span class="menu-badge"></span>
                            </a>
                        </div>
                        @endif
                        @if(!empty($enabledRubriques['succursales']) && auth()->user()->hasPermission('succursales'))
                        <div class="menu-item {{ request()->routeIs('admin.succursales*') ? 'active' : '' }}">
                            <a href="{{ route('admin.succursales.index') }}" class="menu-link">
                                <span class="menu-icon">🏬</span>
                                <span class="menu-title">{{ __('Branches') }}</span>
                            </a>
                        </div>
                        @endif
                        @endif

                        @if(auth()->user()->hasRole('super_admin'))
                        <div class="menu-item {{ request()->routeIs('admin.demorequests') ? 'active' : '' }}">
                            <a href="{{ route('admin.demorequests') }}" class="menu-link">
                                <span class="menu-icon">💬</span>
                                <span class="menu-title">{{ __('Demo requests') }}</span>
                            </a>
                        </div>
                        @endif
                        <!-- Section: Configuration -->
                        <div class="menu-section">{{ __('Configuration') }}</div>

                        @if(!auth()->user()->hasRole('super_admin') && auth()->user()->hasPermission('users'))
                        <div class="menu-item {{ request()->routeIs('admin.users*') ? 'active' : '' }}">
                            <a href="{{ route('admin.users.index') }}" class="menu-link">
                                <span class="menu-icon">👤</span>
                                <span class="menu-title">{{ __('Users') }}</span>
                            </a>
                        </div>
                        @endif

                        @if(auth()->user()->hasPermission('settings'))
                        <div class="menu-item {{ request()->routeIs('admin.settings*') ? 'active' : '' }}">
                            <a href="{{ route('admin.settings') }}" class="menu-link">
                                <span class="menu-icon">🔧</span>
                                <span class="menu-title">{{ __('Settings') }}</span>
                            </a>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Footer with logout -->
                <div class="app-sidebar-footer">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn-logout">
                            <span>🚪</span>
                            Déconnexion
                        </button>
                    </form>
                </div>
            </aside>
            <button class="sidebar-edge-toggle" id="kt_app_sidebar_edge_toggle" type="button" aria-label="Réduire ou ouvrir le menu" title="Réduire ou ouvrir le menu">
                <span aria-hidden="true">←</span>
            </button>

            <!-- ===== MAIN WRAPPER ===== -->
            <div class="app-wrapper d-flex flex-column flex-fill" id="kt_app_wrapper">
                <!-- ===== HEADER ===== -->
                <header id="kt_app_header" class="app-header">
                    <div class="app-container d-flex align-items-center h-100">
                        <!-- Mobile toggle -->
                        <button class="btn btn-icon d-lg-none me-2" id="kt_app_sidebar_toggle" type="button" aria-label="Ouvrir ou fermer le menu">
                            <span style="font-size:20px;">☰</span>
                        </button>

                        <!-- Brand mobile -->
                        <a href="{{ route('admin.dashboard') }}" class="d-lg-none me-3">
                            <span class="badge bg-primary px-3 py-2 rounded-2">D</span>
                        </a>

                        <!-- Search centered in the header -->
                        <div class="topbar-search d-flex align-items-center position-relative" id="employee-search">
                            <span style="color:#8e96a8;">🔍</span>
                            <input type="search" id="employee-search-input" placeholder="{{ __('Search an employee...') }}" autocomplete="off" aria-label="{{ __('Search an employee...') }}" />
                            <span class="text-muted fs-8" style="cursor:pointer;">⌘K</span>
                            <div class="employee-search-results" id="employee-search-results"></div>
                        </div>

                        <!-- Right actions -->
                        <div class="d-flex align-items-center ms-auto gap-2 header-actions">
                            <!-- Language and notifications near the profile -->
                            <div class="dropdown">
                                <button class="btn btn-icon btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('Choose language') }}">🌐</button>
                                <ul class="dropdown-menu dropdown-menu-end header-language-menu">
                                    @php($currentLocale = app()->getLocale())
                                    <li><form method="POST" action="{{ route('locale.update') }}">@csrf<input type="hidden" name="locale" value="fr"><button class="dropdown-item {{ $currentLocale === 'fr' ? 'active' : '' }}" type="submit">🇫🇷 Français</button></form></li>
                                    <li><form method="POST" action="{{ route('locale.update') }}">@csrf<input type="hidden" name="locale" value="en"><button class="dropdown-item {{ $currentLocale === 'en' ? 'active' : '' }}" type="submit">🇬🇧 English</button></form></li>
                                </ul>
                            </div>

                            <div class="dropdown">
                                <button class="btn btn-icon btn-sm position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
                                    🔔
                                    @if(($notificationCount ?? 0) > 0)
                                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:9px; padding:0.2rem 0.4rem;">{{ $notificationCount ?? 0 }}</span>
                                    @endif
                                </button>
                                <div class="dropdown-menu dropdown-menu-end header-notification-menu">
                                    <div class="px-2 py-2 border-bottom fw-bold">{{ __('Notifications') }}</div>
                                    <a href="{{ route('admin.rh.permissions') }}" class="notification-item">
                                        <span class="me-2">📝</span>{{ $pendingPermissions ?? 0 }} {{ __('Pending permission request(s)') }}
                                    </a>
                                    <a href="{{ route('admin.rh.leaves') }}" class="notification-item">
                                        <span class="me-2">🗓️</span>{{ $pendingLeaves ?? 0 }} {{ __('Pending leave request(s)') }}
                                    </a>
                                    @foreach(($stockAlerts ?? collect()) as $stockAlert)
                                        <a href="{{ route('admin.commercial.module', 'etat-stock') }}" class="notification-item">
                                            <span class="me-2">{{ $stockAlert['quantity'] <= 0 ? '🚨' : '⚠️' }}</span>
                                            <strong>{{ $stockAlert['name'] }}</strong>
                                            <span class="d-block small text-muted ms-4">{{ $stockAlert['quantity'] <= 0 ? 'Rupture de stock' : 'Stock faible' }} · {{ number_format(max(0, $stockAlert['quantity']), 2, ',', ' ') }} {{ $stockAlert['unit'] }}</span>
                                        </a>
                                    @endforeach
                                    @if(($notificationCount ?? 0) === 0)
                                        <div class="text-muted small px-2 py-3">{{ __('No new requests.') }}</div>
                                    @endif
                                </div>
                            </div>

                            <!-- User profile -->
                            <div class="dropdown header-profile-dropdown">
                                <button type="button" class="user-profile border-0" data-bs-toggle="dropdown" aria-expanded="false">
                                <div class="user-avatar">
                                    <img src="{{ auth()->user()->avatar_path ? asset('storage/' . auth()->user()->avatar_path) : asset('assets/media/avatars/300-1.jpg') }}" alt="Photo de {{ auth()->user()->name ?? 'Administrateur' }}">
                                </div>
                                <div class="user-details">
                                    <div class="user-name">{{ auth()->user()->name ?? 'Administrateur' }}</div>
                                    <div class="user-role">{{ auth()->user()->role?->label ?? 'Super admin' }}</div>
                                </div>
                                <span class="ms-1 text-muted">⌄</span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end header-user-menu">
                                    <li class="px-3 py-2 border-bottom mb-1">
                                        <div class="fw-bold">{{ auth()->user()->name ?? 'Administrateur' }}</div>
                                        <div class="text-muted fs-8">{{ auth()->user()->email ?? '' }}</div>
                                    </li>
                                    <li><a class="dropdown-item" href="{{ route('admin.profile') }}"><span class="me-2">👤</span>Profil</a></li>
                                    <li>
                                        <form method="POST" action="{{ route('logout') }}">
                                            @csrf
                                            <button type="submit" class="dropdown-item text-danger"><span class="me-2">🚪</span>Se déconnecter</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </div>
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
                        sidebar.classList.toggle('collapsed');
                        localStorage.setItem('diagoma-sidebar-collapsed', sidebar.classList.contains('collapsed') ? '1' : '0');
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
                    if (edge) edge.querySelector('span').textContent = sidebar.classList.contains('collapsed') ? '→' : '←';
                };
                updateArrow();
                document.getElementById('kt_app_sidebar_edge_toggle')?.addEventListener('click', updateArrow);
                overlay.addEventListener('click', close);
                if (window.innerWidth >= 992 && localStorage.getItem('diagoma-sidebar-collapsed') === '1') {
                    sidebar.classList.add('collapsed');
                }

                // Close on Escape
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') close();
                });

        (() => {
                   const input = document.getElementById('employee-search-input');
                   const results = document.getElementById('employee-search-results');
                   const container = document.getElementById('employee-search');
                   if (!input || !results || !container) return;

                   let timer;
                   const render = (employees) => {
                       results.innerHTML = employees.length
                           ? employees.map(employee => `<a class="employee-search-result" href="${employee.url}"><strong>${escapeHtml(employee.name)}</strong><span class="d-block text-muted fs-8">${escapeHtml(employee.matricule || '')}${employee.position ? ' · ' + escapeHtml(employee.position) : ''}</span></a>`).join('')
                           : '<div class="employee-search-empty">Aucun employé trouvé.</div>';
                       results.style.display = 'block';
                   };
                   const escapeHtml = (value) => String(value).replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[character]));

                   input.addEventListener('input', () => {
                       clearTimeout(timer);
                       const query = input.value.trim();
                       if (query.length < 2) {
                           results.style.display = 'none';
                           return;
                       }
                       timer = setTimeout(() => {
                           fetch(`{{ route('admin.rh.employees.search') }}?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } })
                               .then(response => response.ok ? response.json() : Promise.reject(response))
                               .then(render)
                               .catch(() => {
                                   results.innerHTML = '<div class="employee-search-empty">Recherche indisponible.</div>';
                                   results.style.display = 'block';
                               });
                       }, 250);
                   });
                   input.addEventListener('focus', () => {
                       if (input.value.trim().length >= 2) input.dispatchEvent(new Event('input'));
                   });
                   document.addEventListener('click', event => {
                       if (!container.contains(event.target)) results.style.display = 'none';
                   });
        })();
            }
        })();

        // Auto-close sidebar on route change (mobile)
        (function() {
            const sidebar = document.getElementById('kt_app_sidebar');
            const overlay = document.getElementById('kt_app_sidebar_overlay');
            if (window.innerWidth < 992 && sidebar && overlay) {
                document.querySelectorAll('.menu-link').forEach(link => {
                    link.addEventListener('click', () => {
                        sidebar.classList.remove('open');
                        overlay.classList.remove('open');
                        document.body.style.overflow = '';
                    });
                });
            }
        })();

        // Keyboard shortcut: Ctrl+K or Cmd+K to focus search
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                const searchInput = document.querySelector('.topbar-search input');
                if (searchInput) searchInput.focus();
            }
        });

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
</body>
</html>