@extends('admin.layout')

@section('content')
<style>.settings-panel{border:1px solid #edf2f7;border-radius:18px;background:#fff;box-shadow:0 12px 30px rgba(15,23,42,.04)}.settings-panel .panel-head{border-bottom:1px solid #edf2f7;padding:20px 24px}.settings-panel .panel-body{padding:24px}</style>
<div class="settings-panel">
    <div class="panel-body">
        <div class="d-flex align-items-center gap-3 mb-4">
            <span class="fs-1"><i class="bi {{ $module['icon'] }}"></i></span>
            <div>
                <div class="text-uppercase text-muted fs-8 fw-bold">{{ $module['category'] }}</div>
                <h2 class="h4 fw-bold text-dark mb-0">{{ $module['title'] }}</h2>
            </div>
            <a href="{{ route('admin.settings') }}" class="btn btn-light ms-auto">
                ← Retour
            </a>
        </div>
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif
        <p class="text-muted mb-4">Ces paramètres sont utilisés pour les bulletins, notifications et documents envoyés par email.</p>
        <form method="POST" action="{{ route('admin.settings.email.update') }}">
            @csrf
            @method('PATCH')
            <div class="row g-4">
                <div class="col-md-8"><label class="form-label fw-semibold">Serveur SMTP</label><input name="host" class="form-control" value="{{ old('host', $emailSettings['host']) }}" placeholder="smtp.gmail.com" required></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Port</label><input type="number" name="port" class="form-control" value="{{ old('port', $emailSettings['port']) }}" min="1" max="65535" required></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Chiffrement</label><select name="encryption" class="form-select"><option value="">Aucun</option><option value="tls" {{ old('encryption', $emailSettings['encryption']) === 'tls' ? 'selected' : '' }}>TLS</option><option value="ssl" {{ old('encryption', $emailSettings['encryption']) === 'ssl' ? 'selected' : '' }}>SSL</option></select></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Nom d'utilisateur</label><input name="username" class="form-control" value="{{ old('username', $emailSettings['username']) }}"></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Mot de passe SMTP</label><input type="password" name="password" class="form-control" placeholder="{{ $emailSettings['password'] ? 'Mot de passe conservé' : 'Mot de passe SMTP' }}"><div class="form-text">Laissez vide pour conserver le mot de passe actuel.</div></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Adresse d'expédition</label><input type="email" name="from_address" class="form-control" value="{{ old('from_address', $emailSettings['from_address']) }}" required></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Nom d'expédition</label><input name="from_name" class="form-control" value="{{ old('from_name', $emailSettings['from_name']) }}" required></div>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-5">
                <a href="{{ route('admin.settings') }}" class="btn btn-light">Retour aux paramètres</a>
                <button class="btn btn-primary">Enregistrer la configuration</button>
            </div>
        </form>
        <hr class="my-6">
        <h5 class="fw-bold mb-3">Tester la configuration</h5>
        <form method="POST" action="{{ route('admin.settings.email.test') }}" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-8"><label class="form-label fw-semibold">Destinataire du test</label><input type="email" name="test_recipient" class="form-control" value="{{ old('test_recipient', $emailSettings['test_recipient']) }}" required></div>
            <div class="col-md-4"><button class="btn btn-outline-primary w-100">Envoyer un email de test</button></div>
        </form>
    </div>
</div>
@endsection
