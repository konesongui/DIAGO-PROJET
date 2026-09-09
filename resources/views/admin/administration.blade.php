@extends('admin.layout')
@section('content')
<style>
    .admin-shell { padding-bottom: 32px; }
    .admin-hero { background: linear-gradient(135deg,#1d4ed8 0%,#1e3a8a 100%); border-radius:22px; padding:26px 28px; color:#fff; box-shadow:0 18px 32px rgba(29,78,216,.18); margin-bottom:24px; }
    .admin-pill { display:inline-flex; align-items:center; gap:8px; background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.16); padding:8px 14px; border-radius:999px; font-size:11px; letter-spacing:.12em; font-weight:700; text-transform:uppercase; }
    .admin-hero h1 { margin:16px 0 4px; font-size:30px; font-weight:700; color:#fff; }
    .admin-hero p { margin:0; color:rgba(255,255,255,.78); }
    .admin-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:18px; }
    .admin-module-card { background:#fff; border:1px solid #edf1f5; border-radius:18px; padding:18px; min-height:220px; display:flex; flex-direction:column; box-shadow:0 10px 30px rgba(24,39,75,.04); text-decoration:none; color:inherit; transition:all .2s ease; }
    .admin-module-card:hover { transform:translateY(-3px); box-shadow:0 18px 30px rgba(24,39,75,.08); border-color:#d8e2ff; color:inherit; }
    .admin-card-top { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; }
    .admin-icon { width:54px; height:54px; border-radius:16px; display:inline-flex; align-items:center; justify-content:center; font-size:24px; background:rgba(37,99,235,.12); }
    .admin-module-card h3 { margin:14px 0 8px; font-size:18px; font-weight:700; color:#1e2432; }
    .admin-module-card p { color:#64748b; font-size:14px; line-height:1.6; flex:1; margin:0; }
    .admin-open { color:#1d4ed8; font-size:12px; font-weight:700; margin-top:18px; }
    @media (max-width:1199px) { .admin-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @media (max-width:767px) { .admin-grid { grid-template-columns:1fr; } .admin-hero { padding:20px; } }
</style>
<div class="admin-shell">
    <div class="admin-hero">
        <span class="admin-pill">🗂️ Administration</span>
        <h1>Gestion administrative</h1>
        <p>Centralisez les visiteurs, appels, courriers, réunions et documents de votre entreprise.</p>
    </div>
    <div class="admin-grid">
        @foreach($modules as $key => $module)
            <a href="{{ route('admin.administration.module', $key) }}" class="admin-module-card">
                <div class="admin-card-top"><span class="badge badge-light-primary">Module support</span><span class="text-success fw-bold fs-8">Disponible</span></div>
                <div class="admin-icon">{{ ['visiteurs'=>'👥','appels'=>'☎️','courriers'=>'✉️','reunions'=>'📅','documents'=>'📁'][$key] }}</div>
                <h3>{{ $module['title'] }}</h3>
                <p>{{ $module['description'] }}</p>
                <span class="admin-open">Ouvrir le module&nbsp; →</span>
            </a>
        @endforeach
    </div>
</div>
@endsection
