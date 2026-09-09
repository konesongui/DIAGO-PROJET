@extends('admin.layout')

@section('content')
<style>.settings-panel{border:1px solid #edf2f7;border-radius:18px;background:#fff;box-shadow:0 12px 30px rgba(15,23,42,.04)}.settings-panel .panel-head{border-bottom:1px solid #edf2f7;padding:20px 24px}.settings-panel .panel-body{padding:24px}</style>
<div class="settings-panel">
    <div class="panel-head">
        <div class="text-uppercase text-muted fs-8 fw-bold">Modules</div>
        <h2 class="h4 fw-bold mb-1">Rubriques activées</h2>
        <p class="text-muted mb-0">Activez ou désactivez les rubriques disponibles dans votre espace Diagoma ERP.</p>
    </div>
    <form method="POST" action="{{ route('admin.settings.modules.update') }}">
        @csrf
        @method('PATCH')
        <div class="panel-body">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            <div class="row g-4">
                @foreach($rubriques as $key => $rubrique)
                    <div class="col-md-6 col-xl-4">
                        <label class="card border h-100 p-4 cursor-pointer">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <div class="fs-2 mb-2">{{ $rubrique['icon'] }}</div>
                                    <h3 class="h5 fw-bold mb-2">{{ $rubrique['label'] }}</h3>
                                    <p class="text-muted mb-0">{{ $rubrique['description'] }}</p>
                                </div>
                                <div class="form-check form-switch form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="rubriques[{{ $key }}]" value="1" {{ !empty($enabledRubriques[$key]) ? 'checked' : '' }}>
                                </div>
                            </div>
                        </label>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="card-footer bg-white border-0 p-5 text-end">
            <a href="{{ route('admin.settings') }}" class="btn btn-light me-2">Retour</a>
            <button type="submit" class="btn btn-primary">Enregistrer les modules</button>
        </div>
    </form>
</div>
@endsection
