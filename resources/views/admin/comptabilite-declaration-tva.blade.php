@extends('admin.layout')

@section('content')
@php
    $money = fn ($v) => number_format((float) $v, $decimals ?? 0, ',', ' ') . ' ' . ($currencySymbol ?? '');
@endphp
<style>
    .tva-card { border: 1px solid #edf2f7; border-radius: 18px; background: #fff; box-shadow: 0 12px 30px rgba(15,23,42,.04); }
    .tva-total { font-variant-numeric: tabular-nums; }
    .tva-balance { border-radius: 18px; padding: 26px; }
    .tva-balance.payable { background: #fef3c7; color: #92400e; }
    .tva-balance.credit { background: #dcfce7; color: #166534; }
    .tva-table td, .tva-table th { vertical-align: middle; }
    .tva-table td.num, .tva-table th.num { text-align: right; font-variant-numeric: tabular-nums; }
</style>

<div class="container-fluid py-5">

    <div class="card tva-card mb-5">
        <div class="card-body">
            <form method="GET" class="row g-4 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Du</label>
                    <input type="date" name="date_debut" value="{{ $dateDebut }}" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Au</label>
                    <input type="date" name="date_fin" value="{{ $dateFin }}" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Fait générateur</label>
                    <select name="basis" class="form-select">
                        <option value="debits" @selected(($basis ?? 'debits') === 'debits')>Sur les débits</option>
                        <option value="collections" @selected(($basis ?? '') === 'collections')>Sur les encaissements</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary">Calculer</button>
                </div>
                <div class="col-12 text-muted fs-8">
                    @if(($declaration['basis'] ?? 'debits') === 'collections')
                        <strong>Sur les encaissements :</strong> la taxe est exigible au fur et à mesure
                        des règlements. Un paiement partiel ne rend exigible que la fraction encaissée.
                    @else
                        <strong>Sur les débits :</strong> la taxe est due à la date d'émission du document,
                        indépendamment de son encaissement.
                    @endif
                </div>
            </form>
        </div>
    </div>

    @foreach($declaration['warnings'] as $warning)
    <div class="alert alert-warning">
        <strong>{{ $warning['count'] }} document(s) sans régime fiscal renseigné</strong> sur la période.
        Ils faussent le total tant qu'ils ne sont pas qualifiés. Ouvrez-les et confirmez leur régime.
    </div>
    @endforeach

    <div class="row g-5">
        <div class="col-lg-6">
            <div class="card tva-card h-100">
                <div class="card-header"><h3 class="card-title">TVA collectée sur les ventes</h3></div>
                <div class="card-body table-responsive">
                    <table class="table tva-table align-middle">
                        <thead><tr class="text-muted fs-8 text-uppercase">
                            <th>Origine</th><th class="num">Base HT</th><th class="num">Taxe</th><th class="num">Doc.</th>
                        </tr></thead>
                        <tbody>
                        @forelse($declaration['collected']['by_source'] as $row)
                            <tr>
                                <td>{{ $row['source'] }}</td>
                                <td class="num">{{ $money($row['base_ht']) }}</td>
                                <td class="num">{{ $money($row['tax']) }}</td>
                                <td class="num text-muted">{{ $row['count'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-8">Aucune vente sur la période.</td></tr>
                        @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold border-top">
                                <td>Total collecté</td>
                                <td class="num">{{ $money($declaration['collected']['base_ht']) }}</td>
                                <td class="num">{{ $money($declaration['collected']['tax']) }}</td>
                                <td></td>
                            </tr>
                            @if($declaration['credit_notes']['tax'] > 0)
                            <tr class="text-danger">
                                <td>Avoirs émis (en diminution)</td>
                                <td class="num">- {{ $money($declaration['credit_notes']['base_ht']) }}</td>
                                <td class="num">- {{ $money($declaration['credit_notes']['tax']) }}</td>
                                <td class="num text-muted">{{ $declaration['credit_notes']['count'] }}</td>
                            </tr>
                            <tr class="fw-bold">
                                <td>Collectée nette</td><td></td>
                                <td class="num">{{ $money($declaration['collected_net']) }}</td><td></td>
                            </tr>
                            @endif
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card tva-card h-100">
                <div class="card-header"><h3 class="card-title">TVA déductible sur les achats</h3></div>
                <div class="card-body table-responsive">
                    <table class="table tva-table align-middle">
                        <thead><tr class="text-muted fs-8 text-uppercase">
                            <th>Origine</th><th class="num">Base HT</th><th class="num">Taxe</th><th class="num">Doc.</th>
                        </tr></thead>
                        <tbody>
                        @forelse($declaration['deductible']['by_source'] as $row)
                            <tr>
                                <td>{{ $row['source'] }}</td>
                                <td class="num">{{ $money($row['base_ht']) }}</td>
                                <td class="num">{{ $money($row['tax']) }}</td>
                                <td class="num text-muted">{{ $row['count'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-8">Aucun achat sur la période.</td></tr>
                        @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold border-top">
                                <td>Total déductible</td>
                                <td class="num">{{ $money($declaration['deductible']['base_ht']) }}</td>
                                <td class="num">{{ $money($declaration['deductible']['tax']) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="tva-balance {{ $declaration['is_payable'] ? 'payable' : 'credit' }} mt-5 d-flex flex-wrap justify-content-between align-items-center gap-4">
        <div>
            <div class="fs-7 text-uppercase fw-bold opacity-75">
                {{ $declaration['is_payable'] ? 'TVA à payer' : 'Crédit de TVA reportable' }}
            </div>
            <div class="fs-1 fw-bold tva-total">{{ $money(abs($declaration['balance'])) }}</div>
        </div>
        <div class="fs-8 opacity-75" style="max-width:420px">
            Collectée nette {{ $money($declaration['collected_net']) }}
            moins déductible {{ $money($declaration['deductible']['tax']) }}.
            Période du {{ $declaration['from']->format('d/m/Y') }} au {{ $declaration['to']->format('d/m/Y') }}.
        </div>
    </div>

    <div class="card tva-card mt-5">
        <div class="card-header"><h3 class="card-title">Ventilation par taux</h3></div>
        <div class="card-body table-responsive">
            <table class="table tva-table align-middle">
                <thead><tr class="text-muted fs-8 text-uppercase">
                    <th>Taux</th><th class="num">Base HT collectée</th><th class="num">Taxe</th><th class="num">Documents</th>
                </tr></thead>
                <tbody>
                @forelse($declaration['collected']['by_rate'] as $row)
                    <tr>
                        <td class="fw-semibold">{{ rtrim(rtrim(number_format($row['rate'], 3, ',', ' '), '0'), ',') }} %</td>
                        <td class="num">{{ $money($row['base_ht']) }}</td>
                        <td class="num">{{ $money($row['tax']) }}</td>
                        <td class="num text-muted">{{ $row['count'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-8">Aucune donnée sur la période.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
