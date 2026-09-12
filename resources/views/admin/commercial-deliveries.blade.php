@extends('admin.layout')

@section('content')
@php
    $isServiceLine = fn ($line) => ($line['item_type'] ?? null) === 'service'
        || (! isset($line['item_type']) && $serviceNames->contains(mb_strtolower(trim((string) ($line['item_name'] ?? '')))));
    // État lisible de chaque livraison, d'après ce qui existe réellement en base.
    $stateOf = fn ($delivery) => $delivery->status !== 'validated' ? 'pending' : ($delivery->invoice ? 'invoiced' : 'partial');
    $states = [
        'pending' => ['À livrer', 'warning', 'bi-truck', 'À livrer'],
        'invoiced' => ['Livrée et facturée', 'success', 'bi-receipt', 'Facturées'],
        'partial' => ['Partielle, à facturer', 'danger', 'bi-exclamation-triangle', 'Partielles'],
    ];
    $byState = $deliveries->groupBy($stateOf);
    $toDeliver = $byState->get('pending', collect());
    // La livraison partielle n'existe plus : seules d'anciennes livraisons peuvent encore l'être.
    $partialCount = $byState->get('partial', collect())->count();
    if (! $partialCount) {
        unset($states['partial']);
    }
    $articlesToShip = $toDeliver->sum(fn ($d) => collect($d->lines ?? [])->reject($isServiceLine)->count());
    $stats = [
        ['Livraisons', $deliveries->count(), 'bi-truck', 'blue', 'issues des devis validés'],
        ['À livrer', money((float) $toDeliver->sum(fn ($d) => (float) $d->order?->total_ttc)), 'bi-box-seam', 'orange', $toDeliver->count() . ' livraison(s), TTC'],
        ['Facturées', $byState->get('invoiced', collect())->count(), 'bi-receipt', 'green', 'livrées, facture créée'],
        $partialCount
            ? ['Partielles', $partialCount, 'bi-exclamation-triangle', 'red', 'anciennes livraisons à terminer']
            : ['Articles à sortir', $articlesToShip, 'bi-box-arrow-right', 'purple', 'lignes d’article des livraisons à faire'],
    ];
    $validateDeliveryId = old('validate_delivery_id');
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" :subtitle="$subtitle" :back="route('admin.commercial')" back-label="Commercial">
        <x-slot:actions>
            <a href="{{ route('admin.commercial.module', 'devis') }}" class="dg-btn dg-btn--outline"><i class="bi bi-file-earmark-text"></i>Voir les devis</a>
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
            <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-blue"><i class="bi bi-truck"></i></span>Bons de livraison</h2>
            <label class="dg-search" style="max-width:280px">
                <i class="bi bi-search" aria-hidden="true"></i>
                <input id="deliverySearch" type="search" placeholder="Client, BC, devis…" aria-label="Rechercher une livraison">
            </label>
        </div>

        @if($deliveries->isNotEmpty())
            <div class="dg-tabs mb-4" role="group" aria-label="Filtrer par état">
                <button type="button" class="dg-tab is-active" data-state-filter="" aria-pressed="true">Toutes ({{ $deliveries->count() }})</button>
                @foreach($states as $value => [, , , $tabLabel])
                    <button type="button" class="dg-tab" data-state-filter="{{ $value }}" aria-pressed="false">{{ $tabLabel }} ({{ $byState->get($value, collect())->count() }})</button>
                @endforeach
            </div>
        @endif

        <div class="table-responsive">
            <table class="table align-middle mb-0 deliveries-table" id="deliveriesTable">
                <thead>
                    <tr>
                        <th>Livraison</th>
                        <th>Client</th>
                        <th>Contenu</th>
                        <th class="text-end">Montant TTC</th>
                        <th>État</th>
                        <th class="text-end"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($deliveries as $delivery)
                    @php
                        $state = $stateOf($delivery);
                        [$stateLabel, $stateTone, $stateIcon] = $states[$state];
                        $lines = collect($delivery->lines ?? []);
                        $serviceCount = $lines->filter($isServiceLine)->count();
                        $articleCount = $lines->count() - $serviceCount;
                        // Lignes de la fenêtre de validation. Préparées ici : Blade découpe les arguments de @json sur les virgules.
                        $modalLines = $lines->map(fn ($line) => [
                            'name' => $line['item_name'] ?? '—',
                            'quantity' => rtrim(rtrim(number_format((float) ($line['quantity'] ?? 0), 3, ',', ' '), '0'), ','),
                            'unit' => $line['unit'] ?? '',
                            'service' => $isServiceLine($line),
                        ])->values();
                    @endphp
                    <tr data-state="{{ $state }}">
                        <td class="text-nowrap">
                            <span class="d-block fw-semibold">Livraison n° {{ $delivery->id }}</span>
                            <span class="d-block dg-muted" style="font-size:12.5px">{{ $delivery->created_at?->format('d/m/Y') }}{{ $delivery->creator ? ' · ' . $delivery->creator->name : '' }}</span>
                        </td>
                        <td>
                            <span class="d-block fw-semibold">{{ $delivery->client_name }}</span>
                            <span class="d-block dg-muted" style="font-size:12.5px">{{ collect([$delivery->order?->customer_order_code ? 'BC ' . $delivery->order->customer_order_code : null, $delivery->order?->quote?->reference])->filter()->implode(' · ') ?: '—' }}</span>
                        </td>
                        <td>
                            <span class="d-block">{{ $lines->count() }} ligne(s)</span>
                            <span class="d-block dg-muted" style="font-size:12.5px">{{ collect([$articleCount ? $articleCount . ' article(s)' : null, $serviceCount ? $serviceCount . ' service(s)' : null])->filter()->implode(' · ') }}</span>
                        </td>
                        <td class="text-end dg-cell-num">{{ money((float) $delivery->order?->total_ttc) }}</td>
                        <td><span class="dg-badge dg-badge--{{ $stateTone }}"><i class="bi {{ $stateIcon }}"></i>{{ $stateLabel }}</span></td>
                        <td class="text-end">
                            @if($state === 'pending' || $state === 'partial')
                                <button type="button" class="dg-btn dg-btn--outline dg-btn--sm" data-bs-toggle="modal" data-bs-target="#validateDeliveryModal"
                                    data-mode="{{ $state }}"
                                    data-validate-delivery="{{ $delivery->id }}"
                                    data-action="{{ route('admin.commercial.deliveries.validate', $delivery) }}"
                                    data-label="Livraison n° {{ $delivery->id }} · {{ $delivery->client_name }}"
                                    data-amount="{{ money((float) $delivery->order?->total_ttc) }}"
                                    data-lines='@json($modalLines)'><i class="bi bi-check2-circle"></i>{{ $state === 'partial' ? 'Facturer' : 'Valider' }}</button>
                            @elseif($delivery->invoice)
                                <a class="dg-btn dg-btn--outline dg-btn--sm" target="_blank" href="{{ route('admin.commercial.invoices.print', $delivery->invoice) }}"><i class="bi bi-receipt"></i>Facture N° {{ $delivery->invoice->id }}</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-5">
                            <div class="dg-chart-empty" style="min-height:220px">
                                <span class="dg-tile dg-tone-blue"><i class="bi bi-truck"></i></span>
                                <div><strong>Aucune livraison</strong>Validez un devis avec le bon de commande du client : sa livraison apparaîtra ici.</div>
                                <a href="{{ route('admin.commercial.module', 'devis') }}" class="dg-btn dg-btn--outline dg-btn--sm"><i class="bi bi-file-earmark-text"></i>Voir les devis</a>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Hors du tableau : une ligne fusionnée dans le tbody empêcherait DataTables de s'initialiser. --}}
        <div class="dg-chart-empty mt-4 d-none" id="deliveriesNoResult" style="min-height:160px">
            <span class="dg-tile dg-tone-blue"><i class="bi bi-search"></i></span>
            <div><strong>Aucune livraison ne correspond à vos filtres</strong>Modifiez la recherche ou l’état.</div>
        </div>
    </div>

    {{-- Validation : complète (sortie du stock et facture) ou partielle. --}}
    <div class="modal fade" id="validateDeliveryModal" tabindex="-1" aria-labelledby="validateDeliveryTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form method="POST" id="validateDeliveryForm" action="{{ $validateDeliveryId ? route('admin.commercial.deliveries.validate', $validateDeliveryId) : '#' }}">
                    @csrf
                    <input type="hidden" name="validate_delivery_id" id="validateDeliveryId" value="{{ $validateDeliveryId }}">
                    <div class="modal-header">
                        <h5 class="modal-title" id="validateDeliveryTitle"><i class="bi bi-truck me-2"></i>Valider la livraison</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        <div class="delivery-summary mb-4">
                            <span class="dg-tile dg-tile--sm dg-tone-blue"><i class="bi bi-box-seam"></i></span>
                            <span class="flex-grow-1 fw-semibold" id="validateDeliveryLabel"></span>
                            <strong class="delivery-summary__amount" id="validateDeliveryAmount"></strong>
                        </div>
                        <div class="table-responsive mb-4">
                            <table class="table align-middle mb-0 no-export no-column-sort">
                                <thead><tr><th>Désignation</th><th>Type</th><th class="text-end">Quantité</th></tr></thead>
                                <tbody id="validateDeliveryLines"></tbody>
                            </table>
                        </div>
                        <input type="hidden" name="delivery_type" value="complete">
                        <p class="delivery-note mb-0" id="validateDeliveryNote" data-pending="Les articles sortent du stock, les services non ; la facture de vente est créée."
                            data-partial="Cette ancienne livraison partielle est déjà sortie du stock : la terminer crée seulement la facture de vente."></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Valider la livraison</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .deliveries-table { min-width: 860px; }
    .deliveries-table td:nth-child(2) { min-width: 170px; }
    .dg-scope .deliveries-table > thead > tr > th,
    .dg-scope .deliveries-table > tbody > tr > td { padding-left: 12px !important; padding-right: 12px !important; }
    .delivery-summary { display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-radius: var(--dg-radius); background: rgba(37, 99, 235, .06); border: 1px solid rgba(37, 99, 235, .2); }
    .delivery-summary__amount { font-size: 17px; color: #2563eb; white-space: nowrap; }
    .delivery-note { padding: 12px 14px; border-radius: var(--dg-radius); background: rgba(5, 150, 105, .06); border: 1px solid rgba(5, 150, 105, .22); font-size: 14px; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Filtres : recherche et état.
    const rows = Array.from(document.querySelectorAll('#deliveriesTable tbody tr[data-state]'));
    const search = document.getElementById('deliverySearch');
    const tabs = Array.from(document.querySelectorAll('[data-state-filter]'));
    const noResult = document.getElementById('deliveriesNoResult');
    let state = '';
    const filterDeliveries = function () {
        const term = search.value.trim().toLowerCase();
        let visible = 0;
        rows.forEach(function (row) {
            const show = (!term || row.textContent.toLowerCase().includes(term)) && (!state || row.dataset.state === state);
            row.style.display = show ? '' : 'none';
            visible += show ? 1 : 0;
        });
        noResult.classList.toggle('d-none', !rows.length || visible > 0);
    };
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            state = tab.dataset.stateFilter;
            tabs.forEach(function (item) {
                item.classList.toggle('is-active', item === tab);
                item.setAttribute('aria-pressed', item === tab ? 'true' : 'false');
            });
            filterDeliveries();
        });
    });
    search.addEventListener('input', filterDeliveries);

    // Fenêtre de validation unique, remplie à partir de la ligne choisie.
    const modal = document.getElementById('validateDeliveryModal');
    const tbody = document.getElementById('validateDeliveryLines');
    modal.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        if (!trigger || !trigger.dataset.validateDelivery) return;
        document.getElementById('validateDeliveryForm').action = trigger.dataset.action;
        document.getElementById('validateDeliveryId').value = trigger.dataset.validateDelivery;
        document.getElementById('validateDeliveryLabel').textContent = trigger.dataset.label;
        document.getElementById('validateDeliveryAmount').textContent = trigger.dataset.amount;
        const finishing = trigger.dataset.mode === 'partial';
        const note = document.getElementById('validateDeliveryNote');
        note.textContent = finishing ? note.dataset.partial : note.dataset.pending;
        document.getElementById('validateDeliveryTitle').lastChild.textContent = finishing ? 'Terminer et facturer' : 'Valider la livraison';
        modal.querySelector('.modal-footer .btn-primary').lastChild.textContent = finishing ? 'Créer la facture' : 'Valider la livraison';
        tbody.replaceChildren(...JSON.parse(trigger.dataset.lines).map(function (line) {
            const row = document.createElement('tr');
            const name = document.createElement('td');
            name.textContent = line.name;
            const type = document.createElement('td');
            type.innerHTML = line.service
                ? '<span class="dg-badge dg-badge--neutral">Service</span>'
                : '<span class="dg-badge dg-badge--warning"><i class="bi bi-box-seam"></i>' + (finishing ? 'Article, déjà sorti du stock' : 'Article, sort du stock') + '</span>';
            const quantity = document.createElement('td');
            quantity.className = 'text-end text-nowrap';
            quantity.textContent = line.quantity + (line.unit ? ' ' + line.unit : '');
            row.append(name, type, quantity);
            return row;
        }));
    });
});
</script>
@endsection
