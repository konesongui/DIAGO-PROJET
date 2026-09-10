@extends('admin.layout')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-6">
    <div>
        <div class="text-uppercase text-muted fs-8 fw-bold ls-1">Commercial / Proforma</div>
        <h2 class="fs-2 fw-bold text-dark mb-1">{{ isset($proforma) ? 'Modifier le proforma' : 'Créer un proforma' }}</h2>
        <p class="text-muted mb-0">Saisissez les informations de la proforma et ajoutez ses produits ou services.</p>
    </div>
    <a href="{{ route('admin.commercial.module', 'proforma') }}" class="btn btn-light"><i class="ki-duotone ki-left fs-2 me-2"></i>Retour aux proformas</a>
</div>
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<form method="POST" action="{{ isset($proforma) ? route('admin.commercial.proforma.update', $proforma) : route('admin.commercial.proforma.store') }}" id="proformaForm">
@csrf
@if(isset($proforma)) @method('PUT') @endif
<div class="card border-0 mb-5">
    <div class="card-header border-0"><h3 class="card-title fs-4 fw-bold">Informations générales</h3></div>
    <div class="card-body">
        <div class="row g-4">
            <div class="col-md-6"><label class="form-label required">Client</label><select class="form-select" name="client_id" id="clientSelect"><option value="">Sélectionner</option>@foreach($clients as $client)<option value="{{ $client->id }}" @selected(isset($proforma) && $proforma->client_id === $client->id)>{{ $client->name }} ({{ $client->phone ?: '000' }})</option>@endforeach</select><div class="form-text">Sélectionnez un client existant ou utilisez les champs de création.</div></div>
            <div class="col-md-3"><label class="form-label required">Date de création</label><input type="date" class="form-control" name="creation_date" value="{{ isset($proforma) ? $proforma->creation_date->format('Y-m-d') : now()->format('Y-m-d') }}" required></div>
            <div class="col-md-3"><label class="form-label">Date limite</label><input type="date" class="form-control" name="due_date" value="{{ isset($proforma->due_date) && $proforma->due_date ? $proforma->due_date->format('Y-m-d') : '' }}"></div>
            <div class="col-md-6"><label class="form-label">Nouveau client</label><input class="form-control" name="new_client_name" placeholder="Nom du client"></div>
            <div class="col-md-3"><label class="form-label">Téléphone</label><input class="form-control" name="new_client_phone"></div>
            <div class="col-md-3"><label class="form-label">Email</label><input type="email" class="form-control" name="new_client_email"></div>
            <div class="col-md-3"><label class="form-label">Mode de paiement</label><select class="form-select" name="payment_method"><option value="">Sélectionner...</option>@foreach(['Espèces','Chèque','Virement','Carte bancaire'] as $method)<option @selected(isset($proforma) && $proforma->payment_method === $method)>{{ $method }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">Termes de paiements</label><input class="form-control" name="payment_terms" value="{{ $proforma->payment_terms ?? '' }}"></div>
            <div class="col-md-4"><label class="form-label">Termes de livraison</label><input class="form-control" name="delivery_terms" value="{{ $proforma->delivery_terms ?? '' }}"></div>
            <div class="col-md-4"><label class="form-label">Lieu de livraison</label><input class="form-control" name="delivery_location" value="{{ $proforma->delivery_location ?? '' }}"></div>
            <div class="col-12"><label class="form-label">Objet</label><textarea class="form-control" name="subject" rows="2">{{ $proforma->subject ?? '' }}</textarea></div>
        </div>
    </div>
</div>

<div class="card border-0 mb-5">
    <div class="card-header border-0 d-flex justify-content-between align-items-center"><h3 class="card-title fs-4 fw-bold">Articles / services</h3><button type="button" class="btn btn-sm btn-light-primary" id="addProformaLine"><i class="ki-duotone ki-plus fs-2 me-1"></i>Ajouter une ligne</button></div>
    <div class="card-body">
        <div class="table-responsive"><table class="table table-bordered align-middle" id="proformaLines"><thead><tr><th>Type</th><th>Catégorie</th><th>Article / Service</th><th>Unité / Durée</th><th>Qté</th><th>Prix unitaire</th><th>Remise</th><th></th></tr></thead><tbody></tbody></table></div>
        <div class="row justify-content-end mt-5"><div class="col-md-5 col-lg-4"><div class="d-flex justify-content-between mb-2"><span>Total HT</span><strong id="totalHt">0,00</strong></div><div class="d-flex justify-content-between mb-2"><span>Total remise</span><strong id="totalDiscount">0,00</strong></div><div class="d-flex justify-content-between mb-2"><span>Montant net HT</span><strong id="netHt">0,00</strong></div><div class="d-flex justify-content-between align-items-center mb-2"><label for="taxRate">TVA (%)</label><select class="form-select form-select-sm w-50" name="tax_rate" id="taxRate"><option value="0" @selected(isset($proforma) && (float) $proforma->tax_rate == 0)>Aucune taxe (0 %)</option>@foreach($taxRates ?? [] as $rate)<option value="{{ (float) $rate->rate }}" @selected(isset($proforma) ? (float) $proforma->tax_rate === (float) $rate->rate : $rate->is_default)>{{ $rate->name }}@if(!$rate->isZeroRated()) ({{ rtrim(rtrim(number_format((float) $rate->rate, 3, ',', ' '), '0'), ',') }} %)@endif</option>@endforeach</select></div><div class="separator my-3"></div><div class="d-flex justify-content-between fs-3"><strong>Total TTC</strong><strong id="totalTtc">0,00</strong></div></div></div>
    </div>
</div>
<div class="d-flex justify-content-end gap-3"><a href="{{ route('admin.commercial.module', 'proforma') }}" class="btn btn-light">Annuler</a><button class="btn btn-primary" type="submit">{{ isset($proforma) ? 'Enregistrer les modifications' : 'Enregistrer le proforma' }}</button></div>
</form>

<template id="proformaLineTemplate"><tr><td><select class="form-select form-select-sm" name="lines[__INDEX__][type]"><option value="product">Produit</option><option value="service">Service</option></select></td><td><input class="form-control form-control-sm" name="lines[__INDEX__][category]"></td><td><input class="form-control form-control-sm" name="lines[__INDEX__][item_name]" required></td><td><input class="form-control form-control-sm" name="lines[__INDEX__][unit]"></td><td><input class="form-control form-control-sm line-quantity" type="number" name="lines[__INDEX__][quantity]" value="1" min="0.001" step="0.001" required></td><td><input class="form-control form-control-sm line-price" type="number" name="lines[__INDEX__][unit_price]" value="0" min="0" step="0.01" required></td><td><div class="input-group input-group-sm"><input class="form-control line-discount" type="number" name="lines[__INDEX__][discount]" value="0" min="0" step="0.01"><select class="form-select line-discount-type" name="lines[__INDEX__][discount_type]"><option value="percent">%</option><option value="amount">{{ currency_symbol() }}</option></select></div></td><td><button type="button" class="btn btn-sm btn-light-danger remove-line">&times;</button></td></tr></template>
<script>
(() => {
    const tbody = document.querySelector('#proformaLines tbody');
    const template = document.getElementById('proformaLineTemplate');
    const money = (value) => Number(value || 0).toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const addLine = () => { const index = tbody.children.length; tbody.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', index)); };
    const totals = () => {
        let ht = 0, discount = 0;
        tbody.querySelectorAll('tr').forEach((row) => {
            const quantity = Number(row.querySelector('.line-quantity').value) || 0;
            const price = Number(row.querySelector('.line-price').value) || 0;
            const value = Number(row.querySelector('.line-discount').value) || 0;
            const gross = quantity * price;
            const reduction = row.querySelector('.line-discount-type').value === 'amount' ? value : gross * value / 100;
            ht += gross;
            discount += Math.min(gross, reduction);
        });
        const net = ht - discount;
        const tax = net * (Number(document.getElementById('taxRate').value) || 0) / 100;
        document.getElementById('totalHt').textContent = money(ht);
        document.getElementById('totalDiscount').textContent = money(discount);
        document.getElementById('netHt').textContent = money(net);
        document.getElementById('totalTtc').textContent = money(net + tax);
    };
    document.getElementById('addProformaLine').addEventListener('click', addLine);
    document.getElementById('taxRate').addEventListener('change', totals);
    tbody.addEventListener('input', totals);
    tbody.addEventListener('change', totals);
    tbody.addEventListener('click', (event) => { if (event.target.closest('.remove-line')) { event.target.closest('tr').remove(); totals(); } });
    const existingLines = @json(isset($proforma) ? $proforma->lines : []);
    if (existingLines.length) {
        existingLines.forEach((line) => {
            addLine();
            const row = tbody.lastElementChild;
            row.querySelector('[name$="[type]"]').value = line.type || 'product';
            row.querySelector('[name$="[category]"]').value = line.category || '';
            row.querySelector('[name$="[item_name]"]').value = line.item_name || '';
            row.querySelector('[name$="[unit]"]').value = line.unit || '';
            row.querySelector('.line-quantity').value = line.quantity || 1;
            row.querySelector('.line-price').value = line.unit_price || 0;
            row.querySelector('.line-discount').value = line.discount || 0;
            row.querySelector('.line-discount-type').value = line.discount_type || 'percent';
        });
    } else {
        addLine();
    }
    totals();
})();
</script>
@endsection
