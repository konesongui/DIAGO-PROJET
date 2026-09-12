@extends('admin.layout')

@section('content')
@php
    $quantity = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, ',', ' '), '0'), ',');
    $last = $inventories->first();
    $stats = [
        ['Inventaires réalisés', $inventories->count(), 'bi-clipboard-check', 'purple', $last ? 'dernier le ' . $last->inventoried_at->format('d/m/Y') : 'aucun inventaire'],
        ['Articles à compter', $items->count(), 'bi-boxes', 'blue', $items->where('available', '>', 0)->count() . ' en stock'],
        ['Manquants constatés', money((float) $inventories->sum('shortage_value')), 'bi-dash-circle', 'red', 'sortis du stock, au prix d’achat'],
        ['Surplus constatés', money((float) $inventories->sum('surplus_value')), 'bi-plus-circle', 'green', 'entrés au dernier prix d’achat'],
    ];
    // Données de calcul de l'écart : lots du plus ancien au plus récent.
    $valuation = $items->mapWithKeys(fn ($item) => [$item['key'] => [
        'available' => $item['available'],
        'lots' => collect($item['lots'])->reverse()->map(fn ($lot) => ['remaining' => $lot['remaining'], 'price' => $lot['price']])->values(),
        'lastPrice' => $item['lots'][0]['price'] ?? 0,
    ]]);
    $history = $inventories->mapWithKeys(fn ($inventory) => [$inventory->id => [
        'title' => 'Inventaire du ' . $inventory->inventoried_at->format('d/m/Y'),
        'meta' => collect(['compté par ' . ($inventory->countedBy?->name ?? '—'), $inventory->note])->filter()->implode(' · '),
        'shortage' => money((float) $inventory->shortage_value),
        'surplus' => money((float) $inventory->surplus_value),
        'lines' => $inventory->audits->map(fn ($audit) => [
            'designation' => $audit->designation . ($audit->article ? ' · ' . $audit->article : ''),
            'theoretical' => $quantity($audit->theoretical_quantity) . ' ' . $audit->unit,
            'actual' => $quantity($audit->actual_quantity) . ' ' . $audit->unit,
            'variance' => ((float) $audit->variance > 0 ? '+' : '') . $quantity($audit->variance),
            'tone' => (float) $audit->variance < 0 ? 'negative' : ((float) $audit->variance > 0 ? 'positive' : 'zero'),
        ])->values(),
    ]]);
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" subtitle="Comptez le stock réel : les écarts validés corrigent le stock, et chaque inventaire reste dans l’historique." :back="route('admin.commercial')" back-label="Commercial">
        <x-slot:actions>
            <a href="{{ route('admin.commercial.module', 'etat-stock') }}" class="dg-btn dg-btn--outline"><i class="bi bi-boxes"></i>État du stock</a>
        </x-slot:actions>
    </x-dg.page-header>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="dg-kpi-grid">
        @foreach($stats as [$label, $value, $icon, $color, $hint])
            <x-dg.kpi :label="$label" :value="$value" :icon="$icon" :color="$color" :hint="$hint" />
        @endforeach
    </div>

    <form method="POST" action="{{ route('admin.commercial.inventory.store') }}" id="inventoryForm" class="mb-6"
        data-currency="{{ currency_symbol() }}" data-decimals="{{ app(\App\Services\TaxService::class)->decimalsFor(company_currency()['code']) }}">
        @csrf
        <div class="dg-card dg-card--table">
            <div class="dg-card__header flex-wrap">
                <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-purple"><i class="bi bi-clipboard-data"></i></span>Nouveau comptage</h2>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <input name="inventoried_at" type="date" class="form-control" style="width:170px" required value="{{ old('inventoried_at', now()->format('Y-m-d')) }}" aria-label="Date de l’inventaire">
                    <input name="note" class="form-control" style="width:260px" maxlength="255" value="{{ old('note') }}" placeholder="Note (ex : inventaire de fin de mois)" aria-label="Note">
                </div>
            </div>

            @if($items->isNotEmpty())
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <label class="dg-search" style="max-width:260px">
                        <i class="bi bi-search" aria-hidden="true"></i>
                        <input id="inventorySearch" type="search" placeholder="Article, référence…" aria-label="Rechercher un article">
                    </label>
                    <div class="d-flex align-items-center gap-3">
                        <span class="dg-muted" style="font-size:13px">Laissez vide un article non compté.</span>
                        <button type="button" class="dg-btn dg-btn--outline dg-btn--sm" id="fillTheoretical"><i class="bi bi-check2-all"></i>Tout marquer conforme</button>
                    </div>
                </div>
            @endif

            <div class="table-responsive">
                <table class="table align-middle mb-0 no-export no-column-sort inventory-table" id="inventoryTable">
                    <thead>
                        <tr>
                            <th>Article</th>
                            <th class="text-end">Stock théorique</th>
                            <th style="width:150px">Compté</th>
                            <th class="text-end">Écart</th>
                            <th class="text-end">Valeur de l’écart</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($items as $index => $item)
                        <tr data-key="{{ $item['key'] }}">
                            <td>
                                <span class="d-block fw-semibold">{{ $item['designation'] }}</span>
                                <span class="d-block dg-muted" style="font-size:12.5px">{{ collect([$item['article'], $item['unit']])->filter()->implode(' · ') ?: '—' }}</span>
                                <input type="hidden" name="items[{{ $index }}][key]" value="{{ $item['key'] }}">
                            </td>
                            <td class="text-end dg-cell-num fw-semibold">{{ $quantity($item['available']) }} {{ $item['unit'] }}</td>
                            <td><input name="items[{{ $index }}][counted]" type="number" min="0" step="0.001" class="form-control counted-input" value="{{ old('items.' . $index . '.counted') }}" placeholder="{{ $quantity($item['available']) }}" aria-label="Quantité comptée : {{ $item['designation'] }}"></td>
                            <td class="text-end dg-cell-num fw-semibold variance">—</td>
                            <td class="text-end dg-cell-num variance-value">—</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-5">
                                <div class="dg-chart-empty" style="min-height:200px">
                                    <span class="dg-tile dg-tone-purple"><i class="bi bi-clipboard-data"></i></span>
                                    <div><strong>Rien à compter</strong>Les articles reçus en stock apparaîtront ici pour être comptés.</div>
                                    <a href="{{ route('admin.commercial.stock-entries.create') }}" class="dg-btn dg-btn--primary dg-btn--sm"><i class="bi bi-plus-lg"></i>Nouvelle réception</a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if($items->isNotEmpty())
                <div class="inventory-summary">
                    <div><span>Articles comptés</span><strong id="sumCounted">0</strong></div>
                    <div><span>Écarts</span><strong id="sumVariances">0</strong></div>
                    <div class="is-negative"><span>Manquants</span><strong id="sumShortage">0</strong></div>
                    <div class="is-positive"><span>Surplus</span><strong id="sumSurplus">0</strong></div>
                    <button type="button" class="dg-btn dg-btn--primary" id="validateInventory" data-bs-toggle="modal" data-bs-target="#inventoryConfirm" disabled><i class="bi bi-clipboard-check"></i>Valider l’inventaire</button>
                </div>
            @endif
        </div>
    </form>

    <div class="dg-card dg-card--table">
        <div class="dg-card__header">
            <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-indigo"><i class="bi bi-clock-history"></i></span>Historique des inventaires</h2>
        </div>
        @if($inventories->isEmpty())
            <div class="dg-chart-empty" style="min-height:150px">
                <span class="dg-tile dg-tone-indigo"><i class="bi bi-clock-history"></i></span>
                <div><strong>Aucun inventaire validé</strong>Chaque inventaire validé apparaîtra ici avec ses écarts et leur valeur.</div>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0 no-export no-column-sort">
                    <thead><tr><th>Inventaire</th><th class="text-end">Articles comptés</th><th class="text-end">Écarts</th><th class="text-end">Manquants</th><th class="text-end">Surplus</th><th class="text-end"><span class="visually-hidden">Détail</span></th></tr></thead>
                    <tbody>
                    @foreach($inventories as $inventory)
                        <tr>
                            <td>
                                <span class="d-block fw-semibold">{{ $inventory->inventoried_at->format('d/m/Y') }}</span>
                                <span class="d-block dg-muted text-truncate" style="font-size:12.5px;max-width:320px">{{ collect([$inventory->countedBy?->name, $inventory->note])->filter()->implode(' · ') ?: '—' }}</span>
                            </td>
                            <td class="text-end">{{ $inventory->counted_items }}</td>
                            <td class="text-end">
                                @if($inventory->variance_items)
                                    <span class="dg-badge dg-badge--warning">{{ $inventory->variance_items }} écart(s)</span>
                                @else
                                    <span class="dg-badge dg-badge--success"><i class="bi bi-check-lg"></i>Conforme</span>
                                @endif
                            </td>
                            <td class="text-end dg-cell-num {{ (float) $inventory->shortage_value > 0 ? 'dg-amount-negative' : 'dg-muted' }}">{{ money((float) $inventory->shortage_value) }}</td>
                            <td class="text-end dg-cell-num {{ (float) $inventory->surplus_value > 0 ? 'dg-amount-positive' : 'dg-muted' }}">{{ money((float) $inventory->surplus_value) }}</td>
                            <td class="text-end"><button type="button" class="dg-btn dg-btn--outline dg-btn--sm" data-bs-toggle="modal" data-bs-target="#inventoryModal" data-inventory="{{ $inventory->id }}">Voir</button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Confirmation : l'inventaire corrige le stock. --}}
    <div class="modal fade dg-tone-purple" id="inventoryConfirm" tabindex="-1" aria-labelledby="inventoryConfirmTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="inventoryConfirmTitle"><i class="bi bi-clipboard-check me-2"></i>Valider l’inventaire</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3" style="font-size:14px"><strong id="confirmCounted"></strong> article(s) compté(s), dont <strong id="confirmVariances"></strong> avec un écart.</p>
                    <ul class="inventory-confirm">
                        <li class="is-negative"><i class="bi bi-dash-circle"></i>Les manquants sortent du stock (écart d’inventaire) : <strong id="confirmShortage"></strong></li>
                        <li class="is-positive"><i class="bi bi-plus-circle"></i>Les surplus entrent en stock (régularisation) : <strong id="confirmSurplus"></strong></li>
                    </ul>
                    <p class="dg-muted mb-0" style="font-size:13px">Après validation, le stock correspond au comptage. L’inventaire et ses écarts restent dans l’historique.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Revoir le comptage</button>
                    <button type="submit" form="inventoryForm" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Valider et corriger le stock</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade dg-tone-indigo" id="inventoryModal" tabindex="-1" aria-labelledby="inventoryModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="inventoryModalTitle"><i class="bi bi-clipboard-check me-2"></i><span data-inventory-field="title"></span></h5>
                        <div class="text-muted" style="font-size:13px" data-inventory-field="meta"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 no-export no-column-sort">
                            <thead><tr><th>Article</th><th class="text-end">Théorique</th><th class="text-end">Compté</th><th class="text-end">Écart</th></tr></thead>
                            <tbody id="inventoryLines"></tbody>
                        </table>
                    </div>
                    <div class="inventory-summary inventory-summary--modal">
                        <div class="is-negative"><span>Manquants</span><strong data-inventory-field="shortage"></strong></div>
                        <div class="is-positive"><span>Surplus</span><strong data-inventory-field="surplus"></strong></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="application/json" id="inventoryValuation">@json($valuation)</script>
<script type="application/json" id="inventoryHistory">@json($history)</script>
<style>
    .inventory-table { min-width: 700px; }
    .inventory-table .variance.is-negative, .inventory-table .variance-value.is-negative { color: var(--dg-danger); }
    .inventory-table .variance.is-positive, .inventory-table .variance-value.is-positive { color: #059669; }
    .inventory-table tr.is-counted td:first-child { box-shadow: inset 3px 0 0 #7c3aed; }
    .inventory-summary { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: 12px; margin-top: 16px; }
    .inventory-summary > div { min-width: 130px; padding: 10px 14px; border-radius: var(--dg-radius); background: var(--dg-table-head); }
    .inventory-summary span { display: block; font-size: 12.5px; color: var(--dg-muted); }
    .inventory-summary strong { font-size: 16px; }
    .inventory-summary .is-negative strong { color: var(--dg-danger); }
    .inventory-summary .is-positive strong { color: #059669; }
    .inventory-confirm { list-style: none; margin: 0 0 12px; padding: 0; font-size: 14px; }
    .inventory-confirm li { display: flex; gap: 8px; align-items: baseline; padding: 6px 0; }
    .inventory-confirm strong { margin-left: auto; padding-left: 12px; white-space: nowrap; }
    .inventory-confirm .is-negative .bi { color: var(--dg-danger); }
    .inventory-confirm .is-positive .bi { color: #059669; }
    .dg-amount-negative.dg-cell-num, .dg-amount-positive.dg-cell-num { font-weight: 600; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('inventoryForm');
    const valuation = JSON.parse(document.getElementById('inventoryValuation').textContent || '{}');
    const decimals = Number(form.dataset.decimals) || 0;
    const money = value => Number(value || 0).toLocaleString('fr-FR', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }) + ' ' + form.dataset.currency;
    const qty = value => Number(value || 0).toLocaleString('fr-FR', { maximumFractionDigits: 3 });
    const rows = Array.from(document.querySelectorAll('#inventoryTable tbody tr[data-key]'));

    // Valeur d'un manquant : les lots les plus anciens d'abord ; d'un surplus : le dernier prix d'achat.
    const shortageValue = (item, missing) => {
        let left = missing;
        let value = 0;
        item.lots.forEach(lot => { const take = Math.min(lot.remaining, left); value += take * lot.price; left -= take; });
        return value;
    };

    const recalc = () => {
        let counted = 0, variances = 0, shortage = 0, surplus = 0;
        rows.forEach(row => {
            const item = valuation[row.dataset.key];
            const input = row.querySelector('.counted-input');
            const cell = row.querySelector('.variance');
            const valueCell = row.querySelector('.variance-value');
            row.classList.toggle('is-counted', input.value !== '');
            cell.classList.remove('is-negative', 'is-positive');
            valueCell.classList.remove('is-negative', 'is-positive');
            if (input.value === '') { cell.textContent = '—'; valueCell.textContent = '—'; return; }
            counted++;
            const variance = Math.round((Number(input.value) - item.available) * 1000) / 1000;
            if (variance === 0) { cell.textContent = 'Conforme'; valueCell.textContent = money(0); return; }
            variances++;
            const value = variance < 0 ? shortageValue(item, -variance) : variance * item.lastPrice;
            variance < 0 ? shortage += value : surplus += value;
            const tone = variance < 0 ? 'is-negative' : 'is-positive';
            cell.textContent = (variance > 0 ? '+' : '') + qty(variance);
            valueCell.textContent = (variance < 0 ? '− ' : '+ ') + money(value);
            cell.classList.add(tone);
            valueCell.classList.add(tone);
        });
        document.getElementById('sumCounted').textContent = counted;
        document.getElementById('sumVariances').textContent = variances;
        document.getElementById('sumShortage').textContent = money(shortage);
        document.getElementById('sumSurplus').textContent = money(surplus);
        document.getElementById('confirmCounted').textContent = counted;
        document.getElementById('confirmVariances').textContent = variances;
        document.getElementById('confirmShortage').textContent = money(shortage);
        document.getElementById('confirmSurplus').textContent = money(surplus);
        document.getElementById('validateInventory').disabled = counted === 0;
    };

    if (rows.length) {
        form.addEventListener('input', recalc);
        document.getElementById('fillTheoretical').addEventListener('click', () => {
            rows.forEach(row => { const input = row.querySelector('.counted-input'); if (input.value === '') input.value = valuation[row.dataset.key].available; });
            recalc();
        });
        document.getElementById('inventorySearch').addEventListener('input', event => {
            const term = event.target.value.trim().toLowerCase();
            rows.forEach(row => { row.style.display = !term || row.textContent.toLowerCase().includes(term) ? '' : 'none'; });
        });
        recalc();
    }

    // Détail d'un inventaire de l'historique.
    const history = JSON.parse(document.getElementById('inventoryHistory').textContent || '{}');
    const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
    document.getElementById('inventoryModal').addEventListener('show.bs.modal', function (event) {
        const inventory = history[event.relatedTarget?.dataset.inventory];
        if (!inventory) return;
        this.querySelectorAll('[data-inventory-field]').forEach(field => { field.textContent = inventory[field.dataset.inventoryField] ?? ''; });
        const tones = { negative: 'dg-amount-negative', positive: 'dg-amount-positive', zero: 'dg-muted' };
        document.getElementById('inventoryLines').innerHTML = inventory.lines.map(line =>
            '<tr><td class="fw-semibold">' + escape(line.designation) + '</td><td class="text-end dg-cell-num">' + escape(line.theoretical) + '</td>'
            + '<td class="text-end dg-cell-num">' + escape(line.actual) + '</td><td class="text-end dg-cell-num fw-semibold ' + tones[line.tone] + '">'
            + (line.tone === 'zero' ? 'Conforme' : escape(line.variance)) + '</td></tr>').join('');
    });
});
</script>
@endsection
