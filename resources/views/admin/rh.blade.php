@extends('admin.layout')

@section('content')
<style>
    .rh-shell {
        padding: 0 0 32px;
    }
    .rh-hero {
        background: linear-gradient(135deg, #0f766e 0%, #134e4a 100%);
        border-radius: 22px;
        padding: 26px 28px;
        color: #fff;
        box-shadow: 0 18px 32px rgba(15,118,110,.18);
        margin-bottom: 24px;
    }
    .rh-hero h1 {
        margin: 16px 0 0;
        font-size: 30px;
        font-weight: 700;
        color: #fff;
    }
    .rh-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: rgba(255,255,255,.12);
        border: 1px solid rgba(255,255,255,.16);
        padding: 8px 14px;
        border-radius: 999px;
        font-size: 11px;
        letter-spacing: .12em;
        font-weight: 700;
        text-transform: uppercase;
    }
    .rh-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 18px;
    }
    .rh-module-card {
        background: #fff;
        border: 1px solid #edf1f5;
        border-radius: 18px;
        padding: 18px;
        min-height: 236px;
        display: flex;
        flex-direction: column;
        box-shadow: 0 10px 30px rgba(24,39,75,.04);
        text-decoration: none;
        color: inherit;
        transition: all .2s ease;
    }
    .rh-module-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 18px 30px rgba(24,39,75,.08);
        border-color: #bfe8e1;
        color: inherit;
    }
    .rh-topline, .rh-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
    }
    .rh-topline { margin-bottom: 16px; }
    .rh-badge, .rh-status {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 5px 10px;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
    }
    .rh-badge { background: #f3f6fb; color: #58677e; }
    .rh-status { background: #e8fff0; color: #15803d; }
    .rh-icon {
        width: 54px;
        height: 54px;
        border-radius: 16px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        margin-bottom: 14px;
        background: rgba(20,184,166,.12);
        color: #0f766e;
    }
    .rh-title {
        margin: 0 0 8px;
        font-size: 18px;
        font-weight: 700;
        color: #1e2432;
    }
    .rh-description {
        margin: 0 0 18px;
        color: #64748b;
        font-size: 14px;
        line-height: 1.6;
        flex: 1;
    }
    .rh-footer { margin-top: auto; }
    .rh-tagline {
        font-size: 12px;
        color: #64748b;
        font-weight: 600;
    }
    .rh-action {
        border-radius: 12px;
        padding: 10px 14px;
        font-size: 12px;
        font-weight: 700;
        background: #0f766e;
        color: #fff;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    @media (max-width: 1199px) { .rh-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 767px) { .rh-grid { grid-template-columns: 1fr; } .rh-hero { padding: 20px; } }
</style>

<div class="rh-shell">
    <div class="rh-hero">
        <span class="rh-pill"><i class="ki-duotone ki-people fs-5"></i> RH &amp; Paie</span>
        <h1>{{ $title ?? 'Espace RH & Paie' }}</h1>
    </div>

    <div class="rh-grid">
        @foreach($modules as $key => $module)
            <a href="{{ route('admin.rh.module', $key) }}" class="rh-module-card">
                <div class="rh-topline">
                    <span class="rh-badge">Module RH</span>
                    <span class="rh-status">Disponible</span>
                </div>
                <div class="rh-icon">{{ $module['icon'] }}</div>
                <h3 class="rh-title">{{ $module['title'] }}</h3>
                <p class="rh-description">{{ $module['description'] }}</p>
                <div class="rh-footer">
                    <span class="rh-tagline">Gestion RH</span>
                    <span class="rh-action"><i class="ki-duotone ki-arrow-right fs-6"></i> Ouvrir</span>
                </div>
            </a>
        @endforeach
    </div>
</div>
@endsection
