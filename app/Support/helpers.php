<?php

use App\Services\TaxService;

if (! function_exists('company_currency')) {
    /**
     * Devise de l'entreprise connectee : code et symbole.
     *
     * Le resultat est memorise le temps de la requete : la devise ne change
     * pas entre deux affichages d'une meme page.
     */
    function company_currency(): array
    {
        static $cache = [];

        $entrepriseId = auth()->user()?->entreprise_id ?? 0;

        if (! array_key_exists($entrepriseId, $cache)) {
            $taxes = app(TaxService::class);
            $entreprise = auth()->user()?->entreprise;
            $code = $taxes->currencyFor($entreprise);

            $cache[$entrepriseId] = [
                'code' => $code,
                'symbol' => $taxes->currencySymbolFor($entreprise),
                'decimals' => $taxes->decimalsFor($code),
            ];
        }

        return $cache[$entrepriseId];
    }
}

if (! function_exists('money')) {
    /**
     * Formate un montant dans la devise de l'entreprise.
     *
     * Remplace les montants suivis de « FCFA » ecrits en dur : le symbole et
     * le nombre de decimales viennent du parametrage, ce qui rend l'affichage
     * correct hors de la zone franc.
     */
    function money($amount, ?int $decimals = null, bool $withSymbol = true): string
    {
        $currency = company_currency();
        $decimals ??= $currency['decimals'];

        $formatted = number_format((float) $amount, $decimals, ',', ' ');

        return $withSymbol ? trim($formatted . ' ' . $currency['symbol']) : $formatted;
    }
}

if (! function_exists('currency_symbol')) {
    function currency_symbol(): string
    {
        return company_currency()['symbol'];
    }
}

if (! function_exists('currency_decimals')) {
    function currency_decimals(): int
    {
        return company_currency()['decimals'];
    }
}
