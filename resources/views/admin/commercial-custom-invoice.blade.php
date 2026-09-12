@extends('admin.layout')

@section('content')
@php
    $isEdit = ! empty($invoice);
    $value = fn (string $field, $default = null) => old($field, $isEdit ? $invoice->{$field} : $default);
    $dateValue = fn (string $field, string $default) => old($field, $isEdit && $invoice->{$field} ? $invoice->{$field}->format('Y-m-d') : $default);

    // Toutes les lignes sont reprises (en modification comme après une erreur), pas seulement la première.
    $defaultItem = ['item_name' => '', 'item_category' => 'impression', 'unit' => 'Exemplaire', 'quantity' => 1, 'price' => 0,
        'paper_type' => 'bouffant_creme', 'printing_type' => 'noir_blanc', 'cover_type' => 'couche_300g', 'book_format' => 'poche_11x18',
        'lamination' => 'brillant', 'binding_type' => 'dos_carre_colle', 'page_count' => 100];
    $items = array_values(old('items', $isEdit && ! empty($invoice->items) ? $invoice->items : [$defaultItem]));
    $selectedServices = old('global_services', collect($invoice?->global_services ?? [])->pluck('key')->all());

    // Client : celui du carnet dont le nom correspond, sinon la saisie libre.
    $matchedClient = $isEdit ? $clients->first(fn ($client) => mb_strtolower($client->name) === mb_strtolower((string) $invoice->client_name)) : null;
    $customer = (string) old('customer', $isEdit ? ($matchedClient?->id ?? 'new') : '');

    // La remise est enregistrée en montant : on la relit comme telle.
    $discountType = old('discount_type', $isEdit && (float) $invoice->total_discount > 0 ? 'amount' : 'none');
    $discountValue = old('discount_value', $isEdit ? (float) $invoice->total_discount : 0);
    $selectedRate = old('tax_rate_id', $isEdit ? $invoice->tax_rate_id : null);

    $options = \App\Models\CustomInvoice::itemOptions();
    $pageTitle = $isEdit ? 'Modifier la facture ' . $invoice->reference : 'Nouvelle facture personnalisée';
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$pageTitle" subtitle="Facture sur mesure : lignes libres, caractéristiques d’impression et forfaits du catalogue." :back="route('admin.commercial.custom-invoice.index')" back-label="Factures personnalisées">
        <x-slot:actions>
            <a href="{{ route('admin.commercial.custom-invoice.index') }}" class="dg-btn dg-btn--outline">Annuler</a>
            <button type="submit" form="customInvoiceForm" class="dg-btn dg-btn--primary"><i class="bi bi-check-lg"></i>Enregistrer la facture</button>
        </x-slot:actions>
    </x-dg.page-header>

    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ $isEdit ? route('admin.commercial.custom-invoice.update', $invoice) : route('admin.commercial.custom-invoice.store') }}" id="customInvoiceForm"
        data-currency="{{ currency_symbol() }}" data-decimals="{{ app(\App\Services\TaxService::class)->decimalsFor(company_currency()['code']) }}">
        @csrf
        @if($isEdit) @method('PUT') @endif

        <div class="custom-grid mb-6">
            <x-dg.card title="Client et dates" icon="bi-person" color="blue">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="customer">Client</label>
                        <select id="customer" name="customer" class="form-select">
                            <option value="">Sélectionner un client…</option>
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}" @selected($customer === (string) $client->id)>{{ $client->name }}{{ $client->phone ? ' · ' . $client->phone : '' }}</option>
                            @endforeach
                            <option value="new" @selected($customer === 'new')>Autre client (saisie libre)</option>
                        </select>
                    </div>
                    <div class="col-md-12 new-client-field"><label class="form-label" for="newClientName">Nom et prénom</label><input id="newClientName" name="new_client_name" class="form-control" maxlength="255" value="{{ old('new_client_name', $isEdit ? $invoice->client_name : '') }}"></div>
                    <div class="col-md-6 new-client-field"><label class="form-label" for="newClientPhone">Téléphone / WhatsApp</label><input id="newClientPhone" name="new_client_phone" class="form-control" maxlength="255" value="{{ old('new_client_phone', $isEdit ? $invoice->client_phone : '') }}"></div>
                    <div class="col-md-6 new-client-field"><label class="form-label" for="newClientEmail">E-mail</label><input id="newClientEmail" type="email" name="new_client_email" class="form-control" maxlength="255" value="{{ old('new_client_email', $isEdit ? $invoice->client_email : '') }}"></div>
                    <div class="col-md-6"><label class="form-label" for="quoteDate">Date de la facture</label><input id="quoteDate" type="date" name="quote_date" class="form-control" value="{{ $dateValue('quote_date', now()->format('Y-m-d')) }}"></div>
                    <div class="col-md-6"><label class="form-label" for="validUntil">Date limite</label><input id="validUntil" type="date" name="valid_until" class="form-control" value="{{ $dateValue('valid_until', now()->addDays(30)->format('Y-m-d')) }}"></div>
                </div>
            </x-dg.card>

            <x-dg.card title="Conditions" icon="bi-card-checklist" color="teal">
                <div class="row g-3">
                    <div class="col-12"><label class="form-label" for="objet">Objet / type de document</label><input id="objet" name="objet" class="form-control" maxlength="255" value="{{ old('objet', $isEdit ? $invoice->subject : '') }}" placeholder="Ex : livre, brochure, catalogue…"></div>
                    <div class="col-md-6">
                        <label class="form-label" for="paymentMethod">Mode de règlement prévu</label>
                        <select id="paymentMethod" name="payment_method" class="form-select">
                            <option value="">Sélectionner…</option>
                            @foreach(['Espèces', 'Chèque', 'Virement', 'Carte bancaire'] as $method)
                                <option value="{{ $method }}" @selected($value('payment_method') === $method)>{{ $method }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6"><label class="form-label" for="deliveryLocation">Lieu de livraison</label><input id="deliveryLocation" name="delivery_location" class="form-control" maxlength="1000" value="{{ $value('delivery_location') }}"></div>
                    <div class="col-md-6"><label class="form-label" for="paymentTerms">Conditions de règlement</label><textarea id="paymentTerms" name="payment_terms" class="form-control" rows="2" maxlength="1000" placeholder="Ex : 50 % à la commande, 50 % à la livraison">{{ $value('payment_terms') }}</textarea></div>
                    <div class="col-md-6"><label class="form-label" for="deliveryTerms">Conditions de livraison</label><textarea id="deliveryTerms" name="delivery_terms" class="form-control" rows="2" maxlength="1000" placeholder="Ex : livraison à domicile, transport inclus">{{ $value('delivery_terms') }}</textarea></div>
                </div>
            </x-dg.card>
        </div>

        <x-dg.card title="Lignes de la facture" icon="bi-list-ul" color="purple" class="mb-6">
            <x-slot:actions>
                <button type="button" class="dg-btn dg-btn--outline dg-btn--sm" id="addItemBtn"><i class="bi bi-plus-lg"></i>Ajouter une ligne</button>
            </x-slot:actions>
            <div id="itemsContainer" class="d-flex flex-column gap-3"></div>
        </x-dg.card>

        <x-dg.card title="Forfaits du catalogue" icon="bi-bookmark-star" color="pink" class="mb-6">
            @if(empty($globalServiceCatalog))
                <p class="dg-muted mb-0" style="font-size:14px">Aucun forfait n’est proposé. Cochez « Proposé en forfait sur la facture personnalisée » sur un service, dans <a href="{{ route('admin.commercial.module', 'services') }}">Mes services</a>.</p>
            @else
                <div class="forfait-grid">
                    @foreach($globalServiceCatalog as $code => $service)
                        <label class="forfait-option">
                            <input type="checkbox" name="global_services[]" value="{{ $code }}" class="form-check-input global-service" data-price="{{ (float) $service['price'] }}" @checked(in_array($code, $selectedServices, true))>
                            <span class="flex-grow-1">{{ $service['label'] }}</span>
                            <strong>{{ money($service['price']) }}</strong>
                        </label>
                    @endforeach
                </div>
            @endif
        </x-dg.card>

        <div class="custom-summary">
            <x-dg.card title="Paiement reçu à la création" icon="bi-cash-coin" color="green">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label" for="paidAmount">Montant payé maintenant</label><input id="paidAmount" name="paid_amount" type="number" class="form-control" min="0" step="0.01" value="{{ old('paid_amount', $isEdit ? (float) $invoice->paid_amount : 0) }}"></div>
                    <div class="col-md-6">
                        <label class="form-label" for="paymentChannel">Reçu en</label>
                        <select id="paymentChannel" name="payment_channel" class="form-select">
                            <option value="">Sélectionner…</option>
                            <option value="cash" @selected(old('payment_channel') === 'cash')>Espèces (caisse)</option>
                            <option value="bank" @selected(old('payment_channel') === 'bank')>Banque</option>
                        </select>
                    </div>
                    <div class="col-12 d-none" id="cashAccountField">
                        <label class="form-label" for="cashAccount">Caisse</label>
                        <select id="cashAccount" name="cash_account_id" class="form-select">
                            <option value="">Sélectionner une caisse…</option>
                            @foreach($cashAccounts as $account)
                                <option value="{{ $account->id }}" @selected((string) old('cash_account_id') === (string) $account->id)>{{ $account->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 d-none" id="bankAccountField">
                        <label class="form-label" for="bankAccount">Banque</label>
                        <select id="bankAccount" name="bank_account_id" class="form-select">
                            <option value="">Sélectionner une banque…</option>
                            @foreach($bankAccounts as $account)
                                <option value="{{ $account->id }}" @selected((string) old('bank_account_id') === (string) $account->id)>{{ $account->name }}{{ $account->bank_name ? ' - ' . $account->bank_name : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <p class="dg-muted mt-3 mb-0" style="font-size:13px">Laissez 0 si rien n’a été payé : un paiement verrouille la facture, qui ne pourra plus être modifiée.</p>
            </x-dg.card>

            <div class="dg-card custom-totals">
                <div class="custom-totals__row"><span>Lignes HT</span><strong id="linesTotal">0</strong></div>
                <div class="custom-totals__row"><span>Forfaits</span><strong id="servicesTotal">0</strong></div>
                <div class="custom-totals__row custom-totals__row--sep"><span>Total HT</span><strong id="subtotalValue">0</strong></div>
                <div class="custom-totals__row">
                    <label for="discountType" class="mb-0">Remise</label>
                    <span class="d-flex gap-2">
                        <select id="discountType" name="discount_type" class="form-select form-select-sm" style="width:120px">
                            <option value="none" @selected($discountType === 'none')>Aucune</option>
                            <option value="percent" @selected($discountType === 'percent')>En %</option>
                            <option value="amount" @selected($discountType === 'amount')>Montant</option>
                        </select>
                        <input id="discountValueInput" name="discount_value" type="number" class="form-control form-control-sm" style="width:120px" min="0" step="0.01" value="{{ $discountValue }}" aria-label="Valeur de la remise">
                    </span>
                </div>
                <div class="custom-totals__row"><span>Montant de la remise</span><strong id="discountValue">0</strong></div>
                <div class="custom-totals__row"><span>Montant net HT</span><strong id="netAfterDiscountValue">0</strong></div>
                <div class="custom-totals__row">
                    <label for="taxRate" class="mb-0">TVA</label>
                    <select id="taxRate" name="tax_rate_id" class="form-select form-select-sm" style="width:170px">
                        @foreach($taxRates as $rate)
                            <option value="{{ $rate->id }}" data-rate="{{ $rate->isZeroRated() ? 0 : (float) $rate->rate }}" @selected($selectedRate ? (string) $selectedRate === (string) $rate->id : $rate->is_default)>{{ $rate->name }}@if(!$rate->isZeroRated()) ({{ rtrim(rtrim(number_format((float) $rate->rate, 3, ',', ' '), '0'), ',') }} %)@endif</option>
                        @endforeach
                    </select>
                </div>
                <div class="custom-totals__row"><span>Montant de TVA</span><strong id="vatValue">0</strong></div>
                <div class="custom-totals__grand"><span>Total TTC</span><strong id="grandTotalValue">0</strong></div>
                <button type="submit" class="dg-btn dg-btn--primary dg-btn--block mt-4"><i class="bi bi-check-lg"></i>Enregistrer la facture</button>
            </div>
        </div>
    </form>
</div>

{{-- Modèle d'une ligne : désignation et montants, puis caractéristiques d'impression repliables. --}}
<template id="itemTemplate">
    <div class="custom-item" data-item>
        <div class="custom-item__main">
            <div class="custom-item__name"><label class="form-label">Désignation</label><input class="form-control" name="items[__I__][item_name]" maxlength="255" placeholder="Ex : Impression de livre" required></div>
            <div><label class="form-label">Catégorie</label><select class="form-select" name="items[__I__][item_category]">@foreach($options['item_category'] as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
            <div><label class="form-label">Unité</label><input class="form-control" name="items[__I__][unit]" maxlength="50"></div>
            <div><label class="form-label">Qté</label><input class="form-control quantity-input" type="number" min="0.001" step="0.001" name="items[__I__][quantity]" value="1"></div>
            <div><label class="form-label">Prix unitaire HT</label><input class="form-control price-input" type="number" min="0" step="0.01" name="items[__I__][price]" value="0"></div>
            <div class="custom-item__total"><span class="form-label d-block">Total HT</span><strong class="total-line">0</strong></div>
            <div class="custom-item__remove"><button type="button" class="dg-icon-btn dg-icon-btn--sm dg-icon-btn--danger remove-item-btn" title="Retirer la ligne" aria-label="Retirer la ligne"><i class="bi bi-x-lg"></i></button></div>
        </div>
        <details class="custom-item__specs">
            <summary><i class="bi bi-sliders me-1"></i>Caractéristiques d’impression</summary>
            <div class="row g-3 mt-1">
                <div class="col-md-4"><label class="form-label">Type de document</label><select class="form-select has-other" name="items[__I__][book_type]">@foreach($options['book_type'] as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select><input class="form-control mt-2 other-field" name="items[__I__][book_type_other]" placeholder="Précisez le type"></div>
                <div class="col-md-4"><label class="form-label">Format</label><select class="form-select has-other" name="items[__I__][book_format]">@foreach($options['book_format'] as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select><input class="form-control mt-2 other-field" name="items[__I__][format_other]" placeholder="Précisez le format"></div>
                <div class="col-md-4"><label class="form-label">Nombre de pages</label><input class="form-control" type="number" min="1" name="items[__I__][page_count]"></div>
                <div class="col-md-4"><label class="form-label">Papier</label><select class="form-select has-other" name="items[__I__][paper_type]">@foreach($options['paper_type'] as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select><input class="form-control mt-2 other-field" name="items[__I__][paper_type_other]" placeholder="Papier et grammage"></div>
                <div class="col-md-4"><label class="form-label">Impression</label><select class="form-select" name="items[__I__][printing_type]">@foreach($options['printing_type'] as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-4"><label class="form-label">Couverture</label><select class="form-select" name="items[__I__][cover_type]">@foreach($options['cover_type'] as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-4"><label class="form-label">Pelliculage</label><select class="form-select" name="items[__I__][lamination]">@foreach($options['lamination'] as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-4"><label class="form-label">Reliure</label><select class="form-select has-other" name="items[__I__][binding_type]">@foreach($options['binding_type'] as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select><input class="form-control mt-2 other-field" name="items[__I__][binding_other]" placeholder="Précisez la reliure"></div>
                <div class="col-md-4"><label class="form-label">Options supplémentaires</label><textarea class="form-control" rows="2" name="items[__I__][additional_options]"></textarea></div>
            </div>
        </details>
    </div>
</template>

<style>
    .custom-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 24px; }
    .custom-item { padding: 16px; border: 1px solid var(--dg-border); border-radius: var(--dg-radius); background: #fbfcfe; }
    .custom-item__main { display: grid; grid-template-columns: minmax(200px, 2.4fr) 1.2fr 1fr .8fr 1.2fr 1.1fr auto; gap: 12px; align-items: end; }
    .custom-item__total { text-align: right; padding-bottom: 10px; }
    .custom-item__total strong { font-variant-numeric: tabular-nums; white-space: nowrap; }
    .custom-item__remove { padding-bottom: 6px; }
    .custom-item__specs { margin-top: 12px; }
    .custom-item__specs summary { cursor: pointer; font-size: 13.5px; font-weight: 600; color: var(--dg-navy); }
    .custom-item .other-field { display: none; }
    .forfait-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
    .forfait-option { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border: 1px solid var(--dg-border); border-radius: var(--dg-radius); cursor: pointer; font-size: 14px; }
    .forfait-option:has(input:checked) { border-color: #db2777; background: rgba(219, 39, 119, .05); }
    .forfait-option .form-check-input { margin: 0; }
    .custom-summary { display: grid; grid-template-columns: minmax(0, 1fr) 400px; gap: 24px; align-items: start; }
    .custom-totals__row { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 6px 0; font-size: 14px; }
    .custom-totals__row strong { font-variant-numeric: tabular-nums; white-space: nowrap; }
    .custom-totals__row--sep { border-top: 1px solid var(--dg-border); margin-top: 4px; padding-top: 10px; }
    .custom-totals__grand { display: flex; justify-content: space-between; align-items: baseline; margin-top: 8px; padding-top: 12px; border-top: 2px solid var(--dg-navy); color: var(--dg-navy); }
    .custom-totals__grand span { font-weight: 600; font-size: 15px; }
    .custom-totals__grand strong { font-size: 22px; white-space: nowrap; }
    @media (max-width: 1199px) { .custom-item__main { grid-template-columns: repeat(3, minmax(0, 1fr)); } .custom-item__name { grid-column: 1 / -1; } }
    @media (max-width: 991px) { .custom-grid, .custom-summary, .forfait-grid { grid-template-columns: 1fr; } }
</style>
<script>
(() => {
    const form = document.getElementById('customInvoiceForm');
    const container = document.getElementById('itemsContainer');
    const template = document.getElementById('itemTemplate');
    const decimals = Number(form.dataset.decimals) || 0;
    const money = value => Number(value || 0).toLocaleString('fr-FR', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }) + ' ' + form.dataset.currency;

    // Un champ « Autre » n'apparaît que si « Autre » est choisi.
    const syncOther = select => {
        const other = select.parentElement.querySelector('.other-field');
        if (other) other.style.display = select.value === 'autre' ? 'block' : 'none';
    };

    const addItem = (item = {}) => {
        const index = container.children.length;
        container.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__I__', index));
        const row = container.lastElementChild;
        Object.entries(item || {}).forEach(([key, value]) => {
            const field = row.querySelector('[name="items[' + index + '][' + key + ']"]');
            if (field && value !== null && value !== undefined) field.value = value;
        });
        row.querySelectorAll('.has-other').forEach(syncOther);
        // Les caractéristiques déjà renseignées restent visibles.
        if (item && (item.book_type || item.additional_options)) row.querySelector('details').open = true;
        return row;
    };

    const reindex = () => {
        [...container.children].forEach((row, index) => {
            row.querySelectorAll('[name^="items["]').forEach(field => {
                field.name = field.name.replace(/^items\[\d+\]/, 'items[' + index + ']');
            });
        });
    };

    const recalculate = () => {
        let lines = 0;
        container.querySelectorAll('[data-item]').forEach(row => {
            const amount = (Number(row.querySelector('.quantity-input').value) || 0) * (Number(row.querySelector('.price-input').value) || 0);
            row.querySelector('.total-line').textContent = money(amount);
            lines += amount;
        });
        let services = 0;
        document.querySelectorAll('.global-service:checked').forEach(box => { services += Number(box.dataset.price) || 0; });
        const base = lines + services;
        const type = document.getElementById('discountType').value;
        const value = Number(document.getElementById('discountValueInput').value) || 0;
        const discount = Math.min(base, type === 'percent' ? base * value / 100 : (type === 'amount' ? value : 0));
        const net = base - discount;
        const rate = Number(document.getElementById('taxRate').selectedOptions[0]?.dataset.rate) || 0;
        const vat = net * rate / 100;
        document.getElementById('linesTotal').textContent = money(lines);
        document.getElementById('servicesTotal').textContent = money(services);
        document.getElementById('subtotalValue').textContent = money(base);
        document.getElementById('discountValue').textContent = money(discount);
        document.getElementById('netAfterDiscountValue').textContent = money(net);
        document.getElementById('vatValue').textContent = money(vat);
        document.getElementById('grandTotalValue').textContent = money(net + vat);
    };

    // Client : les champs libres ne servent que pour un client hors carnet.
    const customer = document.getElementById('customer');
    const syncCustomer = () => document.querySelectorAll('.new-client-field').forEach(field => field.classList.toggle('d-none', customer.value !== 'new'));
    customer.addEventListener('change', syncCustomer);

    // Paiement immédiat : caisse ou banque.
    const channel = document.getElementById('paymentChannel');
    const syncChannel = () => {
        document.getElementById('cashAccountField').classList.toggle('d-none', channel.value !== 'cash');
        document.getElementById('bankAccountField').classList.toggle('d-none', channel.value !== 'bank');
    };
    channel.addEventListener('change', syncChannel);

    document.getElementById('addItemBtn').addEventListener('click', () => { addItem().querySelector('input').focus(); recalculate(); });
    container.addEventListener('change', event => { if (event.target.matches('.has-other')) syncOther(event.target); });
    container.addEventListener('click', event => {
        if (!event.target.closest('.remove-item-btn')) return;
        if (container.children.length > 1) {
            event.target.closest('[data-item]').remove();
            reindex();
            recalculate();
        }
    });
    form.addEventListener('input', recalculate);
    form.addEventListener('change', recalculate);

    const items = @json($items);
    (items.length ? items : [{}]).forEach(addItem);
    syncCustomer();
    syncChannel();
    recalculate();
})();
</script>
@endsection
