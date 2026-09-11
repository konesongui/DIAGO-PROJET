@extends('admin.layout')

@section('content')
<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title ?? 'Bilans financiers'" subtitle="Bilan et compte de résultat des exercices clos, générés à partir du journal comptable." :back="route('admin.comptabilite')" back-label="Comptabilité" />

    <div class="dg-kpi-grid">
        <x-dg.kpi label="Exercices clos" :value="$reports->count()" icon="bi-clipboard-data" color="indigo" hint="avec des écritures comptables" />
        <x-dg.kpi label="Bilans non lus" :value="$reports->filter(fn ($report) => $report->isUnread())->count()" icon="bi-bell" color="orange" hint="PDF pas encore téléchargé" />
        <x-dg.kpi label="Dernier résultat" :value="$reports->isNotEmpty() ? number_format((float) $reports->first()->net_result, $reports->first()->data['decimals'] ?? 0, ',', ' ') . ' ' . ($reports->first()->data['currency_symbol'] ?? '') : '—'"
            icon="bi-graph-up-arrow" :color="$reports->isNotEmpty() && $reports->first()->net_result < 0 ? 'red' : 'green'"
            :hint="$reports->isNotEmpty() ? 'exercice ' . $reports->first()->fiscal_year : 'aucun exercice clos'" />
    </div>

    <x-dg.card title="Bilans des exercices clos" icon="bi-clipboard-data" color="green" class="dg-card--table">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Exercice</th><th class="text-end">Total actif</th><th class="text-end">Résultat</th><th>État</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                @forelse($reports as $r)
                    @php
                        $d = $r->data;
                    @endphp
                    <tr>
                        <td>
                            <span class="d-inline-flex align-items-center gap-3">
                                <span class="dg-tile dg-tile--sm dg-tone-{{ $r->net_result >= 0 ? 'green' : 'red' }}"><i class="bi bi-calendar-check"></i></span>
                                <span class="fw-semibold" style="font-size:16px">{{ $r->fiscal_year }}</span>
                            </span>
                        </td>
                        <td class="text-end dg-cell-num">{{ number_format((float) $r->total_assets, $d['decimals'] ?? 0, ',', ' ') }} {{ $d['currency_symbol'] ?? '' }}</td>
                        <td class="text-end dg-cell-num {{ $r->net_result >= 0 ? 'dg-amount-positive' : 'dg-amount-negative' }}">
                            {{ number_format((float) $r->net_result, $d['decimals'] ?? 0, ',', ' ') }} {{ $d['currency_symbol'] ?? '' }}
                        </td>
                        <td>
                            @if($r->isUnread())
                                <span class="dg-badge dg-badge--warning">Non lu</span>
                            @else
                                <span class="dg-badge dg-badge--success">Téléchargé le {{ $r->downloaded_at->format('d/m/Y') }}</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-2">
                                <a href="{{ route('admin.bilans.show', $r) }}" class="dg-btn dg-btn--outline dg-btn--sm"><i class="bi bi-eye"></i>Consulter</a>
                                <a href="{{ route('admin.bilans.download', $r) }}" class="dg-btn dg-btn--primary dg-btn--sm"><i class="bi bi-download"></i>PDF</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-5">
                            <div class="dg-chart-empty" style="min-height:200px">
                                <span class="dg-tile dg-tone-green"><i class="bi bi-clipboard-data"></i></span>
                                <div><strong>Aucun bilan disponible</strong>Aucun exercice clos ne comporte d’écritures comptables.</div>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-dg.card>
</div>
@endsection
