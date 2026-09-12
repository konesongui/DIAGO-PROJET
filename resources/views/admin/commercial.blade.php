@extends('admin.layout')

@section('content')
<style>
    .commercial-shell { padding: 0 0 32px; }
    .commercial-hero { background: linear-gradient(135deg, #273772 0%, #1b2550 100%); border-radius: 22px; padding: 26px 28px; color: #fff; box-shadow: 0 18px 32px rgba(39,55,114,.20); margin-bottom: 24px; }
    .commercial-pill { display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.16); padding: 8px 14px; border-radius: 999px; font-size: 11px; letter-spacing: .12em; font-weight: 700; text-transform: uppercase; }
    .commercial-hero h1 { margin: 16px 0 0; font-size: 30px; font-weight: 700; color: #fff; }
    .commercial-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 18px; }
    .commercial-module-card { background: #fff; border: 1px solid #edf1f5; border-radius: 18px; padding: 18px; height: 100%; display: flex; flex-direction: column; box-shadow: 0 10px 30px rgba(24,39,75,.04); text-decoration: none; color: inherit; transition: all .2s ease; }
    .commercial-module-card:hover { transform: translateY(-3px); box-shadow: 0 18px 30px rgba(24,39,75,.08); border-color: #c9d1ea; text-decoration: none; color: inherit; }
    .commercial-topline { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 16px; }
    .commercial-badge, .commercial-status { display: inline-flex; align-items: center; border-radius: 999px; padding: 5px 10px; font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
    .commercial-badge { background: #f3f6fb; color: #58677e; }
    .commercial-status { background: #eceff6; color: #273772; }
    .commercial-icon { width: 54px; height: 54px; border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 14px; color: #fff; background: #273772; box-shadow: 0 8px 16px -6px rgba(39,55,114,.45); }
    .commercial-icon .bi { color: #fff; }
    .commercial-icon.blue { background: #2563eb; box-shadow: 0 8px 16px -6px rgba(37,99,235,.45); }
    .commercial-icon.green { background: #059669; box-shadow: 0 8px 16px -6px rgba(5,150,105,.45); }
    .commercial-icon.purple { background: #7c3aed; box-shadow: 0 8px 16px -6px rgba(124,58,237,.45); }
    .commercial-icon.orange { background: #d97706; box-shadow: 0 8px 16px -6px rgba(217,119,6,.45); }
    .commercial-icon.red { background: #dc2626; box-shadow: 0 8px 16px -6px rgba(220,38,38,.45); }
    .commercial-icon.teal { background: #0f766e; box-shadow: 0 8px 16px -6px rgba(15,118,110,.45); }
    .commercial-icon.cyan { background: #0891b2; box-shadow: 0 8px 16px -6px rgba(8,145,178,.45); }
    .commercial-icon.pink { background: #db2777; box-shadow: 0 8px 16px -6px rgba(219,39,119,.45); }
    .commercial-icon.indigo { background: #4338ca; box-shadow: 0 8px 16px -6px rgba(67,56,202,.45); }
    .commercial-title { margin: 0 0 8px; font-size: 18px; font-weight: 700; color: #172033; }
    .commercial-description { margin: 0 0 18px; color: #64748b; font-size: 14px; line-height: 1.6; flex: 1; }
    .commercial-footer { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-top: auto; }
    .commercial-tagline { font-size: 12px; color: #64748b; font-weight: 600; }
    .commercial-action { border-radius: 12px; padding: 10px 14px; font-size: 12px; font-weight: 700; background: #FADF2F; color: #172033; display: inline-flex; align-items: center; gap: 6px; }
    @media (max-width: 1199px) { .commercial-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 767px) { .commercial-grid { grid-template-columns: 1fr; } .commercial-hero { padding: 20px; } }
</style>

<div class="commercial-shell">
    <div class="commercial-hero">
        <span class="commercial-pill"><i class="bi bi-shop"></i> Commercial</span>
        <h1>Gestion commerciale</h1>
    </div>

    <div class="commercial-grid">
        @foreach($modules as $module)
            <a href="{{ route('admin.commercial.module', $module['key']) }}" class="commercial-module-card">
                <div class="commercial-topline">
                    <span class="commercial-badge">Module</span>
                    <span class="commercial-status">Disponible</span>
                </div>
                <div class="commercial-icon {{ $module['color'] ?? 'blue' }}">
                    <i class="bi {{ $module['icon'] ?? 'bi-shop' }}"></i>
                </div>
                <h3 class="commercial-title">{{ $module['title'] }}</h3>
                <p class="commercial-description">{{ $module['description'] }}</p>
                <div class="commercial-footer">
                    <span class="commercial-tagline">Module commercial</span>
                    <span class="commercial-action">Ouvrir <i class="bi bi-arrow-right"></i></span>
                </div>
            </a>
        @endforeach
    </div>
</div>
@endsection
