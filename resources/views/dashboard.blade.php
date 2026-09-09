@extends('admin.layout')

@section('content')
<div class="row g-5 g-xl-8">
    <div class="col-xl-3 col-sm-6">
        <div class="card card-xl-stretch mb-xl-8 shadow-sm border-0">
            <div class="card-body d-flex flex-column p-6">
                <div class="d-flex align-items-center justify-content-between">
                    <span class="text-muted fw-semibold fs-6">Établissements</span>
                    <span class="badge badge-light-success">+8%</span>
                </div>
                <div class="mt-5 d-flex align-items-center">
                    <span class="fs-2hx fw-bold text-dark">12</span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="card card-xl-stretch mb-xl-8 shadow-sm border-0">
            <div class="card-body d-flex flex-column p-6">
                <div class="d-flex align-items-center justify-content-between">
                    <span class="text-muted fw-semibold fs-6">Utilisateurs</span>
                    <span class="badge badge-light-primary">+12%</span>
                </div>
                <div class="mt-5 d-flex align-items-center">
                    <span class="fs-2hx fw-bold text-dark">368</span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="card card-xl-stretch mb-xl-8 shadow-sm border-0">
            <div class="card-body d-flex flex-column p-6">
                <div class="d-flex align-items-center justify-content-between">
                    <span class="text-muted fw-semibold fs-6">Revenus</span>
                    <span class="badge badge-light-success">+15%</span>
                </div>
                <div class="mt-5 d-flex align-items-center">
                    <span class="fs-2hx fw-bold text-dark">1 248 000</span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="card card-xl-stretch mb-xl-8 shadow-sm border-0">
            <div class="card-body d-flex flex-column p-6">
                <div class="d-flex align-items-center justify-content-between">
                    <span class="text-muted fw-semibold fs-6">Demandes</span>
                    <span class="badge badge-light-warning">-3%</span>
                </div>
                <div class="mt-5 d-flex align-items-center">
                    <span class="fs-2hx fw-bold text-dark">42</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-5 g-xl-8 mt-2">
    <div class="col-xl-8">
        <div class="card shadow-sm border-0">
            <div class="card-header border-0 pt-5 pb-0">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold text-dark">Activité récente</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Synthèse de la plateforme</span>
                </h3>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <div class="timeline-item align-items-start">
                        <div class="timeline-line w-40px"></div>
                        <div class="timeline-icon symbol symbol-40px me-4"><span class="symbol-label bg-primary text-white">N</span></div>
                        <div class="timeline-content">
                            <div class="fw-bold text-dark">Nouvelle demande de démonstration</div>
                            <div class="text-muted fs-7">Société A • Il y a 25 minutes</div>
                        </div>
                    </div>
                    <div class="timeline-item align-items-start mt-5">
                        <div class="timeline-line w-40px"></div>
                        <div class="timeline-icon symbol symbol-40px me-4"><span class="symbol-label bg-success text-white">P</span></div>
                        <div class="timeline-content">
                            <div class="fw-bold text-dark">Paiement validé</div>
                            <div class="text-muted fs-7">Mois de septembre • Il y a 1 heure</div>
                        </div>
                    </div>
                    <div class="timeline-item align-items-start mt-5">
                        <div class="timeline-line w-40px"></div>
                        <div class="timeline-icon symbol symbol-40px me-4"><span class="symbol-label bg-warning text-white">R</span></div>
                        <div class="timeline-content">
                            <div class="fw-bold text-dark">Affectation RH</div>
                            <div class="text-muted fs-7">Nouveau personnel • Aujourd’hui</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card shadow-sm border-0">
            <div class="card-header border-0 pt-5 pb-0">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold text-dark">Performance</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Système et sécurité</span>
                </h3>
            </div>
            <div class="card-body">
                <div class="mb-6">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted fw-semibold fs-7">Disponibilité</span>
                        <span class="fw-bold text-dark">96%</span>
                    </div>
                    <div class="progress h-8px"><div class="progress-bar bg-success" style="width:96%"></div></div>
                </div>
                <div class="mb-6">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted fw-semibold fs-7">Performance</span>
                        <span class="fw-bold text-dark">88%</span>
                    </div>
                    <div class="progress h-8px"><div class="progress-bar bg-primary" style="width:88%"></div></div>
                </div>
                <div class="mb-0">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted fw-semibold fs-7">Sécurité</span>
                        <span class="fw-bold text-dark">92%</span>
                    </div>
                    <div class="progress h-8px"><div class="progress-bar bg-warning" style="width:92%"></div></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
