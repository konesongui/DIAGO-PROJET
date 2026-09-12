@extends('admin.layout')

@section('content')
@php
    $emptyActivity = ['purchases' => 0.0, 'invoices' => [], 'last' => null];
    $activityOf = fn ($supplier) => $activity[$supplier->id] ?? $emptyActivity;
    $matchedCount = collect($activity)->sum(fn ($row) => count($row['invoices']));
    $stats = [
        ['Fournisseurs', $suppliers->count(), 'bi-truck', 'teal', 'dans le carnet'],
        ['Achats cumulés', money((float) collect($activity)->sum('purchases')), 'bi-cart-check', 'orange', 'factures fournisseurs, TTC'],
        ['Factures rattachées', $matchedCount, 'bi-files', 'blue', 'reconnues par le NCC du fournisseur'],
        ['Factures hors carnet', $unmatchedInvoices, 'bi-question-circle', 'purple', 'NCC absent du carnet ou non lu'],
    ];
    // Fiche de chaque fournisseur, lue par la fenêtre « Voir ».
    $fiches = $suppliers->mapWithKeys(fn ($supplier) => [$supplier->id => [
        'name' => $supplier->name, 'responsible' => $supplier->responsible_name, 'phone' => $supplier->phone, 'email' => $supplier->email,
        'address' => $supplier->address, 'ncc' => $supplier->tax_id,
        'purchases' => money($activityOf($supplier)['purchases']), 'count' => count($activityOf($supplier)['invoices']),
        'invoices' => collect($activityOf($supplier)['invoices'])->map(fn ($i) => [
            'number' => $i['number'] ?: 'à vérifier', 'date' => $i['date'] ? \Carbon\Carbon::parse($i['date'])->format('d/m/Y') : '—',
            'amount' => $i['amount'] !== null ? money($i['amount']) : 'À vérifier',
            'status' => $i['verified'] ? 'Importée' : 'À vérifier', 'tone' => $i['verified'] ? 'success' : 'warning',
            'fne' => $i['fne'], 'url' => $i['url'],
        ])->values(),
    ]]);
    // Après une erreur de saisie, la fenêtre se rouvre sur le formulaire soumis (création ou modification).
    $editedSupplierId = old('supplier_id');
    $formHasErrors = $errors->any() && old('_supplier_form');
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" :subtitle="$subtitle" :back="route('admin.commercial')" back-label="Commercial">
        <x-slot:actions>
            <button type="button" class="dg-btn dg-btn--primary" data-bs-toggle="modal" data-bs-target="#supplierModal"><i class="bi bi-plus-lg"></i>Nouveau fournisseur</button>
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
            <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-teal"><i class="bi bi-truck"></i></span>Carnet des fournisseurs</h2>
            <label class="dg-search" style="max-width:320px">
                <i class="bi bi-search" aria-hidden="true"></i>
                <input type="search" id="supplierSearch" placeholder="Nom, NCC, téléphone…" aria-label="Rechercher un fournisseur">
            </label>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0 suppliers-table" id="suppliersTable">
                <thead>
                    <tr>
                        <th>Fournisseur</th>
                        <th>Contact</th>
                        <th class="text-end">Achats cumulés</th>
                        <th class="text-center">Factures</th>
                        <th>Dernière facture</th>
                        <th class="text-end"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($suppliers as $supplier)
                    @php
                        $supplierActivity = $activityOf($supplier);
                        // Préparé ici : Blade découpe les arguments de @json sur les virgules.
                        $supplierData = $supplier->only(['id', 'name', 'responsible_name', 'tax_id', 'phone', 'email', 'address']);
                    @endphp
                    <tr data-supplier-row>
                        <td>
                            <span class="d-inline-flex align-items-center gap-3">
                                <span class="dg-tile dg-tile--sm dg-tone-{{ $supplierActivity['invoices'] ? 'teal' : 'navy' }}"><i class="bi bi-shop"></i></span>
                                <span>
                                    <span class="d-block fw-semibold">{{ $supplier->name }}</span>
                                    <span class="d-block dg-muted" style="font-size:12.5px">NCC / CNI {{ $supplier->tax_id }}</span>
                                </span>
                            </span>
                        </td>
                        <td>
                            <span class="d-block">{{ $supplier->email }}</span>
                            <span class="d-block dg-muted" style="font-size:12.5px">{{ collect([$supplier->phone, $supplier->responsible_name])->filter()->implode(' · ') }}</span>
                        </td>
                        <td class="text-end dg-cell-num">{{ money($supplierActivity['purchases']) }}</td>
                        <td class="text-center"><span class="dg-badge dg-badge--neutral">{{ count($supplierActivity['invoices']) }}</span></td>
                        <td class="text-nowrap">{{ $supplierActivity['last'] ? \Carbon\Carbon::parse($supplierActivity['last'])->format('d/m/Y') : '—' }}</td>
                        <td class="text-end">
                            <div class="d-inline-flex align-items-center gap-2">
                                <button type="button" class="dg-btn dg-btn--outline dg-btn--sm" data-bs-toggle="modal" data-bs-target="#supplierFicheModal" data-fiche="{{ $supplier->id }}">Voir</button>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light action-menu-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions" aria-label="Actions pour {{ $supplier->name }}"></button>
                                    <ul class="dropdown-menu dropdown-menu-end dg-dropdown">
                                        <li>
                                            <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#supplierModal"
                                                data-action="{{ route('admin.commercial.suppliers.update', $supplier) }}"
                                                data-supplier='@json($supplierData)'><i class="bi bi-pencil"></i>Modifier</button>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form method="POST" action="{{ route('admin.commercial.suppliers.destroy', $supplier) }}" onsubmit="return confirm('Supprimer ce fournisseur ? Ses factures resteront dans la Comptabilité.')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="dropdown-item text-danger" type="submit"><i class="bi bi-trash"></i>Supprimer</button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-5">
                            <div class="dg-chart-empty" style="min-height:220px">
                                <span class="dg-tile dg-tone-teal"><i class="bi bi-truck"></i></span>
                                <div><strong>Aucun fournisseur enregistré</strong>Ajoutez vos fournisseurs avec leur NCC : leurs factures importées en Comptabilité leur seront rattachées.</div>
                                <button type="button" class="dg-btn dg-btn--primary dg-btn--sm" data-bs-toggle="modal" data-bs-target="#supplierModal"><i class="bi bi-plus-lg"></i>Nouveau fournisseur</button>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Hors du tableau : une ligne fusionnée dans le tbody empêcherait DataTables de s'initialiser. --}}
        <div class="dg-chart-empty mt-4 d-none" id="suppliersNoResult" style="min-height:160px">
            <span class="dg-tile dg-tone-teal"><i class="bi bi-search"></i></span>
            <div><strong>Aucun fournisseur ne correspond à votre recherche</strong>Cherchez par nom, NCC, téléphone ou e-mail.</div>
        </div>
    </div>

    {{-- Création et modification. --}}
    <div class="modal fade" id="supplierModal" tabindex="-1" aria-labelledby="supplierModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form method="POST" id="supplierForm"
                    action="{{ $editedSupplierId ? route('admin.commercial.suppliers.update', $editedSupplierId) : route('admin.commercial.suppliers.store') }}"
                    data-store-action="{{ route('admin.commercial.suppliers.store') }}">
                    @csrf
                    <input type="hidden" name="_supplier_form" value="1">
                    <input type="hidden" name="_method" value="PUT" id="supplierMethod" @disabled(! $editedSupplierId)>
                    <input type="hidden" name="supplier_id" value="{{ $editedSupplierId }}" id="supplierId" @disabled(! $editedSupplierId)>
                    <div class="modal-header">
                        <h5 class="modal-title" id="supplierModalTitle"><i class="bi bi-truck me-2"></i><span>{{ $editedSupplierId ? 'Modifier le fournisseur' : 'Nouveau fournisseur' }}</span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label" for="supplierName">Fournisseur <span class="text-danger">*</span></label><input id="supplierName" name="name" class="form-control" required maxlength="190" value="{{ old('name') }}" placeholder="Raison sociale"></div>
                            <div class="col-md-6"><label class="form-label" for="supplierResponsible">Nom du responsable <span class="text-danger">*</span></label><input id="supplierResponsible" name="responsible_name" class="form-control" required maxlength="190" value="{{ old('responsible_name') }}"></div>
                            <div class="col-md-4"><label class="form-label" for="supplierNcc">Compte contribuable / CNI <span class="text-danger">*</span></label><input id="supplierNcc" name="tax_id" class="form-control" required maxlength="100" value="{{ old('tax_id') }}"></div>
                            <div class="col-md-4"><label class="form-label" for="supplierPhone">Téléphone <span class="text-danger">*</span></label><input id="supplierPhone" name="phone" class="form-control" required maxlength="60" value="{{ old('phone') }}"></div>
                            <div class="col-md-4"><label class="form-label" for="supplierEmail">E-mail <span class="text-danger">*</span></label><input id="supplierEmail" type="email" name="email" class="form-control" required maxlength="190" value="{{ old('email') }}"></div>
                            <div class="col-12"><label class="form-label" for="supplierAddress">Adresse <span class="text-danger">*</span></label><textarea id="supplierAddress" name="address" class="form-control" rows="2" required maxlength="1000">{{ old('address') }}</textarea></div>
                        </div>
                        <div class="form-text mt-3">Le NCC sert à rattacher au fournisseur les factures importées en Comptabilité.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Fiche fournisseur : coordonnées et factures rattachées. --}}
    <div class="modal fade dg-tone-teal" id="supplierFicheModal" tabindex="-1" aria-labelledby="supplierFicheTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="supplierFicheTitle"><i class="bi bi-shop me-2"></i><span data-fiche-field="name"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6"><div class="supplier-fiche-stat"><span>Achats cumulés</span><strong data-fiche-field="purchases"></strong></div></div>
                        <div class="col-md-6"><div class="supplier-fiche-stat"><span>Factures rattachées</span><strong data-fiche-field="count"></strong></div></div>
                    </div>
                    <dl class="supplier-fiche-info mb-4">
                        <dt>Responsable</dt><dd data-fiche-field="responsible"></dd>
                        <dt>Téléphone</dt><dd data-fiche-field="phone"></dd>
                        <dt>E-mail</dt><dd data-fiche-field="email"></dd>
                        <dt>Adresse</dt><dd data-fiche-field="address"></dd>
                        <dt>NCC / CNI</dt><dd data-fiche-field="ncc"></dd>
                    </dl>
                    <h6 class="fw-semibold mb-2">Factures fournisseurs</h6>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 no-export no-column-sort">
                            <thead><tr><th>Facture</th><th>Date</th><th class="text-end">Total TTC</th><th>Statut</th><th></th></tr></thead>
                            <tbody id="supplierFicheInvoices"></tbody>
                        </table>
                    </div>
                    <p class="dg-muted mt-2 mb-0 d-none" id="supplierFicheNoInvoice" style="font-size:13px">Aucune facture importée ne porte le NCC de ce fournisseur.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="application/json" id="supplierFiches">@json($fiches)</script>
<style>
    .suppliers-table { min-width: 860px; }
    .suppliers-table td:first-child { min-width: 170px; }
    .dg-scope .suppliers-table > thead > tr > th,
    .dg-scope .suppliers-table > tbody > tr > td { padding-left: 12px !important; padding-right: 12px !important; }
    .supplier-fiche-stat { padding: 12px 14px; border: 1px solid var(--dg-border); border-radius: var(--dg-radius); background: var(--dg-surface); }
    .supplier-fiche-stat span { display: block; font-size: 12.5px; color: var(--dg-muted); }
    .supplier-fiche-stat strong { font-size: 17px; }
    .supplier-fiche-info { display: grid; grid-template-columns: max-content 1fr; gap: 6px 18px; margin: 0; font-size: 14px; }
    .supplier-fiche-info dt { font-weight: 500; color: var(--dg-muted); }
    .supplier-fiche-info dd { margin: 0; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Recherche dans la liste.
    const rows = Array.from(document.querySelectorAll('[data-supplier-row]'));
    const search = document.getElementById('supplierSearch');
    const noResult = document.getElementById('suppliersNoResult');
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
    const modal = document.getElementById('supplierModal');
    const form = document.getElementById('supplierForm');
    const method = document.getElementById('supplierMethod');
    const supplierId = document.getElementById('supplierId');
    const title = modal.querySelector('.modal-title span');
    const fields = ['name', 'responsible_name', 'tax_id', 'phone', 'email', 'address'];
    modal.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        if (!trigger) return;
        const supplier = trigger.dataset.supplier ? JSON.parse(trigger.dataset.supplier) : null;
        form.action = supplier ? trigger.dataset.action : form.dataset.storeAction;
        method.disabled = !supplier;
        supplierId.disabled = !supplier;
        supplierId.value = supplier ? supplier.id : '';
        title.textContent = supplier ? 'Modifier le fournisseur' : 'Nouveau fournisseur';
        fields.forEach(function (name) { form.elements[name].value = supplier ? (supplier[name] ?? '') : ''; });
    });
    @if($formHasErrors)
        window.addEventListener('load', () => new bootstrap.Modal(modal).show());
    @endif

    // Fiche fournisseur.
    const fiches = JSON.parse(document.getElementById('supplierFiches').textContent);
    const fiche = document.getElementById('supplierFicheModal');
    const tbody = document.getElementById('supplierFicheInvoices');
    fiche.addEventListener('show.bs.modal', function (event) {
        const data = fiches[event.relatedTarget?.dataset.fiche];
        if (!data) return;
        fiche.querySelectorAll('[data-fiche-field]').forEach(function (el) {
            const value = data[el.dataset.ficheField];
            el.textContent = value === null || value === '' || value === undefined ? '—' : value;
        });
        tbody.replaceChildren(...data.invoices.map(function (invoice) {
            const row = document.createElement('tr');
            [invoice.number, invoice.date, invoice.amount].forEach(function (text, index) {
                const cell = document.createElement('td');
                cell.textContent = text;
                if (index === 2) cell.className = 'text-end text-nowrap';
                row.appendChild(cell);
            });
            const status = document.createElement('td');
            status.innerHTML = '<span class="dg-badge dg-badge--' + invoice.tone + '"></span>' + (invoice.fne ? ' <span class="dg-badge dg-badge--neutral"><i class="bi bi-patch-check"></i>FNE</span>' : '');
            status.firstChild.textContent = invoice.status;
            row.appendChild(status);
            const open = document.createElement('td');
            open.className = 'text-end';
            open.innerHTML = '<a class="dg-icon-btn dg-icon-btn--sm" title="Ouvrir la facture" aria-label="Ouvrir la facture"><i class="bi bi-box-arrow-up-right"></i></a>';
            open.firstChild.href = invoice.url;
            row.appendChild(open);
            return row;
        }));
        document.getElementById('supplierFicheNoInvoice').classList.toggle('d-none', data.invoices.length > 0);
    });
});
</script>
@endsection
