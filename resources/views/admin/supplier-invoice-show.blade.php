@extends('admin.layout')

@section('content')
@php
    $currency = ! $invoice->currency || $invoice->currency === company_currency()['code'] ? currency_symbol() : $invoice->currency;
    $amount = fn ($value) => $value !== null ? number_format((float) $value, 0, ',', ' ') . ' ' . $currency : 'À vérifier';
    // Libellés des données lues dans le PDF ; les montants sont formatés.
    $extractedLabels = [
        'invoice_number' => 'N° facture', 'supplier_name' => 'Fournisseur', 'supplier_tax_id' => 'NCC fournisseur',
        'invoice_date' => 'Date de facture', 'due_date' => 'Échéance', 'subtotal' => 'Sous-total',
        'total_ht' => 'Total HT', 'tax_amount' => 'Montant de TVA', 'total_amount' => 'Total TTC',
        'currency' => 'Devise', 'items' => 'Lignes',
    ];
    $amountKeys = ['subtotal', 'total_ht', 'tax_amount', 'total_amount'];
    $regimeUnconfirmed = in_array($invoice->tax_regime, [null, '', 'unknown'], true);
    $fneLabel = $invoice->fne_status === 'certified' ? 'Certifiée' : ($invoice->fne_status === 'failed' ? 'Échec' : 'Non certifiée');
    $fneTone = $invoice->fne_status === 'certified' ? 'success' : ($invoice->fne_status === 'failed' ? 'danger' : 'neutral');
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$invoice->invoice_number ? 'Facture ' . $invoice->invoice_number : 'Détail de la facture'" :subtitle="$invoice->original_filename" :back="route('admin.comptabilite.supplierInvoices')" back-label="Factures fournisseurs">
        <x-slot:actions>
            <a target="_blank" href="{{ route('admin.comptabilite.supplierInvoices.print', $invoice) }}" class="dg-btn dg-btn--outline"><i class="bi bi-printer"></i>Imprimer</a>
            <a target="_blank" href="{{ route('admin.comptabilite.supplierInvoices.pdf', $invoice) }}" class="dg-btn dg-btn--secondary"><i class="bi bi-file-earmark-pdf"></i>Voir le PDF</a>
        </x-slot:actions>
    </x-dg.page-header>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="dg-kpi-grid">
        <x-dg.kpi label="Fournisseur" :value="$invoice->supplier_name ?: 'À vérifier'" icon="bi-shop" color="indigo" :hint="$invoice->supplier_tax_id ? 'NCC ' . $invoice->supplier_tax_id : null" />
        <x-dg.kpi label="N° facture" :value="$invoice->invoice_number ?: 'À vérifier'" icon="bi-hash" color="blue" :hint="$invoice->invoice_date ? 'du ' . $invoice->invoice_date->format('d/m/Y') : null" />
        <x-dg.kpi label="Total HT" :value="$amount($invoice->total_ht ?? $invoice->subtotal)" icon="bi-calculator" color="teal" />
        <x-dg.kpi label="Total TTC" :value="$amount($invoice->total_amount)" icon="bi-cash-stack" color="orange" />
    </div>

    <div class="dg-grid-3 mb-6">
        <div class="dg-card d-flex align-items-center gap-3">
            <span class="dg-tile dg-tone-pink"><i class="bi bi-file-earmark-pdf"></i></span>
            <div style="min-width:0">
                <div class="dg-muted" style="font-size:13px">Fichier source</div>
                <div class="fw-semibold text-break">{{ $invoice->original_filename ?: '—' }}</div>
            </div>
        </div>
        <div class="dg-card d-flex align-items-center gap-3">
            <span class="dg-tile dg-tone-{{ $invoice->status === 'imported' ? 'green' : 'orange' }}"><i class="bi bi-magic"></i></span>
            <div>
                <div class="dg-muted" style="font-size:13px">État de l’extraction</div>
                <span class="dg-badge dg-badge--{{ $invoice->status === 'imported' ? 'success' : 'warning' }} mt-1">{{ $invoice->status === 'imported' ? 'Importée' : 'À vérifier' }}</span>
            </div>
        </div>
        <div class="dg-card d-flex align-items-center gap-3">
            <span class="dg-tile dg-tone-{{ $invoice->fne_status === 'certified' ? 'green' : ($invoice->fne_status === 'failed' ? 'red' : 'navy') }}"><i class="bi bi-patch-check"></i></span>
            <div>
                <div class="dg-muted" style="font-size:13px">Certification FNE</div>
                <span class="dg-badge dg-badge--{{ $fneTone }} mt-1">{{ $fneLabel }}</span>
                @if($invoice->fne_reference)<span class="d-block dg-muted mt-1" style="font-size:12.5px">{{ $invoice->fne_reference }}</span>@endif
            </div>
        </div>
    </div>

    <div class="dg-callout dg-tone-{{ $regimeUnconfirmed ? 'orange' : 'green' }} mb-6">
        <span class="dg-tile"><i class="bi {{ $regimeUnconfirmed ? 'bi-exclamation-triangle' : 'bi-shield-check' }}"></i></span>
        <div class="flex-grow-1">
            <div class="fw-semibold">Régime fiscal déclaré</div>
            <div style="font-size:13.5px" class="dg-muted">
                @if($regimeUnconfirmed)
                    Ce régime est issu d’une lecture automatique du PDF et n’a pas été confirmé. La certification est bloquée tant qu’il n’est pas renseigné.
                @else
                    Régime confirmé : <strong class="text-dark">{{ \App\Models\TaxRate::regimes()[$invoice->tax_regime] ?? $invoice->tax_regime }}</strong>.
                @endif
            </div>
        </div>
        @if($invoice->fne_status !== 'certified')
            <form method="POST" action="{{ route('admin.comptabilite.supplierInvoices.regime', $invoice) }}" class="d-flex flex-wrap gap-2">
                @csrf @method('PATCH')
                <select name="tax_regime" class="form-select" style="min-width:210px" aria-label="Régime fiscal">
                    @foreach($regimes ?? [] as $key => $label)
                        <option value="{{ $key }}" @selected($invoice->tax_regime === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="btn btn-primary">Confirmer</button>
            </form>
        @endif
    </div>

    <div class="dg-grid-halves">
        <x-dg.card title="Données extraites" icon="bi-list-check" color="blue" class="dg-card--table">
            <div class="table-responsive">
                <table class="table align-middle mb-0 no-export">
                    <tbody>
                        @forelse(($invoice->extracted_data ?? []) as $key => $value)
                            <tr>
                                <td class="dg-muted" style="width:40%">{{ $extractedLabels[$key] ?? ucfirst(str_replace('_', ' ', $key)) }}</td>
                                <td class="fw-semibold">
                                    @if(is_numeric($value) && in_array($key, $amountKeys, true))
                                        {{ $amount($value) }}
                                    @elseif(in_array($key, ['invoice_date', 'due_date'], true) && is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value))
                                        {{ \Carbon\Carbon::parse($value)->format('d/m/Y') }}
                                    @elseif(is_scalar($value) && $value !== null && $value !== '')
                                        {{ $value }}
                                    @elseif(is_array($value) && count($value))
                                        <span class="dg-badge dg-badge--neutral">{{ count($value) }} élément(s)</span>
                                    @else
                                        <span class="dg-badge dg-badge--warning">Non détecté</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-center dg-muted py-6">Aucune donnée extraite.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-dg.card>
        <x-dg.card title="Aperçu PDF" icon="bi-file-earmark-pdf" color="pink">
            <iframe src="{{ route('admin.comptabilite.supplierInvoices.pdf', $invoice) }}" title="Aperçu du PDF de la facture" style="width:100%;height:540px;border:1px solid var(--dg-border);border-radius:var(--dg-radius);"></iframe>
        </x-dg.card>
    </div>

    @if($invoice->raw_text)
        <details class="dg-card mt-6">
            <summary class="fw-semibold" style="cursor:pointer">Texte extrait brut</summary>
            <pre class="mt-3 mb-0 p-4" style="white-space:pre-wrap;background:var(--dg-table-head);border-radius:var(--dg-radius);font-size:13px;">{{ $invoice->raw_text }}</pre>
        </details>
    @endif
</div>
@endsection
