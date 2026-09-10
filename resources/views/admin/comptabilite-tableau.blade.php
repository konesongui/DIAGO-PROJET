@extends('admin.layout')

@section('content')
@php
    $money = fn ($value) => money((float) $value);
    $cards = [
        ['Liquidité totale', $liquidity, 'cash_accounts', 'primary'],
        ['Entrées caisse et banque', $cashIn + $bankIn, 'cash_movements', 'success'],
        ['Sorties caisse et banque', $cashOut + $bankOut, 'bank_transactions', 'danger'],
        ['Flux du mois', $monthlyTotal, 'transfers', 'warning'],
    ];
    $maxFlow = max((float) $months->max(fn ($month) => max($month['entries'], $month['sorties'])), 1);
@endphp
<div class="d-flex justify-content-between align-items-center mb-5">
    <div><div class="text-uppercase text-muted fs-8 fw-bold">Comptabilité</div><h1 class="fs-2 fw-bold mb-1">Tableau Comptabilité</h1><p class="text-muted mb-0">Pilotage des liquidités, flux et engagements financiers.</p></div>
    <a href="{{ route('admin.comptabilite') }}" class="btn btn-light">Retour aux modules</a>
</div>
<div class="card border-0 shadow-sm mb-5"><div class="card-body">
    <form method="GET" action="{{ route('admin.comptabilite.tableau') }}" class="d-flex flex-wrap align-items-end gap-3">
        <div><label class="form-label fw-bold mb-1">Période du</label><input type="date" name="date_debut" value="{{ $periodFrom }}" class="form-control"></div>
        <div><label class="form-label fw-bold mb-1">au</label><input type="date" name="date_fin" value="{{ $periodTo }}" class="form-control"></div>
        <button type="submit" class="btn btn-primary">Filtrer toute la page</button>
        <a href="{{ route('admin.comptabilite.tableau') }}" class="btn btn-light">Réinitialiser</a>
    </form>
</div></div>
<div class="row g-5 mb-5">
@foreach($cards as [$label, $value, $detailKey, $color])
    @php($modalId = 'accountingDetail' . $loop->index)
    <div class="col-xl-3 col-md-6"><div class="card border-0 shadow-sm h-100"><div class="card-body p-5">
        <div class="d-flex justify-content-between align-items-start"><span class="text-muted text-uppercase fs-8 fw-bold">{{ $label }}</span><button class="btn btn-icon btn-sm btn-light-primary" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}" title="Voir les détails" aria-label="Voir les détails"><i class="bi bi-eye"></i></button></div>
        <div class="fs-2hx fw-bold text-{{ $color }} mt-3">{{ $money($value) }}</div><div class="text-muted mt-2">Cliquer sur l’œil pour consulter les opérations.</div>
    </div></div></div>
    <div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">{{ $label }}</h5><button class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
        <div class="modal-body"><div class="table-responsive"><table class="table align-middle"><tbody>
        @forelse($detailLists[$detailKey] as $item)
            <tr><td><strong>{{ $item->name ?? $item->label ?? $item->description ?? $item->reference ?? 'Opération' }}</strong><div class="text-muted fs-8">{{ $item->bank_name ?? $item->cashAccount?->name ?? $item->bankAccount?->name ?? $item->transfer_date?->format('d/m/Y') ?? '' }}</div></td><td class="text-end fw-bold">{{ $money($item->amount ?? $item->balance ?? $item->current_balance ?? $item->acquisition_value ?? 0) }}</td></tr>
        @empty <tr><td class="text-center text-muted py-5">Aucun enregistrement.</td></tr>@endforelse
        </tbody></table></div></div><div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Fermer</button></div>
    </div></div></div>
@endforeach
</div>
<div class="row g-5">
    <div class="col-xl-8"><div class="card border-0 shadow-sm h-100"><div class="card-header border-0 pt-5"><h3 class="card-title fw-bold">Évolution des flux</h3></div><div class="card-body">
        @foreach($months as $month)<div class="d-flex align-items-center gap-3 mb-4"><span style="width:38px" class="text-muted fw-semibold">{{ $month['label'] }}</span><div class="flex-grow-1"><div class="progress mb-2" style="height:9px"><div class="progress-bar bg-success" style="width:{{ ($month['entries'] / $maxFlow) * 100 }}%"></div></div><div class="progress" style="height:9px"><div class="progress-bar bg-danger" style="width:{{ ($month['sorties'] / $maxFlow) * 100 }}%"></div></div></div><span class="text-muted fs-8" style="width:115px">{{ $money($month['entries']) }} / {{ $money($month['sorties']) }}</span></div>@endforeach
        <div class="text-muted fs-8"><span class="badge bg-success">&nbsp;</span> Entrées <span class="badge bg-danger ms-3">&nbsp;</span> Sorties</div>
    </div></div></div>
    <div class="col-xl-4"><div class="card border-0 shadow-sm h-100"><div class="card-header border-0 pt-5"><h3 class="card-title fw-bold">Indicateurs</h3></div><div class="card-body">
        <div class="d-flex justify-content-between border-bottom py-3"><span class="text-muted">Comptes caisse</span><strong>{{ $detailLists['cash_accounts']->count() }}</strong></div>
        <div class="d-flex justify-content-between border-bottom py-3"><span class="text-muted">Comptes bancaires</span><strong>{{ $detailLists['bank_accounts']->count() }}</strong></div>
        <div class="d-flex justify-content-between border-bottom py-3"><span class="text-muted">Factures fournisseurs</span><strong>{{ $detailLists['supplier_invoices']->count() }}</strong></div>
        <div class="d-flex justify-content-between py-3"><span class="text-muted">Immobilisations</span><strong>{{ $detailLists['fixed_assets']->count() }}</strong></div>
    </div></div></div>
</div>
@endsection
