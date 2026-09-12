@extends('admin.layout')

@section('content')
@php
    $isEdit = isset($sale);
    // Après une erreur, la saisie est reprise telle quelle ; en modification, les lignes de la vente.
    $lines = old('lines', $isEdit ? ($sale->lines ?? []) : []);
    $clientChoice = (string) old('client_id', $isEdit ? $sale->client_id : '');
    $selectedRate = old('tax_rate_id', $isEdit ? $sale->tax_rate_id : null);
    $method = old('payment_method', $isEdit ? $sale->payment_method : 'cash');
    $paid = old('paid_amount', $isEdit ? (float) $sale->paid_amount : '');
    $lastSale = session('pos_sale_id') ? \App\Models\PosSale::find(session('pos_sale_id')) : null;
    $catalog = $services->map(fn ($service) => ['id' => $service->id, 'name' => $service->name, 'price_ht' => (float) $service->price, 'unit' => $service->unit])->values();
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" :subtitle="$isEdit ? 'Corrigez les articles ou le client : la caisse, la TVA et le journal suivent.' : 'Vente au comptoir : le prix saisi est le prix payé par le client, TVA comprise.'" :back="route('admin.commercial.module', 'point-de-vente')" back-label="Point de vente">
        <x-slot:actions>
            <a href="{{ route('admin.commercial.module', 'point-de-vente') }}" class="dg-btn dg-btn--outline">{{ $isEdit ? 'Annuler' : 'Voir les ventes' }}</a>
        </x-slot:actions>
    </x-dg.page-header>

    @if(session('success'))
        <div class="dg-callout dg-tone-green mb-6 pos-done">
            <span class="dg-tile"><i class="bi bi-check2-circle"></i></span>
            <div class="flex-grow-1" style="min-width:220px">
                <div class="fw-semibold">{{ session('success') }}</div>
                <div class="dg-muted" style="font-size:13.5px">L’écran est prêt pour la vente suivante.</div>
            </div>
            @if(session('pos_change'))
                <div class="pos-done__change"><span>Monnaie à rendre</span><strong>{{ session('pos_change') }}</strong></div>
            @endif
            @if($lastSale)
                <a href="{{ route('admin.commercial.pos.show', $lastSale) }}" class="dg-btn dg-btn--outline dg-btn--sm"><i class="bi bi-receipt"></i>Voir la vente</a>
            @endif
        </div>
    @endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ $isEdit ? route('admin.commercial.pos.update', $sale) : route('admin.commercial.pos.store') }}" id="posForm"
        data-currency="{{ currency_symbol() }}" data-decimals="{{ app(\App\Services\TaxService::class)->decimalsFor(company_currency()['code']) }}">
        @csrf
        @if($isEdit) @method('PUT') @endif

        <div class="pos-layout">
            <div>
                <x-dg.card title="Articles et services" icon="bi-basket" color="purple">
                    <x-slot:actions>
                        <button type="button" class="dg-btn dg-btn--outline dg-btn--sm" id="addPosLine"><i class="bi bi-plus-lg"></i>Ligne libre</button>
                    </x-slot:actions>
                    @if($catalog->isNotEmpty())
                        <div class="pos-catalog mb-4" aria-label="Services du catalogue">
                            @foreach($catalog as $service)
                                <button type="button" class="pos-catalog__item" data-service="{{ $service['id'] }}">
                                    <span class="fw-semibold">{{ $service['name'] }}</span>
                                    <span class="pos-catalog__price" data-price-ht="{{ $service['price_ht'] }}"></span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 no-export no-column-sort pos-lines" id="posLines">
                            <thead>
                                <tr>
                                    <th>Désignation</th>
                                    <th style="width:78px">Qté</th>
                                    <th style="width:124px">Prix unitaire TTC</th>
                                    <th class="text-end" style="width:124px">Total</th>
                                    <th style="width:44px"><span class="visually-hidden">Retirer</span></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <p class="dg-muted mt-3 mb-0" style="font-size:13px"><i class="bi bi-info-circle me-1"></i>Le catalogue porte des prix HT : un service repris ici affiche son prix TTC au taux choisi. Changer de taux recalcule ces prix, pas ceux saisis à la main.</p>
                </x-dg.card>
            </div>

            <div class="pos-side">
                <x-dg.card title="Encaissement" icon="bi-cash-coin" color="green">
                    <label class="form-label" for="posClient">Client</label>
                    <select id="posClient" name="client_id" class="form-select mb-3">
                        <option value="">Client comptoir</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}" @selected($clientChoice === (string) $client->id)>{{ $client->name }}</option>
                        @endforeach
                    </select>

                    <div class="pos-total">
                        <span>Total à payer</span>
                        <strong id="posTotal">0</strong>
                        <div class="pos-total__tax">
                            <select name="tax_rate_id" id="posTaxRate" class="form-select form-select-sm" aria-label="Taxe incluse">
                                @foreach($taxRates as $rate)
                                    <option value="{{ $rate->id }}" data-rate="{{ $rate->isZeroRated() ? 0 : (float) $rate->rate }}" @selected($selectedRate ? (string) $selectedRate === (string) $rate->id : $rate->is_default)>{{ $rate->name }}@if(!$rate->isZeroRated()) ({{ rtrim(rtrim(number_format((float) $rate->rate, 3, ',', ' '), '0'), ',') }} %)@endif</option>
                                @endforeach
                            </select>
                            <span>dont <strong id="posTaxAmount">0</strong></span>
                        </div>
                    </div>

                    @if($isEdit)
                        {{-- L'argent est déjà dans cette caisse ou sur ce compte : le mode ne se change pas. --}}
                        <input type="hidden" id="posPaymentFixed" value="{{ $sale->payment_method }}">
                        <div class="pos-fixed-payment mb-3">
                            <i class="bi {{ $sale->payment_method === 'cash' ? 'bi-cash-stack' : 'bi-bank' }}"></i>
                            <div>
                                <div class="fw-semibold">{{ $sale->paymentLabel() }}</div>
                                <div class="dg-muted" style="font-size:12.5px">Le mode de paiement ne se modifie pas : annulez la vente pour la ressaisir autrement.</div>
                            </div>
                        </div>
                    @else
                        <span class="form-label d-block">Mode de paiement</span>
                        <div class="pos-methods mb-3" role="radiogroup" aria-label="Mode de paiement">
                            <label class="pos-method"><input type="radio" name="payment_method" value="cash" @checked($method === 'cash')><i class="bi bi-cash-stack"></i>Espèces</label>
                            <label class="pos-method"><input type="radio" name="payment_method" value="bank" @checked($method === 'bank')><i class="bi bi-bank"></i>Banque</label>
                        </div>
                        <div id="posCashField" class="mb-3">
                            <label class="form-label" for="posCashAccount">Caisse</label>
                            <select id="posCashAccount" name="cash_account_id" class="form-select">
                                @if($cashAccounts->count() !== 1)<option value="">Sélectionner une caisse…</option>@endif
                                @foreach($cashAccounts as $account)
                                    <option value="{{ $account->id }}" @selected((string) old('cash_account_id') === (string) $account->id)>{{ $account->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div id="posBankField" class="mb-3">
                            <label class="form-label" for="posBankAccount">Compte bancaire</label>
                            <select id="posBankAccount" name="bank_account_id" class="form-select">
                                @if($bankAccounts->count() !== 1)<option value="">Sélectionner un compte…</option>@endif
                                @foreach($bankAccounts as $account)
                                    <option value="{{ $account->id }}" @selected((string) old('bank_account_id') === (string) $account->id)>{{ $account->name }}{{ $account->bank_name ? ' · ' . $account->bank_name : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div id="posCashOnly">
                        <label class="form-label" for="posPaidAmount">Montant reçu</label>
                        <input id="posPaidAmount" name="paid_amount" type="number" min="0" step="0.01" class="form-control form-control-lg" value="{{ $paid }}" placeholder="0">
                        <div class="pos-quick mt-2" id="posQuick" aria-label="Montants rapides"></div>
                        <div class="pos-change mt-3" id="posChangeBox">
                            <span id="posChangeLabel">Monnaie à rendre</span>
                            <strong id="posChange">0</strong>
                        </div>
                    </div>

                    <button type="submit" class="dg-btn dg-btn--primary dg-btn--block mt-4"><i class="bi bi-check-lg"></i>{{ $isEdit ? 'Enregistrer les modifications' : 'Enregistrer la vente' }}</button>
                </x-dg.card>
            </div>
        </div>
    </form>
</div>

<datalist id="posServicesList">@foreach($catalog as $service)<option value="{{ $service['name'] }}"></option>@endforeach</datalist>
<template id="posLineTemplate">
    <tr>
        <td>
            <input class="form-control line-name" name="lines[__INDEX__][item_name]" list="posServicesList" maxlength="190" required placeholder="Service du catalogue ou désignation libre" aria-label="Désignation">
            <input type="hidden" class="line-service" name="lines[__INDEX__][service_id]">
        </td>
        <td><input class="form-control line-quantity" name="lines[__INDEX__][quantity]" type="number" min="0.001" step="0.001" value="1" required aria-label="Quantité"></td>
        <td><input class="form-control line-price" name="lines[__INDEX__][unit_price]" type="number" min="0" step="0.01" value="0" required aria-label="Prix unitaire TTC"></td>
        <td class="text-end dg-cell-num fw-semibold line-total">0</td>
        <td class="text-end"><button type="button" class="dg-icon-btn dg-icon-btn--sm dg-icon-btn--danger remove-line" title="Retirer la ligne" aria-label="Retirer la ligne"><i class="bi bi-x-lg"></i></button></td>
    </tr>
</template>

<style>
    .pos-layout { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 24px; align-items: start; }
    .pos-side { position: sticky; top: 90px; }
    .pos-catalog { display: flex; flex-wrap: wrap; gap: 8px; }
    .pos-catalog__item { display: inline-flex; flex-direction: column; align-items: flex-start; gap: 2px; padding: 8px 12px; border: 1px solid var(--dg-border); border-radius: var(--dg-radius); background: #fbfcfe; font-family: var(--dg-font); font-size: 13px; color: var(--dg-text); text-align: left; cursor: pointer; transition: border-color .15s ease, background .15s ease; }
    .pos-catalog__item:hover { border-color: #7c3aed; background: rgba(124, 58, 237, .05); }
    .pos-catalog__price { font-size: 12px; font-weight: 600; color: #7c3aed; }
    .pos-lines { min-width: 520px; }
    .pos-lines td:first-child { min-width: 200px; }
    .dg-scope .pos-lines > thead > tr > th, .dg-scope .pos-lines > tbody > tr > td { padding-left: 6px !important; padding-right: 6px !important; }
    .pos-total { margin-bottom: 16px; padding: 16px; border-radius: var(--dg-radius); background: var(--dg-navy); color: #fff; }
    .pos-total > span { display: block; font-size: 13px; opacity: .8; }
    .pos-total > strong { display: block; margin: 2px 0 10px; font-size: 30px; line-height: 1.1; color: var(--dg-yellow); white-space: nowrap; }
    .pos-total__tax { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px 10px; font-size: 13px; }
    .pos-total__tax > span { white-space: nowrap; }
    .pos-total__tax .form-select { max-width: 180px; }
    .pos-methods { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .pos-method { display: flex; align-items: center; justify-content: center; gap: 8px; min-height: 46px; border: 1px solid var(--dg-border-strong); border-radius: var(--dg-radius); font-weight: 600; cursor: pointer; }
    .pos-method input { position: absolute; opacity: 0; pointer-events: none; }
    .pos-method:has(input:checked) { border-color: #059669; background: rgba(5, 150, 105, .08); color: #059669; }
    .pos-method:has(input:focus-visible) { box-shadow: var(--dg-focus); }
    .pos-fixed-payment { display: flex; gap: 12px; align-items: flex-start; padding: 12px 14px; border-radius: var(--dg-radius); background: var(--dg-table-head); }
    .pos-fixed-payment .bi { font-size: 20px; color: #059669; }
    .pos-quick { display: flex; flex-wrap: wrap; gap: 6px; }
    .pos-quick button { padding: 5px 10px; border: 1px solid var(--dg-border-strong); border-radius: 999px; background: #fff; font-size: 12.5px; font-weight: 600; color: var(--dg-text); }
    .pos-quick button:hover { border-color: var(--dg-navy); color: var(--dg-navy); }
    .pos-change { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: baseline; gap: 4px 12px; padding: 12px 14px; border-radius: var(--dg-radius); background: rgba(5, 150, 105, .08); color: #059669; }
    .pos-change span { font-weight: 600; font-size: 14px; }
    .pos-change strong { font-size: 21px; white-space: nowrap; margin-left: auto; }
    .pos-change.is-short { background: var(--dg-danger-bg); color: var(--dg-danger); }
    .pos-done__change { display: flex; flex-direction: column; align-items: flex-end; padding: 6px 14px; border-radius: var(--dg-radius); background: rgba(5, 150, 105, .08); color: #059669; }
    .pos-done__change span { font-size: 12.5px; font-weight: 600; }
    .pos-done__change strong { font-size: 20px; }
    @media (max-width: 1199px) { .pos-layout { grid-template-columns: 1fr; } .pos-side { position: static; } }
</style>
<script>
(() => {
    const form = document.getElementById('posForm');
    const body = document.querySelector('#posLines tbody');
    const template = document.getElementById('posLineTemplate');
    const catalog = @json($catalog);
    const decimals = Number(form.dataset.decimals) || 0;
    const round = value => Math.round(value * 10 ** decimals) / 10 ** decimals;
    const money = value => Number(value || 0).toLocaleString('fr-FR', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }) + ' ' + form.dataset.currency;
    const taxSelect = document.getElementById('posTaxRate');
    const rate = () => Number(taxSelect.selectedOptions[0]?.dataset.rate) || 0;
    // Le catalogue porte des prix HT : en caisse, le prix proposé est TTC, au taux choisi.
    const priceTtc = ht => round((Number(ht) || 0) * (1 + rate() / 100));
    const paid = document.getElementById('posPaidAmount');
    const fixedMethod = document.getElementById('posPaymentFixed')?.value;
    const method = () => fixedMethod || form.querySelector('input[name="payment_method"]:checked')?.value || 'cash';
    let paidTouched = paid.value !== '';

    const reindex = () => body.querySelectorAll('tr').forEach((row, index) => {
        row.querySelectorAll('[name^="lines["]').forEach(field => { field.name = field.name.replace(/^lines\[\d+\]/, 'lines[' + index + ']'); });
    });

    const applyService = (row, service) => {
        row.querySelector('.line-service').value = service ? service.id : '';
        if (!service) return;
        row.querySelector('.line-name').value = service.name;
        const price = row.querySelector('.line-price');
        price.value = priceTtc(service.price_ht);
        price.dataset.priceHt = service.price_ht;
    };

    const add = (line = null) => {
        body.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', body.children.length));
        const row = body.lastElementChild;
        if (line) {
            row.querySelector('.line-name').value = line.item_name || '';
            row.querySelector('.line-service').value = line.service_id || '';
            row.querySelector('.line-quantity').value = Number(line.quantity) || 1;
            row.querySelector('.line-price').value = Number(line.unit_price) || 0;
        }
        return row;
    };

    const total = () => {
        let sum = 0;
        body.querySelectorAll('tr').forEach(row => {
            const lineTotal = (Number(row.querySelector('.line-quantity').value) || 0) * (Number(row.querySelector('.line-price').value) || 0);
            row.querySelector('.line-total').textContent = money(lineTotal);
            sum += lineTotal;
        });
        sum = round(sum);
        // La taxe est extraite du total : le montant à encaisser reste celui des lignes.
        const tax = rate() > 0 ? round(sum - sum / (1 + rate() / 100)) : 0;
        document.getElementById('posTotal').textContent = money(sum);
        document.getElementById('posTaxAmount').textContent = money(tax);
        document.querySelectorAll('.pos-catalog__price').forEach(label => { label.textContent = money(priceTtc(label.dataset.priceHt)); });

        // Espèces : montant reçu et monnaie ; banque : le montant exact, sans monnaie.
        const isCash = method() === 'cash';
        document.getElementById('posCashOnly').classList.toggle('d-none', !isCash);
        paid.disabled = !isCash;
        if (isCash && !paidTouched) paid.value = sum || '';
        const received = Number(paid.value) || 0;
        const change = received - sum;
        document.getElementById('posChange').textContent = money(Math.abs(change));
        document.getElementById('posChangeLabel').textContent = change >= 0 ? 'Monnaie à rendre' : 'Il manque';
        document.getElementById('posChangeBox').classList.toggle('is-short', change < 0);

        // Montants rapides : le montant exact, puis les coupures rondes au-dessus.
        const quick = document.getElementById('posQuick');
        const steps = [...new Set([sum, ...[1000, 5000, 10000].map(step => Math.ceil(sum / step) * step)].filter(value => value >= sum && value > 0))].slice(0, 4);
        quick.innerHTML = steps.map((value, index) => '<button type="button" data-amount="' + value + '">' + (index === 0 ? 'Montant exact' : money(value)) + '</button>').join('');
    };

    const syncMethod = () => {
        if (fixedMethod) return total();
        const isCash = method() === 'cash';
        document.getElementById('posCashField').classList.toggle('d-none', !isCash);
        document.getElementById('posBankField').classList.toggle('d-none', isCash);
        document.getElementById('posCashAccount').required = isCash;
        document.getElementById('posBankAccount').required = !isCash;
        total();
    };

    document.getElementById('addPosLine').addEventListener('click', () => { add().querySelector('.line-name').focus(); total(); });
    // Un clic sur un service du catalogue : s'il est déjà dans la vente, sa quantité augmente.
    document.querySelectorAll('.pos-catalog__item').forEach(button => button.addEventListener('click', () => {
        const service = catalog.find(item => String(item.id) === button.dataset.service);
        const existing = [...body.querySelectorAll('tr')].find(row => row.querySelector('.line-service').value === String(service.id));
        if (existing) {
            const quantity = existing.querySelector('.line-quantity');
            quantity.value = (Number(quantity.value) || 0) + 1;
        } else {
            const empty = [...body.querySelectorAll('tr')].find(row => !row.querySelector('.line-name').value.trim());
            applyService(empty || add(), service);
        }
        total();
    }));
    body.addEventListener('change', event => {
        if (!event.target.matches('.line-name')) return;
        const service = catalog.find(item => item.name.toLowerCase() === event.target.value.trim().toLowerCase());
        const row = event.target.closest('tr');
        if (service) applyService(row, service); else row.querySelector('.line-service').value = '';
        total();
    });
    body.addEventListener('input', event => {
        // Un prix saisi à la main n'est plus recalculé au changement de taux.
        if (event.target.matches('.line-price')) delete event.target.dataset.priceHt;
        total();
    });
    body.addEventListener('click', event => {
        if (!event.target.closest('.remove-line')) return;
        if (body.children.length > 1) {
            event.target.closest('tr').remove();
            reindex();
        } else {
            body.firstElementChild.querySelectorAll('input').forEach(input => { input.value = input.classList.contains('line-quantity') ? 1 : (input.classList.contains('line-price') ? 0 : ''); });
        }
        total();
    });
    taxSelect.addEventListener('change', () => {
        body.querySelectorAll('.line-price[data-price-ht]').forEach(price => { price.value = priceTtc(price.dataset.priceHt); });
        total();
    });
    form.querySelectorAll('input[name="payment_method"]').forEach(radio => radio.addEventListener('change', syncMethod));
    paid.addEventListener('input', () => { paidTouched = paid.value !== ''; total(); });
    document.getElementById('posQuick').addEventListener('click', event => {
        const button = event.target.closest('[data-amount]');
        if (!button) return;
        paid.value = button.dataset.amount;
        paidTouched = true;
        total();
    });

    const lines = @json(array_values($lines));
    (lines.length ? lines : [null]).forEach(add);
    syncMethod();
})();
</script>
@endsection
