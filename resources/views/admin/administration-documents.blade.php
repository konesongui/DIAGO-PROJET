@extends('admin.layout')

@section('content')
@php
    $states = [
        'active' => ['Actif', 'success', 'bi-folder-check', 'Actifs'],
        'archived' => ['Archivé', 'neutral', 'bi-archive', 'Archivés'],
    ];
    $byState = $documents->groupBy('status');
    $addedThisMonth = $documents->filter(fn ($document) => $document->created_at && $document->created_at->isSameMonth(now()))->count();
    $missingFiles = $documents->reject(fn ($document) => $document->fileExists())->count();

    $stats = [
        ['Documents actifs', $byState->get('active', collect())->count(), 'bi-folder-check', 'blue', 'consultables au quotidien'],
        ['Archivés', $byState->get('archived', collect())->count(), 'bi-archive', 'purple', 'conservés sans être courants'],
        ['Catégories', $categories->count(), 'bi-tags', 'teal', $categories->isNotEmpty() ? $categories->take(3)->implode(', ') : 'aucune catégorie'],
        ['Classés ce mois-ci', $addedThisMonth, 'bi-upload', 'green', 'ajoutés depuis le ' . now()->startOfMonth()->format('d/m/Y')],
    ];

    $formHasErrors = $errors->any() && old('_document_form');
    $editedId = old('document_id');
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" :subtitle="$subtitle" :back="route('admin.administration')" back-label="Administration">
        <x-slot:actions>
            <button type="button" class="dg-btn dg-btn--primary" data-bs-toggle="modal" data-bs-target="#documentModal"><i class="bi bi-plus-lg"></i>Classer un document</button>
        </x-slot:actions>
    </x-dg.page-header>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any() && ! $formHasErrors)<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    @if($missingFiles > 0)
        <div class="alert alert-warning">{{ $missingFiles }} document(s) pointent vers un fichier introuvable sur le serveur : remplacez le fichier ou retirez la fiche.</div>
    @endif

    <div class="dg-kpi-grid">
        @foreach($stats as [$label, $value, $icon, $color, $hint])
            <x-dg.kpi :label="$label" :value="$value" :icon="$icon" :color="$color" :hint="$hint" />
        @endforeach
    </div>

    <div class="dg-card dg-card--table">
        <div class="dg-card__header flex-wrap">
            <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-teal"><i class="bi bi-folder2-open"></i></span>Documents classés</h2>
            <div class="d-flex align-items-center gap-3 flex-wrap">
                @if($categories->isNotEmpty())
                    <select id="documentCategory" class="form-select" style="width:210px" aria-label="Filtrer par catégorie">
                        <option value="">Toutes les catégories</option>
                        @foreach($categories as $category)
                            <option value="{{ mb_strtolower($category) }}">{{ $category }}</option>
                        @endforeach
                    </select>
                @endif
                @if($documents->isNotEmpty())
                    <label class="dg-search" style="max-width:240px">
                        <i class="bi bi-search" aria-hidden="true"></i>
                        <input id="documentSearch" type="search" placeholder="Intitulé, description…" aria-label="Rechercher un document">
                    </label>
                @endif
            </div>
        </div>

        @if($documents->isNotEmpty())
            <div class="dg-tabs mb-4" role="group" aria-label="Filtrer par état">
                <button type="button" class="dg-tab is-active" data-state-filter="" aria-pressed="true">Tous ({{ $documents->count() }})</button>
                @foreach($states as $value => [, , , $tabLabel])
                    @if($byState->get($value, collect())->isNotEmpty())
                        <button type="button" class="dg-tab" data-state-filter="{{ $value }}" aria-pressed="false">{{ $tabLabel }} ({{ $byState->get($value)->count() }})</button>
                    @endif
                @endforeach
            </div>
        @endif

        <div class="table-responsive">
            <table class="table align-middle mb-0 documents-table no-export" id="documentsTable">
                <thead>
                    <tr>
                        <th>Document</th>
                        <th>Catégorie</th>
                        <th>Date du document</th>
                        <th>Fichier</th>
                        <th>État</th>
                        <th class="text-end"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($documents as $document)
                    @php [$stateLabel, $stateTone, $stateIcon] = $states[$document->status] ?? $states['active']; @endphp
                    <tr data-state="{{ $document->status }}" data-category="{{ mb_strtolower($document->category ?? '') }}">
                        <td>
                            <span class="d-block fw-semibold" style="max-width:260px">{{ $document->title }}</span>
                            <span class="d-block dg-muted" style="font-size:12.5px;max-width:260px">{{ $document->description ?: 'Sans description' }}</span>
                        </td>
                        <td>{{ $document->category ?: 'Non classé' }}</td>
                        <td class="text-nowrap">{{ $document->document_date?->format('d/m/Y') ?? 'Non renseignée' }}</td>
                        <td class="text-nowrap">
                            @if($document->fileExists())
                                <a class="dg-link-btn" target="_blank" rel="noopener" href="{{ Storage::disk('public')->url($document->file_path) }}"><i class="bi bi-file-earmark-arrow-down"></i>Ouvrir</a>
                                <span class="d-block dg-muted" style="font-size:12.5px">{{ $document->extension() }} · {{ $document->readableSize() }}</span>
                            @else
                                <span class="dg-badge dg-badge--danger"><i class="bi bi-exclamation-triangle"></i>Introuvable</span>
                            @endif
                        </td>
                        <td><span class="dg-badge dg-badge--{{ $stateTone }}"><i class="bi {{ $stateIcon }}"></i>{{ $stateLabel }}</span></td>
                        <td class="text-end">
                            <div class="d-flex align-items-center justify-content-end gap-2">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light action-menu-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions" aria-label="Actions pour le document {{ $document->title }}"></button>
                                    <ul class="dropdown-menu dropdown-menu-end dg-dropdown">
                                        <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#documentModal"
                                            data-document="{{ json_encode([
                                                'id' => $document->id,
                                                'title' => $document->title,
                                                'category' => $document->category,
                                                'document_date' => optional($document->document_date)->format('Y-m-d'),
                                                'status' => $document->status,
                                                'description' => $document->description,
                                                'file' => $document->file_path ? basename($document->file_path) : null,
                                                'action' => route('admin.administration.documents.update', $document),
                                            ]) }}"><i class="bi bi-pencil"></i>Modifier</button></li>
                                        <li>
                                            <form method="POST" action="{{ route('admin.administration.documents.status', $document) }}">
                                                @csrf<input type="hidden" name="status" value="{{ $document->status === 'active' ? 'archived' : 'active' }}">
                                                <button class="dropdown-item"><i class="bi {{ $document->status === 'active' ? 'bi-archive' : 'bi-folder-check' }}"></i>{{ $document->status === 'active' ? 'Archiver' : 'Remettre en actif' }}</button>
                                            </form>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form method="POST" action="{{ route('admin.administration.documents.destroy', $document) }}" onsubmit="return confirm('Supprimer « {{ addslashes($document->title) }} » et son fichier ?')">
                                                @csrf @method('DELETE')
                                                <button class="dropdown-item text-danger"><i class="bi bi-trash"></i>Supprimer</button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-5">
                            <div class="dg-chart-empty" style="min-height:200px">
                                <span class="dg-tile dg-tone-teal"><i class="bi bi-folder2-open"></i></span>
                                <div><strong>Aucun document classé</strong>Rangez ici les statuts, contrats, registres légaux et attestations : chaque fiche porte son fichier.</div>
                                <button type="button" class="dg-btn dg-btn--primary dg-btn--sm" data-bs-toggle="modal" data-bs-target="#documentModal"><i class="bi bi-plus-lg"></i>Classer un document</button>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="dg-chart-empty mt-4 d-none" id="documentsNoResult" style="min-height:140px">
            <span class="dg-tile dg-tone-teal"><i class="bi bi-search"></i></span>
            <div><strong>Aucun document ne correspond</strong>Modifiez la recherche, la catégorie ou l’onglet.</div>
        </div>
        <p class="dg-muted mt-3 mb-0" style="font-size:12.5px"><i class="bi bi-info-circle me-1"></i>Une fiche porte toujours un fichier : PDF, image ou document bureautique, 10 Mo au plus. Archiver un document le sort du courant sans l’effacer ; le supprimer efface aussi le fichier du serveur.</p>
    </div>

    {{-- Classement et modification. --}}
    <div class="modal fade" id="documentModal" tabindex="-1" aria-labelledby="documentModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg dg-tone-teal">
            <div class="modal-content">
                <form method="POST" id="documentForm" enctype="multipart/form-data"
                    action="{{ $editedId ? route('admin.administration.documents.update', $editedId) : route('admin.administration.documents.store') }}"
                    data-store-action="{{ route('admin.administration.documents.store') }}">
                    @csrf
                    <input type="hidden" name="_document_form" value="1">
                    <input type="hidden" name="_method" value="PUT" id="documentMethod" @disabled(! $editedId)>
                    <input type="hidden" name="document_id" value="{{ $editedId }}" id="documentId" @disabled(! $editedId)>
                    <div class="modal-header">
                        <h5 class="modal-title" id="documentModalTitle"><i class="bi bi-folder2-open me-2"></i><span>{{ $editedId ? 'Modifier le document' : 'Classer un document' }}</span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        @if($formHasErrors)<div class="alert alert-danger py-2">{{ $errors->first() }}</div>@endif
                        <div class="row g-3">
                            <div class="col-md-7">
                                <label class="form-label" for="documentTitle">Intitulé <span class="text-danger">*</span></label>
                                <input id="documentTitle" name="title" class="form-control @error('title') is-invalid @enderror" required maxlength="255" value="{{ old('title') }}" placeholder="Ex. Statuts de la société">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label" for="documentCategoryField">Catégorie</label>
                                <input id="documentCategoryField" name="category" class="form-control @error('category') is-invalid @enderror" maxlength="255" list="documentCategories" value="{{ old('category') }}" placeholder="Ex. Registres légaux">
                                <datalist id="documentCategories">
                                    @foreach($categories as $category)
                                        <option value="{{ $category }}"></option>
                                    @endforeach
                                </datalist>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="documentDate">Date du document <span class="text-danger">*</span></label>
                                <input id="documentDate" name="document_date" type="date" class="form-control @error('document_date') is-invalid @enderror" required value="{{ old('document_date', now()->toDateString()) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="documentStatus">État <span class="text-danger">*</span></label>
                                <select id="documentStatus" name="status" class="form-select @error('status') is-invalid @enderror" required>
                                    @foreach(\App\Models\AdminDocument::STATES as $value => $label)
                                        <option value="{{ $value }}" @selected(old('status', 'active') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="documentFile">Fichier <span class="text-danger" id="documentFileRequired">*</span></label>
                                <input id="documentFile" name="file" type="file" class="form-control @error('file') is-invalid @enderror" required accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx">
                                <span class="form-text">PDF, image ou document bureautique, 10 Mo au plus.</span>
                                @error('file')<span class="dg-field-error">{{ $message }}</span>@enderror
                                <div class="mt-2 d-none" id="documentCurrentFile">
                                    <span class="dg-muted" style="font-size:12.5px"><i class="bi bi-paperclip me-1"></i>Fichier actuel : <strong data-document-file-name></strong> — n’en choisissez un que pour le remplacer.</span>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="documentDescription">Description</label>
                                <textarea id="documentDescription" name="description" rows="2" maxlength="2000" class="form-control @error('description') is-invalid @enderror" placeholder="À quoi sert ce document, où se trouve l’original…">{{ old('description') }}</textarea>
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
    .documents-table { min-width: 860px; }
    .dg-scope .documents-table > thead > tr > th, .dg-scope .documents-table > tbody > tr > td { padding-left: 10px !important; padding-right: 10px !important; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const rows = Array.from(document.querySelectorAll('#documentsTable tbody tr[data-state]'));
    const search = document.getElementById('documentSearch');
    const category = document.getElementById('documentCategory');
    const tabs = Array.from(document.querySelectorAll('[data-state-filter]'));
    const noResult = document.getElementById('documentsNoResult');
    let state = '';
    const filterRows = function () {
        const term = search ? search.value.trim().toLowerCase() : '';
        const wanted = category ? category.value : '';
        let visible = 0;
        rows.forEach(function (row) {
            const show = (!term || row.textContent.toLowerCase().includes(term))
                && (!state || row.dataset.state === state)
                && (!wanted || row.dataset.category === wanted);
            row.style.display = show ? '' : 'none';
            visible += show ? 1 : 0;
        });
        noResult.classList.toggle('d-none', !rows.length || visible > 0);
    };
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            state = tab.dataset.stateFilter;
            tabs.forEach(function (item) {
                item.classList.toggle('is-active', item === tab);
                item.setAttribute('aria-pressed', item === tab ? 'true' : 'false');
            });
            filterRows();
        });
    });
    search?.addEventListener('input', filterRows);
    category?.addEventListener('change', filterRows);

    const modal = document.getElementById('documentModal');
    const form = document.getElementById('documentForm');
    const method = document.getElementById('documentMethod');
    const id = document.getElementById('documentId');
    const currentFile = document.getElementById('documentCurrentFile');
    const fileInput = document.getElementById('documentFile');
    const fileRequired = document.getElementById('documentFileRequired');
    modal.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        if (!trigger) return;
        const document_ = trigger.dataset.document ? JSON.parse(trigger.dataset.document) : null;
        form.action = document_ ? document_.action : form.dataset.storeAction;
        method.disabled = !document_;
        id.disabled = !document_;
        id.value = document_ ? document_.id : '';
        modal.querySelector('.modal-title span').textContent = document_ ? 'Modifier le document' : 'Classer un document';
        form.elements.title.value = document_ ? document_.title : '';
        form.elements.category.value = document_ && document_.category ? document_.category : '';
        form.elements.document_date.value = document_ && document_.document_date ? document_.document_date : '{{ now()->toDateString() }}';
        form.elements.status.value = document_ ? document_.status : 'active';
        form.elements.description.value = document_ && document_.description ? document_.description : '';
        fileInput.value = '';
        // Le fichier n'est exigé qu'au classement : à la modification, il est déjà là.
        fileInput.required = !document_;
        fileRequired.classList.toggle('d-none', !!document_);
        currentFile.classList.toggle('d-none', !(document_ && document_.file));
        if (document_ && document_.file) {
            currentFile.querySelector('[data-document-file-name]').textContent = document_.file;
        }
    });
    @if($formHasErrors)
        window.addEventListener('load', function () {
            @if($editedId)
                fileInput.required = false;
                fileRequired.classList.add('d-none');
            @endif
            new bootstrap.Modal(modal).show();
        });
    @endif
});
</script>
@endsection
