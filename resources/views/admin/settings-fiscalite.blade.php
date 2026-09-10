@extends('admin.layout')

@section('content')
<style>
    .fiscal-shell { max-width: 1080px; margin: 0 auto; }
    .fiscal-card { border: 1px solid #edf2f7; border-radius: 18px; background: #fff; box-shadow: 0 12px 30px rgba(15,23,42,.04); }
    .fiscal-card .card-header { border-bottom: 1px solid #f1f5f9; }
    .rate-badge { font-variant-numeric: tabular-nums; font-weight: 600; }
    .regime-pill { font-size: .75rem; padding: .25rem .6rem; border-radius: 999px; background: #eef2ff; color: #4338ca; }
    .regime-pill.exempt { background: #fef3c7; color: #92400e; }
    .table-fiscal td, .table-fiscal th { vertical-align: middle; }
</style>

<div class="fiscal-shell">

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0 ps-3">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card fiscal-card mb-5">
        <div class="card-header">
            <div>
                <h3 class="card-title mb-1">Taux de taxe</h3>
                <div class="text-muted fs-7">
                    Devise de l'entreprise : <strong>{{ $currency }} ({{ $currencySymbol }})</strong>,
                    {{ $decimals === 0 ? 'sans décimale' : $decimals . ' décimale' . ($decimals > 1 ? 's' : '') }}.
                </div>
            </div>
            <div class="card-toolbar">
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newTaxRate">Ajouter un taux</button>
            </div>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-fiscal align-middle">
                <thead>
                    <tr class="text-muted fw-semibold fs-7 text-uppercase">
                        <th>Libellé</th><th>Code</th><th class="text-end">Taux</th><th>Régime</th>
                        <th>En vigueur depuis</th><th>État</th><th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($rates as $rate)
                    <tr>
                        <td>
                            <span class="fw-semibold">{{ $rate->name }}</span>
                            @if($rate->is_default)<span class="badge badge-light-primary ms-2">Par défaut</span>@endif
                        </td>
                        <td class="text-muted">{{ $rate->code ?: '-' }}</td>
                        <td class="text-end rate-badge">{{ rtrim(rtrim(number_format((float) $rate->rate, 3, ',', ' '), '0'), ',') }} %</td>
                        <td><span class="regime-pill {{ $rate->isZeroRated() ? 'exempt' : '' }}">{{ $rate->regimeLabel() }}</span></td>
                        <td class="text-muted">{{ $rate->effective_from?->format('d/m/Y') ?: 'Depuis toujours' }}</td>
                        <td>
                            @if($rate->is_active)
                                <span class="badge badge-light-success">Actif</span>
                            @else
                                <span class="badge badge-light-danger">Désactivé</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#taxRate{{ $rate->id }}">Modifier</button>
                            @if($rate->is_active)
                            <form class="d-inline" method="POST" action="{{ route('admin.settings.tax-rates.destroy', $rate) }}"
                                  onsubmit="return confirm('Désactiver ce taux ? Les documents déjà émis le conservent.')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-light-danger">Désactiver</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-10">
                        Aucun taux configuré. Ajoutez au moins un taux pour pouvoir facturer.
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card fiscal-card mb-5">
        <div class="card-header"><h3 class="card-title">Fait générateur de la taxe</h3></div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.settings.tax-basis.update') }}" class="row g-4 align-items-end">
                @csrf @method('PATCH')
                <div class="col-md-5">
                    <label class="form-label fw-semibold">La taxe devient exigible</label>
                    <select name="tax_basis" class="form-select">
                        <option value="debits" @selected(($taxBasis ?? 'debits') === 'debits')>
                            À la facturation (sur les débits)
                        </option>
                        <option value="collections" @selected(($taxBasis ?? '') === 'collections')>
                            À l'encaissement (sur les encaissements)
                        </option>
                    </select>
                </div>
                <div class="col-md-3"><button class="btn btn-primary">Enregistrer</button></div>
                <div class="col-12 text-muted fs-8">
                    Ce choix dépend du pays et de la nature de l'activité. Il détermine la date à
                    laquelle la taxe est due, et donc le contenu de la déclaration.
                </div>
            </form>
        </div>
    </div>

    <div class="card fiscal-card">
        <div class="card-body d-flex gap-4">
            <div class="fs-2">💡</div>
            <div class="text-muted fs-7">
                <p class="mb-2">Le taux marqué <strong>par défaut</strong> est celui proposé à la saisie d'un devis ou d'une facture. Un seul taux peut l'être à la fois.</p>
                <p class="mb-2">La <strong>date d'entrée en vigueur</strong> sert lorsqu'un taux change : les documents émis avant cette date conservent l'ancien taux, même réédités plus tard.</p>
                <p class="mb-0">Les régimes <strong>Exonéré</strong>, <strong>Export</strong> et <strong>Autoliquidation</strong> ne facturent aucune taxe, quelle que soit la valeur saisie, mais restent déclarables.</p>
            </div>
        </div>
    </div>
</div>

@php($regimeOptions = $regimes)

<div class="modal fade" id="newTaxRate" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.settings.tax-rates.store') }}">
                @csrf
                <div class="modal-header"><h5 class="modal-title">Nouveau taux de taxe</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body row g-4">
                    <div class="col-md-8"><label class="form-label required">Libellé</label>
                        <input name="name" class="form-control" placeholder="TVA 18 %" required></div>
                    <div class="col-md-4"><label class="form-label">Code</label>
                        <input name="code" class="form-control" placeholder="TVA18"></div>
                    <div class="col-md-6"><label class="form-label required">Taux (%)</label>
                        <input name="rate" type="number" step="0.001" min="0" max="100" class="form-control" value="0" required></div>
                    <div class="col-md-6"><label class="form-label required">Régime</label>
                        <select name="regime" class="form-select" required>
                            @foreach($regimeOptions as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                        </select></div>
                    <div class="col-md-6"><label class="form-label">En vigueur depuis</label>
                        <input name="effective_from" type="date" class="form-control"></div>
                    <div class="col-md-6 d-flex align-items-end">
                        <label class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_default" value="1">
                            <span class="form-check-label ms-2">Taux par défaut</span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                    <button class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

@foreach($rates as $rate)
<div class="modal fade" id="taxRate{{ $rate->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.settings.tax-rates.update', $rate) }}">
                @csrf @method('PATCH')
                <div class="modal-header"><h5 class="modal-title">Modifier « {{ $rate->name }} »</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body row g-4">
                    <div class="col-md-8"><label class="form-label required">Libellé</label>
                        <input name="name" class="form-control" value="{{ $rate->name }}" required></div>
                    <div class="col-md-4"><label class="form-label">Code</label>
                        <input name="code" class="form-control" value="{{ $rate->code }}"></div>
                    <div class="col-md-6"><label class="form-label required">Taux (%)</label>
                        <input name="rate" type="number" step="0.001" min="0" max="100" class="form-control" value="{{ (float) $rate->rate }}" required></div>
                    <div class="col-md-6"><label class="form-label required">Régime</label>
                        <select name="regime" class="form-select" required>
                            @foreach($regimeOptions as $key => $label)
                                <option value="{{ $key }}" @selected($rate->regime === $key)>{{ $label }}</option>
                            @endforeach
                        </select></div>
                    <div class="col-md-6"><label class="form-label">En vigueur depuis</label>
                        <input name="effective_from" type="date" class="form-control" value="{{ $rate->effective_from?->format('Y-m-d') }}"></div>
                    <div class="col-md-6 d-flex align-items-end gap-5">
                        <label class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_default" value="1" @checked($rate->is_default)>
                            <span class="form-check-label ms-2">Par défaut</span>
                        </label>
                        <label class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked($rate->is_active)>
                            <span class="form-check-label ms-2">Actif</span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                    <button class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach
@endsection
