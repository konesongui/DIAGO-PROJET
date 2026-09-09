@extends('admin.layout')

@section('content')
<div class="card border-0 mx-auto" style="max-width: 900px;">
    <div class="card-header border-0 px-5 py-4">
        <div class="card-title fs-4 fw-bold text-dark">{{ $title }}</div>
    </div>

    <div class="card-body">
        @if($errors->any())
            <div class="alert alert-danger d-flex align-items-center mb-5" role="alert">
                <i class="ki-duotone ki-shield-cross fs-2 me-3"></i>
                <div>{{ $errors->first() }}</div>
            </div>
        @endif

        <form method="POST" action="{{ isset($entreprise) ? route('admin.entreprises.update', $entreprise) : route('admin.entreprises.store') }}">
            @csrf
            @if(isset($entreprise))
                @method('PUT')
            @endif

            <div class="row g-5">
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-muted">Nom de l'entreprise</label>
                    <input type="text" name="name" value="{{ old('name', $entreprise->name ?? '') }}" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-muted">Slug</label>
                    <input type="text" name="slug" value="{{ old('slug', $entreprise->slug ?? '') }}" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-muted">Nom de la base</label>
                    <input type="text" name="database_name" value="{{ old('database_name', $entreprise->database_name ?? '') }}" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-muted">Statut</label>
                    <select name="is_active" class="form-select">
                        <option value="1" {{ old('is_active', $entreprise->is_active ?? true) ? 'selected' : '' }}>Actif</option>
                        <option value="0" {{ !old('is_active', $entreprise->is_active ?? true) ? 'selected' : '' }}>Inactif</option>
                    </select>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-3 pt-5">
                <a href="{{ route('admin.entreprises.index') }}" class="btn btn-light">Annuler</a>
                <button type="submit" class="btn btn-primary">{{ isset($entreprise) ? 'Enregistrer' : 'Créer' }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
