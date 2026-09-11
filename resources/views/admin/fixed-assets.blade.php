@extends('admin.layout')

@section('content')
@php
    // Pastille de chaque catégorie : icône et couleur.
    $categoryStyles = [
        'Matériel' => ['bi-tools', 'orange'],
        'Mobilier' => ['bi-lamp', 'teal'],
        'Véhicule' => ['bi-truck', 'blue'],
        'Informatique' => ['bi-laptop', 'indigo'],
        'Immobilier' => ['bi-building', 'purple'],
        'Autre' => ['bi-box-seam', 'cyan'],
    ];
    $statuses = [
        'active' => ['En service', 'success'],
        'sold' => ['Cédée', 'neutral'],
        'retired' => ['Mise au rebut', 'warning'],
    ];
    $hasFilters = !empty($filters['search']) || !empty($filters['status']);
    // Après une erreur de saisie, la fenêtre se rouvre sur le formulaire soumis (création ou modification).
    $editedAssetId = old('asset_id');
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" :subtitle="$subtitle" :back="route('admin.comptabilite')" back-label="Comptabilité">
        <x-slot:actions>
            <button type="button" class="dg-btn dg-btn--primary" data-bs-toggle="modal" data-bs-target="#fixedAssetModal"><i class="bi bi-plus-lg"></i>Nouvelle immobilisation</button>
        </x-slot:actions>
    </x-dg.page-header>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="dg-kpi-grid">
        <x-dg.kpi label="Immobilisations" :value="$summary['count']" icon="bi-building" color="purple" hint="dans la liste" />
        <x-dg.kpi label="Valeur brute" :value="money($summary['gross'])" icon="bi-cash-stack" color="blue" hint="coût d'acquisition" />
        <x-dg.kpi label="Valeur nette comptable" :value="money($summary['net'])" icon="bi-calculator" color="green" hint="après amortissements" />
        <x-dg.kpi label="Actifs en service" :value="$summary['active']" icon="bi-check-circle" color="teal" hint="non cédés ni mis au rebut" />
    </div>

    <div class="dg-card dg-card--table">
        <div class="dg-card__header flex-wrap">
            <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-purple"><i class="bi bi-list-ul"></i></span>Registre des immobilisations</h2>
            <form method="GET" class="d-flex flex-wrap align-items-center gap-2" role="search" aria-label="Filtrer les immobilisations">
                <label class="dg-search" style="max-width:280px">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nom, référence…" aria-label="Rechercher une immobilisation">
                </label>
                <select name="status" class="dg-select fixed-assets-status" aria-label="État">
                    <option value="">Tous les états</option>
                    @foreach($statuses as $value => [$label])
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="dg-btn dg-btn--outline"><i class="bi bi-funnel"></i>Filtrer</button>
                @if($hasFilters)
                    <a href="{{ route('admin.comptabilite.fixedAssets') }}" class="dg-btn dg-btn--outline" title="Effacer les filtres" aria-label="Effacer les filtres"><i class="bi bi-x-lg"></i></a>
                @endif
            </form>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0 fixed-assets-table">
                <thead>
                    <tr>
                        <th>Immobilisation</th>
                        <th>Acquisition</th>
                        <th class="text-end">Valeur brute</th>
                        <th class="text-end">Amortissement</th>
                        <th class="text-end">Valeur nette</th>
                        <th>État</th>
                        <th class="text-end"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($assets as $asset)
                    @php
                        [$categoryIcon, $categoryTone] = $categoryStyles[$asset->asset_category] ?? ['bi-box-seam', 'cyan'];
                        [$statusLabel, $statusTone] = $statuses[$asset->status] ?? [$asset->status, 'neutral'];
                        $assetData = [
                            'id' => $asset->id,
                            'name' => $asset->name,
                            'asset_category' => $asset->asset_category,
                            'reference' => $asset->reference,
                            'supplier' => $asset->supplier,
                            'acquisition_date' => $asset->acquisition_date?->format('Y-m-d'),
                            'acquisition_value' => (float) $asset->acquisition_value,
                            'residual_value' => (float) $asset->residual_value,
                            'useful_life_years' => $asset->useful_life_years,
                            'status' => $asset->status,
                            'location' => $asset->location,
                            'description' => $asset->description,
                        ];
                    @endphp
                    <tr>
                        <td>
                            <span class="d-inline-flex align-items-center gap-3">
                                <span class="dg-tile dg-tile--sm dg-tone-{{ $asset->status === 'active' ? $categoryTone : 'navy' }}"><i class="bi {{ $categoryIcon }}"></i></span>
                                <span>
                                    <span class="d-block fw-semibold">{{ $asset->name }}</span>
                                    <span class="d-block dg-muted" style="font-size:12.5px">{{ collect([$asset->asset_category, $asset->reference, $asset->location])->filter()->implode(' · ') }}</span>
                                </span>
                            </span>
                        </td>
                        <td class="text-nowrap">
                            {{ $asset->acquisition_date?->format('d/m/Y') }}
                            <span class="d-block dg-muted" style="font-size:12.5px">sur {{ $asset->useful_life_years }} an{{ $asset->useful_life_years > 1 ? 's' : '' }}</span>
                        </td>
                        <td class="text-end dg-cell-num">{{ money((float) $asset->acquisition_value) }}</td>
                        <td class="text-end">
                            <span class="dg-cell-num">{{ money($asset->depreciation_amount) }}</span>
                            <span class="d-block dg-muted" style="font-size:12.5px">{{ number_format($asset->depreciation_rate, 0, ',', ' ') }} % amorti</span>
                        </td>
                        <td class="text-end dg-cell-num">{{ money($asset->net_value) }}</td>
                        <td><span class="dg-badge dg-badge--{{ $statusTone }}">{{ $statusLabel }}</span></td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light action-menu-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions" aria-label="Actions pour {{ $asset->name }}"></button>
                                <ul class="dropdown-menu dropdown-menu-end dg-dropdown">
                                    <li>
                                        <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#fixedAssetModal"
                                            data-action="{{ route('admin.comptabilite.fixedAssets.update', $asset) }}"
                                            data-asset='@json($assetData)'><i class="bi bi-pencil"></i>Modifier</button>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="POST" action="{{ route('admin.comptabilite.fixedAssets.destroy', $asset) }}" onsubmit="return confirm('Supprimer cette immobilisation ?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="dropdown-item text-danger" type="submit"><i class="bi bi-trash"></i>Supprimer</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-5">
                            <div class="dg-chart-empty" style="min-height:220px">
                                <span class="dg-tile dg-tone-purple"><i class="bi bi-building"></i></span>
                                <div>
                                    <strong>{{ $hasFilters ? 'Aucune immobilisation ne correspond à vos filtres' : 'Aucune immobilisation enregistrée' }}</strong>
                                    Enregistrez vos biens durables (matériel, véhicules, informatique…) pour suivre leur amortissement et leur valeur nette.
                                </div>
                                @unless($hasFilters)
                                    <button type="button" class="dg-btn dg-btn--primary dg-btn--sm" data-bs-toggle="modal" data-bs-target="#fixedAssetModal"><i class="bi bi-plus-lg"></i>Nouvelle immobilisation</button>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="fixedAssetModal" tabindex="-1" aria-labelledby="fixedAssetModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form method="POST" id="fixedAssetForm"
                    action="{{ $editedAssetId ? route('admin.comptabilite.fixedAssets.update', $editedAssetId) : route('admin.comptabilite.fixedAssets.store') }}"
                    data-store-action="{{ route('admin.comptabilite.fixedAssets.store') }}">
                    @csrf
                    <input type="hidden" name="_method" value="PUT" id="fixedAssetMethod" @disabled(! $editedAssetId)>
                    <input type="hidden" name="asset_id" value="{{ $editedAssetId }}" id="fixedAssetId" @disabled(! $editedAssetId)>
                    <div class="modal-header">
                        <h5 class="modal-title" id="fixedAssetModalTitle"><i class="bi bi-building me-2"></i><span>{{ $editedAssetId ? "Modifier l'immobilisation" : 'Nouvelle immobilisation' }}</span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-8"><label class="form-label" for="assetName">Désignation</label><input id="assetName" name="name" class="form-control" required maxlength="255" value="{{ old('name') }}" placeholder="Ex : Véhicule de livraison"></div>
                            <div class="col-md-4">
                                <label class="form-label" for="assetCategory">Catégorie</label>
                                <select id="assetCategory" name="asset_category" class="form-select" required>
                                    @foreach(array_keys($categoryStyles) as $category)
                                        <option @selected(old('asset_category') === $category)>{{ $category }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4"><label class="form-label" for="assetReference">Référence</label><input id="assetReference" name="reference" class="form-control" maxlength="100" value="{{ old('reference') }}"></div>
                            <div class="col-md-4"><label class="form-label" for="assetSupplier">Fournisseur</label><input id="assetSupplier" name="supplier" class="form-control" maxlength="255" value="{{ old('supplier') }}"></div>
                            <div class="col-md-4"><label class="form-label" for="assetLocation">Emplacement</label><input id="assetLocation" name="location" class="form-control" maxlength="255" value="{{ old('location') }}"></div>
                            <div class="col-md-4"><label class="form-label" for="assetDate">Date d'acquisition</label><input type="date" id="assetDate" name="acquisition_date" class="form-control" required value="{{ old('acquisition_date', now()->format('Y-m-d')) }}"></div>
                            <div class="col-md-4"><label class="form-label" for="assetValue">Valeur d'acquisition</label><input type="number" id="assetValue" name="acquisition_value" class="form-control" min="0.01" step="0.01" required value="{{ old('acquisition_value') }}"></div>
                            <div class="col-md-4"><label class="form-label" for="assetResidual">Valeur résiduelle</label><input type="number" id="assetResidual" name="residual_value" class="form-control" min="0" step="0.01" value="{{ old('residual_value', 0) }}"></div>
                            <div class="col-md-4"><label class="form-label" for="assetLife">Durée d'amortissement (ans)</label><input type="number" id="assetLife" name="useful_life_years" class="form-control" min="1" max="100" required value="{{ old('useful_life_years', 5) }}"></div>
                            <div class="col-md-4">
                                <label class="form-label" for="assetStatus">État</label>
                                <select id="assetStatus" name="status" class="form-select" required>
                                    @foreach($statuses as $value => [$label])
                                        <option value="{{ $value }}" @selected(old('status', 'active') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12"><label class="form-label" for="assetDescription">Description</label><textarea id="assetDescription" name="description" class="form-control" rows="2">{{ old('description') }}</textarea></div>
                        </div>
                        <div class="form-text mt-3">Amortissement linéaire : (valeur d'acquisition − valeur résiduelle) répartie sur la durée, au prorata des mois écoulés.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .fixed-assets-table { min-width: 900px; }
    .fixed-assets-table td:first-child { min-width: 190px; }
    /* Trois colonnes de montants : espacement resserré pour tenir sans défilement sur un portable (1366 px). */
    .dg-scope .fixed-assets-table > thead > tr > th,
    .dg-scope .fixed-assets-table > tbody > tr > td { padding-left: 12px !important; padding-right: 12px !important; }
    .fixed-assets-status { width: auto; min-height: 42px; padding-top: 8px; padding-bottom: 8px; font-size: 14px; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('fixedAssetModal');
    const form = document.getElementById('fixedAssetForm');
    const method = document.getElementById('fixedAssetMethod');
    const assetId = document.getElementById('fixedAssetId');
    const title = modal.querySelector('.modal-title span');
    const defaults = {
        name: '', asset_category: 'Matériel', reference: '', supplier: '', location: '',
        acquisition_date: @json(now()->format('Y-m-d')), acquisition_value: '', residual_value: 0,
        useful_life_years: 5, status: 'active', description: '',
    };

    const fill = function (values) {
        Object.entries(values).forEach(function ([name, value]) {
            const field = form.elements[name];
            if (!field) return;
            // Catégorie saisie hors de la liste proposée : on l'ajoute pour ne pas la perdre.
            if (field.tagName === 'SELECT' && value && !Array.from(field.options).some((option) => option.value === String(value))) {
                field.add(new Option(value, value));
            }
            field.value = value ?? '';
        });
    };

    // Ouverture par un bouton : « Modifier » remplit le formulaire, « Nouvelle immobilisation » le vide.
    // Réouverture après une erreur (sans bouton) : les valeurs saisies sont conservées.
    modal.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        if (!trigger) return;
        const asset = trigger.dataset.asset ? JSON.parse(trigger.dataset.asset) : null;
        form.action = asset ? trigger.dataset.action : form.dataset.storeAction;
        method.disabled = !asset;
        assetId.disabled = !asset;
        assetId.value = asset ? asset.id : '';
        title.textContent = asset ? "Modifier l'immobilisation" : 'Nouvelle immobilisation';
        const values = Object.assign({}, defaults, asset || {});
        delete values.id;
        fill(values);
    });

    @if($errors->any())
        window.addEventListener('load', () => new bootstrap.Modal(modal).show());
    @endif
});
</script>
@endsection
