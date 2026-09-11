@extends('admin.layout')

@section('content')
@php
    $money = fn ($v) => number_format((float) $v, $decimals ?? 0, ',', ' ') . ' ' . ($currencySymbol ?? '');
    $isPayable = $declaration['is_payable'];
    $onCollections = ($declaration['basis'] ?? 'debits') === 'collections';
@endphp
<style>
    .tva-table td.num, .tva-table th.num { text-align: right; font-variant-numeric: tabular-nums; }
    .dg-scope .tva-table tfoot td { padding: 12px 16px; border-top: 1px solid var(--dg-border); font-weight: 700; }
    .dg-scope .tva-table tfoot tr:first-child td { border-top: 2px solid var(--dg-tone, var(--dg-navy)); background: var(--dg-tone-bg, var(--dg-table-head)); color: var(--dg-tone, var(--dg-navy)); }
    .tva-balance__amount { font-size: 28px; font-weight: 700; line-height: 1.2; font-variant-numeric: tabular-nums; color: var(--dg-tone); }
</style>

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title ?? 'Déclaration de TVA'" subtitle="Taxe collectée sur les ventes, taxe déductible sur les achats et solde de la période." :back="route('admin.comptabilite')" back-label="Comptabilité">
        <x-slot:actions>
            <form method="GET" class="dg-period" aria-label="Période de la déclaration">
                <input type="date" name="date_debut" value="{{ $dateDebut }}" class="dg-input" aria-label="Du">
                <span class="dg-period__sep">au</span>
                <input type="date" name="date_fin" value="{{ $dateFin }}" class="dg-input" aria-label="Au">
                <select name="basis" class="dg-select" style="width:auto;min-height:42px" aria-label="Fait générateur">
                    <option value="debits" @selected(($basis ?? 'debits') === 'debits')>Sur les débits</option>
                    <option value="collections" @selected(($basis ?? '') === 'collections')>Sur les encaissements</option>
                </select>
                <button class="dg-btn dg-btn--outline"><i class="bi bi-calculator"></i>Calculer</button>
            </form>
        </x-slot:actions>
    </x-dg.page-header>

    <div class="dg-callout dg-tone-blue mb-4">
        <span class="dg-tile"><i class="bi bi-info-lg"></i></span>
        <div class="flex-grow-1" style="font-size:14px">
            @if($onCollections)
                <strong>Fait générateur : sur les encaissements.</strong> La taxe est exigible au fur et à mesure des règlements ; un paiement partiel ne rend exigible que la fraction encaissée.
            @else
                <strong>Fait générateur : sur les débits.</strong> La taxe est due à la date d’émission du document, indépendamment de son encaissement.
            @endif
        </div>
    </div>

    @foreach($declaration['warnings'] as $warning)
        <div class="dg-callout dg-tone-orange mb-4">
            <span class="dg-tile"><i class="bi bi-exclamation-triangle"></i></span>
            <div class="flex-grow-1" style="font-size:14px">
                <strong>{{ $warning['count'] }} document(s) sans régime fiscal renseigné</strong> sur la période.
                Ils faussent le total tant qu’ils ne sont pas qualifiés : ouvrez-les et confirmez leur régime.
            </div>
        </div>
    @endforeach

    <div class="dg-kpi-grid mt-6">
        <x-dg.kpi label="TVA collectée nette" :value="$money($declaration['collected_net'])" icon="bi-box-arrow-in-down" color="green" hint="ventes, avoirs déduits" />
        <x-dg.kpi label="TVA déductible" :value="$money($declaration['deductible']['tax'])" icon="bi-box-arrow-up" color="blue" hint="achats de la période" />
        <x-dg.kpi :label="$isPayable ? 'TVA à payer' : 'Crédit de TVA'" :value="$money(abs($declaration['balance']))" icon="bi-percent" :color="$isPayable ? 'orange' : 'teal'" :hint="$isPayable ? 'à reverser pour la période' : 'reportable sur la période suivante'" />
    </div>

    <div class="dg-grid-halves mb-6">
        <x-dg.card title="TVA collectée sur les ventes" icon="bi-receipt" color="green" class="dg-card--table">
            <div class="table-responsive">
                <table class="table tva-table align-middle mb-0">
                    <thead><tr><th>Origine</th><th class="num">Base HT</th><th class="num">Taxe</th><th class="num">Doc.</th></tr></thead>
                    <tbody>
                    @forelse($declaration['collected']['by_source'] as $row)
                        <tr>
                            <td>{{ $row['source'] }}</td>
                            <td class="num">{{ $money($row['base_ht']) }}</td>
                            <td class="num">{{ $money($row['tax']) }}</td>
                            <td class="num"><span class="dg-badge dg-badge--neutral">{{ $row['count'] }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-6">Aucune vente sur la période.</td></tr>
                    @endforelse
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>Total collecté</td>
                            <td class="num">{{ $money($declaration['collected']['base_ht']) }}</td>
                            <td class="num">{{ $money($declaration['collected']['tax']) }}</td>
                            <td></td>
                        </tr>
                        @if($declaration['credit_notes']['tax'] > 0)
                            <tr class="dg-amount-negative">
                                <td>Avoirs émis (en diminution)</td>
                                <td class="num">- {{ $money($declaration['credit_notes']['base_ht']) }}</td>
                                <td class="num">- {{ $money($declaration['credit_notes']['tax']) }}</td>
                                <td class="num">{{ $declaration['credit_notes']['count'] }}</td>
                            </tr>
                            <tr>
                                <td>Collectée nette</td><td></td>
                                <td class="num">{{ $money($declaration['collected_net']) }}</td><td></td>
                            </tr>
                        @endif
                    </tfoot>
                </table>
            </div>
        </x-dg.card>

        <x-dg.card title="TVA déductible sur les achats" icon="bi-cart" color="blue" class="dg-card--table">
            <div class="table-responsive">
                <table class="table tva-table align-middle mb-0">
                    <thead><tr><th>Origine</th><th class="num">Base HT</th><th class="num">Taxe</th><th class="num">Doc.</th></tr></thead>
                    <tbody>
                    @forelse($declaration['deductible']['by_source'] as $row)
                        <tr>
                            <td>{{ $row['source'] }}</td>
                            <td class="num">{{ $money($row['base_ht']) }}</td>
                            <td class="num">{{ $money($row['tax']) }}</td>
                            <td class="num"><span class="dg-badge dg-badge--neutral">{{ $row['count'] }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-6">Aucun achat sur la période.</td></tr>
                    @endforelse
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>Total déductible</td>
                            <td class="num">{{ $money($declaration['deductible']['base_ht']) }}</td>
                            <td class="num">{{ $money($declaration['deductible']['tax']) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-dg.card>
    </div>

    <div class="dg-callout dg-tone-{{ $isPayable ? 'orange' : 'teal' }} mb-6">
        <span class="dg-tile"><i class="bi {{ $isPayable ? 'bi-cash-coin' : 'bi-arrow-repeat' }}"></i></span>
        <div class="flex-grow-1">
            <div class="dg-muted" style="font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:.06em">{{ $isPayable ? 'TVA à payer' : 'Crédit de TVA reportable' }}</div>
            <div class="tva-balance__amount">{{ $money(abs($declaration['balance'])) }}</div>
        </div>
        <div class="dg-muted" style="font-size:13px;max-width:420px">
            Collectée nette {{ $money($declaration['collected_net']) }} moins déductible {{ $money($declaration['deductible']['tax']) }}.
            Période du {{ $declaration['from']->format('d/m/Y') }} au {{ $declaration['to']->format('d/m/Y') }}.
        </div>
    </div>

    <x-dg.card title="Ventilation par taux" icon="bi-percent" color="purple" class="dg-card--table">
        <div class="table-responsive">
            <table class="table tva-table align-middle mb-0">
                <thead><tr><th>Taux</th><th class="num">Base HT collectée</th><th class="num">Taxe</th><th class="num">Documents</th></tr></thead>
                <tbody>
                @forelse($declaration['collected']['by_rate'] as $row)
                    <tr>
                        <td><span class="dg-badge dg-badge--neutral">{{ rtrim(rtrim(number_format($row['rate'], 3, ',', ' '), '0'), ',') }} %</span></td>
                        <td class="num">{{ $money($row['base_ht']) }}</td>
                        <td class="num dg-cell-num">{{ $money($row['tax']) }}</td>
                        <td class="num">{{ $row['count'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-6">Aucune donnée sur la période.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-dg.card>
</div>
@endsection
