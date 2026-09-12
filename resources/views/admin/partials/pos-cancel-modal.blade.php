{{--
    Fenêtre « Annuler la vente » du point de vente, commune à la liste et à la fiche.
    Déclencheur : un bouton data-bs-target="#cancelSaleModal" portant data-action (URL),
    data-label, data-amount et data-where (caisse ou compte d'où ressort l'argent).
--}}
    <div class="modal fade dg-tone-red" id="cancelSaleModal" tabindex="-1" aria-labelledby="cancelSaleTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" id="cancelSaleForm" action="#">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="cancelSaleTitle"><i class="bi bi-x-circle me-2"></i>Annuler la vente</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        <div class="pos-cancel-summary mb-4">
                            <span class="dg-tile dg-tile--sm dg-tone-red"><i class="bi bi-receipt"></i></span>
                            <span class="flex-grow-1"><span class="d-block fw-semibold" id="cancelSaleLabel"></span><span class="d-block dg-muted" style="font-size:13px">Montant rendu au client</span></span>
                            <strong class="pos-cancel-summary__amount" id="cancelSaleAmount"></strong>
                        </div>
                        <p class="mb-2" style="font-size:14px">Le montant ressort <strong id="cancelSaleWhere"></strong>, et la vente est contrepassée au journal : elle disparaît du chiffre d’affaires et de la TVA collectée.</p>
                        <p class="dg-muted mb-0" style="font-size:13px">L’annulation est définitive. Une vente annulée peut ensuite être supprimée de la liste.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Garder la vente</button>
                        <button class="btn btn-danger"><i class="bi bi-x-circle me-1"></i>Annuler la vente</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<style>
    .pos-cancel-summary { display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-radius: var(--dg-radius); background: rgba(220, 38, 38, .06); border: 1px solid rgba(220, 38, 38, .22); }
    .pos-cancel-summary__amount { font-size: 17px; color: #dc2626; white-space: nowrap; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Fenêtre d'annulation unique, remplie à partir du bouton qui l'ouvre.
    document.getElementById('cancelSaleModal').addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        if (!trigger) return;
        document.getElementById('cancelSaleForm').action = trigger.dataset.action;
        document.getElementById('cancelSaleLabel').textContent = trigger.dataset.label;
        document.getElementById('cancelSaleAmount').textContent = trigger.dataset.amount;
        document.getElementById('cancelSaleWhere').textContent = trigger.dataset.where;
    });
});
</script>
