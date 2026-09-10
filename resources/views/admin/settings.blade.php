@extends('admin.layout')

@section('content')
<style>
    .settings-shell .settings-hero,.settings-shell .settings-card{border:1px solid #edf2f7;border-radius:18px;background:#fff;box-shadow:0 12px 30px rgba(15,23,42,.04)}
    .settings-shell .settings-hero{padding:24px 28px;margin-bottom:20px}
    .settings-shell .settings-card{height:100%;transition:transform .2s,box-shadow .2s}
    .settings-shell .settings-card:hover{transform:translateY(-2px);box-shadow:0 16px 34px rgba(15,23,42,.08)}
    .settings-shell .settings-icon{width:54px;height:54px;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;background:#eef4ff}
    .settings-shell .settings-category{font-size:11px;letter-spacing:.08em;font-weight:800;color:#70809a;text-transform:uppercase}
</style>
<div class="settings-shell">
    <div class="settings-hero d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div><div class="settings-category">Configuration</div><h2 class="fs-2 fw-bold text-dark mb-1">Paramètres</h2><p class="text-muted mb-0">Configurez votre espace Diagoma ERP.</p></div>
        <span class="badge badge-light-primary px-4 py-3">Paramètres de l'entreprise</span>
    </div>
<div class="row g-4">
    @foreach($modules as $module)
        @php
            $parameters = $module['route_parameters'] ?? [];
            $target = route($module['route'], $parameters);
        @endphp
        <div class="col-xl-3 col-md-6">
            <div class="settings-card h-100">
                <div class="card-body p-4 d-flex flex-column">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <span class="settings-category">{{ $module['category'] }}</span>
                        <span class="settings-icon"><i class="bi {{ $module['icon'] }}"></i></span>
                    </div>
                    <h3 class="h5 fw-bold text-dark mb-2">{{ $module['title'] }}</h3>
                    <p class="text-muted mb-4 flex-grow-1">{{ $module['description'] }}</p>
                    <a href="{{ $target }}" class="btn btn-primary w-100">
                        {{ $module['route_label'] }} <span class="ms-2">→</span>
                    </a>
                </div>
            </div>
        </div>
    @endforeach
</div>
</div>
@endsection
