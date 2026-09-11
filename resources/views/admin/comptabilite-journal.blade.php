@extends('admin.layout')

@section('content')
@php
    $money = fn ($v) => (float) $v == 0.0 ? '' : number_format((float) $v, $decimals ?? 0, ',', ' ');
    $total = fn ($v) => number_format((float) $v, $decimals ?? 0, ',', ' ') . ' ' . ($currencySymbol ?? '');
    $gap = abs($totalDebit - $totalCredit);
    $isBalanced = $gap < 0.01;
@endphp
<style>
    .led-table td.num, .led-table th.num { text-align: right; font-variant-numeric: tabular-nums; }
    .dg-scope .led-table > tbody > tr.entry-head > td { background: var(--dg-table-head); font-weight: 600; }
    .dg-scope .led-table > tbody > tr.entry-line > td { padding-top: 8px !important; padding-bottom: 8px !important; }
    .led-table .entry-line .acct { padding-left: 28px !important; color: var(--dg-muted); }
    .dg-scope .led-table tfoot td { padding: 14px 16px; border-top: 2px solid var(--dg-navy); background: var(--dg-neutral-bg); font-weight: 700; color: var(--dg-navy); }
</style>

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title ?? 'Journal comptable'" subtitle="Écritures en partie double et balance des comptes de la période." :back="route('admin.comptabilite')" back-label="Comptabilité">
        <x-slot:actions>
            <form method="GET" class="dg-period" aria-label="Filtrer le journal">
                <input type="date" name="date_debut" value="{{ $dateDebut }}" class="dg-input" aria-label="Du">
                <span class="dg-period__sep">au</span>
                <input type="date" name="date_fin" value="{{ $dateFin }}" class="dg-input" aria-label="Au">
                <select name="journal" class="dg-select" style="width:auto;min-height:42px" aria-label="Journal">
                    <option value="">Tous les journaux</option>
                    @foreach($journaux as $code => $label)
                        <option value="{{ $code }}" @selected($journalFilter === $code)>{{ $code }} — {{ $label }}</option>
                    @endforeach
                </select>
                <button class="dg-btn dg-btn--outline"><i class="bi bi-funnel"></i>Afficher</button>
            </form>
        </x-slot:actions>
    </x-dg.page-header>

    <div class="dg-kpi-grid">
        <x-dg.kpi label="Total débits" :value="$total($totalDebit)" icon="bi-box-arrow-in-down" color="blue" hint="sur la période" />
        <x-dg.kpi label="Total crédits" :value="$total($totalCredit)" icon="bi-box-arrow-up" color="purple" hint="sur la période" />
        <x-dg.kpi label="Équilibre" :value="$isBalanced ? 'Équilibré' : 'Écart de ' . $total($gap)" :icon="$isBalanced ? 'bi-check2-circle' : 'bi-exclamation-triangle'" :color="$isBalanced ? 'green' : 'red'" :hint="$isBalanced ? 'débits et crédits concordent' : 'débits et crédits ne concordent pas'" />
    </div>

    <x-dg.card title="Pièces de la période" icon="bi-journal-text" color="teal" class="dg-card--table mb-6">
        <div class="table-responsive">
            {{-- Pas de tri par colonne : il séparerait les lignes de leur pièce. --}}
            <table class="table led-table align-middle mb-0 no-column-sort">
                <thead><tr><th>Pièce</th><th>Date</th><th>Libellé</th><th class="num">Débit</th><th class="num">Crédit</th></tr></thead>
                <tbody>
                @forelse($entries as $entry)
                    <tr class="entry-head">
                        <td class="text-nowrap">{{ $entry->reference }} <span class="dg-badge dg-badge--neutral ms-2">{{ $entry->journal }}</span></td>
                        <td>{{ $entry->entry_date->format('d/m/Y') }}</td>
                        <td>{{ $entry->label }}</td>
                        <td class="num">{{ $money($entry->totalDebit()) }}</td>
                        <td class="num">{{ $money($entry->totalCredit()) }}</td>
                    </tr>
                    @foreach($entry->lines as $line)
                        <tr class="entry-line">
                            <td></td>
                            <td class="dg-muted" style="font-size:12.5px">{{ $line->account->code }}</td>
                            <td class="acct">{{ $line->account->name }}@if($line->label) <span class="dg-muted" style="font-size:12.5px">— {{ $line->label }}</span>@endif</td>
                            <td class="num">{{ $money($line->debit) }}</td>
                            <td class="num">{{ $money($line->credit) }}</td>
                        </tr>
                    @endforeach
                @empty
                    <tr>
                        <td colspan="5" class="p-5">
                            <div class="dg-chart-empty" style="min-height:180px">
                                <span class="dg-tile dg-tone-teal"><i class="bi bi-journal-text"></i></span>
                                <div><strong>Aucune écriture sur la période</strong>Les écritures sont passées automatiquement à la validation des ventes, achats et mouvements.</div>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-dg.card>

    <x-dg.card title="Balance des comptes" icon="bi-list-columns-reverse" color="indigo" class="dg-card--table">
        <div class="table-responsive">
            <table class="table led-table align-middle mb-0">
                <thead><tr><th>Compte</th><th>Intitulé</th><th class="num">Débit</th><th class="num">Crédit</th><th class="num">Solde</th></tr></thead>
                <tbody>
                @forelse($balance as $row)
                    @php
                        $solde = (float) $row->debit - (float) $row->credit;
                    @endphp
                    <tr>
                        <td class="fw-semibold">{{ $row->code }}</td>
                        <td>{{ $row->name }}</td>
                        <td class="num">{{ $money($row->debit) }}</td>
                        <td class="num">{{ $money($row->credit) }}</td>
                        <td class="num fw-semibold">
                            {{ number_format(abs($solde), $decimals ?? 0, ',', ' ') }}
                            <span class="dg-badge dg-badge--{{ $solde >= 0 ? 'neutral' : 'warning' }} ms-1">{{ $solde >= 0 ? 'D' : 'C' }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-8">Aucun mouvement sur la période.</td></tr>
                @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2">Totaux</td>
                        <td class="num">{{ $money($totalDebit) }}</td>
                        <td class="num">{{ $money($totalCredit) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </x-dg.card>
</div>
@endsection
