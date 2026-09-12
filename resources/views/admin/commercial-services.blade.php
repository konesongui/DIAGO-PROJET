@extends('admin.layout')

@section('content')
@php
    $active = $services->where('is_active', true);
    $stats = [
        ['Services', $services->count(), 'bi-grid', 'blue', 'dans le catalogue'],
        ['Actifs', $active->count(), 'bi-check-circle', 'green', 'proposés sur les devis et en caisse'],
        ['Inactifs', $services->count() - $active->count(), 'bi-pause-circle', 'orange', 'masqués des nouvelles ventes'],
        ['Forfaits', $services->where('is_global', true)->where('is_active', true)->count(), 'bi-bookmark-star', 'purple', 'proposés sur la facture personnalisée'],
    ];
    // Après une erreur de saisie, la fenêtre se rouvre sur le formulaire soumis (création ou modification).
    $editedServiceId = old('service_id');
    $formHasErrors = $errors->any() && old('_service_form');
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" :subtitle="$subtitle" :back="route('admin.commercial')" back-label="Commercial">
        <x-slot:actions>
            <button type="button" class="dg-btn dg-btn--primary" data-bs-toggle="modal" data-bs-target="#serviceModal"><i class="bi bi-plus-lg"></i>Nouveau service</button>
        </x-slot:actions>
    </x-dg.page-header>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="dg-kpi-grid">
        @foreach($stats as [$label, $value, $icon, $color, $hint])
            <x-dg.kpi :label="$label" :value="$value" :icon="$icon" :color="$color" :hint="$hint" />
        @endforeach
    </div>

    <div class="dg-card dg-card--table">
        <div class="dg-card__header flex-wrap">
            <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-blue"><i class="bi bi-grid"></i></span>Catalogue des services</h2>
            <label class="dg-search" style="max-width:320px">
                <i class="bi bi-search" aria-hidden="true"></i>
                <input type="search" id="serviceSearch" placeholder="Nom, code, description…" aria-label="Rechercher un service">
            </label>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0 services-table" id="servicesTable">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Service</th>
                        <th>Unité</th>
                        <th class="text-end">Prix HT</th>
                        <th>Facture perso.</th>
                        <th>Disponibilité</th>
                        <th class="text-end"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($services as $service)
                    @php
                        // Préparé ici : Blade découpe les arguments de @json sur les virgules.
                        $serviceData = $service->only(['id', 'code', 'name', 'description', 'price', 'unit', 'is_active', 'is_global']);
                    @endphp
                    <tr data-service-row>
                        <td class="text-nowrap fw-semibold">{{ $service->code ?: '—' }}</td>
                        <td>
                            <span class="d-block fw-semibold">{{ $service->name }}</span>
                            @if($service->description)<span class="dg-muted service-description" style="font-size:12.5px">{{ $service->description }}</span>@endif
                        </td>
                        <td>{{ $service->unit ?: '—' }}</td>
                        <td class="text-end dg-cell-num">{{ money((float) $service->price) }}</td>
                        <td>
                            @if($service->is_global)
                                <span class="dg-badge dg-badge--neutral"><i class="bi bi-bookmark-star"></i>Forfait</span>
                            @else
                                <span class="dg-muted">—</span>
                            @endif
                        </td>
                        <td><span class="dg-badge dg-badge--{{ $service->is_active ? 'success' : 'warning' }}">{{ $service->is_active ? 'Actif' : 'Inactif' }}</span></td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light action-menu-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions" aria-label="Actions pour {{ $service->name }}"></button>
                                <ul class="dropdown-menu dropdown-menu-end dg-dropdown">
                                    <li>
                                        <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#serviceModal"
                                            data-action="{{ route('admin.commercial.services.update', $service) }}"
                                            data-service='@json($serviceData)'><i class="bi bi-pencil"></i>Modifier</button>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="POST" action="{{ route('admin.commercial.services.destroy', $service) }}" onsubmit="return confirm('Supprimer ce service ? Les devis et factures qui le contiennent ne changent pas.')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="dropdown-item text-danger" type="submit"><i class="bi bi-trash"></i>Supprimer</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-5">
                            <div class="dg-chart-empty" style="min-height:220px">
                                <span class="dg-tile dg-tone-blue"><i class="bi bi-grid"></i></span>
                                <div><strong>Aucun service enregistré</strong>Enregistrez vos prestations et leur prix : ils seront proposés sur les devis et au point de vente.</div>
                                <button type="button" class="dg-btn dg-btn--primary dg-btn--sm" data-bs-toggle="modal" data-bs-target="#serviceModal"><i class="bi bi-plus-lg"></i>Nouveau service</button>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Hors du tableau : une ligne fusionnée dans le tbody empêcherait DataTables de s'initialiser. --}}
        <div class="dg-chart-empty mt-4 d-none" id="servicesNoResult" style="min-height:160px">
            <span class="dg-tile dg-tone-blue"><i class="bi bi-search"></i></span>
            <div><strong>Aucun service ne correspond à votre recherche</strong>Cherchez par nom, code ou description.</div>
        </div>
    </div>

    {{-- Création et modification. --}}
    <div class="modal fade" id="serviceModal" tabindex="-1" aria-labelledby="serviceModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form method="POST" id="serviceForm"
                    action="{{ $editedServiceId ? route('admin.commercial.services.update', $editedServiceId) : route('admin.commercial.services.store') }}"
                    data-store-action="{{ route('admin.commercial.services.store') }}">
                    @csrf
                    <input type="hidden" name="_service_form" value="1">
                    <input type="hidden" name="_method" value="PUT" id="serviceMethod" @disabled(! $editedServiceId)>
                    <input type="hidden" name="service_id" value="{{ $editedServiceId }}" id="serviceId" @disabled(! $editedServiceId)>
                    <div class="modal-header">
                        <h5 class="modal-title" id="serviceModalTitle"><i class="bi bi-grid me-2"></i><span>{{ $editedServiceId ? 'Modifier le service' : 'Nouveau service' }}</span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12"><label class="form-label" for="serviceName">Nom du service <span class="text-danger">*</span></label><input id="serviceName" name="name" class="form-control" required maxlength="190" value="{{ old('name') }}" placeholder="Ex : Audit des comptes annuels"></div>
                            <div class="col-md-6"><label class="form-label" for="servicePrice">Prix unitaire HT <span class="text-danger">*</span></label><input id="servicePrice" type="number" name="price" class="form-control" required min="0" step="0.01" value="{{ old('price') }}"></div>
                            <div class="col-md-6"><label class="form-label" for="serviceUnit">Unité ou durée</label><input id="serviceUnit" name="unit" class="form-control" maxlength="50" value="{{ old('unit') }}" placeholder="Forfait, heure, jour…"></div>
                            <div class="col-12"><label class="form-label" for="serviceDescription">Description</label><textarea id="serviceDescription" name="description" class="form-control" rows="2" maxlength="2000">{{ old('description') }}</textarea></div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input type="hidden" name="is_global" value="0">
                                    <input class="form-check-input" type="checkbox" name="is_global" value="1" id="serviceGlobal" @checked(old('is_global'))>
                                    <label class="form-check-label" for="serviceGlobal">Proposé en forfait sur la facture personnalisée</label>
                                </div>
                            </div>
                            <div class="col-12 d-none" id="serviceActiveField">
                                <div class="form-check">
                                    <input type="hidden" name="is_active" value="0">
                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="serviceActive" checked>
                                    <label class="form-check-label" for="serviceActive">Service actif (proposé sur les nouvelles ventes)</label>
                                </div>
                            </div>
                            <div class="col-12 d-none" id="serviceCodeField">
                                <span class="dg-muted" style="font-size:13px">Code du forfait : <strong id="serviceCode"></strong> (fixe, les factures émises le référencent)</span>
                            </div>
                        </div>
                        <div class="form-text mt-3">Prix hors taxes : la TVA s’y ajoute sur les devis et les factures ; au point de vente, le prix proposé au client est calculé TTC.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .services-table { min-width: 860px; }
    .services-table td:nth-child(2) { min-width: 220px; }
    .dg-scope .services-table > thead > tr > th,
    .dg-scope .services-table > tbody > tr > td { padding-left: 12px !important; padding-right: 12px !important; }
    .service-description { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Recherche dans le catalogue.
    const rows = Array.from(document.querySelectorAll('[data-service-row]'));
    const search = document.getElementById('serviceSearch');
    const noResult = document.getElementById('servicesNoResult');
    search.addEventListener('input', function () {
        const term = search.value.trim().toLowerCase();
        let visible = 0;
        rows.forEach(function (row) {
            const show = !term || row.textContent.toLowerCase().includes(term);
            row.style.display = show ? '' : 'none';
            visible += show ? 1 : 0;
        });
        noResult.classList.toggle('d-none', !rows.length || visible > 0);
    });

    // Création et modification dans la même fenêtre.
    const modal = document.getElementById('serviceModal');
    const form = document.getElementById('serviceForm');
    const method = document.getElementById('serviceMethod');
    const serviceId = document.getElementById('serviceId');
    const title = modal.querySelector('.modal-title span');
    modal.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        if (!trigger) return;
        const service = trigger.dataset.service ? JSON.parse(trigger.dataset.service) : null;
        form.action = service ? trigger.dataset.action : form.dataset.storeAction;
        method.disabled = !service;
        serviceId.disabled = !service;
        serviceId.value = service ? service.id : '';
        title.textContent = service ? 'Modifier le service' : 'Nouveau service';
        ['name', 'price', 'unit', 'description'].forEach(function (name) { form.elements[name].value = service ? (service[name] ?? '') : ''; });
        document.getElementById('serviceGlobal').checked = !!(service && service.is_global);
        document.getElementById('serviceActive').checked = !service || !!service.is_active;
        // L'état actif ne se règle qu'en modification : un nouveau service est toujours actif.
        document.getElementById('serviceActiveField').classList.toggle('d-none', !service);
        document.getElementById('serviceCodeField').classList.toggle('d-none', !(service && service.code));
        document.getElementById('serviceCode').textContent = service?.code || '';
    });
    @if($formHasErrors)
        document.getElementById('serviceActiveField').classList.toggle('d-none', {{ $editedServiceId ? 'false' : 'true' }});
        document.getElementById('serviceActive').checked = {{ old('is_active', 1) ? 'true' : 'false' }};
        window.addEventListener('load', () => new bootstrap.Modal(modal).show());
    @endif
});
</script>
@endsection
