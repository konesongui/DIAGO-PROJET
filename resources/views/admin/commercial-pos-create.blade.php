@extends('admin.layout')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-5"><div><div class="text-uppercase text-muted fs-8 fw-bold">Commercial / Point de vente</div><h2 class="fs-2 fw-bold mb-1">Nouvelle vente</h2><p class="text-muted mb-0">Enregistrez une vente comptoir.</p></div><a href="{{ route('admin.commercial.module', 'point-de-vente') }}" class="btn btn-light">Retour</a></div>
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ isset($sale) ? route('admin.commercial.pos.update', $sale) : route('admin.commercial.pos.store') }}" id="posForm">@csrf @if(isset($sale)) @method('PUT') @endif
<div class="row g-5"><div class="col-lg-8"><div class="card border-0"><div class="card-header border-0 d-flex justify-content-between align-items-center"><h3 class="card-title">Articles</h3><button type="button" class="btn btn-sm btn-light-primary" id="addPosLine">Ajouter une ligne</button></div><div class="card-body table-responsive"><table class="table align-middle" id="posLines"><thead><tr><th>Article / service</th><th>Qté</th><th>Prix unitaire</th><th>Total</th><th></th></tr></thead><tbody></tbody></table></div></div></div>
<div class="col-lg-4"><div class="card border-0"><div class="card-header border-0"><h3 class="card-title">Encaissement</h3></div><div class="card-body"><label class="form-label">Client</label><select name="client_id" class="form-select mb-4"><option value="">Client comptoir</option>@foreach($clients as $client)<option value="{{ $client->id }}" @selected(isset($sale) && $sale->client_id === $client->id)>{{ $client->name }}</option>@endforeach</select><label class="form-label">Montant à payer</label><input id="posAmountDue" class="form-control mb-3" readonly value="0"><label class="form-label">Montant payé *</label><input name="paid_amount" id="posPaidAmount" type="number" min="0" step="0.01" class="form-control mb-3" value="{{ $sale->paid_amount ?? 0 }}" required><label class="form-label">Montant rendu</label><input id="posChangeAmount" class="form-control mb-4" readonly value="0"><label class="form-label">Mode de paiement *</label><select name="payment_method" id="posPaymentMethod" class="form-select mb-4" required><option value="">Sélectionner...</option><option value="cash" @selected(isset($sale) && $sale->payment_method === 'cash')>Espèces</option><option value="bank" @selected(isset($sale) && $sale->payment_method === 'bank')>Banque</option></select><div id="posCash" class="d-none"><label class="form-label">Caisse *</label><select name="cash_account_id" class="form-select" disabled><option value="">Sélectionner...</option>@foreach($cashAccounts as $account)<option value="{{ $account->id }}">{{ $account->name }}</option>@endforeach</select></div><div id="posBank" class="d-none"><label class="form-label">Banque *</label><select name="bank_account_id" class="form-select" disabled><option value="">Sélectionner...</option>@foreach($bankAccounts as $account)<option value="{{ $account->id }}">{{ $account->name }} - {{ $account->bank_name }}</option>@endforeach</select></div><div class="separator my-5"></div><div class="d-flex justify-content-between align-items-center mb-3"><label for="posTaxRate" class="mb-0">Taxe incluse</label><select name="tax_rate_id" id="posTaxRate" class="form-select form-select-sm w-50">@foreach($taxRates ?? [] as $rate)<option value="{{ $rate->id }}" data-rate="{{ $rate->isZeroRated() ? 0 : (float) $rate->rate }}" @selected($rate->is_default)>{{ $rate->name }}@if(!$rate->isZeroRated()) ({{ rtrim(rtrim(number_format((float) $rate->rate, 3, ',', ' '), '0'), ',') }} %)@endif</option>@endforeach</select></div><div class="d-flex justify-content-between text-muted fs-7 mb-2"><span>dont taxe</span><span id="posTaxAmount">0</span></div><div class="d-flex justify-content-between fs-2"><strong>Total</strong><strong id="posTotal">0 XOF</strong></div><button class="btn btn-primary w-100 mt-5">Enregistrer la vente</button></div></div></div></div>
</form>
<template id="posLineTemplate"><tr><td><select name="lines[__INDEX__][service_id]" class="form-select service-select" required><option value="">Choisir un service</option>@foreach($services as $service)<option value="{{ $service->id }}" data-name="{{ e($service->name) }}" data-price="{{ $service->price }}">{{ $service->name }}</option>@endforeach</select><input type="hidden" name="lines[__INDEX__][item_name]" class="item-name"></td><td><input name="lines[__INDEX__][quantity]" type="number" min="0.001" step="0.001" value="1" class="form-control line-quantity" required></td><td><input name="lines[__INDEX__][unit_price]" type="number" min="0" step="0.01" value="0" class="form-control line-price" required></td><td class="line-total">0 XOF</td><td><button type="button" class="btn btn-light-danger remove-line">&times;</button></td></tr></template>
<script>
(() => {
    const body = document.querySelector('#posLines tbody');
    const tpl = document.getElementById('posLineTemplate');
    const money = value => Number(value || 0).toLocaleString('fr-FR') + ' XOF';
    const add = () => body.insertAdjacentHTML('beforeend', tpl.innerHTML.replaceAll('__INDEX__', body.children.length));
    const total = () => {
        let sum = 0;
        body.querySelectorAll('tr').forEach(row => {
            const lineTotal = (Number(row.querySelector('.line-quantity').value) || 0) * (Number(row.querySelector('.line-price').value) || 0);
            row.querySelector('.line-total').textContent = money(lineTotal);
            sum += lineTotal;
        });
        document.getElementById('posTotal').textContent = money(sum);

        // La taxe est extraite du total affiche : le montant a encaisser
        // reste exactement celui des lignes.
        const taxSelect = document.getElementById('posTaxRate');
        const rate = Number(taxSelect?.selectedOptions[0]?.dataset.rate) || 0;
        const taxAmount = rate > 0 ? sum - (sum / (1 + rate / 100)) : 0;
        const taxLabel = document.getElementById('posTaxAmount');
        if (taxLabel) { taxLabel.textContent = money(taxAmount); }

        document.getElementById('posAmountDue').value = sum.toFixed(2);
        document.getElementById('posChangeAmount').value = Math.max(0, (Number(document.getElementById('posPaidAmount').value) || 0) - sum).toFixed(2);
    };
    document.getElementById('posTaxRate')?.addEventListener('change', total);
    body.addEventListener('change', event => {
        if (event.target.classList.contains('service-select')) {
            const option = event.target.selectedOptions[0];
            const row = event.target.closest('tr');
            row.querySelector('.item-name').value = option.dataset.name || '';
            row.querySelector('.line-price').value = option.dataset.price || 0;
            total();
        }
    });
    document.getElementById('addPosLine').addEventListener('click', add);
    document.getElementById('posPaidAmount').addEventListener('input', total);
    body.addEventListener('input', total);
    body.addEventListener('click', event => {
        if (event.target.closest('.remove-line')) {
            event.target.closest('tr').remove();
            total();
        }
    });
    document.getElementById('posPaymentMethod').addEventListener('change', event => {
        const cash = event.target.value === 'cash';
        const cashBox = document.getElementById('posCash');
        const bankBox = document.getElementById('posBank');
        const cashSelect = cashBox.querySelector('select');
        const bankSelect = bankBox.querySelector('select');
        cashBox.classList.toggle('d-none', !cash);
        bankBox.classList.toggle('d-none', cash || !event.target.value);
        cashSelect.disabled = !cash;
        cashSelect.required = cash;
        bankSelect.disabled = cash || !event.target.value;
        bankSelect.required = !cash && !!event.target.value;
    });
    document.getElementById('posPaymentMethod').dispatchEvent(new Event('change'));
    const existingLines = @json($sale->lines ?? []);
    if (existingLines.length) {
        existingLines.forEach(line => {
            add();
            const row = body.lastElementChild;
            row.querySelector('.service-select').value = line.service_id || '';
            row.querySelector('.item-name').value = line.item_name || '';
            row.querySelector('.line-quantity').value = line.quantity;
            row.querySelector('.line-price').value = line.unit_price;
        });
    } else {
        add();
    }
    total();
})();
</script>
@endsection
