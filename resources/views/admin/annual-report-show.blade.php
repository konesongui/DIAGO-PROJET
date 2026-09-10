@extends('admin.layout')

@section('content')
@php($m = fn ($v) => number_format((float) $v, $data['decimals'] ?? 0, ',', ' ') . ' ' . ($data['currency_symbol'] ?? ''))
<style>
    .ar-card { border: 1px solid #edf2f7; border-radius: 18px; background: #fff; box-shadow: 0 12px 30px rgba(15,23,42,.04); }
    .ar-table td.num, .ar-table th.num { text-align: right; font-variant-numeric: tabular-nums; }
    .ar-total td { font-weight: 700; border-top: 2px solid #cbd5e1; }
</style>

<div class="container-fluid py-5">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-5">
        <div>
            <div class="text-muted fs-8">Exercice du {{ \Carbon\Carbon::parse($data['from'])->format('d/m/Y') }}
                au {{ \Carbon\Carbon::parse($data['to'])->format('d/m/Y') }}, {{ $data['entry_count'] }} écriture(s)</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.bilans.index') }}" class="btn btn-light">Tous les bilans</a>
            <a href="{{ route('admin.bilans.download', $report) }}" class="btn btn-primary">Télécharger le PDF</a>
        </div>
    </div>

    @if($report->isUnread())
    <div class="alert alert-warning">
        Ce bilan est signalé comme <strong>non lu</strong>. Il le restera tant que le PDF n'aura pas été téléchargé.
    </div>
    @endif

    <div class="row g-5">
        <div class="col-lg-6">
            <div class="card ar-card h-100">
                <div class="card-header"><h3 class="card-title">Actif</h3></div>
                <div class="card-body table-responsive">
                    <table class="table ar-table align-middle">
                        <thead><tr class="text-muted fs-8 text-uppercase"><th>Compte</th><th class="num">Montant</th></tr></thead>
                        <tbody>
                        @forelse($data['balance_sheet']['assets'] as $l)
                            <tr><td><span class="text-muted fs-8">{{ $l['code'] }}</span> {{ $l['name'] }}</td>
                                <td class="num">{{ $m($l['amount']) }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="text-muted text-center py-6">Aucun compte d'actif mouvementé.</td></tr>
                        @endforelse
                            <tr class="ar-total"><td>Total actif</td><td class="num">{{ $m($data['totals']['assets']) }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card ar-card h-100">
                <div class="card-header"><h3 class="card-title">Passif et capitaux propres</h3></div>
                <div class="card-body table-responsive">
                    <table class="table ar-table align-middle">
                        <thead><tr class="text-muted fs-8 text-uppercase"><th>Compte</th><th class="num">Montant</th></tr></thead>
                        <tbody>
                        @foreach($data['balance_sheet']['liabilities'] as $l)
                            <tr><td><span class="text-muted fs-8">{{ $l['code'] }}</span> {{ $l['name'] }}</td>
                                <td class="num">{{ $m($l['amount']) }}</td></tr>
                        @endforeach
                        @foreach($data['balance_sheet']['equity'] as $l)
                            <tr><td><span class="text-muted fs-8">{{ $l['code'] }}</span> {{ $l['name'] }}</td>
                                <td class="num">{{ $m($l['amount']) }}</td></tr>
                        @endforeach
                            <tr><td>Résultat de l'exercice</td><td class="num">{{ $m($data['totals']['net_result']) }}</td></tr>
                            <tr class="ar-total"><td>Total passif</td>
                                <td class="num">{{ $m($data['totals']['liabilities_and_equity']) }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card ar-card mt-5">
        <div class="card-header"><h3 class="card-title">Compte de résultat</h3></div>
        <div class="card-body table-responsive">
            <table class="table ar-table align-middle">
                <thead><tr class="text-muted fs-8 text-uppercase"><th>Compte</th><th class="num">Montant</th></tr></thead>
                <tbody>
                @foreach($data['income_statement']['revenue'] as $l)
                    <tr><td><span class="text-muted fs-8">{{ $l['code'] }}</span> {{ $l['name'] }}</td>
                        <td class="num">{{ $m($l['amount']) }}</td></tr>
                @endforeach
                    <tr class="ar-total"><td>Total produits</td><td class="num">{{ $m($data['totals']['revenue']) }}</td></tr>
                @foreach($data['income_statement']['expenses'] as $l)
                    <tr><td><span class="text-muted fs-8">{{ $l['code'] }}</span> {{ $l['name'] }}</td>
                        <td class="num">{{ $m($l['amount']) }}</td></tr>
                @endforeach
                    <tr class="ar-total"><td>Total charges</td><td class="num">{{ $m($data['totals']['expenses']) }}</td></tr>
                </tbody>
            </table>
            <div class="p-4 rounded-3 mt-4" style="background:{{ $data['totals']['net_result'] >= 0 ? '#dcfce7' : '#fee2e2' }}">
                <div class="fs-8 text-uppercase fw-bold opacity-75">Résultat de l'exercice</div>
                <div class="fs-1 fw-bold">{{ $m($data['totals']['net_result']) }}
                    <span class="fs-6 fw-normal">{{ $data['totals']['net_result'] >= 0 ? 'bénéfice' : 'perte' }}</span></div>
            </div>
        </div>
    </div>
</div>
@endsection
