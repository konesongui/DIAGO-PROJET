@extends('admin.layout')

@section('content')
<style>
    .ohada-shell {
        padding: 0 0 32px;
    }

    .ohada-hero {
        background: linear-gradient(135deg, #1d4ed8 0%, #1e3a8a 100%);
        border-radius: 22px;
        padding: 26px 28px;
        color: #fff;
        box-shadow: 0 18px 32px rgba(29, 78, 216, 0.18);
        margin-bottom: 24px;
    }

    .ohada-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: rgba(255,255,255,0.12);
        border: 1px solid rgba(255,255,255,0.16);
        padding: 8px 14px;
        border-radius: 999px;
        font-size: 11px;
        letter-spacing: .12em;
        font-weight: 700;
        text-transform: uppercase;
    }

    .ohada-hero h1 {
        margin: 16px 0 0;
        font-size: 30px;
        font-weight: 700;
        color: #fff;
    }

    .ohada-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 18px;
    }

    .ohada-module-card {
        background: #fff;
        border: 1px solid #edf1f5;
        border-radius: 18px;
        padding: 18px;
        height: 100%;
        display: flex;
        flex-direction: column;
        box-shadow: 0 10px 30px rgba(24, 39, 75, 0.04);
        text-decoration: none;
        color: inherit;
        transition: all .2s ease;
    }

    .ohada-module-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 18px 30px rgba(24, 39, 75, 0.08);
        border-color: #d8e2ff;
        text-decoration: none;
        color: inherit;
    }

    .module-topline {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
    }

    .module-badge,
    .module-status {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 5px 10px;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .module-badge {
        background: #f3f6fb;
        color: #58677e;
    }

    .module-status.available {
        background: #e8fff0;
        color: #15803d;
    }

    .module-status.planned {
        background: #fff4d8;
        color: #a16207;
    }

    .module-icon {
        width: 54px;
        height: 54px;
        border-radius: 16px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        margin-bottom: 14px;
    }

    .module-icon.blue { background: rgba(37, 99, 235, .12); color: #2563eb; }
    .module-icon.green { background: rgba(16, 185, 129, .12); color: #059669; }
    .module-icon.purple { background: rgba(139, 92, 246, .12); color: #7c3aed; }
    .module-icon.orange { background: rgba(245, 158, 11, .12); color: #d97706; }
    .module-icon.red { background: rgba(239, 68, 68, .12); color: #dc2626; }
    .module-icon.teal { background: rgba(20, 184, 166, .12); color: #0f766e; }
    .module-icon.cyan { background: rgba(6, 182, 212, .12); color: #0891b2; }
    .module-icon.pink { background: rgba(236, 72, 153, .12); color: #db2777; }
    .module-icon.indigo { background: rgba(79, 70, 229, .12); color: #4338ca; }
    .module-icon.gray { background: rgba(100, 116, 139, .12); color: #475569; }

    .module-title {
        margin: 0 0 8px;
        font-size: 18px;
        font-weight: 700;
        color: #1e2432;
    }

    .module-description {
        margin: 0 0 18px;
        color: #64748b;
        font-size: 14px;
        line-height: 1.6;
        flex: 1;
    }

    .module-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-top: auto;
    }

    .module-tagline {
        font-size: 12px;
        color: #64748b;
        font-weight: 600;
    }

    .module-action {
        border-radius: 12px;
        padding: 10px 14px;
        font-size: 12px;
        font-weight: 700;
        background: #1d4ed8;
        color: #fff;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    @media (max-width: 1199px) {
        .ohada-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (max-width: 767px) {
        .ohada-grid { grid-template-columns: 1fr; }
        .ohada-hero { padding: 20px; }
    }
</style>

<div class="ohada-shell">
    <div class="ohada-hero">
        <span class="ohada-pill"><i class="ki-duotone ki-wallet fs-5"></i> Comptabilité</span>
        <h1>{{ $title ?? 'Espace Comptabilite' }}</h1>
    </div>

    @foreach(($hub['sections'] ?? []) as $section)
        <div class="ohada-grid mb-6">
            @foreach(($section['items'] ?? []) as $module)
                @php
                    $status = $module['status'] ?? 'available';
                    $isAvailable = $status === 'available';
                @endphp

                @if($isAvailable)
                    <a href="{{ $module['url'] ?? '#' }}" class="ohada-module-card">
                        <div class="module-topline">
                            <span class="module-badge">{{ $module['badge'] ?? 'Module' }}</span>
                            <span class="module-status available">{{ $module['status_label'] ?? 'Disponible' }}</span>
                        </div>

                        <div class="module-icon {{ $module['color'] ?? 'blue' }}">
                            <i class="ki-duotone {{ $module['icon'] ?? 'ki-wallet' }} fs-2"></i>
                        </div>

                        <h3 class="module-title">{{ $module['title'] }}</h3>
                        <p class="module-description">{{ $module['description'] }}</p>

                        <div class="module-footer">
                            <span class="module-tagline">{{ ($module['is_ohada'] ?? false) ? 'Conforme OHADA' : 'Module support' }}</span>
                            <span class="module-action"><i class="ki-duotone ki-arrow-right fs-6"></i> Ouvrir</span>
                        </div>
                    </a>
                @else
                    <div class="ohada-module-card">
                        <div class="module-topline">
                            <span class="module-badge">{{ $module['badge'] ?? 'Module' }}</span>
                            <span class="module-status planned">{{ $module['status_label'] ?? 'A activer' }}</span>
                        </div>

                        <div class="module-icon {{ $module['color'] ?? 'blue' }}">
                            <i class="ki-duotone {{ $module['icon'] ?? 'ki-wallet' }} fs-2"></i>
                        </div>

                        <h3 class="module-title">{{ $module['title'] }}</h3>
                        <p class="module-description">{{ $module['description'] }}</p>

                        <div class="module-footer">
                            <span class="module-tagline">{{ ($module['is_ohada'] ?? false) ? 'Conforme OHADA' : 'Module support' }}</span>
                            <span class="module-action" style="background:#eef2f7;color:#64748b;cursor:not-allowed;"><i class="ki-duotone ki-timer fs-6"></i> A activer</span>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    @endforeach
</div>
@endsection
