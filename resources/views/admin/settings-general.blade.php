@extends('admin.layout')

@section('content')
@php($value = fn (string $key, mixed $default = '') => old($key, $settings[$key] ?? $default))
@php($logoPath = $settings['logo'] ?? null)
<style>
    .subscription-overview-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1.5rem;
        align-items: start;
    }
    @media (max-width: 767.98px) {
        .subscription-overview-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
<style>
    .settings-general .card{border:1px solid #edf2f7!important;border-radius:18px;background:#fff;box-shadow:0 12px 30px rgba(15,23,42,.04)!important;overflow:hidden}
    .settings-general .card-header,.settings-general .card-footer{border-color:#edf2f7!important}
    .settings-general .card-header{padding:20px 24px!important}
    .settings-general .card-body{padding:24px!important}
</style>
<div class="settings-general">
<form method="POST" action="{{ route('admin.settings.general.update') }}" enctype="multipart/form-data">
    @csrf
    @method('PATCH')
    <div class="card border-0 shadow-sm mb-5">
        <div class="card-header bg-white border-0 p-5">
            <h2 class="h4 fw-bold mb-1">Informations de l'entreprise</h2>
            <p class="text-muted mb-0">Renseignez les informations utilisées dans les factures, devis et documents.</p>
        </div>
        <div class="card-body p-5">
            <div class="row g-4">
                @foreach([
                    ['name','Raison sociale','text',true], ['trade_register','Registre de commerce','text',false],
                    ['taxpayer_account','Compte contribuable','text',false], ['legal_form','Forme juridique','text',false],
                    ['cnps_number','Numéro CNPS','text',false], ['po_box','Boîte Postale','text',false],
                    ['address','Adresse','text',true], ['bank_name','Nom de la banque','text',false],
                    ['bank_account','Compte bancaire','text',false], ['tax_center','Centre des impôts','text',false],
                    ['tax_regime','Régime d’imposition','text',false],
                ] as [$key, $label, $type, $required])
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">{{ $label }}{{ $required ? ' *' : '' }}</label>
                        <input type="{{ $type }}" name="{{ $key }}" class="form-control" value="{{ $value($key) }}" {{ $required ? 'required' : '' }}>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-5">
        <div class="card-header bg-white border-0 p-5"><h2 class="h4 fw-bold mb-0">Contact et identification</h2></div>
        <div class="card-body p-5">
            <div class="row g-4">
                @foreach([['phone','Téléphone',true],['nccm_rccm','NCCM/RCCM',true],['email','Email',true],['website','Site web',false],['activity','Activité de l’entreprise',false],['supplier_name','Nom du fournisseur',false],['manager_name','Nom du responsable',false],['manager_title','Titre',false]] as [$key,$label,$required])
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">{{ $label }}{{ $required ? ' *' : '' }}</label>
                        <input type="{{ $key === 'email' ? 'email' : 'text' }}" name="{{ $key }}" class="form-control" value="{{ $value($key) }}" {{ $required ? 'required' : '' }}>
                    </div>
                @endforeach
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Langue *</label>
                    <select name="locale" class="form-select" required>
                        @foreach(['fr'=>'Français','en'=>'English'] as $code => $label)
                            <option value="{{ $code }}" {{ $value('locale', 'fr') === $code ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-5">
        <div class="card-header bg-white border-0 p-5"><h2 class="h4 fw-bold mb-0">Informations financières et préférences</h2></div>
        <div class="card-body p-5">
            <div class="row g-4">
                <div class="col-md-4"><label class="form-label fw-semibold">Devise</label><input name="currency" class="form-control" value="{{ $value('currency','XOF') }}"></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Symbole de la devise</label><input name="currency_symbol" class="form-control" value="{{ $value('currency_symbol','FCFA') }}"></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Année fiscale</label><input name="fiscal_year" class="form-control" value="{{ $value('fiscal_year') }}" placeholder="2026-27"></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Format de date</label><select name="date_format" class="form-select">@foreach(['dd/mm/yyyy','dd-mm-yyyy','yyyy/mm/dd'] as $format)<option value="{{ $format }}" {{ $value('date_format','dd/mm/yyyy') === $format ? 'selected' : '' }}>{{ $format }}</option>@endforeach</select></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Début de semaine</label><select name="week_start" class="form-select">@foreach(['monday'=>'Lundi','sunday'=>'Dimanche'] as $code=>$label)<option value="{{ $code }}" {{ $value('week_start','monday') === $code ? 'selected' : '' }}>{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Logo</label>
                    @if($logoPath)
                        <div class="d-flex align-items-center gap-3 mb-2 p-2 rounded bg-light">
                            <img src="{{ asset('storage/' . ltrim($logoPath, '/')) }}" alt="Logo actuel" style="width: 56px; height: 56px; object-fit: contain;">
                            <span class="text-muted fs-7">Logo actuel enregistré</span>
                        </div>
                    @endif
                    <input type="file" name="logo" class="form-control" accept="image/*">
                    <div class="form-text">Sélectionnez une nouvelle image uniquement pour remplacer le logo actuel.</div>
                </div>
            </div>
        </div>
        <div class="card-footer bg-white border-0 p-5 d-flex justify-content-end gap-3">
            <a href="{{ route('admin.settings') }}" class="btn btn-light">Annuler</a>
            <button type="submit" class="btn btn-primary">Enregistrer la configuration</button>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm mb-5">
    <div class="card-header bg-white border-0 p-5">
        <h2 class="h4 fw-bold mb-1">Abonnement</h2>
        <p class="text-muted mb-0">Consultez la date d’expiration et prolongez l’accès à votre espace.</p>
    </div>
    <div class="card-body p-5">
        <div class="subscription-overview-grid">
            <div>
                <label class="form-label fw-semibold">Date d’expiration</label>
                <input type="date" class="form-control" value="{{ $value('subscription_expires_at') }}" readonly>
                <div class="form-text">Cette date est prolongée automatiquement après un réabonnement.</div>
            </div>
            <div>
                <label class="form-label fw-semibold">Dernier mode de paiement</label>
                <input type="text" class="form-control" value="{{ str_replace('_', ' ', ucfirst($value('subscription_payment_method', 'Non renseigné'))) }}" readonly>
            </div>
        </div>
    </div>
    <div class="card-footer bg-white border-0 p-5">
        <form method="POST" action="{{ route('admin.settings.general.renew') }}" class="row g-4 align-items-end">
            @csrf
            <div class="col-md-4">
                <label class="form-label fw-semibold">Durée du réabonnement</label>
                <select name="renewal_duration" class="form-select" required>
                    <option value="1">1 mois</option>
                    <option value="3">3 mois</option>
                    <option value="6">6 mois</option>
                    <option value="12">12 mois</option>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label fw-semibold">Mode de paiement</label>
                <select name="renewal_payment_method" class="form-select" required>
                    <option value="">Sélectionnez un mode</option>
                    <option value="orange_money">Orange Money</option>
                    <option value="mtn_money">MTN Money</option>
                    <option value="moov_money">Moov Money</option>
                    <option value="wave_money">Wave</option>
                    <option value="other_mobile_money">Autre mobile money</option>
                    <option value="mastercard">Mastercard</option>
                    <option value="visa">Visa</option>
                    <option value="bank_transfer">Virement bancaire</option>
                    <option value="cinetpay">CinetPay</option>
                </select>
            </div>
            <div class="col-md-3">
                <div class="small text-muted mb-1">Montant estimé</div>
                <div class="fw-bold text-primary" id="renewalAmountPreview">25 000 FCFA</div>
                <button type="submit" class="btn btn-primary w-100 mt-3">Réabonner</button>
            </div>
        </form>
    <div class="form-text mt-3">CinetPay peut être activé avec les identifiants informatiques ci-dessous ou via les variables d’environnement du projet.</div>
    </div>
</div>

    <script>
        const renewalDurationSelect = document.querySelector('select[name="renewal_duration"]');
        const renewalAmountPreview = document.getElementById('renewalAmountPreview');
        const renewalAmounts = {
            1: 25000,
            3: 60000,
            6: 110000,
            12: 200000,
        };

        if (renewalDurationSelect && renewalAmountPreview) {
            const updateRenewalAmount = () => {
                const duration = Number(renewalDurationSelect.value || 1);
                const amount = renewalAmounts[duration] || renewalAmounts[1];
                renewalAmountPreview.textContent = window.formatMoney(amount);
            };

            renewalDurationSelect.addEventListener('change', updateRenewalAmount);
            updateRenewalAmount();
        }
    </script>
    @endsection
