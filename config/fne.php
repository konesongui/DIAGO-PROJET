<?php

return [
    'environment' => env('FNE_ENVIRONMENT', 'test'),
    'test_url' => env('FNE_TEST_URL', 'http://54.247.95.108/ws/external/invoices/sign'),
    'production_url' => env('FNE_PRODUCTION_URL'),
    'api_key' => env('FNE_API_KEY'),
    'point_of_sale' => env('FNE_POINT_OF_SALE', 'DIAGOMA'),
    'establishment' => env('FNE_ESTABLISHMENT', 'INTENDANT'),
    'timeout' => (int) env('FNE_TIMEOUT', 30),

    /*
     |--------------------------------------------------------------------------
     | Correspondance regime interne -> code fiscal transmis
     |--------------------------------------------------------------------------
     |
     | Le code declare depend du REGIME du taux applique, jamais du montant de
     | la facture. Un client exonere doit etre declare exonere, meme si sa
     | facture porte un montant positif.
     |
     | ATTENTION : seuls 'TVA' et 'TVAE' sont ceux deja utilises par le projet
     | et connus comme acceptes. Les codes des regimes reduit, export et
     | autoliquidation doivent etre CONFIRMES aupres de l'administration
     | fiscale avant mise en production, puis corriges ici. C'est le seul
     | endroit a modifier.
     |
     */
    'tax_codes' => [
        'standard' => env('FNE_CODE_STANDARD', 'TVA'),
        'reduced' => env('FNE_CODE_REDUCED', 'TVA'),
        'exempt' => env('FNE_CODE_EXEMPT', 'TVAE'),
        'export' => env('FNE_CODE_EXPORT', 'TVAE'),
        'reverse_charge' => env('FNE_CODE_REVERSE_CHARGE', 'TVAE'),
    ],

    'default_tax_code' => env('FNE_CODE_DEFAULT', 'TVA'),
];
