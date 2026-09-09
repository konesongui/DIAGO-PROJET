@extends('admin.layout')

@section('content')
<style>
    .commercial-shell { padding: 0 0 32px; }
    .commercial-hero { background: linear-gradient(135deg, #0f766e 0%, #134e4a 100%); border-radius: 22px; padding: 26px 28px; color: #fff; box-shadow: 0 18px 32px rgba(15,118,110,.18); margin-bottom: 24px; }
    .commercial-pill { display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.16); padding: 8px 14px; border-radius: 999px; font-size: 11px; letter-spacing: .12em; font-weight: 700; text-transform: uppercase; }
    .commercial-hero h1 { margin: 16px 0 0; font-size: 30px; font-weight: 700; color: #fff; }
    .commercial-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 18px; }
    .commercial-module-card { background: #fff; border: 1px solid #edf1f5; border-radius: 18px; padding: 18px; height: 100%; display: flex; flex-direction: column; box-shadow: 0 10px 30px rgba(24,39,75,.04); text-decoration: none; color: inherit; transition: all .2s ease; }
    .commercial-module-card:hover { transform: translateY(-3px); box-shadow: 0 18px 30px rgba(24,39,75,.08); border-color: #bde5df; text-decoration: none; color: inherit; }
    .commercial-topline { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 16px; }
    .commercial-badge, .commercial-status { display: inline-flex; align-items: center; border-radius: 999px; padding: 5px 10px; font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
    .commercial-badge { background: #f3f6fb; color: #58677e; }
    .commercial-status { background: #e8fff0; color: #15803d; }
    .commercial-icon { width: 54px; height: 54px; border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; font-size: 22px; margin-bottom: 14px; }
    .commercial-icon.primary { background: rgba(37,99,235,.12); color: #2563eb; }
    .commercial-icon.info { background: rgba(6,182,212,.12); color: #0891b2; }
    .commercial-icon.success { background: rgba(16,185,129,.12); color: #059669; }
    .commercial-icon.warning { background: rgba(245,158,11,.12); color: #d97706; }
    .commercial-icon.danger { background: rgba(239,68,68,.12); color: #dc2626; }
    .commercial-title { margin: 0 0 8px; font-size: 18px; font-weight: 700; color: #1e2432; }
    .commercial-description { margin: 0 0 18px; color: #64748b; font-size: 14px; line-height: 1.6; flex: 1; }
    .commercial-footer { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-top: auto; }
    .commercial-tagline { font-size: 12px; color: #64748b; font-weight: 600; }
    .commercial-action { border-radius: 12px; padding: 10px 14px; font-size: 12px; font-weight: 700; background: #0f766e; color: #fff; display: inline-flex; align-items: center; gap: 6px; }
    @media (max-width: 1199px) { .commercial-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 767px) { .commercial-grid { grid-template-columns: 1fr; } .commercial-hero { padding: 20px; } }
</style>

<div class="commercial-shell">
    <div class="commercial-hero">
        <span class="commercial-pill"><i class="ki-duotone ki-shop fs-5"></i> Commercial</span>
        <h1>Gestion commerciale</h1>
    </div>

    <div class="commercial-grid">
        @foreach($modules as $module)
            <a href="{{ route('admin.commercial.module', $module['key']) }}" class="commercial-module-card">
                <div class="commercial-topline">
                    <span class="commercial-badge">Module</span>
                    <span class="commercial-status">Disponible</span>
                </div>
                <div class="commercial-icon {{ $module['color'] ?? 'primary' }}">
                    <i class="ki-duotone {{ $module['icon'] ?? 'ki-shop' }} fs-2"></i>
                </div>
                <h3 class="commercial-title">{{ $module['title'] }}</h3>
                <p class="commercial-description">{{ $module['description'] }}</p>
                <div class="commercial-footer">
                    <span class="commercial-tagline">Module commercial</span>
                    <span class="commercial-action"><i class="ki-duotone ki-arrow-right fs-6"></i> Ouvrir</span>
                </div>
            </a>
        @endforeach
    </div>
</div>
@endsection
