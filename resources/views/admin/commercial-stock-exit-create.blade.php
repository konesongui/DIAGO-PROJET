@extends('admin.layout')

@section('content')
@php
    // Après une erreur, la saisie est reprise telle quelle.
    $lines = array_values(old('lines', []));
    $quantity = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, ',', ' '), '0'), ',');
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" subtitle="Les quantités sortent des réceptions les plus anciennes en premier ; chaque unité reste reliée à son fournisseur." :back="route('admin.commercial.module', 'sorties-stock')" back-label="Sorties de stock">
        <x-slot:actions>
            <a href="{{ route('admin.commercial.module', 'sorties-stock') }}" class="dg-btn dg-btn--outline">Annuler</a>
            <button type="submit" form="stockExitForm" class="dg-btn dg-btn--primary"><i class="bi bi-check-lg"></i>Enregistrer la sortie</button>
        </x-slot:actions>
    </x-dg.page-header>

    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    @if($articles->isEmpty())
        <div class="dg-callout dg-tone-orange mb-6">
            <span class="dg-tile"><i class="bi bi-inbox"></i></span>
            <div style="flex:1 1 300px">
                <div class="fw-semibold">Aucun article en stock</div>
                <div class="dg-muted" style="font-size:13.5px">Enregistrez d’abord une réception : les articles reçus pourront ensuite sortir du stock.</div>
            </div>
            <a href="{{ route('admin.commercial.stock-entries.create') }}" class="dg-btn dg-btn--primary"><i class="bi bi-plus-lg"></i>Nouvelle réception</a>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.commercial.stock-exits.store') }}" id="stockExitForm"
        data-currency="{{ currency_symbol() }}" data-decimals="{{ app(\App\Services\TaxService::class)->decimalsFor(company_currency()['code']) }}">
        @csrf

        <x-dg.card title="Sortie" icon="bi-signpost-split" color="orange" class="mb-6">
            <div class="row g-3">
                <div class="col-md-3"><label class="form-label" for="exitDate">Date de sortie <span class="text-danger">*</span></label><input id="exitDate" name="exit_date" type="date" class="form-control" required value="{{ old('exit_date', now()->format('Y-m-d')) }}"></div>
                <div class="col-md-4">
                    <label class="form-label" for="exitReason">Motif <span class="text-danger">*</span></label>
                    <select id="exitReason" name="reason" class="form-select" required>
                        <option value="">Où part la marchandise ?</option>
                        @foreach(\App\Models\StockExit::reasons() as $key => $label)
                            <option value="{{ $key }}" @selected(old('reason') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5"><label class="form-label" for="exitNote">Précision</label><input id="exitNote" name="note" class="form-control" maxlength="255" value="{{ old('note') }}" placeholder="Ex : service comptable, carton abîmé à la réception…"></div>
            </div>
            <p class="dg-muted mt-3 mb-0" style="font-size:13px"><i class="bi bi-info-circle me-1"></i>Les livraisons de commandes sortent le stock d’elles-mêmes : cette saisie est réservée aux autres sorties.</p>
        </x-dg.card>

        <x-dg.card title="Articles sortis" icon="bi-box-seam" color="purple" class="mb-6">
            <x-slot:actions>
                <button type="button" class="dg-btn dg-btn--outline dg-btn--sm" id="addExitLine" @disabled($articles->isEmpty())><i class="bi bi-plus-lg"></i>Ajouter un article</button>
            </x-slot:actions>
            <div class="table-responsive">
                <table class="table align-middle mb-0 no-export no-column-sort exit-lines" id="exitLines">
                    <thead>
                        <tr>
                            <th>Article</th>
                            <th class="text-end" style="width:150px">Disponible</th>
                            <th style="width:120px">Qté sortie</th>
                            <th class="text-end" style="width:150px">Valeur</th>
                            <th style="width:44px"><span class="visually-hidden">Retirer</span></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </x-dg.card>

        <div class="exit-summary">
            <div class="dg-callout dg-tone-blue">
                <span class="dg-tile"><i class="bi bi-diagram-3"></i></span>
                <div style="font-size:14px; flex:1 1 0; min-width:220px">
                    <strong class="d-block mb-1">Premier entré, premier sorti</strong>
                    Une quantité peut couvrir plusieurs réceptions : elle est prise dans la plus ancienne d’abord, et valorisée au prix d’achat de chacune.
                </div>
            </div>
            <div class="dg-card exit-totals">
                <div class="exit-totals__row"><span>Articles</span><strong id="exitCount">0</strong></div>
                <div class="exit-totals__grand"><span>Valeur au prix d’achat</span><strong id="exitTotal">0</strong></div>
                <button type="submit" class="dg-btn dg-btn--primary dg-btn--block mt-4" @disabled($articles->isEmpty())><i class="bi bi-check-lg"></i>Enregistrer la sortie</button>
            </div>
        </div>
    </form>
</div>

<template id="exitLineTemplate">
    <tr>
        <td>
            <select class="form-select line-article" name="lines[__INDEX__][article_key]" required aria-label="Article">
                <option value="">Choisir un article en stock…</option>
                @foreach($articles as $article)
                    <option value="{{ $article['key'] }}">{{ $article['designation'] }}{{ $article['article'] ? ' · ' . $article['article'] : '' }}</option>
                @endforeach
            </select>
        </td>
        <td class="text-end line-available dg-muted">—</td>
        <td><input class="form-control line-quantity" name="lines[__INDEX__][quantity]" type="number" min="0.001" step="0.001" value="1" required aria-label="Quantité sortie"></td>
        <td class="text-end dg-cell-num fw-semibold line-value">0</td>
        <td class="text-end"><button type="button" class="dg-icon-btn dg-icon-btn--sm dg-icon-btn--danger remove-line" title="Retirer l’article" aria-label="Retirer l’article"><i class="bi bi-x-lg"></i></button></td>
    </tr>
</template>

<style>
    .exit-lines { min-width: 640px; }
    .dg-scope .exit-lines > thead > tr > th, .dg-scope .exit-lines > tbody > tr > td { padding-left: 8px !important; padding-right: 8px !important; }
    .exit-lines .line-available.is-short { color: var(--dg-danger) !important; font-weight: 600; }
    .exit-summary { display: grid; grid-template-columns: minmax(0, 1fr) 380px; gap: 24px; align-items: start; }
    .exit-totals__row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 14px; }
    .exit-totals__grand { display: flex; justify-content: space-between; align-items: baseline; margin-top: 8px; padding-top: 12px; border-top: 2px solid var(--dg-navy); color: var(--dg-navy); }
    .exit-totals__grand span { font-weight: 600; font-size: 15px; }
    .exit-totals__grand strong { font-size: 22px; white-space: nowrap; }
    @media (max-width: 991px) { .exit-summary { grid-template-columns: 1fr; } }
</style>
<script>
(() => {
    const form = document.getElementById('stockExitForm');
    const body = document.querySelector('#exitLines tbody');
    const template = document.getElementById('exitLineTemplate');
    const articles = Object.fromEntries(@json($articles).map(article => [article.key, article]));
    const decimals = Number(form.dataset.decimals) || 0;
    const money = value => Number(value || 0).toLocaleString('fr-FR', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }) + ' ' + form.dataset.currency;
    const qty = value => Number(value || 0).toLocaleString('fr-FR', { maximumFractionDigits: 3 });

    const reindex = () => body.querySelectorAll('tr').forEach((row, index) => {
        row.querySelectorAll('[name^="lines["]').forEach(field => { field.name = field.name.replace(/^lines\[\d+\]/, 'lines[' + index + ']'); });
    });

    const add = (line = null) => {
        body.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', body.children.length));
        const row = body.lastElementChild;
        if (line) {
            row.querySelector('.line-article').value = line.article_key || '';
            row.querySelector('.line-quantity').value = Number(line.quantity) || 1;
        }
        return row;
    };

    // Même répartition que le serveur : les lots les plus anciens d'abord, en tenant compte des autres lignes du même article.
    const totals = () => {
        const taken = {};
        let total = 0;
        body.querySelectorAll('tr').forEach(row => {
            const article = articles[row.querySelector('.line-article').value];
            const wanted = Number(row.querySelector('.line-quantity').value) || 0;
            const available = row.querySelector('.line-available');
            if (!article) {
                available.textContent = '—';
                available.classList.remove('is-short');
                row.querySelector('.line-value').textContent = money(0);
                return;
            }
            let left = wanted;
            let value = 0;
            const used = taken[article.key] || 0;
            let skip = used;
            article.lots.forEach(lot => {
                let free = lot.remaining;
                const skipped = Math.min(free, skip);
                free -= skipped;
                skip -= skipped;
                const take = Math.min(free, left);
                value += take * lot.price;
                left -= take;
            });
            taken[article.key] = used + wanted;
            const remaining = article.available - used;
            available.textContent = qty(remaining) + ' ' + (article.unit || '');
            available.classList.toggle('is-short', wanted > remaining + 0.0005);
            row.querySelector('.line-value').textContent = money(value);
            total += value;
        });
        document.getElementById('exitCount').textContent = body.querySelectorAll('tr').length;
        document.getElementById('exitTotal').textContent = money(total);
    };

    document.getElementById('addExitLine').addEventListener('click', () => { add().querySelector('.line-article').focus(); totals(); });
    body.addEventListener('change', totals);
    body.addEventListener('input', totals);
    body.addEventListener('click', event => {
        if (!event.target.closest('.remove-line')) return;
        if (body.children.length > 1) {
            event.target.closest('tr').remove();
            reindex();
        } else {
            body.firstElementChild.querySelector('.line-article').value = '';
            body.firstElementChild.querySelector('.line-quantity').value = 1;
        }
        totals();
    });

    const lines = @json($lines);
    (lines.length ? lines : [null]).forEach(add);
    totals();
})();
</script>
@endsection
