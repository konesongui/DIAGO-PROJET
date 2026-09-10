<?php

namespace App\Services;

use App\Models\Entreprise;
use App\Models\TaxRate;
use Illuminate\Support\Collection;

/**
 * Point d'entree unique du calcul de taxe.
 *
 * Aucun module ne doit calculer une TVA lui-meme : le taux, le regime et
 * l'arrondi dependent de l'entreprise et de sa devise, jamais d'une valeur
 * ecrite dans le code.
 */
class TaxService
{
    /**
     * Nombre de decimales par devise. Le franc CFA ne se subdivise pas,
     * l'euro utilise deux decimales, le dinar koweitien trois.
     */
    private const CURRENCY_DECIMALS = [
        'XOF' => 0, 'XAF' => 0, 'JPY' => 0, 'KRW' => 0, 'CLP' => 0, 'ISK' => 0,
        'KWD' => 3, 'BHD' => 3, 'OMR' => 3, 'TND' => 3, 'IQD' => 3, 'JOD' => 3,
    ];

    private const DEFAULT_DECIMALS = 2;

    /** Taux actifs de l'entreprise, applicables a la date donnee. */
    public function ratesFor(int $entrepriseId, $date = null): Collection
    {
        return TaxRate::withoutGlobalScope('entreprise')
            ->where('entreprise_id', $entrepriseId)
            ->active()
            ->effectiveOn($date ?: now()->toDateString())
            ->ordered()
            ->get();
    }

    /** Taux propose par defaut a la saisie. */
    public function defaultRate(int $entrepriseId, $date = null): ?TaxRate
    {
        $rates = $this->ratesFor($entrepriseId, $date);

        return $rates->firstWhere('is_default', true) ?: $rates->first();
    }

    /**
     * Retrouve un taux par son identifiant, en restant dans l'entreprise.
     * Retourne le taux par defaut si l'identifiant est absent ou invalide.
     */
    public function resolveRate(int $entrepriseId, $taxRateId = null, $date = null): ?TaxRate
    {
        if (! $taxRateId) {
            return $this->defaultRate($entrepriseId, $date);
        }

        return $this->ratesFor($entrepriseId, $date)->firstWhere('id', (int) $taxRateId)
            ?: $this->defaultRate($entrepriseId, $date);
    }

    /** Devise de l'entreprise, telle que configuree dans ses reglages. */
    public function currencyFor(?Entreprise $entreprise): string
    {
        return strtoupper((string) data_get($entreprise?->settings ?? [], 'currency', 'XOF')) ?: 'XOF';
    }

    public function currencySymbolFor(?Entreprise $entreprise): string
    {
        return (string) data_get($entreprise?->settings ?? [], 'currency_symbol', 'FCFA') ?: 'FCFA';
    }

    public function decimalsFor(string $currency): int
    {
        return self::CURRENCY_DECIMALS[strtoupper($currency)] ?? self::DEFAULT_DECIMALS;
    }

    /** Arrondi monetaire, selon les decimales reellement utilisees par la devise. */
    public function roundMoney(float $amount, string $currency = 'XOF'): float
    {
        return round($amount, $this->decimalsFor($currency));
    }

    /**
     * Calcule la taxe sur une base hors taxes.
     *
     * Un regime exonere, export ou en autoliquidation ne facture aucune taxe,
     * quelle que soit la valeur du taux enregistree.
     */
    public function computeTax(float $baseHt, ?TaxRate $rate, string $currency = 'XOF'): float
    {
        if (! $rate || $rate->isZeroRated()) {
            return 0.0;
        }

        return $this->roundMoney($baseHt * ((float) $rate->rate / 100), $currency);
    }

    /**
     * Ventile un montant hors taxes : base, taxe et total toutes taxes comprises.
     *
     * @return array{rate_id:int|null, rate_name:string, rate_value:float, regime:string, base_ht:float, tax_amount:float, total_ttc:float, currency:string}
     */
    public function breakdown(float $baseHt, ?TaxRate $rate, string $currency = 'XOF'): array
    {
        $base = $this->roundMoney($baseHt, $currency);
        $tax = $this->computeTax($base, $rate, $currency);

        return [
            'rate_id' => $rate?->id,
            'rate_name' => $rate?->name ?? 'Sans taxe',
            'rate_value' => $rate && ! $rate->isZeroRated() ? (float) $rate->rate : 0.0,
            'regime' => $rate?->regime ?? TaxRate::REGIME_EXEMPT,
            'base_ht' => $base,
            'tax_amount' => $tax,
            'total_ttc' => $this->roundMoney($base + $tax, $currency),
            'currency' => strtoupper($currency),
        ];
    }

    /**
     * Ventile un montant TOUTES TAXES COMPRISES.
     *
     * En caisse, le prix affiche est celui que le client paye. La taxe s'en
     * extrait, elle ne s'y ajoute pas : ajouter la taxe changerait le montant
     * encaisse et fausserait le rapprochement de caisse.
     *
     * @return array{rate_id:int|null, rate_name:string, rate_value:float, regime:string, base_ht:float, tax_amount:float, total_ttc:float, currency:string}
     */
    public function breakdownFromTtc(float $totalTtc, ?TaxRate $rate, string $currency = 'XOF'): array
    {
        $ttc = $this->roundMoney($totalTtc, $currency);

        if (! $rate || $rate->isZeroRated() || (float) $rate->rate <= 0) {
            return [
                'rate_id' => $rate?->id,
                'rate_name' => $rate?->name ?? 'Sans taxe',
                'rate_value' => 0.0,
                'regime' => $rate?->regime ?? TaxRate::REGIME_EXEMPT,
                'base_ht' => $ttc,
                'tax_amount' => 0.0,
                'total_ttc' => $ttc,
                'currency' => strtoupper($currency),
            ];
        }

        $baseHt = $this->roundMoney($ttc / (1 + ((float) $rate->rate / 100)), $currency);

        return [
            'rate_id' => $rate->id,
            'rate_name' => $rate->name,
            'rate_value' => (float) $rate->rate,
            'regime' => $rate->regime,
            'base_ht' => $baseHt,
            // Difference plutot que recalcul : la somme reste exactement le TTC.
            'tax_amount' => $this->roundMoney($ttc - $baseHt, $currency),
            'total_ttc' => $ttc,
            'currency' => strtoupper($currency),
        ];
    }

    /**
     * Code fiscal a declarer pour un regime donne.
     *
     * Le code depend du regime du taux applique, jamais du montant du
     * document : un client exonere reste exonere quel que soit le total.
     */
    public function fiscalCodeFor(?string $regime): string
    {
        $codes = config('fne.tax_codes', []);

        return $codes[$regime] ?? config('fne.default_tax_code', 'TVA');
    }

    /**
     * Un document dont le regime est inconnu ne doit pas partir au fisc :
     * mieux vaut bloquer et demander une correction que declarer un regime
     * devine. Concerne les documents anterieurs a la mise en place des taux.
     */
    public function isDeclarable(?string $regime): bool
    {
        return $regime !== null && $regime !== '' && $regime !== 'unknown';
    }

    /** Jeu de taux cree pour une nouvelle entreprise, d'apres sa devise. */
    public function defaultRatesFor(string $currency): array
    {
        $catalogues = [
            // Zone UEMOA : taux normal 18 %, taux reduit 9 %.
            'XOF' => [
                ['name' => 'TVA 18 %', 'code' => 'TVA18', 'rate' => 18, 'regime' => TaxRate::REGIME_STANDARD, 'is_default' => true],
                ['name' => 'TVA 9 %', 'code' => 'TVA9', 'rate' => 9, 'regime' => TaxRate::REGIME_REDUCED],
            ],
            'EUR' => [
                ['name' => 'TVA 20 %', 'code' => 'TVA20', 'rate' => 20, 'regime' => TaxRate::REGIME_STANDARD, 'is_default' => true],
                ['name' => 'TVA 10 %', 'code' => 'TVA10', 'rate' => 10, 'regime' => TaxRate::REGIME_REDUCED],
                ['name' => 'TVA 5,5 %', 'code' => 'TVA55', 'rate' => 5.5, 'regime' => TaxRate::REGIME_REDUCED],
            ],
            'MAD' => [
                ['name' => 'TVA 20 %', 'code' => 'TVA20', 'rate' => 20, 'regime' => TaxRate::REGIME_STANDARD, 'is_default' => true],
                ['name' => 'TVA 14 %', 'code' => 'TVA14', 'rate' => 14, 'regime' => TaxRate::REGIME_REDUCED],
                ['name' => 'TVA 7 %', 'code' => 'TVA7', 'rate' => 7, 'regime' => TaxRate::REGIME_REDUCED],
            ],
        ];

        $rates = $catalogues[strtoupper($currency)] ?? [
            ['name' => 'Taux normal', 'code' => 'STD', 'rate' => 0, 'regime' => TaxRate::REGIME_STANDARD, 'is_default' => true],
        ];

        // Tout pays a besoin d'un regime exonere, quel que soit son bareme.
        $rates[] = ['name' => 'Exonéré', 'code' => 'EXO', 'rate' => 0, 'regime' => TaxRate::REGIME_EXEMPT];

        return array_map(static function (array $rate, int $index) {
            return $rate + ['is_default' => false, 'is_active' => true, 'position' => $index];
        }, $rates, array_keys($rates));
    }
}
