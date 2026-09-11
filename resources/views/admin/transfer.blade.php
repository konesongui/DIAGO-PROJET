@extends('admin.layout')

@section('content')
<div class="dg-font dg-scope">
    <div>
        <x-dg.page-header :title="$title" :subtitle="$subtitle" :back="route('admin.comptabilite')" back-label="Comptabilité">
            <x-slot:actions>
                <button type="button" class="dg-btn dg-btn--primary" data-bs-toggle="modal" data-bs-target="#transferModal"><i class="bi bi-arrow-left-right"></i>Nouveau transfert</button>
            </x-slot:actions>
        </x-dg.page-header>

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
                        <span class="input-group-text">{{ currency_symbol() }}</span>
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

        <div class="dg-card dg-card--table">
            <div class="dg-card__header flex-wrap">
                <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-purple"><i class="bi bi-arrow-left-right"></i></span>Historique des transferts</h2>
                <form method="GET" class="dg-period" aria-label="Filtrer par période">
                    <input type="date" name="date_debut" class="dg-input" value="{{ $filters['date_debut'] }}" aria-label="Du">
                    <span class="dg-period__sep">au</span>
                    <input type="date" name="date_fin" class="dg-input" value="{{ $filters['date_fin'] }}" aria-label="Au">
                    <button class="dg-btn dg-btn--outline"><i class="bi bi-funnel"></i>Filtrer</button>
                    <a href="{{ route('admin.comptabilite.transfers') }}" class="dg-btn dg-btn--outline" title="Réinitialiser" aria-label="Réinitialiser"><i class="bi bi-arrow-counterclockwise"></i></a>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr>
                        <th>Source</th><th>Destination</th><th>Montant</th><th>Date</th><th>Référence</th><th>Description</th>
                    </tr></thead>
                    <tbody>
                    @forelse($transfers as $transfer)
                        <tr>
                            <td><span class="fw-semibold">{{ $transfer['source'] }}</span><br><span class="dg-badge dg-badge--neutral mt-1">{{ $transfer['source_type'] }}</span></td>
                            <td><span class="fw-semibold">{{ $transfer['destination'] }}</span><br><span class="dg-badge dg-badge--success mt-1">{{ $transfer['destination_type'] }}</span></td>
                            <td class="dg-cell-num">{{ $transfer['amount'] }}</td>
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
            const option = new Option(account.name + ' - ' +window.formatMoney(account.balance), account.id);
            option.dataset.balance = account.balance;
            if (String(account.id) === String(selected || '')) option.selected = true;
            select.add(option);
        });
    }
    function updateSourceBalance() {
        const selected = document.querySelector('#source_id option:checked');
        document.getElementById('source_balance').textContent = selected && selected.value
            ? 'Solde disponible : ' +window.formatMoney(Number(selected.dataset.balance || 0))
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
        // Bootstrap est chargé en fin de layout : on rouvre la fenêtre une fois la page prête.
        window.addEventListener('load', () => new bootstrap.Modal(document.getElementById('transferModal')).show());
    @endif
</script>
@endsection
