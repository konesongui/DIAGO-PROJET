<?php

namespace App\Models\Concerns;

use App\Models\TaxRate;

/**
 * Libellé de la ligne de TVA d'un document de vente (colonnes tax_regime et tax_rate).
 */
trait HasTaxLabel
{
    /** Le taux, ou le régime quand aucune TVA n'est facturée. */
    public function taxLabel(): string
    {
        if (in_array($this->tax_regime, ['exempt', 'export', 'reverse_charge'], true)) {
            return 'TVA (' . mb_strtolower(TaxRate::regimes()[$this->tax_regime]) . ')';
        }
        if ((float) $this->tax_rate > 0) {
            return 'TVA (' . rtrim(rtrim(number_format((float) $this->tax_rate, 3, ',', ''), '0'), ',') . ' %)';
        }

        return 'TVA';
    }
}
