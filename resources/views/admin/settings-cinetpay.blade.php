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
            <a href="{{ route('admin.settings') }}" class="btn btn-light ms-auto">← Retour</a>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <p class="text-muted mb-4">Configurez la passerelle CinetPay utilisée pour le renouvellement des abonnements de votre console.</p>

        <form method="POST" action="{{ route('admin.settings.cinetpay.update') }}">
            @csrf
            @method('PATCH')

            <div class="row g-4">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Activer CinetPay</label>
                    <select name="cinetpay_enabled" class="form-select">
                        <option value="1" {{ old('cinetpay_enabled', data_get($cinetpay, 'enabled', false) ? '1' : '0') == '1' ? 'selected' : '' }}>Oui</option>
                        <option value="0" {{ old('cinetpay_enabled', data_get($cinetpay, 'enabled', false) ? '1' : '0') == '0' ? 'selected' : '' }}>Non</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Site ID</label>
                    <input type="text" name="cinetpay_site_id" class="form-control" value="{{ old('cinetpay_site_id', data_get($cinetpay, 'site_id', '')) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">API key</label>
                    <input type="text" name="cinetpay_api_key" class="form-control" value="{{ old('cinetpay_api_key', data_get($cinetpay, 'api_key', '')) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Secret key</label>
                    <input type="text" name="cinetpay_secret_key" class="form-control" value="{{ old('cinetpay_secret_key', data_get($cinetpay, 'secret_key', '')) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Mode</label>
                    <select name="cinetpay_mode" class="form-select">
                        <option value="test" {{ old('cinetpay_mode', data_get($cinetpay, 'mode', 'test')) === 'test' ? 'selected' : '' }}>Test</option>
                        <option value="production" {{ old('cinetpay_mode', data_get($cinetpay, 'mode', 'test')) === 'production' ? 'selected' : '' }}>Production</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">URL de retour</label>
                    <input type="url" name="cinetpay_return_url" class="form-control" value="{{ old('cinetpay_return_url', data_get($cinetpay, 'return_url', url('/setting/general/renew/callback'))) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">URL de notification</label>
                    <input type="url" name="cinetpay_notify_url" class="form-control" value="{{ old('cinetpay_notify_url', data_get($cinetpay, 'notify_url', url('/setting/general/renew/callback'))) }}">
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-5">
                <a href="{{ route('admin.settings') }}" class="btn btn-light">Retour aux paramètres</a>
                <button type="submit" class="btn btn-primary">Enregistrer CinetPay</button>
            </div>
        </form>
    </div>
</div>
@endsection
