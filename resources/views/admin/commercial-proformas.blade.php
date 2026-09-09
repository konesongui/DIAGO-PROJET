@extends('admin.layout')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-6">
    <div><div class="text-uppercase text-muted fs-8 fw-bold ls-1">Commercial</div><h2 class="fs-2 fw-bold text-dark mb-1">Proformas</h2><p class="text-muted mb-0">Créez une proforma avec produits, services, remises et taxes.</p></div>
    <a class="btn btn-primary" href="{{ route('admin.commercial.proforma.create') }}"><i class="ki-duotone ki-plus fs-2 me-2"></i>Créer un proforma</a>
</div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('admin.commercial.proforma.bulkDestroy') }}" id="bulkProformaForm">@csrf @method('DELETE')</form>
<div class="d-flex justify-content-between align-items-center mb-3">
    <span class="text-muted small" id="selectedProformasLabel">Aucun proforma sélectionné</span>
    <div class="d-flex gap-2">
        <select class="form-select form-select-sm" id="bulkProformaAction" disabled><option value="">Action sélectionnée</option><option value="edit">Modifier</option><option value="delete">Supprimer</option><option value="print">Imprimer</option><option value="email">Envoyer par mail</option></select>
        <button type="button" class="btn btn-sm btn-primary" id="applyProformaAction" disabled>Appliquer</button>
    </div>
</div>
<div class="card border-0">
    <div class="card-header border-0"><h3 class="card-title fs-4 fw-bold">Proformas récentes</h3></div>
    <div class="card-body pt-0 table-responsive">
        <table class="table align-middle table-row-bordered">
            <thead><tr><th><input class="form-check-input" type="checkbox" id="selectAllProformas"></th><th>Client</th><th>Date</th><th>Lignes</th><th>Total HT</th><th>Total TTC</th><th>État</th><th class="text-end">Action</th></tr></thead>
            <tbody>
            @forelse($proformas as $proforma)
                <tr><td><input class="form-check-input proforma-checkbox" form="bulkProformaForm" type="checkbox" name="proforma_ids[]" value="{{ $proforma->id }}" data-edit="{{ route('admin.commercial.proforma.edit', $proforma) }}" data-print="{{ route('admin.commercial.proforma.print', $proforma) }}" data-email="{{ route('admin.commercial.proforma.email', $proforma) }}"></td><td class="fw-bold">{{ $proforma->client_name }}<div class="text-muted fs-7">{{ $proforma->client_phone }}</div></td><td>{{ $proforma->creation_date->format('d/m/Y') }}</td><td>{{ $proforma->lines->count() }}</td><td>{{ number_format($proforma->net_ht, 0, ',', ' ') }} XOF</td><td class="fw-bold">{{ number_format($proforma->total_ttc, 0, ',', ' ') }} XOF</td><td><span class="badge badge-light-{{ $proforma->status === 'sent' ? 'success' : 'warning' }}">{{ $proforma->status === 'sent' ? 'Envoyé' : 'Brouillon' }}</span></td><td class="text-end"><div class="dropdown"><button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown">...</button><ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="{{ route('admin.commercial.proforma.edit', $proforma) }}"><i class="ki-duotone ki-pencil me-2"></i>Modifier</a></li><li><a class="dropdown-item" target="_blank" href="{{ route('admin.commercial.proforma.print', $proforma) }}"><i class="ki-duotone ki-printer me-2"></i>Imprimer</a></li><li><button type="button" class="dropdown-item email-proforma" data-url="{{ route('admin.commercial.proforma.email', $proforma) }}" data-email=""><i class="ki-duotone ki-sms me-2"></i>Envoyer par mail</button></li><li><hr class="dropdown-divider"></li><li><form method="POST" action="{{ route('admin.commercial.proforma.destroy', $proforma) }}" onsubmit="return confirm('Supprimer ce proforma ?')">@csrf @method('DELETE')<button class="dropdown-item text-danger"><i class="ki-duotone ki-trash me-2"></i>Supprimer</button></form></li></ul></div></td></tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-8">Aucun proforma enregistré.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<form method="POST" id="emailProformaForm" class="d-none">@csrf</form>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const all = document.getElementById('selectAllProformas');
    const boxes = Array.from(document.querySelectorAll('.proforma-checkbox'));
    const action = document.getElementById('bulkProformaAction');
    const apply = document.getElementById('applyProformaAction');
    const label = document.getElementById('selectedProformasLabel');
    const update = () => { const count = boxes.filter((box) => box.checked).length; action.disabled = count === 0; apply.disabled = count === 0; label.textContent = count ? count + ' proforma(s) sélectionné(s)' : 'Aucun proforma sélectionné'; all.checked = boxes.length > 0 && count === boxes.length; all.indeterminate = count > 0 && count < boxes.length; };
    all.addEventListener('change', () => { boxes.forEach((box) => box.checked = all.checked); update(); });
    boxes.forEach((box) => box.addEventListener('change', update));
    apply.addEventListener('click', () => {
        const selected = boxes.filter((box) => box.checked);
        if (!action.value) return;
        if (action.value === 'edit') {
            if (selected.length !== 1) return alert('Sélectionnez un seul proforma pour le modifier.');
            window.location.href = selected[0].dataset.edit;
        } else if (action.value === 'print') {
            selected.forEach((box) => window.open(box.dataset.print, '_blank'));
        } else if (action.value === 'delete') {
            if (confirm('Supprimer les proformas sélectionnés ?')) document.getElementById('bulkProformaForm').submit();
        } else if (action.value === 'email') {
            const email = prompt('Adresse e-mail du destinataire :');
            if (!email) return;
            const token = document.querySelector('#bulkProformaForm input[name="_token"]').value;
            Promise.all(selected.map((box) => fetch(box.dataset.email, {method: 'POST', headers: {'X-CSRF-TOKEN': token, 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json'}, body: new URLSearchParams({email})}))).then(() => window.location.reload());
        }
    });
    document.querySelectorAll('.email-proforma').forEach((button) => button.addEventListener('click', () => { const email = prompt('Adresse e-mail du destinataire :', button.dataset.email || ''); if (!email) return; const form = document.getElementById('emailProformaForm'); form.action = button.dataset.url; form.innerHTML = '@csrf<input type="hidden" name="email" value="' + email.replace(/"/g, '&quot;') + '">'; form.submit(); }));
});
</script>
@endsection
