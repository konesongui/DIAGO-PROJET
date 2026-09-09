@extends('admin.layout')
@section('content')
<style>
    .console-metric{border:0;border-radius:18px;box-shadow:0 12px 30px rgba(15,23,42,.06);height:100%}.console-metric .metric-icon{width:52px;height:52px;border-radius:15px;display:flex;align-items:center;justify-content:center;font-size:1.35rem}.console-metric .metric-value{font-size:1.75rem;font-weight:800;line-height:1}.console-alert{border-radius:18px;border:1px solid #edf2f7;background:#fff;box-shadow:0 12px 30px rgba(15,23,42,.04)}
</style>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div><div class="text-uppercase text-muted small fw-bold">Espace central</div><h1 class="h3 fw-bold mb-1">{{ $dashboardTitle ?? 'Dashboard Super Administrateur' }}</h1><p class="text-muted mb-0">Suivez les entreprises, les comptes et les abonnements depuis une seule vue.</p></div>
    <div class="d-flex gap-2">
        <a href="{{ route('console.pack-requests') }}" class="btn btn-light-primary px-4">Demandes de packs</a>
        <a href="{{ route('console.landing') }}" class="btn btn-light-primary px-4">Landing</a>
        <a href="{{ route('console.entreprises.create') }}" class="btn btn-primary px-4">+ Nouvelle entreprise</a>
    </div>
</div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="row g-4 mb-4">
    @foreach([['🏢',$entreprises->count(),'Entreprises','#e8f2ff'],['✅',$activeEntreprises,'Comptes actifs','#e8f8ef'],['⛔',$inactiveEntreprises,'Comptes désactivés','#fff0f0'],['⏳',$expiringEntreprises->count(),'Expirent sous 30 jours','#fff7df'],['⚠️',$expiredEntreprises->count(),'Abonnements expirés','#ffe8e8'],['👥',$activeUsers,'Utilisateurs actifs','#eeeaff'],['🚫',$inactiveUsers,'Utilisateurs désactivés','#f1f1f1'],['🔐',$superAdmins,'Super administrateurs','#e8f2ff'],['📈',$activationRate.'%','Taux d’activation','#e8f8ef']] as [$icon,$value,$label,$color])
    <div class="col-xl-3 col-md-6"><div class="console-metric bg-white p-4 d-flex align-items-center gap-3"><div class="metric-icon" style="background:{{ $color }}">{{ $icon }}</div><div><div class="metric-value">{{ $value }}</div><div class="text-muted mt-1">{{ $label }}</div></div></div></div>
    @endforeach
</div>
<div class="row g-4 mb-4">
    <div class="col-xl-6"><div class="console-alert p-4 h-100"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 fw-bold mb-0">Abonnements à surveiller</h2><span class="badge bg-warning-subtle text-warning">{{ $expiringEntreprises->count() }}</span></div>@forelse($expiringEntreprises as $entreprise)<div class="d-flex justify-content-between align-items-center border-bottom py-2"><span class="fw-semibold">{{ $entreprise->name }}</span><span class="text-warning">{{ \Carbon\Carbon::make(data_get($entreprise->settings, 'subscription_expires_at'))?->format('d/m/Y') }}</span></div>@empty<div class="text-muted">Aucun abonnement n’expire dans les 30 prochains jours.</div>@endforelse</div></div>
    <div class="col-xl-6"><div class="console-alert p-4 h-100"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 fw-bold mb-0">Comptes désactivés / expirés</h2><span class="badge bg-danger-subtle text-danger">{{ $inactiveEntreprises + $expiredEntreprises->count() }}</span></div>@forelse($entreprises->where('is_active', false)->merge($expiredEntreprises->where('is_active', true)) as $entreprise)<div class="d-flex justify-content-between align-items-center border-bottom py-2"><span class="fw-semibold">{{ $entreprise->name }}</span><span class="text-danger">{{ $entreprise->is_active ? 'Abonnement expiré' : 'Compte désactivé' }}</span></div>@empty<div class="text-muted">Aucun compte désactivé ou expiré.</div>@endforelse</div></div>
</div>
@endsection
