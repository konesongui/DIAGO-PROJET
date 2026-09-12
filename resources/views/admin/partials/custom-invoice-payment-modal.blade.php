{{--
    Fenêtre « Enregistrer un paiement » d'une facture personnalisée, commune à la liste et à la fiche.
    Variables : $cashAccounts, $bankAccounts.
    Déclencheur : un bouton data-bs-toggle="modal" data-bs-target="#paymentModal" portant
    data-payment-invoice (id), data-action (URL), data-label, data-remaining et data-remaining-label.
    Après une erreur, la fenêtre se rouvre sur la même facture avec la saisie conservée.
--}}
@php $paymentInvoiceId = old('payment_invoice_id'); @endphp

<div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="paymentForm" action="{{ $paymentInvoiceId ? route('admin.commercial.custom-invoice.payment', $paymentInvoiceId) : '#' }}">
                @csrf
                <input type="hidden" name="payment_invoice_id" id="paymentInvoiceId" value="{{ $paymentInvoiceId }}">
                <div class="modal-header">
                    <h5 class="modal-title" id="paymentModalTitle"><i class="bi bi-cash-coin me-2"></i>Enregistrer un paiement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="custom-payment-summary mb-4">
                        <span class="dg-tile dg-tile--sm dg-tone-green"><i class="bi bi-receipt"></i></span>
                        <span class="flex-grow-1">
                            <span class="d-block fw-semibold" id="paymentInvoiceLabel"></span>
                            <span class="d-block dg-muted" style="font-size:13px">Reste à payer</span>
                        </span>
                        <strong class="custom-payment-summary__amount" id="paymentRemainingLabel"></strong>
                    </div>
                    <label class="form-label" for="paymentAmount">Montant reçu</label>
                    <input id="paymentAmount" name="amount" type="number" min="0.01" step="0.01" class="form-control mb-4" required value="{{ $paymentInvoiceId ? old('amount') : '' }}">
                    <label class="form-label" for="paymentMethod">Mode de paiement</label>
                    <select id="paymentMethod" name="payment_method" class="form-select" required>
                        <option value="">Sélectionner…</option>
                        <option value="cash" @selected(old('payment_method') === 'cash')>Espèces (caisse)</option>
                        <option value="bank" @selected(old('payment_method') === 'bank')>Banque</option>
                    </select>
                    <div class="mt-4 d-none" id="paymentCashField">
                        <label class="form-label" for="paymentCash">Caisse</label>
                        <select id="paymentCash" name="cash_account_id" class="form-select" disabled>
                            <option value="">Sélectionner une caisse…</option>
                            @foreach($cashAccounts as $account)
                                <option value="{{ $account->id }}" @selected((string) old('cash_account_id') === (string) $account->id)>{{ $account->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mt-4 d-none" id="paymentBankField">
                        <label class="form-label" for="paymentBank">Banque</label>
                        <select id="paymentBank" name="bank_account_id" class="form-select" disabled>
                            <option value="">Sélectionner une banque…</option>
                            @foreach($bankAccounts as $account)
                                <option value="{{ $account->id }}" @selected((string) old('bank_account_id') === (string) $account->id)>{{ $account->name }}{{ $account->bank_name ? ' - ' . $account->bank_name : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                    <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Enregistrer le paiement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .custom-payment-summary { display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-radius: var(--dg-radius); background: rgba(5, 150, 105, .08); border: 1px solid rgba(5, 150, 105, .25); }
    .custom-payment-summary__amount { font-size: 17px; color: #059669; white-space: nowrap; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Fenêtre de paiement unique, remplie à partir du bouton qui l'ouvre.
    const modal = document.getElementById('paymentModal');
    const form = document.getElementById('paymentForm');
    const amount = document.getElementById('paymentAmount');
    const method = document.getElementById('paymentMethod');
    const cashField = document.getElementById('paymentCashField');
    const bankField = document.getElementById('paymentBankField');
    const cashSelect = document.getElementById('paymentCash');
    const bankSelect = document.getElementById('paymentBank');
    const toggleAccounts = function () {
        const isCash = method.value === 'cash';
        const isBank = method.value === 'bank';
        cashField.classList.toggle('d-none', !isCash);
        bankField.classList.toggle('d-none', !isBank);
        cashSelect.disabled = !isCash;
        cashSelect.required = isCash;
        bankSelect.disabled = !isBank;
        bankSelect.required = isBank;
    };
    method.addEventListener('change', toggleAccounts);
    modal.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        if (!trigger || !trigger.dataset.paymentInvoice) return;
        form.action = trigger.dataset.action;
        document.getElementById('paymentInvoiceId').value = trigger.dataset.paymentInvoice;
        document.getElementById('paymentInvoiceLabel').textContent = trigger.dataset.label;
        document.getElementById('paymentRemainingLabel').textContent = trigger.dataset.remainingLabel;
        amount.max = trigger.dataset.remaining;
        if (!trigger.dataset.reopen) {
            amount.value = trigger.dataset.remaining;
            method.value = '';
            cashSelect.value = '';
            bankSelect.value = '';
        }
        toggleAccounts();
    });
    @if($paymentInvoiceId && $errors->any())
        window.addEventListener('load', function () {
            const trigger = document.querySelector('[data-payment-invoice="{{ (int) $paymentInvoiceId }}"]');
            if (!trigger) return;
            trigger.dataset.reopen = '1';
            bootstrap.Modal.getOrCreateInstance(modal).show(trigger);
            delete trigger.dataset.reopen;
        });
    @endif
});
</script>
