@extends('admin.layout')

@section('content')
@php
    $settings = $entreprise->settings ?? [];
    $value = fn (string $key, $default = '') => old($key, $default);
    $expiry = data_get($settings, 'subscription_expires_at');
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" :subtitle="$subtitle" :back="route('admin.entreprises.index')" back-label="Entreprises" />

    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ $entreprise ? route('admin.entreprises.update', $entreprise) : route('admin.entreprises.store') }}">
        @csrf
        @if($entreprise)@method('PUT')@endif

        <x-dg.card title="Identité de l’entreprise" icon="bi-buildings" color="indigo" class="mb-5">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="entName">Raison sociale <span class="text-danger">*</span></label>
                    <input id="entName" name="name" class="form-control @error('name') is-invalid @enderror" required maxlength="255"
                        value="{{ $value('name', $entreprise->name ?? '') }}" placeholder="Ex. CME Expertises SARL">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="entActivity">Secteur d’activité</label>
                    <input id="entActivity" name="activity" class="form-control @error('activity') is-invalid @enderror" maxlength="255"
                        value="{{ $value('activity', data_get($settings, 'activity', '')) }}" placeholder="Ex. Conseil &amp; audit financier">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="entAddress">Siège social <span class="text-danger">*</span></label>
                    <input id="entAddress" name="address" class="form-control @error('address') is-invalid @enderror" required maxlength="500"
                        value="{{ $value('address', data_get($settings, 'address', '')) }}" placeholder="Ex. Rue du Commerce, immeuble Alpha 2000">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="entCity">Ville</label>
                    <input id="entCity" name="city" class="form-control @error('city') is-invalid @enderror" maxlength="100"
                        value="{{ $value('city', data_get($settings, 'city', '')) }}" placeholder="Ex. Abidjan, Plateau">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="entEmail">Contact principal</label>
                    <input id="entEmail" name="email" type="email" class="form-control @error('email') is-invalid @enderror" maxlength="255"
                        value="{{ $value('email', data_get($settings, 'email', '')) }}" placeholder="contact@entreprise.ci">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="entPhone">Téléphone</label>
                    <input id="entPhone" name="phone" class="form-control @error('phone') is-invalid @enderror" maxlength="50"
                        value="{{ $value('phone', data_get($settings, 'phone', '')) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="entSlug">Identifiant</label>
                    <input id="entSlug" name="slug" class="form-control @error('slug') is-invalid @enderror" maxlength="255"
                        value="{{ $value('slug', $entreprise->slug ?? '') }}" placeholder="Déduit de la raison sociale">
                    <span class="form-text">Sert de référence interne : laissez vide pour le déduire de la raison sociale.</span>
                    @error('slug')<span class="dg-field-error">{{ $message }}</span>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="entExpiry">Abonnement jusqu’au <span class="text-danger">*</span></label>
                    <input id="entExpiry" name="subscription_expires_at" type="date" class="form-control @error('subscription_expires_at') is-invalid @enderror" required
                        value="{{ $value('subscription_expires_at', $expiry ? \Illuminate\Support\Carbon::parse($expiry)->toDateString() : now()->addYear()->toDateString()) }}">
                    <span class="form-text">Au-delà, l’espace est fermé jusqu’au réabonnement.</span>
                </div>
            </div>
        </x-dg.card>

        <x-dg.card title="Rubriques ouvertes" icon="bi-grid" color="blue" class="mb-5">
            <p class="dg-card__text">Les rubriques cochées apparaissent dans le menu de l’entreprise. Au moins une est nécessaire.</p>
            <div class="row g-3">
                @foreach($rubriques as $key => $rubrique)
                    <div class="col-md-6 col-xl-4">
                        <label class="rubrique-choice">
                            <input type="checkbox" class="form-check-input mt-0" name="rubriques[{{ $key }}]" value="1"
                                @checked(old('rubriques.' . $key, $enabled[$key] ?? false))>
                            <span>
                                <strong><i class="bi {{ $rubrique['icon'] }} me-2"></i>{{ $rubrique['label'] }}</strong>
                                <span class="dg-muted d-block" style="font-size:12.5px">{{ $rubrique['description'] }}</span>
                            </span>
                        </label>
                    </div>
                @endforeach
            </div>
        </x-dg.card>

        @unless($entreprise)
            <x-dg.card title="Compte administrateur" icon="bi-person-badge" color="green" class="mb-5">
                <p class="dg-card__text">Ce compte est le premier à se connecter à l’espace : sans lui, l’entreprise reste inaccessible.</p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="entAdminName">Nom complet <span class="text-danger">*</span></label>
                        <input id="entAdminName" name="admin_name" class="form-control @error('admin_name') is-invalid @enderror" required maxlength="255" value="{{ old('admin_name') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="entAdminEmail">E-mail <span class="text-danger">*</span></label>
                        <input id="entAdminEmail" name="admin_email" type="email" class="form-control @error('admin_email') is-invalid @enderror" required maxlength="255" value="{{ old('admin_email') }}">
                        @error('admin_email')<span class="dg-field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="entAdminPassword">Mot de passe <span class="text-danger">*</span></label>
                        <input id="entAdminPassword" name="admin_password" type="password" class="form-control @error('admin_password') is-invalid @enderror" required minlength="8">
                        <span class="form-text">Huit caractères au moins.</span>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="entAdminPasswordConfirm">Confirmation <span class="text-danger">*</span></label>
                        <input id="entAdminPasswordConfirm" name="admin_password_confirmation" type="password" class="form-control" required minlength="8">
                    </div>
                </div>
            </x-dg.card>
        @else
            <div class="dg-callout mb-5">
                <i class="bi bi-info-circle me-2"></i>Les comptes de cette entreprise se gèrent depuis l’écran Utilisateurs. Désactiver l’entreprise ferme l’accès de tous ses comptes.
            </div>
        @endunless

        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('admin.entreprises.index') }}" class="dg-btn dg-btn--outline">Annuler</a>
            <button type="submit" class="dg-btn dg-btn--primary"><i class="bi bi-check-lg"></i>{{ $entreprise ? 'Enregistrer' : 'Créer l’entreprise' }}</button>
        </div>
    </form>
</div>

<style>
    .rubrique-choice {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 12px 14px;
        border: 1px solid var(--dg-border);
        border-radius: var(--dg-radius);
        background: var(--dg-surface);
        cursor: pointer;
        height: 100%;
    }
    .rubrique-choice:hover { border-color: var(--dg-navy); }
    .rubrique-choice strong { font-size: 14px; font-weight: 600; }
</style>
@endsection
