@extends('admin.layout')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-5">
    <div><div class="text-uppercase text-muted fs-8 fw-bold">Commercial / Point de vente</div><h2 class="fs-2 fw-bold mb-1">Détail de la vente</h2></div>
    <a href="{{ route('admin.commercial.module', 'point-de-vente') }}" class="btn btn-light">Retour</a>
</div>
<div class="card border-0"><div class="card-body">
    <p><strong>Client :</strong> {{ $sale->client_name ?: 'Client comptoir' }}</p>
    <p><strong>Statut :</strong> {{ $sale->status === 'cancelled' ? 'Annulée' : 'Terminée' }}</p>
    <table class="table align-middle"><thead><tr><th>Article</th><th>Qté</th><th>Prix unitaire</th><th class="text-end">Total</th></tr></thead><tbody>
    @foreach($sale->lines ?: [] as $line)<tr><td>{{ $line['item_name'] }}</td><td>{{ $line['quantity'] }}</td><td>{{ number_format($line['unit_price'], 0, ',', ' ') }} XOF</td><td class="text-end">{{ number_format($line['quantity'] * $line['unit_price'], 0, ',', ' ') }} XOF</td></tr>@endforeach
    </tbody></table>
    <div class="row text-end"><div class="col"><strong>À payer</strong><br>{{ number_format($sale->total, 0, ',', ' ') }} XOF</div><div class="col"><strong>Payé</strong><br>{{ number_format($sale->paid_amount, 0, ',', ' ') }} XOF</div><div class="col"><strong>Rendu</strong><br>{{ number_format($sale->change_amount, 0, ',', ' ') }} XOF</div></div>
</div></div>
@endsection
