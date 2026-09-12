@extends('admin.layout')

@section('content')
@php
    $isEdit = isset($proforma);
    $value = fn (string $field, $default = null) => old($field, $isEdit ? $proforma->{$field} : $default);
    $dateValue = fn (string $field, ?string $default) => old($field, $isEdit && $proforma->{$field} ? $proforma->{$field}->format('Y-m-d') : $default);
    // Après une erreur, les lignes saisies sont reprises telles quelles.
    $lines = old('lines', $isEdit ? $proforma->lines->map->only(['type', 'category', 'item_name', 'unit', 'quantity', 'unit_price', 'discount', 'discount_type'])->all() : []);
    $clientChoice = (string) old('client_id', $isEdit ? $proforma->client_id : '');
    $selectedRate = old('tax_rate_id', $isEdit ? $proforma->tax_rate_id : null);
    $pageTitle = $isEdit ? 'Modifier la proforma ' . $proforma->reference : 'Nouvelle proforma';
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$pageTitle" subtitle="Offre chiffrée à remettre au client : produits, services, remises par ligne et TVA." :back="route('admin.commercial.module', 'proforma')" back-label="Proformas">
        <x-slot:actions>
            <a href="{{ route('admin.commercial.module', 'proforma') }}" class="dg-btn dg-btn--outline">Annuler</a>
            <button type="submit" form="proformaForm" class="dg-btn dg-btn--primary"><i class="bi bi-check-lg"></i>Enregistrer la proforma</button>
        </x-slot:actions>
    </x-dg.page-header>

    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ $isEdit ? route('admin.commercial.proforma.update', $proforma) : route('admin.commercial.proforma.store') }}" id="proformaForm"
        data-currency="{{ currency_symbol() }}" data-decimals="{{ app(\App\Services\TaxService::class)->decimalsFor(company_currency()['code']) }}">
        @csrf
        @if($isEdit) @method('PUT') @endif

        <x-dg.card title="Informations de la proforma" icon="bi-file-earmark-richtext" color="blue" class="mb-6">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="proformaClient">Client <span class="text-danger">*</span></label>
                    <select id="proformaClient" name="client_id" class="form-select">
                        <option value="">Sélectionner un client…</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}" @selected($clientChoice === (string) $client->id)>{{ $client->name }}{{ $client->phone ? ' · ' . $client->phone : '' }}</option>
                        @endforeach
                        <option value="new" @selected($clientChoice === 'new')>Nouveau client…</option>
                    </select>
                </div>
                <div class="col-md-3"><label class="form-label" for="proformaDate">Date <span class="text-danger">*</span></label><input id="proformaDate" type="date" name="creation_date" class="form-control" required value="{{ $dateValue('creation_date', now()->format('Y-m-d')) }}"></div>
                <div class="col-md-3"><label class="form-label" for="proformaDueDate">Valable jusqu’au</label><input id="proformaDueDate" type="date" name="due_date" class="form-control" value="{{ $dateValue('due_date', now()->addMonth()->format('Y-m-d')) }}"></div>
                <div class="col-12 new-client-fields">
                    <div class="proforma-new-client">
                        <div class="row g-3">
                            <div class="col-md-5"><label class="form-label" for="newClientName">Nom du nouveau client <span class="text-danger">*</span></label><input id="newClientName" name="new_client_name" class="form-control" maxlength="190" value="{{ old('new_client_name') }}"></div>
                            <div class="col-md-3"><label class="form-label" for="newClientPhone">Téléphone</label><input id="newClientPhone" name="new_client_phone" class="form-control" maxlength="60" value="{{ old('new_client_phone') }}"></div>
                            <div class="col-md-4"><label class="form-label" for="newClientEmail">E-mail</label><input id="newClientEmail" type="email" name="new_client_email" class="form-control" maxlength="190" value="{{ old('new_client_email') }}"></div>
                        </div>
                        <p class="dg-muted mb-0 mt-2" style="font-size:13px"><i class="bi bi-info-circle me-1"></i>Le client est ajouté au carnet à l’enregistrement.</p>
                    </div>
                </div>
                <div class="col-md-4"><label class="form-label" for="proformaPaymentTerms">Conditions de règlement</label><input id="proformaPaymentTerms" name="payment_terms" class="form-control" maxlength="190" value="{{ $value('payment_terms') }}" placeholder="Ex : 50 % à la commande, solde à la livraison"></div>
                <div class="col-md-4">
                    <label class="form-label" for="proformaPaymentMethod">Mode de paiement</label>
                    <select id="proformaPaymentMethod" name="payment_method" class="form-select">
                        <option value="">Sélectionner…</option>
                        @foreach(['Espèces', 'Chèque', 'Virement', 'Carte bancaire'] as $method)
                            <option value="{{ $method }}" @selected($value('payment_method') === $method)>{{ $method }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4"><label class="form-label" for="proformaDeliveryLocation">Lieu de livraison</label><input id="proformaDeliveryLocation" name="delivery_location" class="form-control" maxlength="190" value="{{ $value('delivery_location') }}"></div>
                <div class="col-md-6"><label class="form-label" for="proformaDeliveryTerms">Conditions de livraison</label><input id="proformaDeliveryTerms" name="delivery_terms" class="form-control" maxlength="190" value="{{ $value('delivery_terms') }}"></div>
                <div class="col-md-6"><label class="form-label" for="proformaSubject">Objet</label><input id="proformaSubject" name="subject" class="form-control" maxlength="2000" value="{{ $value('subject') }}" placeholder="Ex : Audit des systèmes d’information"></div>
            </div>
        </x-dg.card>

        <x-dg.card title="Lignes de la proforma" icon="bi-list-ul" color="purple" class="mb-6">
            <x-slot:actions>
                <button type="button" class="dg-btn dg-btn--outline dg-btn--sm" id="addProformaLine"><i class="bi bi-plus-lg"></i>Ajouter une ligne</button>
            </x-slot:actions>
            <div class="table-responsive">
                <table class="table align-middle mb-0 no-export no-column-sort proforma-lines" id="proformaLines">
                    <thead>
                        <tr>
                            <th>Désignation</th>
                            <th style="width:94px">Unité</th>
                            <th style="width:80px">Qté</th>
                            <th style="width:124px">Prix unitaire HT</th>
                            <th style="width:190px">Remise</th>
                            <th class="text-end" style="width:130px">Total HT</th>
                            <th style="width:44px"><span class="visually-hidden">Retirer</span></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <p class="dg-muted mt-3 mb-0" style="font-size:13px"><i class="bi bi-info-circle me-1"></i>La désignation reste libre : le stock et le catalogue des services ne sont que des suggestions. Un service du catalogue reprend son prix HT.</p>
        </x-dg.card>

        <div class="proforma-summary">
            <div class="dg-callout dg-tone-blue">
                <span class="dg-tile"><i class="bi bi-info-lg"></i></span>
                <div style="font-size:14px; flex:1 1 0; min-width:220px">
                    <strong class="d-block mb-1">Une offre, pas une vente</strong>
                    La proforma n’entre ni au journal ni dans le chiffre d’affaires. Son numéro est attribué à l’enregistrement ; envoyez-la ensuite au client depuis la liste.
                </div>
            </div>
            <div class="dg-card proforma-totals">
                <div class="proforma-totals__row"><span>Total HT brut</span><strong id="totalHt">0</strong></div>
                <div class="proforma-totals__row"><span>Remises sur les lignes</span><strong id="totalDiscount">0</strong></div>
                <div class="proforma-totals__row proforma-totals__row--sep"><span>Montant net HT</span><strong id="netHt">0</strong></div>
                <div class="proforma-totals__row">
                    <label for="taxRate" class="mb-0">TVA</label>
                    <select name="tax_rate_id" id="taxRate" class="form-select form-select-sm" style="width:190px">
                        @foreach($taxRates ?? [] as $rate)
                            <option value="{{ $rate->id }}" data-rate="{{ $rate->isZeroRated() ? 0 : (float) $rate->rate }}" @selected($selectedRate ? (string) $selectedRate === (string) $rate->id : $rate->is_default)>{{ $rate->name }}@if(!$rate->isZeroRated()) ({{ rtrim(rtrim(number_format((float) $rate->rate, 3, ',', ' '), '0'), ',') }} %)@endif</option>
                        @endforeach
                    </select>
                </div>
                <div class="proforma-totals__row"><span>Montant de TVA</span><strong id="taxAmount">0</strong></div>
                <div class="proforma-totals__grand"><span>Total TTC</span><strong id="totalTtc">0</strong></div>
                <button type="submit" class="dg-btn dg-btn--primary dg-btn--block mt-4"><i class="bi bi-check-lg"></i>Enregistrer la proforma</button>
            </div>
        </div>
    </form>
</div>

<datalist id="proformaArticlesList">@foreach($catalogArticles as $article)<option value="{{ $article['name'] }}" data-unit="{{ $article['unit'] }}" label="{{ trim(($article['article'] ?: '') . (($article['unit'] ?? '') ? ' - ' . $article['unit'] : '')) }}"></option>@endforeach</datalist>
<datalist id="proformaServicesList">@foreach($catalogServices as $service)<option value="{{ $service->name }}" data-price="{{ $service->price }}" data-unit="{{ $service->unit }}" label="{{ $service->unit ?: 'Service' }}"></option>@endforeach</datalist>
<template id="proformaLineTemplate">
    <tr>
        <td>
            <input class="form-control line-name" name="lines[__INDEX__][item_name]" list="proformaArticlesList" placeholder="Choisir ou saisir une désignation" maxlength="190" required aria-label="Désignation">
            <div class="proforma-line-meta">
                <select class="form-select form-select-sm line-type" name="lines[__INDEX__][type]" aria-label="Type"><option value="product">Produit</option><option value="service">Service</option></select>
                <input class="form-control form-control-sm line-category" name="lines[__INDEX__][category]" maxlength="190" placeholder="Catégorie (facultatif)" aria-label="Catégorie">
            </div>
        </td>
        <td><input class="form-control line-unit" name="lines[__INDEX__][unit]" maxlength="50" aria-label="Unité"></td>
        <td><input class="form-control line-quantity" name="lines[__INDEX__][quantity]" type="number" min="0.001" step="0.001" value="1" required aria-label="Quantité"></td>
        <td><input class="form-control line-price" name="lines[__INDEX__][unit_price]" type="number" min="0" step="0.01" value="0" required aria-label="Prix unitaire HT"></td>
        <td>
            <div class="input-group">
                <input class="form-control line-discount" name="lines[__INDEX__][discount]" type="number" min="0" step="0.01" value="0" aria-label="Remise">
                <select class="form-select line-discount-type" name="lines[__INDEX__][discount_type]" aria-label="Type de remise"><option value="percent">%</option><option value="amount">{{ currency_symbol() }}</option></select>
            </div>
        </td>
        <td class="text-end dg-cell-num line-total">0</td>
        <td class="text-end"><button type="button" class="dg-icon-btn dg-icon-btn--sm dg-icon-btn--danger remove-proforma-line" title="Retirer la ligne" aria-label="Retirer la ligne"><i class="bi bi-x-lg"></i></button></td>
    </tr>
</template>

<style>
    .proforma-new-client { padding: 16px; border: 1px dashed rgba(37, 99, 235, .45); border-radius: var(--dg-radius); background: rgba(37, 99, 235, .04); }
    .proforma-lines { min-width: 880px; }
    .proforma-line-meta { display: flex; gap: 8px; margin-top: 8px; }
    .proforma-line-meta .line-type { flex: 0 0 116px; }
    .proforma-lines .line-discount-type { flex: 0 0 76px; padding-left: 10px; padding-right: 24px; background-position: right 8px center; }
    .proforma-lines td { vertical-align: top; }
    .proforma-lines td.line-total { padding-top: 18px !important; }
    .dg-scope .proforma-lines > thead > tr > th,
    .dg-scope .proforma-lines > tbody > tr > td { padding-left: 6px !important; padding-right: 6px !important; }
    .proforma-summary { display: grid; grid-template-columns: minmax(0, 1fr) 400px; gap: 24px; align-items: start; }
    .proforma-totals__row { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 7px 0; font-size: 14px; }
    .proforma-totals__row strong { font-variant-numeric: tabular-nums; white-space: nowrap; }
    .proforma-totals__row--sep { margin-top: 4px; padding-top: 10px; border-top: 1px solid var(--dg-border); }
    .proforma-totals__grand { display: flex; justify-content: space-between; align-items: baseline; margin-top: 8px; padding-top: 12px; border-top: 2px solid var(--dg-navy); color: var(--dg-navy); }
    .proforma-totals__grand span { font-weight: 600; font-size: 15px; }
    .proforma-totals__grand strong { font-size: 22px; white-space: nowrap; }
    @media (max-width: 991px) { .proforma-summary { grid-template-columns: 1fr; } }
</style>
<script>
(() => {
    const form = document.getElementById('proformaForm');
    const body = document.querySelector('#proformaLines tbody');
    const template = document.getElementById('proformaLineTemplate');
    const decimals = Number(form.dataset.decimals) || 0;
    const round = value => Math.round(value * 10 ** decimals) / 10 ** decimals;
    const money = value => Number(value || 0).toLocaleString('fr-FR', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }) + ' ' + form.dataset.currency;

    // Client : les champs du nouveau client n'apparaissent que pour « Nouveau client ».
    const client = document.getElementById('proformaClient');
    const syncClient = () => {
        const isNew = client.value === 'new';
        document.querySelector('.new-client-fields').classList.toggle('d-none', !isNew);
        document.getElementById('newClientName').required = isNew;
    };
    client.addEventListener('change', syncClient);

    // Le type choisit la liste de suggestions ; un service du catalogue complète l'unité et le prix HT.
    const applyCatalog = row => {
        const type = row.querySelector('.line-type').value;
        const list = document.getElementById(type === 'service' ? 'proformaServicesList' : 'proformaArticlesList');
        row.querySelector('.line-name').setAttribute('list', list.id);
        const name = row.querySelector('.line-name').value.trim().toLowerCase();
        const option = [...list.options].find(item => item.value.toLowerCase() === name);
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
        row.querySelector('.line-type').value = line?.type === 'service' ? 'service' : 'product';
        if (line) {
            row.querySelector('.line-name').value = line.item_name || '';
            row.querySelector('.line-category').value = line.category || '';
            row.querySelector('.line-unit').value = line.unit || '';
            row.querySelector('.line-quantity').value = Number(line.quantity) || 1;
            row.querySelector('.line-price').value = Number(line.unit_price) || 0;
            row.querySelector('.line-discount').value = Number(line.discount) || 0;
            row.querySelector('.line-discount-type').value = line.discount_type === 'amount' ? 'amount' : 'percent';
        }
        applyCatalog(row);
        return row;
    };

    // Même calcul que le serveur : remise plafonnée au montant de la ligne, TVA sur le net arrondi.
    const totals = () => {
        let gross = 0;
        let discount = 0;
        body.querySelectorAll('tr').forEach(row => {
            const lineGross = (Number(row.querySelector('.line-quantity').value) || 0) * (Number(row.querySelector('.line-price').value) || 0);
            const value = Number(row.querySelector('.line-discount').value) || 0;
            const lineDiscount = Math.min(lineGross, row.querySelector('.line-discount-type').value === 'amount' ? value : lineGross * Math.min(value, 100) / 100);
            row.querySelector('.line-total').textContent = money(lineGross - lineDiscount);
            gross += lineGross;
            discount += lineDiscount;
        });
        const net = round(gross - discount);
        const rate = Number(document.getElementById('taxRate').selectedOptions[0]?.dataset.rate) || 0;
        const tax = round(net * rate / 100);
        document.getElementById('totalHt').textContent = money(gross);
        document.getElementById('totalDiscount').textContent = discount > 0 ? '− ' + money(discount) : money(0);
        document.getElementById('netHt').textContent = money(net);
        document.getElementById('taxAmount').textContent = money(tax);
        document.getElementById('totalTtc').textContent = money(net + tax);
    };

    document.getElementById('addProformaLine').addEventListener('click', () => { add().querySelector('.line-name').focus(); totals(); });
    body.addEventListener('change', event => {
        if (event.target.matches('.line-type, .line-name')) applyCatalog(event.target.closest('tr'));
        totals();
    });
    body.addEventListener('click', event => {
        if (!event.target.closest('.remove-proforma-line')) return;
        // Une proforma garde toujours au moins une ligne.
        if (body.children.length > 1) {
            event.target.closest('tr').remove();
            reindex();
        } else {
            body.firstElementChild.querySelectorAll('input').forEach(input => {
                input.value = input.classList.contains('line-quantity') ? 1 : (input.classList.contains('line-price') || input.classList.contains('line-discount') ? 0 : '');
            });
        }
        totals();
    });
    form.addEventListener('input', totals);
    document.getElementById('taxRate').addEventListener('change', totals);

    const lines = @json(array_values($lines));
    (lines.length ? lines : [null]).forEach(add);
    syncClient();
    totals();
})();
</script>
@endsection
