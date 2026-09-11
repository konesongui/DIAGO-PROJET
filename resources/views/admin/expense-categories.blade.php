@extends('admin.layout')

@section('content')
@php
    // Une couleur par catégorie, attribuée dans l'ordre de la liste.
    $categoryTones = ['blue', 'green', 'orange', 'purple', 'teal', 'pink', 'cyan', 'indigo', 'red'];
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" :subtitle="$subtitle" :back="route('admin.comptabilite')" back-label="Comptabilité" />

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="dg-kpi-grid">
        <x-dg.kpi label="Catégories" :value="$categories->count()" icon="bi-tags" color="indigo" hint="au total" />
        <x-dg.kpi label="Actives" :value="$categories->where('is_active', true)->count()" icon="bi-check-circle" color="green" hint="utilisables pour les dépenses" />
        <x-dg.kpi label="Mouvements classés" :value="$categories->sum('cash_movements_count')" icon="bi-arrow-left-right" color="orange" hint="sorties de caisse rattachées" />
    </div>

    <x-dg.card title="Nouvelle catégorie" icon="bi-plus-lg" color="green" class="mb-6">
        <form method="POST" action="{{ route('admin.comptabilite.expenseCategories.store') }}" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-4"><label class="form-label" for="categoryName">Nom</label><input id="categoryName" name="name" class="form-control" required placeholder="Ex : Fournitures"></div>
            <div class="col-md-6"><label class="form-label" for="categoryDescription">Description</label><input id="categoryDescription" name="description" class="form-control" placeholder="Description facultative"></div>
            <div class="col-md-2 d-grid"><button class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Ajouter</button></div>
        </form>
    </x-dg.card>

    <x-dg.card title="Liste des catégories" icon="bi-tags" color="purple" class="dg-card--table">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Catégorie</th><th>Description</th><th>Mouvements</th><th>État</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                @forelse($categories as $category)
                    <tr>
                        <td>
                            <span class="d-inline-flex align-items-center gap-3">
                                <span class="dg-tile dg-tile--sm dg-tone-{{ $category->is_active ? $categoryTones[$loop->index % count($categoryTones)] : 'navy' }}"><i class="bi bi-tag"></i></span>
                                <span class="fw-semibold">{{ $category->name }}</span>
                            </span>
                        </td>
                        <td class="dg-muted">{{ $category->description ?: '—' }}</td>
                        <td><span class="dg-badge dg-badge--neutral">{{ $category->cash_movements_count }}</span></td>
                        <td><span class="dg-badge dg-badge--{{ $category->is_active ? 'success' : 'danger' }}">{{ $category->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-2">
                                <form method="POST" action="{{ route('admin.comptabilite.expenseCategories.update', $category) }}">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="name" value="{{ $category->name }}">
                                    <input type="hidden" name="description" value="{{ $category->description }}">
                                    <input type="hidden" name="is_active" value="{{ $category->is_active ? 0 : 1 }}">
                                    <button class="dg-btn dg-btn--outline dg-btn--sm">{{ $category->is_active ? 'Désactiver' : 'Activer' }}</button>
                                </form>
                                @if($category->cash_movements_count === 0)
                                    <form method="POST" action="{{ route('admin.comptabilite.expenseCategories.destroy', $category) }}" onsubmit="return confirm('Supprimer cette catégorie ?')">
                                        @csrf @method('DELETE')
                                        <button class="dg-icon-btn dg-icon-btn--sm dg-icon-btn--danger" title="Supprimer" aria-label="Supprimer {{ $category->name }}"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-5">
                            <div class="dg-chart-empty" style="min-height:200px">
                                <span class="dg-tile dg-tone-purple"><i class="bi bi-tags"></i></span>
                                <div><strong>Aucune catégorie créée</strong>Ajoutez une catégorie pour classer les dépenses de caisse et de banque.</div>
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
