@extends('admin.layout')

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-body p-6">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-6">
            <div>
                <div class="text-uppercase text-muted fs-8 fw-bold ls-1">Comptabilité</div>
                <h3 class="fs-2 fw-bold text-dark mb-1">{{ $title }}</h3>
                <p class="text-muted mb-0">{{ $subtitle }}</p>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#transferModal"><i class="bi bi-arrow-left-right me-2"></i>Nouveau transfert</button>
                <a href="{{ route('admin.comptabilite') }}" class="btn btn-light">Retour</a>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <div class="modal fade" id="transferModal" tabindex="-1" aria-labelledby="transferModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('admin.comptabilite.transfers.store') }}">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="transferModalLabel">Nouveau transfert</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body"><div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Compte source</label>
                    <div class="input-group">
                        <select name="source_type" id="source_type" class="form-select" style="max-width: 145px;">
                            <option value="cash">Caisse</option>
                            <option value="bank">Banque</option>
                        </select>
                        <select name="source_id" id="source_id" class="form-select" required></select>
                    </div>
                    <div id="source_balance" class="form-text"></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Compte destination</label>
                    <div class="input-group">
                        <select name="destination_type" id="destination_type" class="form-select" style="max-width: 145px;">
                            <option value="cash">Caisse</option>
                            <option value="bank">Banque</option>
                        </select>
                        <select name="destination_id" id="destination_id" class="form-select" required></select>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Montant</label>
                    <div class="input-group">
                        <input type="number" name="amount" min="0.01" step="0.01" class="form-control" required value="{{ old('amount') }}">
                        <span class="input-group-text">FCFA</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Date</label>
                    <input type="date" name="transfer_date" class="form-control" required value="{{ old('transfer_date', now()->format('Y-m-d')) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Description</label>
                    <input type="text" name="description" class="form-control" maxlength="500" value="{{ old('description') }}" placeholder="Motif du transfert">
                </div>
            </div></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                <button class="btn btn-primary px-6"><i class="bi bi-arrow-left-right me-2"></i>Valider le transfert</button>
            </div>
                    </form>
                </div>
            </div>
        </div>

        <form method="GET" class="d-flex gap-3 align-items-end flex-wrap mb-4">
            <div><label class="form-label">Du</label><input type="date" name="date_debut" class="form-control" value="{{ $filters['date_debut'] }}"></div>
            <div><label class="form-label">Au</label><input type="date" name="date_fin" class="form-control" value="{{ $filters['date_fin'] }}"></div>
            <button class="btn btn-light-primary">Filtrer</button>
            <a href="{{ route('admin.comptabilite.transfers') }}" class="btn btn-light">Réinitialiser</a>
        </form>

        <div class="table-responsive">
            <table class="table align-middle table-row-dashed">
                <thead><tr class="text-muted text-uppercase fs-7">
                    <th>Source</th><th>Destination</th><th>Montant</th><th>Date</th><th>Référence</th><th>Description</th>
                </tr></thead>
                <tbody>
                @forelse($transfers as $transfer)
                    <tr>
                        <td><strong>{{ $transfer['source'] }}</strong><br><span class="badge badge-light-primary">{{ $transfer['source_type'] }}</span></td>
                        <td><strong>{{ $transfer['destination'] }}</strong><br><span class="badge badge-light-success">{{ $transfer['destination_type'] }}</span></td>
                        <td class="fw-bold text-primary">{{ $transfer['amount'] }}</td>
                        <td>{{ $transfer['date'] }}</td>
                        <td>{{ $transfer['reference'] }}</td>
                        <td>{{ $transfer['description'] ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-8">Aucun transfert enregistré.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    const transferAccounts = {
        cash: @json($cashAccounts->map(fn ($account) => ['id' => $account->id, 'name' => $account->name, 'balance' => (float) $account->balance])),
        bank: @json($bankAccounts->map(fn ($account) => ['id' => $account->id, 'name' => $account->name . ' (' . $account->account_number . ')', 'balance' => (float) $account->current_balance]))
    };
    function fillTransferAccounts(type, target, selected) {
        const select = document.getElementById(target);
        select.innerHTML = '<option value="">Sélectionner...</option>';
        transferAccounts[type].forEach(account => {
            const option = new Option(account.name + ' - ' + account.balance.toLocaleString('fr-FR') + ' FCFA', account.id);
            option.dataset.balance = account.balance;
            if (String(account.id) === String(selected || '')) option.selected = true;
            select.add(option);
        });
    }
    function updateSourceBalance() {
        const selected = document.querySelector('#source_id option:checked');
        document.getElementById('source_balance').textContent = selected && selected.value
            ? 'Solde disponible : ' + Number(selected.dataset.balance || 0).toLocaleString('fr-FR') + ' FCFA'
            : '';
    }
    function refreshTransferAccounts() {
        fillTransferAccounts(document.getElementById('source_type').value, 'source_id', @json(old('source_id')));
        fillTransferAccounts(document.getElementById('destination_type').value, 'destination_id', @json(old('destination_id')));
        updateSourceBalance();
    }
    document.getElementById('source_type').addEventListener('change', refreshTransferAccounts);
    document.getElementById('destination_type').addEventListener('change', refreshTransferAccounts);
    document.getElementById('source_id').addEventListener('change', updateSourceBalance);
    refreshTransferAccounts();
    @if($errors->any())
        new bootstrap.Modal(document.getElementById('transferModal')).show();
    @endif
</script>
@endsection
