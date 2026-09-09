@extends('admin.layout')

@section('content')
<style>.settings-panel{border:1px solid #edf2f7;border-radius:18px;background:#fff;box-shadow:0 12px 30px rgba(15,23,42,.04)}.settings-panel .panel-body{padding:24px}.field-hint{font-size:.8rem;color:#64748b}.settings-panel .panel-title{display:flex;align-items:center;gap:16px;margin-bottom:24px}.settings-panel .panel-title .icon{font-size:2rem}</style>
<div class="settings-panel">
    <div class="panel-body">
        <div class="panel-title">
            <span class="icon">{{ $module['icon'] }}</span>
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

        <p class="text-muted mb-4">Configurez la passerelle WhatsApp Business utilisée pour envoyer les notifications et messages automatiques depuis l’application.</p>

        <form method="POST" action="{{ route('admin.settings.whatsapp.update') }}">
            @csrf
            @method('PATCH')

            <div class="row g-4">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Activer WhatsApp</label>
                    <select name="whatsapp_enabled" class="form-select">
                        <option value="1" {{ old('whatsapp_enabled', data_get($whatsapp, 'enabled', false) ? '1' : '0') == '1' ? 'selected' : '' }}>Oui</option>
                        <option value="0" {{ old('whatsapp_enabled', data_get($whatsapp, 'enabled', false) ? '1' : '0') == '0' ? 'selected' : '' }}>Non</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">URL API</label>
                    <input type="url" name="whatsapp_api_url" class="form-control" value="{{ old('whatsapp_api_url', data_get($whatsapp, 'api_url', '')) }}" placeholder="https://graph.facebook.com/v19.0/...">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Token</label>
                    <input type="text" name="whatsapp_token" class="form-control" value="{{ old('whatsapp_token', data_get($whatsapp, 'token', '')) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Phone Number ID</label>
                    <input type="text" name="whatsapp_phone_number_id" class="form-control" value="{{ old('whatsapp_phone_number_id', data_get($whatsapp, 'phone_number_id', '')) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Business Account ID</label>
                    <input type="text" name="whatsapp_business_account_id" class="form-control" value="{{ old('whatsapp_business_account_id', data_get($whatsapp, 'business_account_id', '')) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Nom expéditeur</label>
                    <input type="text" name="whatsapp_sender_name" class="form-control" value="{{ old('whatsapp_sender_name', data_get($whatsapp, 'sender_name', '')) }}" placeholder="Diagoma">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Nom du template</label>
                    <input type="text" name="whatsapp_template_name" class="form-control" value="{{ old('whatsapp_template_name', data_get($whatsapp, 'template_name', '')) }}" placeholder="invoice_notification">
                </div>
            </div>

            <div class="mt-5 d-flex justify-content-between align-items-center">
                <a href="{{ route('admin.settings') }}" class="btn btn-light">Retour aux paramètres</a>
                <button type="submit" class="btn btn-primary">Enregistrer WhatsApp</button>
            </div>
        </form>
    </div>
</div>
@endsection
