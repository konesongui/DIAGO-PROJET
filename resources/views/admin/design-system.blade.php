@extends('admin.layout')

@section('content')
<div class="dg-font">
    <x-dg.page-header title="Design System UI Kit — Diago" subtitle="Synthèse des normes visuelles pour les développeurs & designers.">
        <x-slot:actions>
            <x-dg.badge tone="neutral" icon="bi-check2-all">Charte CME Expertises</x-dg.badge>
        </x-slot:actions>
    </x-dg.page-header>

    <div class="dg-grid-3 mb-6">
        <x-dg.card title="Palette de couleurs">
            @foreach([['#273772', 'Bleu dominant'], ['#FADF2F', 'Jaune accent'], ['#F4F6FB', 'Fond principal'], ['#172033', 'Texte principal']] as [$hex, $name])
                <div class="d-flex align-items-center gap-3 mb-3">
                    <span style="width:36px;height:36px;border-radius:8px;background:{{ $hex }};border:1px solid var(--dg-border)"></span>
                    <span><strong>{{ $hex }}</strong> <span class="dg-muted">({{ $name }})</span></span>
                </div>
            @endforeach
        </x-dg.card>

        <x-dg.card title="Typographie">
            <p class="mb-2" style="font-weight:700">Poppins Bold 700 <span class="fw-normal">: titres principaux</span></p>
            <p class="mb-2" style="font-weight:600">Poppins SemiBold 600 <span class="fw-normal">: titres de cartes, boutons</span></p>
            <p class="mb-2" style="font-weight:500">Poppins Medium 500 <span class="fw-normal">: menus, labels</span></p>
            <p class="mb-0" style="font-weight:400">Poppins Regular 400 : textes et descriptions</p>
        </x-dg.card>

        <x-dg.card title="Composants éléments">
            <button type="button" class="dg-btn dg-btn--primary dg-btn--block mb-3">Bouton primaire accent</button>
            <button type="button" class="dg-btn dg-btn--secondary dg-btn--block mb-3">Bouton secondaire navy</button>
            <div class="d-flex flex-wrap gap-2">
                <x-dg.badge tone="success">Payée</x-dg.badge>
                <x-dg.badge tone="warning">En attente</x-dg.badge>
                <x-dg.badge tone="danger">En retard</x-dg.badge>
            </div>
        </x-dg.card>
    </div>

    <h2 class="dg-section-title">Boutons</h2>
    <x-dg.card class="mb-6">
        <div class="d-flex flex-wrap align-items-center gap-3">
            <button type="button" class="dg-btn dg-btn--primary"><i class="bi bi-plus-lg"></i>Action rapide</button>
            <button type="button" class="dg-btn dg-btn--secondary"><i class="bi bi-arrow-left-right"></i>Rapprochement bancaire</button>
            <button type="button" class="dg-btn dg-btn--outline">Brouillon</button>
            <button type="button" class="dg-btn dg-btn--outline"><i class="bi bi-box-arrow-up-right"></i>Exporter PDF / Excel</button>
            <button type="button" class="dg-btn dg-btn--outline dg-btn--sm">Voir</button>
            <button type="button" class="dg-icon-btn" aria-label="Modifier"><i class="bi bi-pencil-square"></i></button>
            <button type="button" class="dg-icon-btn dg-icon-btn--ghost" aria-label="Options"><i class="bi bi-three-dots-vertical"></i></button>
        </div>
    </x-dg.card>

    <h2 class="dg-section-title">Indicateurs</h2>
    <div class="dg-kpi-grid">
        <x-dg.kpi label="Chiffre d'affaires" value="25 480 000 FCFA" icon="bi-graph-up-arrow" trend="+12,5%" hint="vs mois dernier" />
        <x-dg.kpi label="Dépenses" value="8 240 000 FCFA" icon="bi-receipt" trend="+4,8%" trend-tone="danger" hint="vs mois dernier" />
        <x-dg.kpi label="Bénéfice net" value="17 240 000 FCFA" icon="bi-coin" trend="+15,2%" hint="marge 67,6 %" />
        <x-dg.kpi label="Factures impayées" value="2 350 000 FCFA" icon="bi-exclamation-triangle-fill" tone="danger" trend="-8,4%" trend-direction="down" trend-tone="success" hint="recouvrement" />
    </div>
    <div class="dg-kpi-grid mb-6">
        <x-dg.kpi label="NSIA Banque CI" value="18 400 000 FCFA" icon="bi-bank" accent="navy" hint="CI092 01001 0039281" />
        <x-dg.kpi label="Banque Atlantique" value="12 150 000 FCFA" icon="bi-bank" accent="success" hint="CI034 02005 0019284" />
        <x-dg.kpi label="Caisse principale" value="820 000 FCFA" icon="bi-cash" accent="yellow" hint="Espèces / Plateau" />
        <x-dg.kpi label="Flux net (mois)" value="+9 820 000 FCFA" icon="bi-graph-up" trend="Entrées positives" />
    </div>

    <h2 class="dg-section-title">Filtres et onglets</h2>
    <x-dg.card class="mb-6">
        <div class="dg-tabs mb-4" role="tablist">
            <button type="button" class="dg-tab is-active" role="tab" aria-selected="true">Toutes (148)</button>
            <button type="button" class="dg-tab" role="tab" aria-selected="false">Payées</button>
            <button type="button" class="dg-tab" role="tab" aria-selected="false">En attente</button>
            <button type="button" class="dg-tab" role="tab" aria-selected="false">En retard</button>
        </div>
        <label class="dg-search">
            <i class="bi bi-search"></i>
            <input type="search" placeholder="Recherche globale (factures, clients, dépenses…)" aria-label="Recherche globale">
        </label>
    </x-dg.card>

    <h2 class="dg-section-title">Tableau</h2>
    <x-dg.card class="dg-card--table mb-6">
        <div class="dg-table-wrap">
            <table class="dg-table">
                <thead>
                    <tr><th>N° facture</th><th>Client</th><th>Date d'émission</th><th>Échéance</th><th>Montant TTC</th><th>Statut</th><th class="dg-cell-actions">Option</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="dg-cell-strong">FAC-2026-089</td>
                        <td>Société Générale CI<span class="dg-cell-sub">NCC : 0102938 A</span></td>
                        <td>20/08/2026</td><td>20/09/2026</td>
                        <td class="dg-cell-num">8 500 000 FCFA</td>
                        <td><x-dg.badge tone="success" icon="bi-check-lg">Payée</x-dg.badge></td>
                        <td class="dg-cell-actions"><button type="button" class="dg-icon-btn dg-icon-btn--ghost" aria-label="Options"><i class="bi bi-three-dots-vertical"></i></button></td>
                    </tr>
                    <tr>
                        <td class="dg-cell-strong">FAC-2026-088</td>
                        <td>Orange Côte d'Ivoire<span class="dg-cell-sub">NCC : 9876543 B</span></td>
                        <td>14/08/2026</td><td>14/09/2026</td>
                        <td class="dg-cell-num dg-amount-negative">1 850 000 FCFA</td>
                        <td><x-dg.badge tone="warning" icon="bi-clock-fill">En attente</x-dg.badge></td>
                        <td class="dg-cell-actions"><a href="#" class="dg-chip"><i class="bi bi-file-earmark-pdf"></i>Facture.pdf</a></td>
                    </tr>
                    <tr>
                        <td class="dg-cell-strong">FAC-2026-085</td>
                        <td>Sotra BTP &amp; Construction<span class="dg-cell-sub">NCC : 4561237 C</span></td>
                        <td>01/07/2026</td><td>31/07/2026</td>
                        <td class="dg-cell-num dg-amount-warning">500 000 FCFA</td>
                        <td><x-dg.badge tone="danger" icon="bi-exclamation-lg">En retard</x-dg.badge></td>
                        <td class="dg-cell-actions"><button type="button" class="dg-icon-btn" aria-label="Modifier"><i class="bi bi-pencil-square"></i></button></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </x-dg.card>

    <h2 class="dg-section-title">Formulaire</h2>
    <x-dg.card class="mb-6">
        <div class="dg-form-grid mb-5">
            <div class="dg-field">
                <label class="dg-label" for="ds-org">Nom de l'organisation <span class="dg-required">*</span></label>
                <input id="ds-org" class="dg-input" value="CME Expertises SARL">
            </div>
            <div class="dg-field">
                <label class="dg-label" for="ds-ncc">Numéro CC / DGI</label>
                <input id="ds-ncc" class="dg-input" placeholder="010293847 A">
                <span class="dg-field-hint">Identifiant fiscal délivré par la DGI.</span>
            </div>
            <div class="dg-field">
                <label class="dg-label" for="ds-currency">Devise principale</label>
                <select id="ds-currency" class="dg-select"><option>Franc CFA (FCFA / XOF)</option><option>Euro (EUR)</option></select>
            </div>
            <div class="dg-field">
                <label class="dg-label" for="ds-date">Date d'émission</label>
                <input id="ds-date" type="date" class="dg-input" value="2026-05-09">
            </div>
        </div>
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-4">
            <div class="dg-field" style="flex:1 1 320px">
                <label class="dg-label" for="ds-notes">Notes &amp; mentions légales</label>
                <textarea id="ds-notes" class="dg-textarea">Règlement par virement sur le compte NSIA Banque N° CI092 01001 0039281.</textarea>
            </div>
            <div class="dg-card" style="padding:16px 20px">
                <div class="dg-totals">
                    <div class="dg-totals__row"><span>Total HT :</span><span>17 000 000 FCFA</span></div>
                    <div class="dg-totals__row"><span>TVA (18 %) :</span><span>3 060 000 FCFA</span></div>
                    <div class="dg-totals__row dg-totals__row--grand"><span>Total TTC :</span><span>20 060 000 FCFA</span></div>
                </div>
            </div>
        </div>
    </x-dg.card>

    <h2 class="dg-section-title">Cartes d'action</h2>
    <div class="dg-grid-3">
        <x-dg.card title="Compte de résultat" icon="bi-graph-up">
            <p class="dg-card__text">Bilan détaillé du chiffre d'affaires, des charges et du résultat net de l'exercice.</p>
            <button type="button" class="dg-btn dg-btn--outline dg-btn--block">Générer rapport</button>
        </x-dg.card>
        <x-dg.card title="État de déclaration TVA" icon="bi-percent">
            <p class="dg-card__text">Calcul automatique de la TVA collectée et déductible pour déclaration fiscale.</p>
            <button type="button" class="dg-btn dg-btn--outline dg-btn--block">Consulter TVA</button>
        </x-dg.card>
        <x-dg.card title="Balance âgée clients" icon="bi-clock-history">
            <p class="dg-card__text">Analyse de l'ancienneté des créances et relances automatiques préventives.</p>
            <button type="button" class="dg-btn dg-btn--outline dg-btn--block">Voir la balance</button>
        </x-dg.card>
    </div>
</div>
@endsection
