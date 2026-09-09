@extends('admin.layout')

@section('content')
<style>
    .commercial-detail-hero{background:linear-gradient(135deg,#0f766e 0%,#134e4a 100%);border-radius:22px;color:#fff;box-shadow:0 18px 32px rgba(15,118,110,.16)}.commercial-detail-hero h2{color:#fff}.commercial-detail-card{border:1px solid #edf1f5;border-radius:18px;box-shadow:0 10px 30px rgba(24,39,75,.04)}.commercial-detail-link{border-radius:10px;padding:9px 10px}.commercial-detail-link:hover{background:#f0fdfa}
</style>

<div class="row g-5">
    <div class="col-lg-8">
        <div class="card border-0 commercial-detail-hero">
            <div class="card-body p-8">
                <div class="d-flex align-items-center gap-4 mb-6">
                    <span class="symbol symbol-60px bg-light-{{ $module['color'] }} rounded-3">
                        <i class="ki-duotone {{ $module['icon'] }} fs-1 text-{{ $module['color'] }}"></i>
                    </span>
                    <div>
                        <div class="text-uppercase text-white-50 fs-8 fw-bold ls-1">Commercial</div>
                        <h2 class="fs-2 fw-bold text-dark mb-1">{{ $module['title'] }}</h2>
                        <p class="text-muted mb-0">{{ $module['description'] }}</p>
                    </div>
                </div>
                <div class="alert alert-light-primary d-flex align-items-center gap-3 mb-0">
                    <i class="ki-duotone ki-information-5 fs-2 text-primary"></i>
                    <div>Cette rubrique est reliée au module Commercial. Les écrans de gestion seront ajoutés progressivement en conservant les règles de séparation par entreprise.</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 commercial-detail-card">
            <div class="card-header border-0">
                <h3 class="card-title fs-4 fw-bold">Autres rubriques</h3>
            </div>
            <div class="card-body pt-0">
                @foreach($modules as $item)
                    <a href="{{ route('admin.commercial.module', $item['key']) }}" class="commercial-detail-link d-flex align-items-center gap-3 py-3 text-decoration-none {{ $item['key'] === $module['key'] ? 'text-primary fw-bold' : 'text-dark' }}">
                        <i class="ki-duotone {{ $item['icon'] }} fs-2"></i>
                        <span>{{ $item['title'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
