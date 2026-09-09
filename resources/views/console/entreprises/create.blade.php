@extends('admin.layout')
@section('content')
<div class="console-card bg-white p-4 p-md-5 mx-auto" style="max-width:980px">
    <div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h4 fw-bold mb-1">Créer un espace entreprise</h1><p class="text-muted mb-0">L’administrateur recevra les accès que vous définissez ici.</p></div><a href="{{ route('console.account-tracking') }}" class="btn btn-light">← Retour</a></div>
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('console.entreprises.store') }}">@csrf
        <h2 class="h6 fw-bold border-bottom pb-3">Informations de l’entreprise</h2>
        <div class="row g-4 mb-4">
            <div class="col-md-6"><label class="form-label">Nom de l’entreprise *</label><input name="company_name" value="{{ old('company_name') }}" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Raison sociale</label><input name="trade_name" value="{{ old('trade_name') }}" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Téléphone *</label><input name="phone" value="{{ old('phone') }}" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Ville *</label><input name="city" value="{{ old('city') }}" class="form-control" required></div>
            <div class="col-12"><label class="form-label">Adresse *</label><input name="address" value="{{ old('address') }}" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Fin de l’abonnement *</label><input type="date" name="subscription_expires_at" value="{{ old('subscription_expires_at') }}" min="{{ now()->toDateString() }}" class="form-control" required><div class="form-text">Date jusqu’à laquelle l’espace reste actif.</div></div>
            <div class="col-md-6"><label class="form-label">Slug</label><input name="slug" value="{{ old('slug') }}" class="form-control" placeholder="généré automatiquement"></div>
            <div class="col-md-6"><label class="form-label">Nom de la base</label><input name="database_name" value="{{ old('database_name') }}" class="form-control"></div>
        </div>
        <h2 class="h6 fw-bold border-bottom pb-3">Compte administrateur</h2>
        <div class="row g-4"><div class="col-md-6"><label class="form-label">Nom complet *</label><input name="admin_name" value="{{ old('admin_name') }}" class="form-control" required></div><div class="col-md-6"><label class="form-label">Email *</label><input type="email" name="admin_email" value="{{ old('admin_email') }}" class="form-control" required></div><div class="col-md-6"><label class="form-label">Mot de passe *</label><input type="password" name="admin_password" class="form-control" minlength="8" required></div><div class="col-md-6"><label class="form-label">Confirmation *</label><input type="password" name="admin_password_confirmation" class="form-control" minlength="8" required></div></div>
        <h2 class="h6 fw-bold border-bottom pb-3 mt-5">Modules de l’entreprise *</h2>
        <p class="text-muted">Sélectionnez les rubriques qui seront visibles dans cet espace.</p>
        <div class="row g-3">
            @foreach($moduleRubriques as $key => $rubrique)
                <div class="col-md-6 col-xl-4"><label class="border rounded-3 p-3 d-flex gap-3 align-items-start h-100"><input class="form-check-input mt-1" type="checkbox" name="rubriques[{{ $key }}]" value="1" {{ old("rubriques.$key") ? 'checked' : '' }}><span><strong>{{ $rubrique['icon'] }} {{ $rubrique['label'] }}</strong><small class="d-block text-muted mt-1">{{ $rubrique['description'] }}</small></span></label></div>
            @endforeach
        </div>
        <div class="form-check form-switch border rounded-3 p-3 mt-4">
            <input class="form-check-input ms-0 me-3" type="checkbox" role="switch" name="ai_assistant_enabled" value="1" id="aiAssistantEnabled" {{ old('ai_assistant_enabled') ? 'checked' : '' }}>
            <label class="form-check-label" for="aiAssistantEnabled"><strong>Activer l’assistant IA</strong><small class="d-block text-muted mt-1">Autoriser cette entreprise à utiliser l’assistant intelligent dans son espace.</small></label>
        </div>
        <div class="text-end mt-5"><button class="btn btn-primary px-4">Créer l’espace et le compte admin</button></div>
    </form>
</div>
@endsection
