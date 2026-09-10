@extends('admin.layout')

@section('content')
@php
    $money = fn ($v) => (float) $v == 0.0 ? '' : number_format((float) $v, $decimals ?? 0, ',', ' ');
    $total = fn ($v) => number_format((float) $v, $decimals ?? 0, ',', ' ') . ' ' . ($currencySymbol ?? '');
@endphp
<style>
    .led-card { border: 1px solid #edf2f7; border-radius: 18px; background: #fff; box-shadow: 0 12px 30px rgba(15,23,42,.04); }
    .led-table td, .led-table th { vertical-align: middle; }
    .led-table td.num, .led-table th.num { text-align: right; font-variant-numeric: tabular-nums; }
    .entry-head td { background: #f8fafc; font-weight: 600; }
    .entry-line td { border-top: none; }
    .entry-line .acct { padding-left: 28px; color: #475569; }
    .jrnl { font-size: .7rem; padding: .15rem .5rem; border-radius: 999px; background: #eef2ff; color: #4338ca; }
    .ok-badge { background: #dcfce7; color: #166534; padding: .2rem .6rem; border-radius: 999px; font-size: .72rem; }
    .ko-badge { background: #fee2e2; color: #991b1b; padding: .2rem .6rem; border-radius: 999px; font-size: .72rem; }
</style>

<div class="container-fluid py-5">

    <div class="card led-card mb-5">
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
                    <label class="form-label fw-semibold">Journal</label>
                    <select name="journal" class="form-select">
                        <option value="">Tous</option>
                        @foreach($journaux as $code => $label)
                            <option value="{{ $code }}" @selected($journalFilter === $code)>{{ $code }} — {{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3"><button class="btn btn-primary">Afficher</button></div>
            </form>
        </div>
    </div>

    <div class="row g-5 mb-5">
        <div class="col-md-4"><div class="card led-card h-100"><div class="card-body">
            <div class="text-muted fs-8 text-uppercase fw-bold">Total débits</div>
            <div class="fs-2 fw-bold">{{ $total($totalDebit) }}</div>
        </div></div></div>
        <div class="col-md-4"><div class="card led-card h-100"><div class="card-body">
            <div class="text-muted fs-8 text-uppercase fw-bold">Total crédits</div>
            <div class="fs-2 fw-bold">{{ $total($totalCredit) }}</div>
        </div></div></div>
        <div class="col-md-4"><div class="card led-card h-100"><div class="card-body">
            <div class="text-muted fs-8 text-uppercase fw-bold">Équilibre</div>
            <div class="fs-2 fw-bold">
                @if(abs($totalDebit - $totalCredit) < 0.01)
                    <span class="ok-badge">Équilibré</span>
                @else
                    <span class="ko-badge">Écart de {{ $total(abs($totalDebit - $totalCredit)) }}</span>
                @endif
            </div>
        </div></div></div>
    </div>

    <div class="card led-card mb-5">
        <div class="card-header"><h3 class="card-title">Pièces de la période</h3></div>
        <div class="card-body table-responsive">
            <table class="table led-table align-middle">
                <thead><tr class="text-muted fs-8 text-uppercase">
                    <th>Pièce</th><th>Date</th><th>Libellé</th><th class="num">Débit</th><th class="num">Crédit</th>
                </tr></thead>
                <tbody>
                @forelse($entries as $entry)
                    <tr class="entry-head">
                        <td>{{ $entry->reference }} <span class="jrnl ms-2">{{ $entry->journal }}</span></td>
                        <td>{{ $entry->entry_date->format('d/m/Y') }}</td>
                        <td>{{ $entry->label }}</td>
                        <td class="num">{{ $money($entry->totalDebit()) }}</td>
                        <td class="num">{{ $money($entry->totalCredit()) }}</td>
                    </tr>
                    @foreach($entry->lines as $line)
                    <tr class="entry-line">
                        <td></td>
                        <td class="text-muted fs-8">{{ $line->account->code }}</td>
                        <td class="acct">{{ $line->account->name }}@if($line->label) <span class="text-muted fs-8">— {{ $line->label }}</span>@endif</td>
                        <td class="num">{{ $money($line->debit) }}</td>
                        <td class="num">{{ $money($line->credit) }}</td>
                    </tr>
                    @endforeach
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-10">Aucune écriture sur la période.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card led-card">
        <div class="card-header"><h3 class="card-title">Balance des comptes</h3></div>
        <div class="card-body table-responsive">
            <table class="table led-table align-middle">
                <thead><tr class="text-muted fs-8 text-uppercase">
                    <th>Compte</th><th>Intitulé</th><th class="num">Débit</th><th class="num">Crédit</th><th class="num">Solde</th>
                </tr></thead>
                <tbody>
                @forelse($balance as $row)
                    @php($solde = (float) $row->debit - (float) $row->credit)
                    <tr>
                        <td class="fw-semibold">{{ $row->code }}</td>
                        <td>{{ $row->name }}</td>
                        <td class="num">{{ $money($row->debit) }}</td>
                        <td class="num">{{ $money($row->credit) }}</td>
                        <td class="num fw-semibold">
                            {{ number_format(abs($solde), $decimals ?? 0, ',', ' ') }}
                            <span class="text-muted fs-8">{{ $solde >= 0 ? 'D' : 'C' }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-10">Aucun mouvement sur la période.</td></tr>
                @endforelse
                </tbody>
                <tfoot>
                    <tr class="fw-bold border-top">
                        <td colspan="2">Totaux</td>
                        <td class="num">{{ $money($totalDebit) }}</td>
                        <td class="num">{{ $money($totalCredit) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection
