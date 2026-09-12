@extends('admin.layout')

@section('content')
@php
    $active = $categories->where('is_active', true);
    $usedOf = fn ($category) => $usage[mb_strtolower(trim($category->name))] ?? ['count' => 0, 'salary' => 0.0];
    $attached = collect($usage)->sum('count');
    $stats = [
        ['Catégories', $categories->count(), 'bi-tags', 'pink', $active->count() . ' ouverte(s) aux fiches employés'],
        ['Employés rattachés', $attached, 'bi-people', 'purple', 'sur l’ensemble des fiches'],
        ['Montant le plus bas', $active->whereNotNull('amount')->isNotEmpty() ? money((float) $active->whereNotNull('amount')->min('amount')) : '—', 'bi-arrow-down-circle', 'blue', 'référence la plus faible'],
        ['Montant le plus haut', $active->whereNotNull('amount')->isNotEmpty() ? money((float) $active->whereNotNull('amount')->max('amount')) : '—', 'bi-arrow-up-circle', 'green', 'référence la plus élevée'],
    ];
    // Après une erreur, ou depuis un lien « modifier », la fenêtre se rouvre sur la catégorie.
    $formHasErrors = $errors->any() && old('_category_form');
    $editedId = old('category_id', request('categorie'));
    $edited = $editedId ? $categories->firstWhere('id', (int) $editedId) : null;
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" :subtitle="$subtitle" :back="route('admin.rh')" back-label="RH & Paie">
        <x-slot:actions>
            <a href="{{ route('admin.rh.module', 'personnel') }}" class="dg-btn dg-btn--outline"><i class="bi bi-people"></i>Liste du personnel</a>
            <button type="button" class="dg-btn dg-btn--primary" data-bs-toggle="modal" data-bs-target="#categoryModal"><i class="bi bi-plus-lg"></i>Nouvelle catégorie</button>
        </x-slot:actions>
    </x-dg.page-header>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any() && ! $formHasErrors)<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="dg-kpi-grid">
        @foreach($stats as [$label, $value, $icon, $color, $hint])
            <x-dg.kpi :label="$label" :value="$value" :icon="$icon" :color="$color" :hint="$hint" />
        @endforeach
    </div>

    <div class="dg-card dg-card--table">
        <div class="dg-card__header flex-wrap">
            <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-pink"><i class="bi bi-tags"></i></span>Catégories salariales</h2>
            <span class="dg-card__meta">{{ $active->count() }} ouverte(s) · {{ $categories->count() - $active->count() }} fermée(s)</span>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0 categories-table no-export">
                <thead>
                    <tr>
                        <th>Catégorie</th>
                        <th class="text-end">Montant de référence</th>
                        <th class="text-end">Employés</th>
                        <th class="text-end">Salaire moyen</th>
                        <th>État</th>
                        <th class="text-end"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($categories as $category)
                    @php $used = $usedOf($category); @endphp
                    <tr>
                        <td>
                            <span class="d-block fw-semibold">{{ $category->name }}</span>
                            <span class="d-block dg-muted" style="font-size:12.5px">{{ $category->description ?: 'Sans précision' }}</span>
                        </td>
                        <td class="text-end dg-cell-num">{{ $category->amount !== null ? money((float) $category->amount) : '—' }}</td>
                        <td class="text-end dg-cell-num">{{ $used['count'] }}</td>
                        <td class="text-end dg-cell-num">{{ $used['count'] > 0 ? money($used['salary']) : '—' }}</td>
                        <td>
                            <span class="dg-badge dg-badge--{{ $category->is_active ? 'success' : 'neutral' }}">
                                <i class="bi {{ $category->is_active ? 'bi-check-circle' : 'bi-slash-circle' }}"></i>{{ $category->is_active ? 'Ouverte' : 'Fermée' }}
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light action-menu-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions" aria-label="Actions pour la catégorie {{ $category->name }}"></button>
                                <ul class="dropdown-menu dropdown-menu-end dg-dropdown">
                                    <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#categoryModal"
                                        data-category="{{ json_encode(['id' => $category->id, 'name' => $category->name, 'amount' => $category->amount, 'description' => $category->description, 'is_active' => (bool) $category->is_active, 'action' => route('admin.rh.salaryCategories.update', $category)]) }}"><i class="bi bi-pencil"></i>Modifier</button></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="POST" action="{{ route('admin.rh.salaryCategories.destroy', $category) }}" onsubmit="return confirm('Supprimer la catégorie « {{ addslashes($category->name) }} » ?')">
                                            @csrf @method('DELETE')
                                            <button class="dropdown-item text-danger"><i class="bi bi-trash"></i>Supprimer</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-5">
                            <div class="dg-chart-empty" style="min-height:200px">
                                <span class="dg-tile dg-tone-pink"><i class="bi bi-tags"></i></span>
                                <div><strong>Aucune catégorie salariale</strong>Créez vos niveaux de rémunération (1A, 2B…) : ils sont proposés sur chaque fiche employé.</div>
                                <button type="button" class="dg-btn dg-btn--primary dg-btn--sm" data-bs-toggle="modal" data-bs-target="#categoryModal"><i class="bi bi-plus-lg"></i>Nouvelle catégorie</button>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <p class="dg-muted mt-3 mb-0" style="font-size:12.5px"><i class="bi bi-info-circle me-1"></i>Le montant de référence sert de repère à l’embauche : le salaire réellement payé reste celui de la fiche de l’employé. Une catégorie à laquelle des employés sont rattachés ne se supprime pas, elle se ferme.</p>
    </div>

    {{-- Création et modification. --}}
    <div class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" id="categoryForm"
                    action="{{ $edited ? route('admin.rh.salaryCategories.update', $edited) : route('admin.rh.salaryCategories.store') }}"
                    data-store-action="{{ route('admin.rh.salaryCategories.store') }}">
                    @csrf
                    <input type="hidden" name="_category_form" value="1">
                    <input type="hidden" name="_method" value="PUT" id="categoryMethod" @disabled(! $edited)>
                    <input type="hidden" name="category_id" value="{{ $edited?->id }}" id="categoryId" @disabled(! $edited)>
                    <div class="modal-header">
                        <h5 class="modal-title" id="categoryModalTitle"><i class="bi bi-tags me-2"></i><span>{{ $edited ? 'Modifier la catégorie' : 'Nouvelle catégorie salariale' }}</span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        @if($formHasErrors)<div class="alert alert-danger py-2">{{ $errors->first() }}</div>@endif
                        <div class="row g-3">
                            <div class="col-sm-5">
                                <label class="form-label" for="categoryName">Nom <span class="text-danger">*</span></label>
                                <input id="categoryName" name="name" class="form-control @error('name') is-invalid @enderror" required maxlength="100" value="{{ old('name', $edited?->name) }}" placeholder="Ex. 1A">
                            </div>
                            <div class="col-sm-7">
                                <label class="form-label" for="categoryAmount">Montant de référence ({{ currency_symbol() }})</label>
                                <input id="categoryAmount" name="amount" type="number" min="0" step="0.01" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount', $edited?->amount) }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="categoryDescription">Précision</label>
                                <textarea id="categoryDescription" name="description" rows="2" maxlength="1000" class="form-control @error('description') is-invalid @enderror" placeholder="Qui entre dans cette catégorie…">{{ old('description', $edited?->description) }}</textarea>
                            </div>
                            <div class="col-12" id="categoryActiveRow">
                                <label class="form-check d-flex align-items-center gap-2">
                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="categoryActive" @checked(old('is_active', $edited?->is_active ?? true))>
                                    <span class="form-check-label">Proposée sur les fiches employés</span>
                                </label>
                            </div>
                        </div>
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
    .categories-table { min-width: 760px; }
    .dg-scope .categories-table > thead > tr > th, .dg-scope .categories-table > tbody > tr > td { padding-left: 10px !important; padding-right: 10px !important; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('categoryModal');
    const form = document.getElementById('categoryForm');
    const method = document.getElementById('categoryMethod');
    const id = document.getElementById('categoryId');
    const activeRow = document.getElementById('categoryActiveRow');
    modal.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        if (!trigger) return;
        const category = trigger.dataset.category ? JSON.parse(trigger.dataset.category) : null;
        form.action = category ? category.action : form.dataset.storeAction;
        method.disabled = !category;
        id.disabled = !category;
        id.value = category ? category.id : '';
        modal.querySelector('.modal-title span').textContent = category ? 'Modifier la catégorie' : 'Nouvelle catégorie salariale';
        form.elements.name.value = category ? category.name : '';
        form.elements.amount.value = category && category.amount !== null ? category.amount : '';
        form.elements.description.value = category && category.description ? category.description : '';
        form.elements.is_active.checked = category ? category.is_active : true;
        // À la création, la catégorie est forcément proposée : la case n'a pas lieu d'être.
        activeRow.classList.toggle('d-none', !category);
    });
    activeRow.classList.toggle('d-none', !{{ $edited ? 'true' : 'false' }});
    @if($formHasErrors || $edited)
        window.addEventListener('load', () => new bootstrap.Modal(modal).show());
    @endif
});
</script>
@endsection
