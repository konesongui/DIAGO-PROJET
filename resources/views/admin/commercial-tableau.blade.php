@extends('admin.layout')

@section('content')
<style>
    .commercial-flow-grid { min-width: 0; }
    @media (max-width: 767px) {
        .commercial-flow-grid { grid-template-columns: 58px 1fr !important; }
    }
</style>
@php
    $money = fn ($value) => number_format((float) $value, 0, ',', ' ') . ' FCFA';
    $cards = [
        ['Chiffre d’affaires réalisé', $invoiced + $detailLists['sales']->sum('total'), 'invoices', 'primary'],
        ['Croissance mensuelle', $monthlyGrowth, 'invoices', 'success'],
        ['Nouveaux clients', $newClients->count(), 'clients', 'info'],
        ['Factures impayées', $unpaidAmount, 'unpaid', 'danger'],
    ];
    $maxFlow = max((float) $months->max(fn ($month) => max($month['invoices'], $month['custom_invoices'], $month['sales'])), 1);
@endphp
<div class="d-flex justify-content-between align-items-center mb-5">
    <div><div class="text-uppercase text-muted fs-8 fw-bold">Commercial</div><h1 class="fs-2 fw-bold mb-1">Tableau Commercial</h1><p class="text-muted mb-0">Pilotage des ventes, encaissements, clients et opportunités.</p></div>
    <a href="{{ route('admin.commercial') }}" class="btn btn-light">Retour aux modules</a>
</div>
<div class="card border-0 shadow-sm mb-5">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.commercial.tableau') }}" class="d-flex flex-wrap align-items-end gap-3">
            <div><label class="form-label fw-bold mb-1">Période du</label><input type="date" name="date_debut" value="{{ $paymentFrom }}" class="form-control"></div>
            <div><label class="form-label fw-bold mb-1">au</label><input type="date" name="date_fin" value="{{ $paymentTo }}" class="form-control"></div>
            <button type="submit" class="btn btn-primary">Filtrer toute la page</button>
            <a href="{{ route('admin.commercial.tableau') }}" class="btn btn-light">Réinitialiser</a>
        </form>
        <div class="text-muted fs-8 mt-3">La période s’applique aux ventes, factures, clients, prospects, devis, commandes, services et mouvements de stock.</div>
    </div>
</div>
<div class="row g-5 mb-5">
@foreach($cards as [$label, $value, $detailKey, $color])
    @php($modalId = 'commercialDetail' . $loop->index)
    <div class="col-xl-3 col-md-6"><div class="card border-0 shadow-sm h-100"><div class="card-body p-5">
        <div class="d-flex justify-content-between align-items-start"><span class="text-muted text-uppercase fs-8 fw-bold">{{ $label }}</span><button class="btn btn-icon btn-sm btn-light-primary" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}" title="Voir les détails" aria-label="Voir les détails"><i class="bi bi-eye"></i></button></div>
        <div class="fs-2hx fw-bold text-{{ $color }} mt-3">{{ $label === 'Croissance mensuelle' ? number_format($value, 1, ',', ' ') . ' %' : ($label === 'Nouveaux clients' ? $value : $money($value)) }}</div><div class="text-muted mt-2">Cliquer sur l’œil pour voir les enregistrements.</div>
    </div></div></div>
    <div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">{{ $label }}</h5><button class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
        <div class="modal-body"><div class="table-responsive"><table class="table align-middle"><tbody>
        @forelse($detailKey === 'invoices' ? $detailLists['invoices']->merge($detailLists['customInvoices']) : $detailLists[$detailKey] as $item)
            <tr><td><strong>{{ $item->client_name ?? $item->name ?? 'Client / opération' }}</strong><div class="text-muted fs-8">{{ $item->reference ?? $item->status ?? $item->payment_method ?? '' }}</div></td><td class="text-end fw-bold">{{ $money($item->amount ?? $item->total_ttc ?? $item->total ?? 0) }}</td></tr>
        @empty <tr><td class="text-center text-muted py-5">Aucun enregistrement.</td></tr>@endforelse
        </tbody></table></div></div><div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Fermer</button></div>
    </div></div></div>
@endforeach
</div>
<div class="row g-5 mb-5">
    <div class="col-xl-7"><div class="card border-0 shadow-sm h-100"><div class="card-header border-0 pt-5"><h3 class="card-title fw-bold">Chiffre d’affaires par mois</h3></div><div class="card-body">
        @php($maxRevenue = max((float) $monthlyRevenue->max('value'), 1))
        @foreach($monthlyRevenue as $month)<div class="d-flex align-items-center gap-3 mb-3"><span style="width:65px" class="text-muted fs-8">{{ $month['label'] }}</span><div class="progress flex-grow-1" style="height:12px"><div class="progress-bar bg-primary" style="width:{{ ($month['value'] / $maxRevenue) * 100 }}%"></div></div><strong style="width:120px;text-align:right">{{ $money($month['value']) }}</strong></div>@endforeach
    </div></div></div>
    <div class="col-xl-5"><div class="card border-0 shadow-sm h-100"><div class="card-header border-0 pt-5"><h3 class="card-title fw-bold">Répartition par ville</h3></div><div class="card-body">
        @forelse($cityStats as $city => $count)<div class="d-flex justify-content-between border-bottom py-3"><span>{{ $city }}</span><strong>{{ $count }} client(s)</strong></div>@empty<div class="text-muted py-4">Aucune ville renseignée.</div>@endforelse
    </div></div></div>
</div>
<div class="row g-5 mb-5">
    <div class="col-xl-7"><div class="card border-0 shadow-sm h-100"><div class="card-header border-0 pt-5"><h3 class="card-title fw-bold">Évolution du CA global</h3></div><div class="card-body">
        @php($maxGlobal = max((float) $monthlyRevenue->max('value'), 1))
        <div class="d-flex align-items-end gap-2" style="height:180px">@foreach($monthlyRevenue as $month)<div class="flex-grow-1 text-center"><div class="bg-success rounded-top" style="height:{{ max(8, ($month['value'] / $maxGlobal) * 150) }}px" title="{{ $money($month['value']) }}"></div><small class="text-muted">{{ $month['label'] }}</small></div>@endforeach</div>
    </div></div></div>
    <div class="col-xl-5"><div class="card border-0 shadow-sm h-100"><div class="card-header border-0 pt-5"><h3 class="card-title fw-bold">Performance par commercial</h3></div><div class="card-body">
        @forelse($commercialPerformance as $commercial)<div class="d-flex justify-content-between border-bottom py-3"><span>{{ $commercial['name'] }} <small class="text-muted">({{ $commercial['count'] }})</small></span><strong>{{ $money($commercial['value']) }}</strong></div>@empty<div class="text-muted py-4">Aucune vente attribuée.</div>@endforelse
    </div></div></div>
</div>
<div class="row g-5 mb-5">
    <div class="col-xl-6"><div class="card border-0 shadow-sm h-100"><div class="card-header border-0 pt-5"><h3 class="card-title fw-bold">Top 10 produits/services vendus</h3></div><div class="card-body">
        @forelse($productStats as $product => $quantity)<div class="d-flex justify-content-between border-bottom py-3"><span>{{ $product }}</span><strong>{{ number_format($quantity, 0, ',', ' ') }}</strong></div>@empty<div class="text-muted py-4">Aucune vente détaillée.</div>@endforelse
    </div></div></div>
    <div class="col-xl-6"><div class="card border-0 shadow-sm h-100"><div class="card-header border-0 pt-5"><h3 class="card-title fw-bold">Stock faible / À réapprovisionner</h3></div><div class="card-body">
        @forelse($lowStock as $item)<div class="d-flex justify-content-between border-bottom py-3"><span>{{ $item['article'] }} <small class="text-muted">{{ $item['unit'] }}</small></span><span class="badge bg-danger">{{ number_format($item['available'], 0, ',', ' ') }}</span></div>@empty<div class="text-success py-4">Aucun article en rupture.</div>@endforelse
    </div></div></div>
</div>
<div class="card border-0 shadow-sm mb-5"><div class="card-header border-0 pt-5"><h3 class="card-title fw-bold">Récapitulatif des paiements clients</h3></div><div class="card-body">
<form method="GET" action="{{ route('admin.commercial.tableau') }}" class="d-flex flex-wrap align-items-end gap-3 mb-5">
    <div><label class="form-label fw-bold mb-1">Du :</label><input type="date" name="date_debut" value="{{ $paymentFrom }}" class="form-control"></div>
    <div><label class="form-label fw-bold mb-1">Au :</label><input type="date" name="date_fin" value="{{ $paymentTo }}" class="form-control"></div>
    <button class="btn btn-primary" type="submit">Filtrer</button>
    <a href="{{ route('admin.commercial.tableau') }}" class="btn btn-light">Réinitialiser</a>
</form>
<div class="table-responsive"><table class="table align-middle table-row-bordered"><thead><tr><th>Client</th><th>Dernière échéance</th><th>Total à payer</th><th>Déjà payé</th><th>Reste à payer</th></tr></thead><tbody>
@forelse($paymentRecap as $row)
    <tr><td>{{ $row['client'] }}</td><td>{!! $row['due_date'] ? '<span class="badge ' . ($row['due_date']->isPast() && $row['remaining'] > 0 ? 'bg-danger' : 'bg-light text-dark') . '">' . $row['due_date']->format('d/m/Y') . '</span>' : '-' !!}</td><td><span class="badge bg-info">{{ $money($row['total']) }}</span></td><td><span class="badge bg-success">{{ $money($row['paid']) }}</span></td><td><span class="badge {{ $row['remaining'] > 0 ? 'bg-danger' : 'bg-success' }}">{{ $row['remaining'] > 0 ? $money($row['remaining']) : '✓ Soldé' }}</span></td></tr>
@empty <tr><td colspan="5" class="text-center text-muted py-5">Aucun paiement client sur cette période.</td></tr>
@endforelse
</tbody></table></div>
<div class="row g-4 mt-2">
    @forelse($paymentSummary as $method => $amount)<div class="col-md-3"><div class="bg-light rounded p-4"><div class="text-muted">{{ $method }}</div><div class="fs-2 fw-bold mt-2">{{ $money($amount) }}</div></div></div>@empty<div class="col-12 text-muted">Aucun paiement enregistré.</div>@endforelse
</div></div></div>
<div class="row g-5">
    <div class="col-xl-8"><div class="card border-0 shadow-sm h-100"><div class="card-header border-0 pt-5"><h3 class="card-title fw-bold">Évolution facturation et ventes</h3></div><div class="card-body">
        <div class="commercial-flow-grid d-none d-md-grid text-muted fs-8 fw-bold mb-3" style="grid-template-columns:70px 1fr 1fr 1fr;gap:16px;">
            <span>Période</span><span>Factures</span><span>Factures personnalisées</span><span>Point de vente</span>
        </div>
        @foreach($months as $month)
            <div class="commercial-flow-grid d-grid align-items-center mb-4" style="grid-template-columns:70px 1fr 1fr 1fr;gap:16px;">
                <span class="text-muted fw-semibold">{{ $month['label'] }}</span>
                @foreach([['invoices', 'primary'], ['custom_invoices', 'warning'], ['sales', 'success']] as [$series, $color])
                    <div class="min-w-0">
                        <div class="d-flex justify-content-between align-items-center gap-2 mb-1">
                            <span class="d-md-none text-muted fs-8">{{ $series === 'invoices' ? 'Factures' : ($series === 'custom_invoices' ? 'Personnalisées' : 'Point de vente') }}</span>
                            <strong class="text-dark fs-8 text-nowrap">{{ $money($month[$series]) }}</strong>
                        </div>
                        <div class="progress" style="height:9px"><div class="progress-bar bg-{{ $color }}" style="width:{{ ($month[$series] / $maxFlow) * 100 }}%"></div></div>
                    </div>
                @endforeach
            </div>
        @endforeach
        <div class="text-muted fs-8"><span class="badge bg-primary">&nbsp;</span> Factures <span class="badge bg-warning ms-3">&nbsp;</span> Factures personnalisées <span class="badge bg-success ms-3">&nbsp;</span> Point de vente</div>
    </div></div></div>
    <div class="col-xl-4"><div class="card border-0 shadow-sm h-100"><div class="card-header border-0 pt-5"><h3 class="card-title fw-bold">Indicateurs</h3></div><div class="card-body">
        @foreach([['Clients', 'clients'], ['Devis', 'quotes'], ['Commandes', 'orders'], ['Prospects', 'leads'], ['Services', 'services']] as [$label, $key])
            <div class="d-flex justify-content-between border-bottom py-3"><span class="text-muted">{{ $label }}</span><strong>{{ $detailLists[$key]->count() }}</strong></div>
        @endforeach
    </div></div></div>
</div>
@endsection
