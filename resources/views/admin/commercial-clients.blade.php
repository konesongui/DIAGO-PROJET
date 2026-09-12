@extends('admin.layout')

@section('content')
@php
    $emptyActivity = ['purchases' => 0.0, 'due' => 0.0, 'last' => null, 'invoices' => [], 'pos_count' => 0];
    $activityOf = fn ($client) => $activity[$client->id] ?? $emptyActivity;
    $debtors = $clients->filter(fn ($client) => $activityOf($client)['due'] > 0);
    $withoutNcc = $clients->filter(fn ($client) => blank($client->tax_id));
    $stats = [
        ['Clients', $clients->count(), 'bi-people', 'indigo', 'dans le portefeuille'],
        ['Achats cumulés', money((float) collect($activity)->sum('purchases')), 'bi-bag-check', 'blue', 'factures de ventes et ventes comptoir'],
        ['Solde impayé', money((float) collect($activity)->sum('due')), 'bi-hourglass-split', 'red', $debtors->count() . ' client(s) débiteur(s)'],
        ['NCC manquant', $withoutNcc->count(), 'bi-exclamation-triangle', 'orange', 'certification FNE en B2C'],
    ];
    $statuses = [
        'paid' => ['Payée', 'success'], 'partially_paid' => ['Partielle', 'warning'],
        'unpaid' => ['Impayée', 'danger'], 'cancelled' => ['Annulée', 'neutral'],
    ];
    // Fiche de chaque client, lue par la fenêtre « Voir ».
    $fiches = $clients->mapWithKeys(fn ($client) => [$client->id => [
        'name' => $client->name, 'responsible' => $client->responsible_name, 'phone' => $client->phone, 'email' => $client->email,
        'city' => $client->city, 'address' => $client->address, 'ncc' => $client->tax_id, 'regime' => $client->tax_regime,
        'purchases' => money($activityOf($client)['purchases']), 'due' => money($activityOf($client)['due']),
        'dueRaw' => $activityOf($client)['due'], 'posCount' => $activityOf($client)['pos_count'],
        'invoices' => collect($activityOf($client)['invoices'])->map(fn ($i) => [
            'number' => 'N° ' . $i['id'], 'date' => \Carbon\Carbon::parse($i['date'])->format('d/m/Y'),
            'amount' => money($i['amount']), 'remaining' => $i['status'] === 'cancelled' ? '—' : money($i['remaining']),
            'status' => $statuses[$i['status']][0] ?? $i['status'], 'tone' => $statuses[$i['status']][1] ?? 'neutral', 'print' => $i['print'],
        ])->values(),
    ]]);
    // Après une erreur de saisie, la fenêtre se rouvre sur le formulaire soumis (création ou modification).
    $editedClientId = old('client_id');
    $formHasErrors = $errors->any() && old('_client_form');
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" :subtitle="$subtitle" :back="route('admin.commercial')" back-label="Commercial">
        <x-slot:actions>
            <button type="button" class="dg-btn dg-btn--primary" data-bs-toggle="modal" data-bs-target="#clientModal"><i class="bi bi-person-plus"></i>Nouveau client</button>
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
            <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-indigo"><i class="bi bi-person-lines-fill"></i></span>Portefeuille clients</h2>
            <label class="dg-search" style="max-width:320px">
                <i class="bi bi-search" aria-hidden="true"></i>
                <input type="search" id="clientSearch" placeholder="Nom, NCC, téléphone…" aria-label="Rechercher un client">
            </label>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0 clients-table" id="clientsTable">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Contact</th>
                        <th class="text-end">Achats cumulés</th>
                        <th class="text-end">Solde impayé</th>
                        <th>Dernier achat</th>
                        <th class="text-end"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($clients as $client)
                    @php
                        $clientActivity = $activityOf($client);
                        // Préparés ici : Blade découpe les arguments de @json sur les virgules.
                        $clientData = $client->only(['id', 'name', 'responsible_name', 'phone', 'email', 'city', 'tax_id', 'tax_regime', 'address']);
                        $deleteMessage = count($clientActivity['invoices'])
                            ? 'Supprimer ce client ? Ses devis et factures resteront, mais sans lien vers sa fiche.'
                            : 'Supprimer ce client ?';
                    @endphp
                    <tr data-client-row>
                        <td>
                            <span class="d-inline-flex align-items-center gap-3">
                                <span class="dg-tile dg-tile--sm dg-tone-{{ $clientActivity['due'] > 0 ? 'red' : ($clientActivity['purchases'] > 0 ? 'green' : 'indigo') }}"><i class="bi bi-building"></i></span>
                                <span>
                                    <span class="d-block fw-semibold">{{ $client->name }}</span>
                                    <span class="d-block dg-muted" style="font-size:12.5px">{{ $client->tax_id ? 'NCC ' . $client->tax_id : 'NCC non renseigné' }}{{ $client->city ? ' · ' . $client->city : '' }}</span>
                                </span>
                            </span>
                        </td>
                        <td>
                            <span class="d-block">{{ $client->email }}</span>
                            <span class="d-block dg-muted" style="font-size:12.5px">{{ collect([$client->phone, $client->responsible_name])->filter()->implode(' · ') }}</span>
                        </td>
                        <td class="text-end dg-cell-num">{{ money($clientActivity['purchases']) }}</td>
                        <td class="text-end dg-cell-num {{ $clientActivity['due'] > 0 ? 'dg-amount-negative' : 'dg-amount-positive' }}">{{ money($clientActivity['due']) }}</td>
                        <td class="text-nowrap">{{ $clientActivity['last'] ? \Carbon\Carbon::parse($clientActivity['last'])->format('d/m/Y') : '—' }}</td>
                        <td class="text-end">
                            <div class="d-inline-flex align-items-center gap-2">
                                <button type="button" class="dg-btn dg-btn--outline dg-btn--sm" data-bs-toggle="modal" data-bs-target="#clientFicheModal" data-fiche="{{ $client->id }}">Voir</button>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light action-menu-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions" aria-label="Actions pour {{ $client->name }}"></button>
                                    <ul class="dropdown-menu dropdown-menu-end dg-dropdown">
                                        <li>
                                            <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#clientModal"
                                                data-action="{{ route('admin.commercial.clients.update', $client) }}"
                                                data-client='@json($clientData)'><i class="bi bi-pencil"></i>Modifier</button>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form method="POST" action="{{ route('admin.commercial.clients.destroy', $client) }}"
                                                onsubmit='return confirm(@json($deleteMessage))'>
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
                                <span class="dg-tile dg-tone-indigo"><i class="bi bi-people"></i></span>
                                <div><strong>Aucun client enregistré</strong>Ajoutez vos clients pour établir devis, factures et ventes comptoir à leur nom.</div>
                                <button type="button" class="dg-btn dg-btn--primary dg-btn--sm" data-bs-toggle="modal" data-bs-target="#clientModal"><i class="bi bi-person-plus"></i>Nouveau client</button>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Hors du tableau : une ligne fusionnée dans le tbody empêcherait DataTables de s'initialiser. --}}
        <div class="dg-chart-empty mt-4 d-none" id="clientsNoResult" style="min-height:160px">
            <span class="dg-tile dg-tone-indigo"><i class="bi bi-search"></i></span>
            <div><strong>Aucun client ne correspond à votre recherche</strong>Cherchez par nom, NCC, téléphone ou e-mail.</div>
        </div>
    </div>

    {{-- Création et modification. --}}
    <div class="modal fade" id="clientModal" tabindex="-1" aria-labelledby="clientModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form method="POST" id="clientForm"
                    action="{{ $editedClientId ? route('admin.commercial.clients.update', $editedClientId) : route('admin.commercial.clients.store') }}"
                    data-store-action="{{ route('admin.commercial.clients.store') }}">
                    @csrf
                    <input type="hidden" name="_client_form" value="1">
                    <input type="hidden" name="_method" value="PUT" id="clientMethod" @disabled(! $editedClientId)>
                    <input type="hidden" name="client_id" value="{{ $editedClientId }}" id="clientId" @disabled(! $editedClientId)>
                    <div class="modal-header">
                        <h5 class="modal-title" id="clientModalTitle"><i class="bi bi-person-plus me-2"></i><span>{{ $editedClientId ? 'Modifier le client' : 'Nouveau client' }}</span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label" for="clientName">Client <span class="text-danger">*</span></label><input id="clientName" name="name" class="form-control" required maxlength="190" value="{{ old('name') }}" placeholder="Raison sociale ou nom"></div>
                            <div class="col-md-6"><label class="form-label" for="clientResponsible">Nom du responsable</label><input id="clientResponsible" name="responsible_name" class="form-control" maxlength="190" value="{{ old('responsible_name') }}"></div>
                            <div class="col-md-6"><label class="form-label" for="clientPhone">Téléphone <span class="text-danger">*</span></label><input id="clientPhone" name="phone" class="form-control" required maxlength="60" value="{{ old('phone') }}"></div>
                            <div class="col-md-6"><label class="form-label" for="clientEmail">E-mail <span class="text-danger">*</span></label><input id="clientEmail" type="email" name="email" class="form-control" required maxlength="190" value="{{ old('email') }}"></div>
                            <div class="col-md-4"><label class="form-label" for="clientCity">Ville</label><input id="clientCity" name="city" class="form-control" maxlength="100" value="{{ old('city') }}"></div>
                            <div class="col-md-4"><label class="form-label" for="clientNcc">Compte contribuable (NCC)</label><input id="clientNcc" name="tax_id" class="form-control" maxlength="100" value="{{ old('tax_id') }}"></div>
                            <div class="col-md-4"><label class="form-label" for="clientRegime">Régime d'imposition</label><input id="clientRegime" name="tax_regime" class="form-control" maxlength="190" value="{{ old('tax_regime') }}"></div>
                            <div class="col-12"><label class="form-label" for="clientAddress">Adresse</label><textarea id="clientAddress" name="address" class="form-control" rows="2" maxlength="1000">{{ old('address') }}</textarea></div>
                        </div>
                        <div class="form-text mt-3">Le compte contribuable permet de certifier ses factures à la FNE en B2B ; sans lui, elles le sont en B2C.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Fiche client : coordonnées et factures, sans quitter la liste. --}}
    <div class="modal fade dg-tone-indigo" id="clientFicheModal" tabindex="-1" aria-labelledby="clientFicheTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="clientFicheTitle"><i class="bi bi-person-vcard me-2"></i><span data-fiche-field="name"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-4"><div class="client-fiche-stat"><span>Achats cumulés</span><strong data-fiche-field="purchases"></strong></div></div>
                        <div class="col-md-4"><div class="client-fiche-stat client-fiche-stat--due"><span>Solde impayé</span><strong data-fiche-field="due"></strong></div></div>
                        <div class="col-md-4"><div class="client-fiche-stat"><span>Ventes comptoir</span><strong data-fiche-field="posCount"></strong></div></div>
                    </div>
                    <dl class="client-fiche-info mb-4">
                        <dt>Responsable</dt><dd data-fiche-field="responsible"></dd>
                        <dt>Téléphone</dt><dd data-fiche-field="phone"></dd>
                        <dt>E-mail</dt><dd data-fiche-field="email"></dd>
                        <dt>Adresse</dt><dd data-fiche-field="address"></dd>
                        <dt>Compte contribuable</dt><dd data-fiche-field="ncc"></dd>
                        <dt>Régime d'imposition</dt><dd data-fiche-field="regime"></dd>
                    </dl>
                    <h6 class="fw-semibold mb-2">Factures de ventes</h6>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 no-export no-column-sort">
                            <thead><tr><th>Facture</th><th>Date</th><th class="text-end">Montant TTC</th><th class="text-end">Reste</th><th>Statut</th><th></th></tr></thead>
                            <tbody id="clientFicheInvoices"></tbody>
                        </table>
                    </div>
                    <p class="dg-muted mt-2 mb-0 d-none" id="clientFicheNoInvoice" style="font-size:13px">Aucune facture de vente pour ce client.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="application/json" id="clientFiches">@json($fiches)</script>
<style>
    .clients-table { min-width: 860px; }
    .clients-table td:first-child { min-width: 160px; }
    .dg-scope .clients-table > thead > tr > th,
    .dg-scope .clients-table > tbody > tr > td { padding-left: 12px !important; padding-right: 12px !important; }
    .client-fiche-stat { padding: 12px 14px; border: 1px solid var(--dg-border); border-radius: var(--dg-radius); background: var(--dg-surface); }
    .client-fiche-stat span { display: block; font-size: 12.5px; color: var(--dg-muted); }
    .client-fiche-stat strong { font-size: 17px; }
    .client-fiche-stat--due strong { color: #dc2626; }
    .client-fiche-info { display: grid; grid-template-columns: max-content 1fr; gap: 6px 18px; margin: 0; font-size: 14px; }
    .client-fiche-info dt { font-weight: 500; color: var(--dg-muted); }
    .client-fiche-info dd { margin: 0; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Recherche dans la liste.
    const rows = Array.from(document.querySelectorAll('[data-client-row]'));
    const search = document.getElementById('clientSearch');
    const noResult = document.getElementById('clientsNoResult');
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
    const modal = document.getElementById('clientModal');
    const form = document.getElementById('clientForm');
    const method = document.getElementById('clientMethod');
    const clientId = document.getElementById('clientId');
    const title = modal.querySelector('.modal-title span');
    const fields = ['name', 'responsible_name', 'phone', 'email', 'city', 'tax_id', 'tax_regime', 'address'];
    modal.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        if (!trigger) return;
        const client = trigger.dataset.client ? JSON.parse(trigger.dataset.client) : null;
        form.action = client ? trigger.dataset.action : form.dataset.storeAction;
        method.disabled = !client;
        clientId.disabled = !client;
        clientId.value = client ? client.id : '';
        title.textContent = client ? 'Modifier le client' : 'Nouveau client';
        fields.forEach(function (name) { form.elements[name].value = client ? (client[name] ?? '') : ''; });
    });
    @if($formHasErrors)
        window.addEventListener('load', () => new bootstrap.Modal(modal).show());
    @endif

    // Fiche client.
    const fiches = JSON.parse(document.getElementById('clientFiches').textContent);
    const fiche = document.getElementById('clientFicheModal');
    const tbody = document.getElementById('clientFicheInvoices');
    fiche.addEventListener('show.bs.modal', function (event) {
        const data = fiches[event.relatedTarget?.dataset.fiche];
        if (!data) return;
        fiche.querySelectorAll('[data-fiche-field]').forEach(function (el) {
            const value = data[el.dataset.ficheField];
            el.textContent = value === null || value === '' || value === undefined ? '—' : value;
        });
        fiche.querySelector('.client-fiche-stat--due strong').style.color = data.dueRaw > 0 ? '#dc2626' : '#059669';
        tbody.replaceChildren(...data.invoices.map(function (invoice) {
            const row = document.createElement('tr');
            [invoice.number, invoice.date, invoice.amount, invoice.remaining].forEach(function (text, index) {
                const cell = document.createElement('td');
                cell.textContent = text;
                if (index >= 2) cell.className = 'text-end text-nowrap';
                row.appendChild(cell);
            });
            const status = document.createElement('td');
            status.innerHTML = '<span class="dg-badge dg-badge--' + invoice.tone + '"></span>';
            status.firstChild.textContent = invoice.status;
            row.appendChild(status);
            const print = document.createElement('td');
            print.className = 'text-end';
            print.innerHTML = '<a class="dg-icon-btn dg-icon-btn--sm" target="_blank" title="Imprimer" aria-label="Imprimer"><i class="bi bi-printer"></i></a>';
            print.firstChild.href = invoice.print;
            row.appendChild(print);
            return row;
        }));
        document.getElementById('clientFicheNoInvoice').classList.toggle('d-none', data.invoices.length > 0);
    });
});
</script>
@endsection
