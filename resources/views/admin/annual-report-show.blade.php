@extends('admin.layout')

@section('content')
@php
    $m = fn ($v) => number_format((float) $v, $data['decimals'] ?? 0, ',', ' ') . ' ' . ($data['currency_symbol'] ?? '');
    $isProfit = $data['totals']['net_result'] >= 0;
@endphp
<style>
    .ar-table td.num, .ar-table th.num { text-align: right; font-variant-numeric: tabular-nums; }
    .dg-scope .ar-table > tbody > tr.ar-total > td { border-top: 2px solid var(--dg-tone, var(--dg-navy)) !important; background: var(--dg-tone-bg, var(--dg-table-head)); font-weight: 700; color: var(--dg-tone, var(--dg-navy)); }
    .ar-code { margin-right: 6px; font-size: 12.5px; color: var(--dg-subtle); }
</style>

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title ?? $report->label()"
        :subtitle="'Exercice du ' . \Carbon\Carbon::parse($data['from'])->format('d/m/Y') . ' au ' . \Carbon\Carbon::parse($data['to'])->format('d/m/Y') . ' · ' . $data['entry_count'] . ' écriture(s)'"
        :back="route('admin.bilans.index')" back-label="Tous les bilans">
        <x-slot:actions>
            <a href="{{ route('admin.bilans.download', $report) }}" class="dg-btn dg-btn--primary"><i class="bi bi-download"></i>Télécharger le PDF</a>
        </x-slot:actions>
    </x-dg.page-header>

    @if($report->isUnread())
        <div class="dg-callout dg-tone-orange mb-6">
            <span class="dg-tile"><i class="bi bi-bell"></i></span>
            <div class="flex-grow-1" style="font-size:14px">Ce bilan est signalé comme <strong>non lu</strong>. Il le restera tant que le PDF n’aura pas été téléchargé.</div>
        </div>
    @endif

    <div class="dg-kpi-grid">
        <x-dg.kpi label="Total actif" :value="$m($data['totals']['assets'])" icon="bi-building" color="blue" />
        <x-dg.kpi label="Passif et capitaux" :value="$m($data['totals']['liabilities_and_equity'])" icon="bi-bank" color="indigo" />
        <x-dg.kpi label="Produits" :value="$m($data['totals']['revenue'])" icon="bi-graph-up-arrow" color="green" />
        <x-dg.kpi label="Charges" :value="$m($data['totals']['expenses'])" icon="bi-graph-down-arrow" color="orange" />
    </div>

    <div class="dg-grid-halves mb-6">
        <x-dg.card title="Actif" icon="bi-building" color="blue" class="dg-card--table">
            <div class="table-responsive">
                <table class="table ar-table align-middle mb-0 no-column-sort">
                    <thead><tr><th>Compte</th><th class="num">Montant</th></tr></thead>
                    <tbody>
                    @forelse($data['balance_sheet']['assets'] as $l)
                        <tr><td><span class="ar-code">{{ $l['code'] }}</span>{{ $l['name'] }}</td><td class="num">{{ $m($l['amount']) }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="text-muted text-center py-6">Aucun compte d’actif mouvementé.</td></tr>
                    @endforelse
                        <tr class="ar-total"><td>Total actif</td><td class="num">{{ $m($data['totals']['assets']) }}</td></tr>
                    </tbody>
                </table>
            </div>
        </x-dg.card>

        <x-dg.card title="Passif et capitaux propres" icon="bi-bank" color="indigo" class="dg-card--table">
            <div class="table-responsive">
                <table class="table ar-table align-middle mb-0 no-column-sort">
                    <thead><tr><th>Compte</th><th class="num">Montant</th></tr></thead>
                    <tbody>
                    @foreach($data['balance_sheet']['liabilities'] as $l)
                        <tr><td><span class="ar-code">{{ $l['code'] }}</span>{{ $l['name'] }}</td><td class="num">{{ $m($l['amount']) }}</td></tr>
                    @endforeach
                    @foreach($data['balance_sheet']['equity'] as $l)
                        <tr><td><span class="ar-code">{{ $l['code'] }}</span>{{ $l['name'] }}</td><td class="num">{{ $m($l['amount']) }}</td></tr>
                    @endforeach
                        <tr><td>Résultat de l’exercice</td><td class="num">{{ $m($data['totals']['net_result']) }}</td></tr>
                        <tr class="ar-total"><td>Total passif</td><td class="num">{{ $m($data['totals']['liabilities_and_equity']) }}</td></tr>
                    </tbody>
                </table>
            </div>
        </x-dg.card>
    </div>

    <x-dg.card title="Compte de résultat" icon="bi-graph-up" color="green" class="dg-card--table mb-6">
        <div class="table-responsive">
            <table class="table ar-table align-middle mb-0 no-column-sort">
                <thead><tr><th>Compte</th><th class="num">Montant</th></tr></thead>
                <tbody>
                @foreach($data['income_statement']['revenue'] as $l)
                    <tr><td><span class="ar-code">{{ $l['code'] }}</span>{{ $l['name'] }}</td><td class="num">{{ $m($l['amount']) }}</td></tr>
                @endforeach
                    <tr class="ar-total"><td>Total produits</td><td class="num">{{ $m($data['totals']['revenue']) }}</td></tr>
                @foreach($data['income_statement']['expenses'] as $l)
                    <tr><td><span class="ar-code">{{ $l['code'] }}</span>{{ $l['name'] }}</td><td class="num">{{ $m($l['amount']) }}</td></tr>
                @endforeach
                    <tr class="ar-total"><td>Total charges</td><td class="num">{{ $m($data['totals']['expenses']) }}</td></tr>
                </tbody>
            </table>
        </div>
    </x-dg.card>

    <div class="dg-callout dg-tone-{{ $isProfit ? 'green' : 'red' }}">
        <span class="dg-tile"><i class="bi {{ $isProfit ? 'bi-graph-up-arrow' : 'bi-graph-down-arrow' }}"></i></span>
        <div class="flex-grow-1">
            <div class="dg-muted" style="font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:.06em">Résultat de l’exercice</div>
            <div style="font-size:28px;font-weight:700;line-height:1.2;color:var(--dg-tone)">{{ $m($data['totals']['net_result']) }}
                <span class="dg-badge dg-badge--{{ $isProfit ? 'success' : 'danger' }} ms-2 align-middle">{{ $isProfit ? 'Bénéfice' : 'Perte' }}</span>
            </div>
        </div>
    </div>
</div>
@endsection
