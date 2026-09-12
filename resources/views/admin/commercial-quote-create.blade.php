@extends('admin.layout')

@section('content')
@php
    $isEdit = isset($quote);
    $value = fn (string $field, $default = null) => old($field, $isEdit ? $quote->{$field} : $default);
    $dateValue = fn (string $field, ?string $default) => old($field, $isEdit && $quote->{$field} ? $quote->{$field}->format('Y-m-d') : $default);
    // Après une erreur, les lignes saisies sont reprises telles quelles.
    $lines = old('lines', $isEdit ? ($quote->lines ?? []) : []);
    $selectedRate = old('tax_rate_id', $isEdit ? $quote->tax_rate_id : null);
    $pageTitle = $isEdit ? 'Modifier le devis ' . $quote->reference : 'Nouveau devis';
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$pageTitle" subtitle="Le devis est enregistré en attente de validation par le client." :back="route('admin.commercial.module', 'devis')" back-label="Devis">
        <x-slot:actions>
            <a href="{{ route('admin.commercial.module', 'devis') }}" class="dg-btn dg-btn--outline">Annuler</a>
            <button type="submit" form="quoteForm" class="dg-btn dg-btn--primary"><i class="bi bi-check-lg"></i>Enregistrer le devis</button>
        </x-slot:actions>
    </x-dg.page-header>

    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ $isEdit ? route('admin.commercial.quotes.update', $quote) : route('admin.commercial.quotes.store') }}" id="quoteForm"
        data-currency="{{ currency_symbol() }}" data-decimals="{{ app(\App\Services\TaxService::class)->decimalsFor(company_currency()['code']) }}">
        @csrf
        @if($isEdit) @method('PUT') @endif

        <x-dg.card title="Informations du devis" icon="bi-file-earmark-text" color="blue" class="mb-6">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="quoteClient">Client <span class="text-danger">*</span></label>
                    <select id="quoteClient" name="client_id" class="form-select" required>
                        <option value="">Sélectionner un client…</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}" @selected((string) $value('client_id') === (string) $client->id)>{{ $client->name }}{{ $client->phone ? ' · ' . $client->phone : '' }}</option>
                        @endforeach
                    </select>
                    @if($clients->isEmpty())
                        <div class="form-text">Aucun client : <a href="{{ route('admin.commercial.module', 'clients') }}">ajoutez-en un</a> avant de créer un devis.</div>
                    @endif
                </div>
                <div class="col-md-3"><label class="form-label" for="quoteDate">Date du devis <span class="text-danger">*</span></label><input id="quoteDate" type="date" name="quote_date" class="form-control" required value="{{ $dateValue('quote_date', now()->format('Y-m-d')) }}"></div>
                <div class="col-md-3"><label class="form-label" for="quoteDueDate">Valable jusqu’au</label><input id="quoteDueDate" type="date" name="due_date" class="form-control" value="{{ $dateValue('due_date', now()->addMonth()->format('Y-m-d')) }}"></div>
                <div class="col-md-4"><label class="form-label" for="quotePaymentTerms">Conditions de règlement</label><input id="quotePaymentTerms" name="payment_terms" class="form-control" maxlength="190" value="{{ $value('payment_terms') }}" placeholder="Ex : 30 jours net à réception de facture"></div>
                <div class="col-md-4">
                    <label class="form-label" for="quotePaymentMethod">Mode de paiement</label>
                    <select id="quotePaymentMethod" name="payment_method" class="form-select">
                        <option value="">Sélectionner…</option>
                        @foreach(['Espèces', 'Chèque', 'Virement', 'Carte bancaire'] as $method)
                            <option value="{{ $method }}" @selected($value('payment_method') === $method)>{{ $method }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4"><label class="form-label" for="quoteDeliveryLocation">Lieu de livraison</label><input id="quoteDeliveryLocation" name="delivery_location" class="form-control" maxlength="190" value="{{ $value('delivery_location') }}"></div>
                <div class="col-md-6"><label class="form-label" for="quoteDeliveryTerms">Conditions de livraison</label><input id="quoteDeliveryTerms" name="delivery_terms" class="form-control" maxlength="190" value="{{ $value('delivery_terms') }}"></div>
                <div class="col-md-6"><label class="form-label" for="quoteSubject">Objet</label><input id="quoteSubject" name="subject" class="form-control" maxlength="2000" value="{{ $value('subject') }}" placeholder="Ex : Mission d’audit des comptes 2026"></div>
            </div>
        </x-dg.card>

        <x-dg.card title="Lignes du devis" icon="bi-list-ul" color="purple" class="mb-6">
            <x-slot:actions>
                <button type="button" class="dg-btn dg-btn--outline dg-btn--sm" id="addQuoteLine"><i class="bi bi-plus-lg"></i>Ajouter une ligne</button>
            </x-slot:actions>
            <div class="table-responsive">
                <table class="table align-middle mb-0 no-export no-column-sort quote-lines" id="quoteLines">
                    <thead>
                        <tr>
                            <th style="width:150px">Type</th>
                            <th>Désignation</th>
                            <th style="width:110px">Unité</th>
                            <th style="width:100px">Qté</th>
                            <th style="width:150px">Prix unitaire HT</th>
                            <th class="text-end" style="width:150px">Total HT</th>
                            <th style="width:48px"><span class="visually-hidden">Retirer</span></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <p class="dg-muted mt-3 mb-0" style="font-size:13px"><i class="bi bi-info-circle me-1"></i>Un article sort du stock à la livraison ; un service n’est pas stocké. La désignation reste libre, le catalogue n’est qu’une suggestion.</p>
        </x-dg.card>

        <div class="quote-summary">
            <div class="dg-callout dg-tone-blue quote-summary__note">
                <span class="dg-tile"><i class="bi bi-arrow-repeat"></i></span>
                <div style="font-size:14px; flex:1 1 0; min-width:220px">
                    <strong class="d-block mb-1">Et ensuite ?</strong>
                    Une fois le devis accepté, validez-le avec le bon de commande du client : il devient une commande et une livraison à préparer. La facture est créée à la livraison complète.
                </div>
            </div>
            <div class="dg-card quote-totals">
                <div class="quote-totals__row"><span>Total HT</span><strong id="totalHt">0</strong></div>
                <div class="quote-totals__row">
                    <label for="globalDiscount" class="mb-0">Remise</label>
                    <input type="number" min="0" step="0.01" name="total_discount" id="globalDiscount" class="form-control form-control-sm quote-totals__input" value="{{ $value('total_discount', 0) }}">
                </div>
                <div class="quote-totals__row"><span>Montant net HT</span><strong id="netHt">0</strong></div>
                <div class="quote-totals__row">
                    <label for="taxRate" class="mb-0">TVA</label>
                    <select name="tax_rate_id" id="taxRate" class="form-select form-select-sm quote-totals__input">
                        @foreach($taxRates ?? [] as $rate)
                            <option value="{{ $rate->id }}" data-rate="{{ $rate->isZeroRated() ? 0 : (float) $rate->rate }}" @selected($selectedRate ? (string) $selectedRate === (string) $rate->id : $rate->is_default)>{{ $rate->name }}@if(!$rate->isZeroRated()) ({{ rtrim(rtrim(number_format((float) $rate->rate, 3, ',', ' '), '0'), ',') }} %)@endif</option>
                        @endforeach
                    </select>
                </div>
                <div class="quote-totals__row"><span>Montant de TVA</span><strong id="taxAmount">0</strong></div>
                <div class="quote-totals__grand"><span>Total TTC</span><strong id="totalTtc">0</strong></div>
                <button type="submit" class="dg-btn dg-btn--primary dg-btn--block mt-4"><i class="bi bi-check-lg"></i>Enregistrer le devis</button>
            </div>
        </div>
    </form>
</div>

<datalist id="quoteArticlesList">@foreach($quoteArticles as $article)<option value="{{ $article['name'] }}" data-unit="{{ $article['unit'] }}" label="{{ trim(($article['article'] ?: '') . (($article['unit'] ?? '') ? ' - ' . $article['unit'] : '')) }}"></option>@endforeach</datalist>
<datalist id="quoteServicesList">@foreach($quoteServices as $service)<option value="{{ $service->name }}" data-price="{{ $service->price }}" data-unit="{{ $service->unit }}" label="{{ $service->unit ?: 'Service' }}"></option>@endforeach</datalist>
<template id="quoteLineTemplate">
    <tr>
        <td><select class="form-select line-type" name="lines[__INDEX__][item_type]" aria-label="Type"><option value="article">Article</option><option value="service">Service</option></select></td>
        <td><input class="form-control line-name" name="lines[__INDEX__][item_name]" list="quoteArticlesList" placeholder="Choisir ou saisir une désignation" maxlength="190" required aria-label="Désignation"></td>
        <td><input class="form-control line-unit" name="lines[__INDEX__][unit]" maxlength="50" aria-label="Unité"></td>
        <td><input class="form-control line-quantity" name="lines[__INDEX__][quantity]" type="number" min="0.001" step="0.001" value="1" required aria-label="Quantité"></td>
        <td><input class="form-control line-price" name="lines[__INDEX__][unit_price]" type="number" min="0" step="0.01" value="0" required aria-label="Prix unitaire HT"></td>
        <td class="text-end dg-cell-num line-total">0</td>
        <td class="text-end"><button type="button" class="dg-icon-btn dg-icon-btn--sm dg-icon-btn--danger remove-quote-line" title="Retirer la ligne" aria-label="Retirer la ligne"><i class="bi bi-x-lg"></i></button></td>
    </tr>
</template>

<style>
    .quote-lines { min-width: 860px; }
    .quote-lines td { vertical-align: middle; }
    .dg-scope .quote-lines > thead > tr > th,
    .dg-scope .quote-lines > tbody > tr > td { padding-left: 8px !important; padding-right: 8px !important; }
    .quote-summary { display: grid; grid-template-columns: minmax(0, 1fr) 380px; gap: 24px; align-items: start; }
    .quote-summary__note { align-self: start; }
    .quote-totals__row { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 7px 0; font-size: 14px; }
    .quote-totals__row strong { font-variant-numeric: tabular-nums; white-space: nowrap; }
    .quote-totals__input { width: 170px !important; }
    .quote-totals__grand { display: flex; justify-content: space-between; align-items: baseline; margin-top: 8px; padding-top: 12px; border-top: 2px solid var(--dg-navy); color: var(--dg-navy); }
    .quote-totals__grand span { font-weight: 600; font-size: 15px; }
    .quote-totals__grand strong { font-size: 22px; white-space: nowrap; }
    @media (max-width: 991px) { .quote-summary { grid-template-columns: 1fr; } }
</style>
<script>
(() => {
    const form = document.getElementById('quoteForm');
    const body = document.querySelector('#quoteLines tbody');
    const template = document.getElementById('quoteLineTemplate');
    const decimals = Number(form.dataset.decimals) || 0;
    const money = value => Number(value || 0).toLocaleString('fr-FR', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }) + ' ' + form.dataset.currency;
    const catalogOption = (row, type) => {
        const name = row.querySelector('.line-name').value.trim().toLowerCase();
        const list = document.getElementById(type === 'service' ? 'quoteServicesList' : 'quoteArticlesList');
        return [...list.options].find(option => option.value.toLowerCase() === name);
    };

    // Le type choisit la liste de suggestions ; une désignation du catalogue complète l'unité et, pour un service, le prix HT.
    const applyCatalog = row => {
        const type = row.querySelector('.line-type').value;
        row.querySelector('.line-name').setAttribute('list', type === 'service' ? 'quoteServicesList' : 'quoteArticlesList');
        const option = catalogOption(row, type);
        if (!option) return;
        const unit = row.querySelector('.line-unit');
        if (!unit.value && option.dataset.unit) unit.value = option.dataset.unit;
        const price = row.querySelector('.line-price');
        if (type === 'service' && !Number(price.value)) price.value = option.dataset.price || 0;
    };

    const reindex = () => {
        body.querySelectorAll('tr').forEach((row, index) => {
            row.querySelectorAll('[name^="lines["]').forEach(field => {
                field.name = field.name.replace(/^lines\[\d+\]/, 'lines[' + index + ']');
            });
        });
    };

    const add = (line = null) => {
        body.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', body.children.length));
        const row = body.lastElementChild;
        row.querySelector('.line-type').value = line?.item_type === 'service' ? 'service' : 'article';
        if (line) {
            row.querySelector('.line-name').value = line.item_name || '';
            row.querySelector('.line-unit').value = line.unit || '';
            row.querySelector('.line-quantity').value = line.quantity || 1;
            row.querySelector('.line-price').value = line.unit_price || 0;
        }
        applyCatalog(row);
        return row;
    };

    const totals = () => {
        let ht = 0;
        body.querySelectorAll('tr').forEach(row => {
            const lineTotal = (Number(row.querySelector('.line-quantity').value) || 0) * (Number(row.querySelector('.line-price').value) || 0);
            row.querySelector('.line-total').textContent = money(lineTotal);
            ht += lineTotal;
        });
        const discount = Math.min(ht, Number(document.getElementById('globalDiscount').value) || 0);
        const net = ht - discount;
        const rate = Number(document.getElementById('taxRate').selectedOptions[0]?.dataset.rate) || 0;
        const tax = net * rate / 100;
        document.getElementById('totalHt').textContent = money(ht);
        document.getElementById('netHt').textContent = money(net);
        document.getElementById('taxAmount').textContent = money(tax);
        document.getElementById('totalTtc').textContent = money(net + tax);
    };

    document.getElementById('addQuoteLine').addEventListener('click', () => { add().querySelector('.line-name').focus(); totals(); });
    body.addEventListener('change', event => {
        if (event.target.matches('.line-type, .line-name')) applyCatalog(event.target.closest('tr'));
        totals();
    });
    body.addEventListener('click', event => {
        if (!event.target.closest('.remove-quote-line')) return;
        // Un devis garde toujours au moins une ligne.
        if (body.children.length > 1) {
            event.target.closest('tr').remove();
            reindex();
        } else {
            const row = body.firstElementChild;
            row.querySelectorAll('input').forEach(input => { input.value = input.classList.contains('line-quantity') ? 1 : (input.classList.contains('line-price') ? 0 : ''); });
        }
        totals();
    });
    form.addEventListener('input', totals);
    document.getElementById('taxRate').addEventListener('change', totals);

    const lines = @json(array_values($lines));
    (lines.length ? lines : [null]).forEach(add);
    totals();
})();
</script>
@endsection
