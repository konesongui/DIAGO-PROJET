@extends('admin.layout')

@section('content')
<style>
    .adm-shell { padding: 0 0 32px; }
    .adm-hero {
        background: linear-gradient(135deg, #273772 0%, #1b2550 100%);
        border-radius: 22px;
        padding: 26px 28px;
        color: #fff;
        box-shadow: 0 18px 32px rgba(39,55,114,.20);
        margin-bottom: 24px;
    }
    .adm-hero h1 { margin: 16px 0 4px; font-size: 30px; font-weight: 700; color: #fff; }
    .adm-hero p { margin: 0; color: rgba(255,255,255,.78); }
    .adm-pill {
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
    .adm-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }
    .adm-card {
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
    .adm-card:hover { transform: translateY(-3px); box-shadow: 0 18px 30px rgba(24,39,75,.08); border-color: #c9d1ea; color: inherit; }
    .adm-topline, .adm-footer { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
    .adm-topline { margin-bottom: 16px; }
    .adm-badge, .adm-status {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 5px 10px;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
    }
    .adm-badge { background: #f3f6fb; color: #58677e; }
    .adm-status { background: #eceff6; color: #273772; }
    .adm-status.is-pending { background: #fef3c7; color: #92400e; }
    .adm-icon {
        width: 54px;
        height: 54px;
        border-radius: 16px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        margin-bottom: 14px;
        background: #273772;
        color: #fff;
        box-shadow: 0 8px 16px -6px rgba(39,55,114,.45);
    }
    .adm-icon .bi { color: #fff; }
    .adm-icon.blue { background: #2563eb; box-shadow: 0 8px 16px -6px rgba(37,99,235,.45); }
    .adm-icon.green { background: #059669; box-shadow: 0 8px 16px -6px rgba(5,150,105,.45); }
    .adm-icon.purple { background: #7c3aed; box-shadow: 0 8px 16px -6px rgba(124,58,237,.45); }
    .adm-icon.orange { background: #d97706; box-shadow: 0 8px 16px -6px rgba(217,119,6,.45); }
    .adm-icon.teal { background: #0f766e; box-shadow: 0 8px 16px -6px rgba(15,118,110,.45); }
    .adm-title { margin: 0 0 8px; font-size: 18px; font-weight: 700; color: #172033; }
    .adm-description { margin: 0 0 18px; color: #64748b; font-size: 14px; line-height: 1.6; flex: 1; }
    .adm-footer { margin-top: auto; }
    .adm-tagline { font-size: 12px; color: #64748b; font-weight: 600; }
    .adm-action {
        border-radius: 12px;
        padding: 10px 14px;
        font-size: 12px;
        font-weight: 700;
        background: #FADF2F;
        color: #172033;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    @media (max-width: 1199px) { .adm-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 767px) { .adm-grid { grid-template-columns: 1fr; } .adm-hero { padding: 20px; } }
</style>

<div class="adm-shell">
    <div class="adm-hero">
        <span class="adm-pill"><i class="bi bi-folder-fill"></i> Administration</span>
        <h1>{{ $title ?? 'Gestion administrative' }}</h1>
        <p>{{ $subtitle }}</p>
    </div>

    <div class="adm-grid">
        @foreach($modules as $key => $module)
            <a href="{{ route($module['route']) }}" class="adm-card">
                <div class="adm-topline">
                    <span class="adm-badge">{{ $module['count'] }} enregistrement(s)</span>
                    <span class="adm-status {{ $module['pending'] > 0 ? 'is-pending' : '' }}">
                        {{ $module['pending'] > 0 ? $module['pending'] . ' ' . $module['pendingLabel'] : 'À jour' }}
                    </span>
                </div>
                <div class="adm-icon {{ $module['color'] }}"><i class="bi {{ $module['icon'] }}"></i></div>
                <h3 class="adm-title">{{ $module['title'] }}</h3>
                <p class="adm-description">{{ $module['description'] }}</p>
                <div class="adm-footer">
                    <span class="adm-tagline">Vie de l’entreprise</span>
                    <span class="adm-action">Ouvrir <i class="bi bi-arrow-right"></i></span>
                </div>
            </a>
        @endforeach
    </div>
</div>
@endsection
