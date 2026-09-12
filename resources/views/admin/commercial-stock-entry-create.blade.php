@extends('admin.layout')

@section('content')
@php
    // Après une erreur, la saisie est reprise telle quelle.
    $lines = array_values(old('lines', []));
    $articles = $knownArticles->map(fn ($line) => [
        'designation' => $line->designation, 'article' => $line->article, 'unit' => $line->unit,
        'purchase_price' => (float) $line->purchase_price, 'profit_per_unit' => (float) $line->profit_per_unit,
    ])->values();
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" subtitle="Chaque article reçu est rattaché au fournisseur qui l’a livré, avec son prix d’achat et le bénéfice attendu." :back="route('admin.commercial.module', 'entrees-stock')" back-label="Entrées de stock">
        <x-slot:actions>
            <a href="{{ route('admin.commercial.module', 'entrees-stock') }}" class="dg-btn dg-btn--outline">Annuler</a>
            <button type="submit" form="stockEntryForm" class="dg-btn dg-btn--primary"><i class="bi bi-check-lg"></i>Enregistrer la réception</button>
        </x-slot:actions>
    </x-dg.page-header>

    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ route('admin.commercial.stock-entries.store') }}" id="stockEntryForm"
        data-currency="{{ currency_symbol() }}" data-decimals="{{ app(\App\Services\TaxService::class)->decimalsFor(company_currency()['code']) }}">
        @csrf

        <x-dg.card title="Réception" icon="bi-truck" color="blue" class="mb-6">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="entrySupplier">Fournisseur <span class="text-danger">*</span></label>
                    <select id="entrySupplier" name="supplier_id" class="form-select" required>
                        <option value="">Sélectionner le fournisseur qui a livré…</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected((string) old('supplier_id') === (string) $supplier->id)>{{ $supplier->name }}{{ $supplier->phone ? ' · ' . $supplier->phone : '' }}</option>
                        @endforeach
                    </select>
                    @if($suppliers->isEmpty())
                        <div class="form-text text-danger">Aucun fournisseur dans le carnet : <a href="{{ route('admin.commercial.module', 'fournisseurs') }}">ajoutez-en un</a> avant d’enregistrer une réception.</div>
                    @else
                        <div class="form-text">Fournisseur absent de la liste ? <a href="{{ route('admin.commercial.module', 'fournisseurs') }}">Ajoutez-le au carnet</a>.</div>
                    @endif
                </div>
                <div class="col-md-3"><label class="form-label" for="entryDate">Date de réception <span class="text-danger">*</span></label><input id="entryDate" name="entry_date" type="date" class="form-control" required value="{{ old('entry_date', now()->format('Y-m-d')) }}"></div>
                <div class="col-md-3"><label class="form-label" for="entryReference">N° du bon de livraison</label><input id="entryReference" name="supplier_reference" class="form-control" maxlength="100" value="{{ old('supplier_reference') }}" placeholder="Ex : BL-2026-0412"></div>
            </div>
        </x-dg.card>

        <x-dg.card title="Articles reçus" icon="bi-box-seam" color="purple" class="mb-6">
            <x-slot:actions>
                <button type="button" class="dg-btn dg-btn--outline dg-btn--sm" id="addStockLine"><i class="bi bi-plus-lg"></i>Ajouter un article</button>
            </x-slot:actions>
            <div class="table-responsive">
                <table class="table align-middle mb-0 no-export no-column-sort stock-lines" id="stockLines">
                    <thead>
                        <tr>
                            <th>Désignation</th>
                            <th style="width:110px">Unité</th>
                            <th style="width:92px">Qté</th>
                            <th style="width:128px">Prix d’achat</th>
                            <th style="width:128px">Bénéfice / unité</th>
                            <th class="text-end" style="width:130px">Total achat</th>
                            <th style="width:44px"><span class="visually-hidden">Retirer</span></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <p class="dg-muted mt-3 mb-0" style="font-size:13px"><i class="bi bi-info-circle me-1"></i>Un article déjà reçu est proposé à la saisie, avec son dernier prix d’achat et son bénéfice. Le prix de vente est le prix d’achat augmenté du bénéfice par unité.</p>
        </x-dg.card>

        <div class="stock-summary">
            <div class="dg-callout dg-tone-blue">
                <span class="dg-tile"><i class="bi bi-boxes"></i></span>
                <div style="font-size:14px; flex:1 1 0; min-width:220px">
                    <strong class="d-block mb-1">Et ensuite ?</strong>
                    Les quantités reçues s’ajoutent au stock disponible dès l’enregistrement. Elles en sortent par une sortie de stock ou à la livraison d’une commande.
                </div>
            </div>
            <div class="dg-card stock-totals">
                <div class="stock-totals__row"><span>Articles</span><strong id="stockCount">0</strong></div>
                <div class="stock-totals__row"><span>Bénéfice attendu</span><strong class="text-success" id="stockProfit">0</strong></div>
                <div class="stock-totals__row"><span>Marge</span><strong id="stockMargin">—</strong></div>
                <div class="stock-totals__grand"><span>Valeur d’achat</span><strong id="stockTotal">0</strong></div>
                <button type="submit" class="dg-btn dg-btn--primary dg-btn--block mt-4"><i class="bi bi-check-lg"></i>Enregistrer la réception</button>
            </div>
        </div>
    </form>
</div>

<datalist id="knownArticles">@foreach($articles as $article)<option value="{{ $article['designation'] }}" label="{{ collect([$article['article'], $article['unit']])->filter()->implode(' · ') }}"></option>@endforeach</datalist>
<template id="stockLineTemplate">
    <tr>
        <td>
            <input class="form-control line-name" name="lines[__INDEX__][designation]" list="knownArticles" maxlength="190" required placeholder="Article reçu" aria-label="Désignation">
            <input class="form-control form-control-sm mt-2 line-article" name="lines[__INDEX__][article]" maxlength="190" placeholder="Référence ou catégorie (facultatif)" aria-label="Référence ou catégorie">
        </td>
        <td><input class="form-control line-unit" name="lines[__INDEX__][unit]" maxlength="50" placeholder="pièce" aria-label="Unité"></td>
        <td><input class="form-control line-quantity" name="lines[__INDEX__][quantity]" type="number" min="0.001" step="0.001" value="1" required aria-label="Quantité reçue"></td>
        <td><input class="form-control line-price" name="lines[__INDEX__][purchase_price]" type="number" min="0" step="0.01" value="0" required aria-label="Prix d’achat unitaire"></td>
        <td>
            <input class="form-control line-profit" name="lines[__INDEX__][profit_per_unit]" type="number" min="0" step="0.01" value="0" aria-label="Bénéfice par unité">
            <span class="d-block dg-muted mt-1 line-sale" style="font-size:12px"></span>
        </td>
        <td class="text-end dg-cell-num fw-semibold line-total">0</td>
        <td class="text-end"><button type="button" class="dg-icon-btn dg-icon-btn--sm dg-icon-btn--danger remove-line" title="Retirer l’article" aria-label="Retirer l’article"><i class="bi bi-x-lg"></i></button></td>
    </tr>
</template>

<style>
    .stock-lines { min-width: 820px; }
    .stock-lines td { vertical-align: top; }
    .stock-lines td.line-total { padding-top: 18px !important; }
    .dg-scope .stock-lines > thead > tr > th, .dg-scope .stock-lines > tbody > tr > td { padding-left: 6px !important; padding-right: 6px !important; }
    .stock-summary { display: grid; grid-template-columns: minmax(0, 1fr) 380px; gap: 24px; align-items: start; }
    .stock-totals__row { display: flex; justify-content: space-between; gap: 12px; padding: 6px 0; font-size: 14px; }
    .stock-totals__row strong { font-variant-numeric: tabular-nums; white-space: nowrap; }
    .stock-totals__row strong.text-success { color: #059669 !important; }
    .stock-totals__grand { display: flex; justify-content: space-between; align-items: baseline; margin-top: 8px; padding-top: 12px; border-top: 2px solid var(--dg-navy); color: var(--dg-navy); }
    .stock-totals__grand span { font-weight: 600; font-size: 15px; }
    .stock-totals__grand strong { font-size: 22px; white-space: nowrap; }
    @media (max-width: 991px) { .stock-summary { grid-template-columns: 1fr; } }
</style>
<script>
(() => {
    const form = document.getElementById('stockEntryForm');
    const body = document.querySelector('#stockLines tbody');
    const template = document.getElementById('stockLineTemplate');
    const articles = @json($articles);
    const decimals = Number(form.dataset.decimals) || 0;
    const money = value => Number(value || 0).toLocaleString('fr-FR', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }) + ' ' + form.dataset.currency;

    const reindex = () => body.querySelectorAll('tr').forEach((row, index) => {
        row.querySelectorAll('[name^="lines["]').forEach(field => { field.name = field.name.replace(/^lines\[\d+\]/, 'lines[' + index + ']'); });
    });

    const add = (line = null) => {
        body.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', body.children.length));
        const row = body.lastElementChild;
        if (line) {
            row.querySelector('.line-name').value = line.designation || '';
            row.querySelector('.line-article').value = line.article || '';
            row.querySelector('.line-unit').value = line.unit || '';
            row.querySelector('.line-quantity').value = Number(line.quantity) || 1;
            row.querySelector('.line-price').value = Number(line.purchase_price) || 0;
            row.querySelector('.line-profit').value = Number(line.profit_per_unit) || 0;
        }
        return row;
    };

    const totals = () => {
        let purchase = 0;
        let profit = 0;
        body.querySelectorAll('tr').forEach(row => {
            const quantity = Number(row.querySelector('.line-quantity').value) || 0;
            const price = Number(row.querySelector('.line-price').value) || 0;
            const unitProfit = Number(row.querySelector('.line-profit').value) || 0;
            row.querySelector('.line-total').textContent = money(quantity * price);
            row.querySelector('.line-sale').textContent = 'vente : ' + money(price + unitProfit);
            purchase += quantity * price;
            profit += quantity * unitProfit;
        });
        document.getElementById('stockCount').textContent = body.querySelectorAll('tr').length;
        document.getElementById('stockTotal').textContent = money(purchase);
        document.getElementById('stockProfit').textContent = money(profit);
        document.getElementById('stockMargin').textContent = purchase > 0 ? (profit / purchase * 100).toLocaleString('fr-FR', { maximumFractionDigits: 1 }) + ' %' : '—';
    };

    // Un article déjà reçu reprend sa référence, son unité et ses derniers prix, sans écraser une saisie.
    body.addEventListener('change', event => {
        if (!event.target.matches('.line-name')) return;
        const known = articles.find(item => item.designation.toLowerCase() === event.target.value.trim().toLowerCase());
        if (!known) return;
        const row = event.target.closest('tr');
        const fill = (selector, value) => { const field = row.querySelector(selector); if (!field.value || field.value === '0') field.value = value ?? ''; };
        fill('.line-article', known.article);
        fill('.line-unit', known.unit);
        fill('.line-price', known.purchase_price);
        fill('.line-profit', known.profit_per_unit);
        totals();
    });
    body.addEventListener('input', totals);
    body.addEventListener('click', event => {
        if (!event.target.closest('.remove-line')) return;
        if (body.children.length > 1) {
            event.target.closest('tr').remove();
            reindex();
        } else {
            body.firstElementChild.querySelectorAll('input').forEach(input => {
                input.value = input.classList.contains('line-quantity') ? 1 : (input.classList.contains('line-price') || input.classList.contains('line-profit') ? 0 : '');
            });
        }
        totals();
    });
    document.getElementById('addStockLine').addEventListener('click', () => { add().querySelector('.line-name').focus(); totals(); });

    const lines = @json($lines);
    (lines.length ? lines : [null]).forEach(add);
    totals();
})();
</script>
@endsection
