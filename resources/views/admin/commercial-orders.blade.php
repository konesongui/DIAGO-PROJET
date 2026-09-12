@extends('admin.layout')

@section('content')
@php
    // État lisible d'une commande, déduit de ce qui existe vraiment : sa
    // livraison, puis sa facture et son règlement.
    $stateOf = function ($order) {
        $invoice = $order->delivery?->invoice;
        if (! $invoice) {
            return $order->delivery?->status === 'validated' ? 'delivered' : 'to_deliver';
        }

        return (float) $invoice->paid_amount >= (float) $invoice->amount - (float) $invoice->credited_amount ? 'paid' : 'invoiced';
    };
    $states = [
        'to_deliver' => ['À livrer', 'warning', 'bi-truck', 'À livrer'],
        'delivered' => ['Livrée, à facturer', 'danger', 'bi-exclamation-triangle', 'À facturer'],
        'invoiced' => ['Facturée', 'neutral', 'bi-receipt', 'Facturées'],
        'paid' => ['Facturée et payée', 'success', 'bi-check-circle', 'Payées'],
    ];
    $byState = $orders->groupBy($stateOf);
    if ($byState->get('delivered', collect())->isEmpty()) {
        unset($states['delivered']);
    }
    $toDeliver = $byState->get('to_deliver', collect());
    $stats = [
        ['Commandes', $orders->count(), 'bi-basket', 'blue', 'issues des devis validés'],
        ['Montant commandé', money((float) $orders->sum('total_ttc')), 'bi-cash-stack', 'purple', 'TTC, toutes commandes'],
        ['À livrer', money((float) $toDeliver->sum('total_ttc')), 'bi-truck', 'orange', $toDeliver->count() . ' commande(s) en attente'],
        ['Facturées', $byState->get('invoiced', collect())->count() + $byState->get('paid', collect())->count(), 'bi-receipt', 'green',
            'livrées et facturées, dont ' . $byState->get('paid', collect())->count() . ' payée(s)'],
    ];
    // Détail de chaque commande, lu par la fenêtre « Voir ».
    $details = $orders->mapWithKeys(fn ($order) => [$order->id => [
        'reference' => $order->reference ?: 'Commande n° ' . $order->id,
        'client' => $order->client_name ?: 'Client non renseigné',
        'date' => $order->created_at?->format('d/m/Y') ?? '—',
        'customer_code' => $order->customer_order_code ?: '—',
        'quote' => $order->quote?->reference ?: ($order->quote_id ? 'Devis n° ' . $order->quote_id : '—'),
        'subject' => $order->quote?->subject ?: '—',
        'seller' => $order->creator?->name ?: 'Utilisateur supprimé',
        'ht' => money((float) ($order->total_ht ?? 0)),
        'tax' => money((float) ($order->tax_amount ?? 0)),
        'ttc' => money((float) $order->total_ttc),
        'lines' => collect($order->lines ?? [])->map(fn ($line) => [
            'name' => $line['item_name'] ?? $line['designation'] ?? 'Article',
            'quantity' => rtrim(rtrim(number_format((float) ($line['quantity'] ?? 0), 3, ',', ' '), '0'), ','),
            'unit' => $line['unit'] ?? '',
            'price' => money((float) ($line['unit_price'] ?? 0)),
            'total' => money((float) ($line['quantity'] ?? 0) * (float) ($line['unit_price'] ?? 0)),
        ])->values(),
    ]]);
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" :subtitle="$subtitle" :back="route('admin.commercial')" back-label="Commercial">
        <x-slot:actions>
            <a href="{{ route('admin.commercial.module', 'devis') }}" class="dg-btn dg-btn--outline"><i class="bi bi-file-earmark-text"></i>Voir les devis</a>
            <a href="{{ route('admin.commercial.module', 'livraisons') }}" class="dg-btn dg-btn--outline"><i class="bi bi-truck"></i>Voir les livraisons</a>
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
            <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-blue"><i class="bi bi-basket"></i></span>Bons de commande</h2>
            <label class="dg-search" style="max-width:280px">
                <i class="bi bi-search" aria-hidden="true"></i>
                <input id="orderSearch" type="search" placeholder="N° BC, client, devis…" aria-label="Rechercher une commande">
            </label>
        </div>

        @if($orders->isNotEmpty())
            <div class="dg-tabs mb-4" role="group" aria-label="Filtrer par état">
                <button type="button" class="dg-tab is-active" data-state-filter="" aria-pressed="true">Toutes ({{ $orders->count() }})</button>
                @foreach($states as $value => [, , , $tabLabel])
                    <button type="button" class="dg-tab" data-state-filter="{{ $value }}" aria-pressed="false">{{ $tabLabel }} ({{ $byState->get($value, collect())->count() }})</button>
                @endforeach
            </div>
        @endif

        <div class="table-responsive">
            <table class="table align-middle mb-0 orders-table" id="ordersTable">
                <thead>
                    <tr>
                        <th>Commande</th>
                        <th>Client</th>
                        <th>Origine</th>
                        <th class="text-end">Montant TTC</th>
                        <th>État</th>
                        <th class="text-end"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($orders as $order)
                    @php
                        $state = $stateOf($order);
                        [$stateLabel, $stateTone, $stateIcon] = $states[$state] ?? ['Livrée, à facturer', 'danger', 'bi-exclamation-triangle'];
                        $invoice = $order->delivery?->invoice;
                        $lines = collect($order->lines ?? []);
                    @endphp
                    <tr data-state="{{ $state }}">
                        <td class="text-nowrap">
                            <span class="d-block fw-semibold">{{ $order->reference ?: 'Commande n° ' . $order->id }}</span>
                            <span class="d-block dg-muted" style="font-size:12.5px">{{ $order->created_at?->format('d/m/Y') }}{{ $order->creator ? ' · ' . $order->creator->name : '' }}</span>
                        </td>
                        <td>
                            <span class="d-block fw-semibold">{{ $order->client_name ?: 'Client non renseigné' }}</span>
                            <span class="d-block dg-muted" style="font-size:12.5px">{{ $lines->count() }} ligne(s){{ $order->customer_order_code ? ' · réf. client ' . $order->customer_order_code : '' }}</span>
                        </td>
                        <td>
                            <span class="d-block">{{ $order->quote?->reference ?: ($order->quote_id ? 'Devis n° ' . $order->quote_id : '—') }}</span>
                            <span class="d-block dg-muted text-truncate" style="font-size:12.5px;max-width:210px">{{ $order->quote?->subject ?: 'Sans objet' }}</span>
                        </td>
                        <td class="text-end">
                            <span class="d-block dg-cell-num">{{ money((float) $order->total_ttc) }}</span>
                            <span class="d-block dg-muted text-nowrap" style="font-size:12.5px">HT : {{ money((float) ($order->total_ht ?? 0)) }}</span>
                        </td>
                        <td><span class="dg-badge dg-badge--{{ $stateTone }}"><i class="bi {{ $stateIcon }}"></i>{{ $stateLabel }}</span></td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light action-menu-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions" aria-label="Actions pour la commande {{ $order->reference ?: $order->id }}"></button>
                                <ul class="dropdown-menu dropdown-menu-end dg-dropdown">
                                    <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#orderModal" data-order="{{ $order->id }}"><i class="bi bi-eye"></i>Voir le détail</button></li>
                                    <li><a class="dropdown-item" target="_blank" href="{{ route('admin.commercial.orders.print', $order) }}"><i class="bi bi-printer"></i>Imprimer</a></li>
                                    @if($order->quote)
                                        <li><a class="dropdown-item" target="_blank" href="{{ route('admin.commercial.quotes.print', $order->quote) }}"><i class="bi bi-file-earmark-text"></i>Devis d’origine</a></li>
                                    @endif
                                    <li><hr class="dropdown-divider"></li>
                                    @if($state === 'to_deliver' || $state === 'delivered')
                                        <li><a class="dropdown-item" href="{{ route('admin.commercial.module', 'livraisons') }}"><i class="bi bi-truck"></i>{{ $state === 'to_deliver' ? 'Valider la livraison' : 'Terminer la livraison' }}</a></li>
                                    @endif
                                    @if($invoice)
                                        <li><a class="dropdown-item" target="_blank" href="{{ route('admin.commercial.invoices.print', $invoice) }}"><i class="bi bi-receipt"></i>Facture N° {{ $invoice->id }}</a></li>
                                    @endif
                                </ul>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-5">
                            <div class="dg-chart-empty" style="min-height:220px">
                                <span class="dg-tile dg-tone-blue"><i class="bi bi-basket"></i></span>
                                <div><strong>Aucun bon de commande</strong>Un bon de commande naît de la validation d’un devis : il porte les articles commandés jusqu’à la livraison et à la facture.</div>
                                <a href="{{ route('admin.commercial.module', 'devis') }}" class="dg-btn dg-btn--primary dg-btn--sm"><i class="bi bi-file-earmark-text"></i>Voir les devis</a>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Hors du tableau : une ligne fusionnée dans le tbody empêcherait DataTables de s'initialiser. --}}
        <div class="dg-chart-empty mt-4 d-none" id="ordersNoResult" style="min-height:160px">
            <span class="dg-tile dg-tone-blue"><i class="bi bi-search"></i></span>
            <div><strong>Aucune commande ne correspond à vos filtres</strong>Cherchez par numéro, client, référence du client ou devis.</div>
        </div>
        <p class="dg-muted mt-3 mb-0" style="font-size:12.5px"><i class="bi bi-info-circle me-1"></i>Une commande est créée à la validation d’un devis, avec le numéro de commande du client. Elle n’est pas modifiable : le devis fait foi.</p>
    </div>

    {{-- Détail d'une commande. --}}
    <div class="modal fade dg-tone-blue" id="orderModal" tabindex="-1" aria-labelledby="orderModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="orderModalTitle"><i class="bi bi-basket me-2"></i><span data-order-field="reference"></span></h5>
                        <div class="text-muted" style="font-size:13px"><span data-order-field="client"></span> · <span data-order-field="date"></span></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <dl class="order-info mb-4">
                        <dt>Devis d’origine</dt><dd data-order-field="quote"></dd>
                        <dt>Objet</dt><dd data-order-field="subject"></dd>
                        <dt>Référence du client</dt><dd data-order-field="customer_code"></dd>
                        <dt>Commercial</dt><dd data-order-field="seller"></dd>
                    </dl>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 no-export no-column-sort">
                            <thead><tr><th>Article ou prestation</th><th class="text-end">Quantité</th><th class="text-end">Prix unitaire</th><th class="text-end">Total</th></tr></thead>
                            <tbody id="orderLines"></tbody>
                            <tfoot>
                                <tr><td colspan="3">Total HT</td><td class="text-end" data-order-field="ht"></td></tr>
                                <tr><td colspan="3">TVA</td><td class="text-end" data-order-field="tax"></td></tr>
                                <tr class="order-total"><td colspan="3">Total TTC</td><td class="text-end" data-order-field="ttc"></td></tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <a class="dg-btn dg-btn--outline" id="orderPrintLink" target="_blank" href="#"><i class="bi bi-printer"></i>Imprimer</a>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="application/json" id="orderDetails">@json($details)</script>
<style>
    .orders-table { min-width: 900px; }
    .dg-scope .orders-table > thead > tr > th, .dg-scope .orders-table > tbody > tr > td { padding-left: 10px !important; padding-right: 10px !important; }
    .order-info { display: grid; grid-template-columns: max-content 1fr; gap: 6px 18px; margin: 0; font-size: 14px; }
    .order-info dt { font-weight: 500; color: var(--dg-muted); }
    .order-info dd { margin: 0; }
    .dg-scope #orderModal tfoot td { font-weight: 600; }
    .dg-scope #orderModal tfoot .order-total td { border-top: 2px solid var(--dg-tone); color: var(--dg-tone); font-size: 15px; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Recherche et filtre par état.
    const rows = Array.from(document.querySelectorAll('#ordersTable tbody tr[data-state]'));
    const search = document.getElementById('orderSearch');
    const tabs = Array.from(document.querySelectorAll('[data-state-filter]'));
    const noResult = document.getElementById('ordersNoResult');
    let state = '';
    const filterOrders = function () {
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
            filterOrders();
        });
    });
    search.addEventListener('input', filterOrders);

    // Détail : lignes commandées et totaux.
    const details = JSON.parse(document.getElementById('orderDetails').textContent || '{}');
    const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
    const printLink = document.getElementById('orderPrintLink');
    document.getElementById('orderModal').addEventListener('show.bs.modal', function (event) {
        const id = event.relatedTarget?.dataset.order;
        const order = details[id];
        if (!order) return;
        this.querySelectorAll('[data-order-field]').forEach(field => { field.textContent = order[field.dataset.orderField] ?? ''; });
        printLink.href = @json(route('admin.commercial.orders.print', ['order' => '__ID__'])).replace('__ID__', id);
        document.getElementById('orderLines').innerHTML = order.lines.length
            ? order.lines.map(line => '<tr><td>' + escape(line.name) + '</td>'
                + '<td class="text-end dg-cell-num">' + escape(line.quantity) + ' ' + escape(line.unit) + '</td>'
                + '<td class="text-end dg-cell-num">' + escape(line.price) + '</td>'
                + '<td class="text-end dg-cell-num fw-semibold">' + escape(line.total) + '</td></tr>').join('')
            : '<tr><td colspan="4" class="dg-muted">Aucune ligne sur cette commande.</td></tr>';
    });
});
</script>
@endsection
