@extends('admin.layout')

@section('content')
@php
    $isCancelled = $sale->status === 'cancelled';
    $lines = collect($sale->lines ?: []);
    $number = $sale->reference ?: 'N° ' . $sale->id;
    $quantity = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, ',', ' '), '0'), ',');
    $isCash = $sale->payment_method === 'cash';
    $account = $isCash ? $sale->cashAccount?->name : $sale->bankAccount?->name;
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="'Vente ' . $number" :subtitle="$sale->created_at->format('d/m/Y à H:i') . ' · ' . ($sale->client_name ?: 'Client comptoir')" :back="route('admin.commercial.module', 'point-de-vente')" back-label="Point de vente">
        <x-slot:actions>
            @if($isCancelled)
                <form method="POST" action="{{ route('admin.commercial.pos.destroy', $sale) }}" onsubmit="return confirm('Supprimer définitivement cette vente annulée ?')">
                    @csrf @method('DELETE')
                    <button class="dg-btn dg-btn--outline dg-btn--danger"><i class="bi bi-trash"></i>Supprimer</button>
                </form>
            @else
                <button type="button" class="dg-btn dg-btn--outline dg-btn--danger" data-bs-toggle="modal" data-bs-target="#cancelSaleModal"
                    data-action="{{ route('admin.commercial.pos.cancel', $sale) }}"
                    data-label="{{ $number }} · {{ $sale->client_name ?: 'Client comptoir' }}"
                    data-amount="{{ money((float) $sale->total) }}"
                    data-where="{{ $sale->refundSourceLabel() }}"><i class="bi bi-x-circle"></i>Annuler la vente</button>
                <a href="{{ route('admin.commercial.pos.edit', $sale) }}" class="dg-btn dg-btn--outline"><i class="bi bi-pencil"></i>Modifier</a>
            @endif
            <a href="{{ route('admin.commercial.pos.create') }}" class="dg-btn dg-btn--primary"><i class="bi bi-plus-lg"></i>Nouvelle vente</a>
        </x-slot:actions>
    </x-dg.page-header>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="dg-callout dg-tone-{{ $isCancelled ? 'red' : 'green' }} mb-6">
        <span class="dg-tile"><i class="bi {{ $isCancelled ? 'bi-x-circle' : 'bi-check2-circle' }}"></i></span>
        <div style="flex:1 1 320px;min-width:0">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="fw-semibold">{{ $isCancelled ? 'Vente annulée' : 'Vente terminée' }}</span>
                <span class="dg-badge dg-badge--{{ $isCancelled ? 'danger' : 'success' }}"><i class="bi {{ $isCancelled ? 'bi-x-circle' : 'bi-check-lg' }}"></i>{{ $isCancelled ? 'Annulée' : 'Terminée' }}</span>
            </div>
            <div class="dg-muted mt-1" style="font-size:13.5px">
                @if($isCancelled)
                    {{ money((float) $sale->total) }} sont ressortis {{ $sale->refundSourceLabel() }}, et la vente a été retirée du journal. Elle peut être supprimée de la liste.
                @else
                    Encaissée le {{ $sale->created_at->format('d/m/Y à H:i') }} {{ $isCash ? 'en espèces' : 'par banque' }}{{ $account ? ' (' . $account . ')' : '' }}.
                @endif
            </div>
        </div>
    </div>

    <div class="pos-show">
        <div class="dg-card dg-card--table">
            <div class="dg-card__header">
                <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-purple"><i class="bi bi-basket"></i></span>Articles</h2>
                <span class="dg-card__meta">{{ $lines->count() }} ligne(s)</span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0 no-export no-column-sort">
                    <thead>
                        <tr>
                            <th>Désignation</th>
                            <th class="text-end">Qté</th>
                            <th class="text-end">Prix unitaire TTC</th>
                            <th class="text-end">Total TTC</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($lines as $line)
                            <tr>
                                <td class="fw-semibold">{{ $line['item_name'] ?? '—' }}</td>
                                <td class="text-end dg-cell-num">{{ $quantity($line['quantity'] ?? 0) }}</td>
                                <td class="text-end dg-cell-num">{{ money((float) ($line['unit_price'] ?? 0)) }}</td>
                                <td class="text-end dg-cell-num fw-semibold">{{ money((float) ($line['quantity'] ?? 0) * (float) ($line['unit_price'] ?? 0)) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center dg-muted py-6">Aucun article.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pos-show__totals">
                <div><span>Total HT</span><strong>{{ money((float) $sale->total_ht) }}</strong></div>
                <div><span>{{ $sale->taxLabel() }}</span><strong>{{ money((float) $sale->tax_amount) }}</strong></div>
                <div class="pos-show__grand {{ $isCancelled ? 'is-cancelled' : '' }}"><span>Total TTC</span><strong>{{ money((float) $sale->total) }}</strong></div>
            </div>
        </div>

        <div class="d-flex flex-column gap-4">
            <x-dg.card title="Encaissement" icon="bi-cash-coin" color="green">
                <table class="dg-mini-table">
                    <tr><td class="dg-muted">Mode</td><td><i class="bi {{ $isCash ? 'bi-cash-stack text-success' : 'bi-bank text-info' }} me-1"></i>{{ $isCash ? 'Espèces' : 'Banque' }}</td></tr>
                    <tr><td class="dg-muted">{{ $isCash ? 'Caisse' : 'Compte' }}</td><td>{{ $account ?: '—' }}</td></tr>
                    <tr><td class="dg-muted">Montant reçu</td><td>{{ money((float) $sale->paid_amount) }}</td></tr>
                    <tr><td class="dg-muted">Monnaie rendue</td><td class="text-success">{{ money((float) $sale->change_amount) }}</td></tr>
                </table>
            </x-dg.card>
            <x-dg.card title="Client" icon="bi-person" color="blue">
                <div class="fw-semibold" style="font-size:16px">{{ $sale->client_name ?: 'Client comptoir' }}</div>
                <div class="dg-muted" style="font-size:13px">{{ $sale->client_id ? 'Client du carnet' : 'Vente sans client enregistré' }}</div>
            </x-dg.card>
        </div>
    </div>

    @unless($isCancelled)
        @include('admin.partials.pos-cancel-modal')
    @endunless
</div>

<style>
    .pos-show { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 24px; align-items: start; }
    .pos-show__totals { display: flex; flex-direction: column; gap: 6px; width: min(100%, 360px); margin: 18px 0 0 auto; font-size: 14px; }
    .pos-show__totals > div { display: flex; justify-content: space-between; gap: 16px; }
    .pos-show__totals strong { font-weight: 500; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .pos-show__grand { margin-top: 6px; padding: 12px 16px; border-radius: var(--dg-radius); background: var(--dg-navy); color: #fff; }
    .pos-show__grand span { font-weight: 600; }
    .pos-show__grand strong { font-size: 19px; font-weight: 700 !important; color: var(--dg-yellow); }
    .pos-show__grand.is-cancelled strong { text-decoration: line-through; }
    @media (max-width: 991px) { .pos-show { grid-template-columns: 1fr; } }
</style>
@endsection
