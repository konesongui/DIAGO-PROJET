@extends('admin.layout')

@section('content')
<style>
    .employee-wizard { background:#fff; border-radius:18px; box-shadow:0 8px 28px rgba(24,39,75,.06); overflow:hidden; }
    .employee-wizard-header { padding:24px 32px; border-bottom:1px solid #edf1f5; }
    .employee-wizard-body { display:flex; min-height:650px; }
    .wizard-steps { width:245px; padding:34px 24px; background:#fbfcff; border-right:1px solid #edf1f5; flex-shrink:0; }
    .wizard-step { position:relative; display:flex; align-items:center; gap:14px; padding:0 0 34px; color:#172554; }
    .wizard-step:not(:last-child)::after { content:''; position:absolute; left:15px; top:34px; height:28px; border-left:1px dashed #cbd5e1; }
    .wizard-number { width:30px; height:30px; border-radius:8px; display:flex; align-items:center; justify-content:center; background:#e8f1ff; color:#2563eb; font-weight:700; flex-shrink:0; }
    .wizard-step.active .wizard-number { background:#27267b; color:#fff; }
    .wizard-step-title { font-weight:700; font-size:14px; }
    .wizard-step-subtitle { color:#8792ad; font-size:11px; margin-top:3px; }
    .wizard-content { flex:1; padding:34px 40px; min-width:0; }
    .wizard-panel { display:none; }
    .wizard-panel.active { display:block; }
    .wizard-actions { display:flex; justify-content:space-between; margin-top:34px; padding-top:24px; border-top:1px solid #edf1f5; }
    .wizard-actions .btn { min-width:110px; }
    .wizard-section-title { font-size:20px; font-weight:700; color:#172554; margin-bottom:24px; }
    .wizard-content .form-label { font-size:13px; font-weight:600; color:#334155; }
    @media(max-width:767px) {
        .employee-wizard-body { display:block; }
        .wizard-steps { width:auto; display:flex; overflow-x:auto; gap:18px; padding:18px; border-right:0; border-bottom:1px solid #edf1f5; }
        .wizard-step { padding:0; min-width:145px; }
        .wizard-step:not(:last-child)::after { display:none; }
        .wizard-content { padding:24px 18px; }
    }
</style>

<form method="POST" action="{{ $employee ? route('admin.rh.employees.update', $employee) : route('admin.rh.employees.store') }}" enctype="multipart/form-data" id="employeeWizard">
    @csrf
    @if($employee) @method('PUT') @endif
    <div class="employee-wizard">
        <div class="employee-wizard-header d-flex justify-content-between align-items-center">
            <div>
                <a href="{{ route('admin.rh.module', 'personnel') }}" class="text-primary text-decoration-none fs-7">← Retour à la liste du personnel</a>
                <h2 class="fs-2 fw-bold text-dark mb-0 mt-3">{{ $employee ? 'Modifier un employé' : 'Créer un employé' }}</h2>
            </div>
            <span class="badge badge-light-primary">RH &amp; Paie</span>
        </div>
        <div class="employee-wizard-body">
            <aside class="wizard-steps">
                @foreach([['Informations','Personnelles'],['Informations','Professionnelles'],['Parents','Liens familiaux'],['Réseaux sociaux','Liens médias sociaux'],['Télécharger dossiers','Joindre les dossiers']] as $index => $step)
                    <div class="wizard-step {{ $index === 0 ? 'active' : '' }}" data-step-indicator="{{ $index }}">
                        <span class="wizard-number">{{ $index + 1 }}</span>
                        <div><div class="wizard-step-title">{{ $step[0] }}</div><div class="wizard-step-subtitle">{{ $step[1] }}</div></div>
                    </div>
                @endforeach
            </aside>

            <div class="wizard-content">
                @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

                <section class="wizard-panel active" data-step="0">
                    <h3 class="wizard-section-title">Informations personnelles</h3>
                    <div class="row g-5">
                        <div class="col-md-4"><label class="form-label">Civilité</label><select name="civility" class="form-select"><option value="">Sélectionner</option><option @selected(old('civility', $employee?->civility) === 'M.')>M.</option><option @selected(old('civility', $employee?->civility) === 'Mme')>Mme</option><option @selected(old('civility', $employee?->civility) === 'Mlle')>Mlle</option></select></div>
                        <div class="col-md-4"><label class="form-label">Rôle *</label><select name="role_id" class="form-select" required><option value="">Sélectionner</option>@foreach($roles as $role)<option value="{{ $role->id }}" @selected(old('role_id', $employee?->user?->role_id) == $role->id)>{{ $role->label }}</option>@endforeach</select></div>
                        <div class="col-md-4"><label class="form-label">Nom *</label><input name="last_name" class="form-control" value="{{ old('last_name', $employee ? trim(str_replace($employee->first_name ?? '', '', $employee->full_name ?? '')) : '') }}" placeholder="Nom" required></div>
                        <div class="col-md-4"><label class="form-label">Prénom(s) *</label><input name="first_name" class="form-control" value="{{ old('first_name', $employee?->first_name) }}" placeholder="Prénom(s)" required></div>
                        <div class="col-md-4"><label class="form-label">Sexe *</label><div class="d-flex gap-5 pt-3"><label><input type="radio" name="gender" value="Masculin" required @checked(old('gender', $employee?->gender) === 'Masculin')> Masculin</label><label><input type="radio" name="gender" value="Femme" @checked(old('gender', $employee?->gender) === 'Femme')> Féminin</label></div></div>
                        <div class="col-md-4"><label class="form-label">Date de naissance *</label><input type="date" name="birth_date" class="form-control" value="{{ old('birth_date', optional($employee?->birth_date)->format('Y-m-d')) }}" required></div>
                        <div class="col-md-4"><label class="form-label">Nationalité</label><input name="nationality" class="form-control" value="{{ old('nationality', $employee?->nationality) }}"></div>
                        <div class="col-md-4"><label class="form-label">Email (nom d'utilisateur) *</label><input type="email" name="email" class="form-control" value="{{ old('email', $employee?->email) }}" placeholder="email@" required></div>
                        <div class="col-md-4"><label class="form-label">Téléphone</label><input name="phone" class="form-control" value="{{ old('phone', $employee?->phone) }}"></div>
                        <div class="col-md-4"><label class="form-label">Situation matrimoniale</label><select name="marital_status" class="form-select"><option value="">Sélectionner</option><option @selected(old('marital_status', $employee?->marital_status) === 'Célibataire')>Célibataire</option><option @selected(old('marital_status', $employee?->marital_status) === 'Marié')>Marié</option><option @selected(old('marital_status', $employee?->marital_status) === 'Veuf')>Veuf</option><option @selected(old('marital_status', $employee?->marital_status) === 'Séparé')>Séparé</option><option @selected(old('marital_status', $employee?->marital_status) === 'Non précisé')>Non précisé</option></select></div>
                        <div class="col-md-6"><label class="form-label">Adresse actuelle</label><input name="address" class="form-control" value="{{ old('address', $employee?->address) }}"></div>
                        <div class="col-md-6"><label class="form-label">Adresse permanente</label><input name="permanent_address" class="form-control" value="{{ old('permanent_address', $employee?->permanent_address) }}"></div>
                        <div class="col-md-6"><label class="form-label">Photo</label><input type="file" name="photo" class="form-control" accept="image/*"></div>
                        <div class="col-md-6"><label class="form-label">Date d'adhésion *</label><input type="date" name="registered_at" class="form-control" value="{{ old('registered_at', optional($employee?->registered_at)->format('Y-m-d') ?: now()->format('Y-m-d')) }}" required></div>
                    </div>
                </section>

                <section class="wizard-panel" data-step="1">
                    <h3 class="wizard-section-title">Informations professionnelles</h3>
                    <div class="row g-5">
                        <div class="col-md-6"><label class="form-label">Fonction *</label><select name="position" class="form-select" required><option value="">Sélectionner</option>@foreach($designations as $item)<option @selected(old('position', $employee?->position) === $item->name)>{{ $item->name }}</option>@endforeach</select></div>
                        <div class="col-md-6"><label class="form-label">Service *</label><select name="department" class="form-select" required><option value="">Sélectionner</option>@foreach($departments as $item)<option @selected(old('department', $employee?->department) === $item->name)>{{ $item->name }}</option>@endforeach</select></div>
                        <div class="col-md-6"><label class="form-label">Catégorie salariale</label><select name="salary_category" class="form-select"><option value="">Sélectionner</option>@foreach(($salaryCategories ?? []) as $category)<option value="{{ $category->name }}" @selected(old('salary_category', $employee?->salary_category) === $category->name)>{{ $category->name }}{{ $category->amount !== null ? ' (' . number_format($category->amount, 0, ',', ' ') . ')' : '' }}</option>@endforeach</select></div>
                        <div class="col-md-6"><label class="form-label">Numéro CNPS employé</label><input name="cnps_number" class="form-control" value="{{ old('cnps_number', $employee?->cnps_number) }}"></div>
                        <div class="col-md-4"><label class="form-label">Type de contrat</label><select name="contract_type" class="form-select"><option value="">Sélectionner</option><option @selected(old('contract_type', $employee?->contract_type) === 'CDI')>CDI</option><option @selected(old('contract_type', $employee?->contract_type) === 'CDD')>CDD</option><option @selected(old('contract_type', $employee?->contract_type) === 'STAGE')>STAGE</option></select></div>
                        <div class="col-md-4"><label class="form-label">Date de départ</label><input type="date" name="contract_end_date" class="form-control" value="{{ old('contract_end_date', optional($employee?->contract_end_date)->format('Y-m-d')) }}"></div>
                        <div class="col-md-4"><label class="form-label">Salaire de base</label><input type="number" step="0.01" name="monthly_salary" class="form-control" value="{{ old('monthly_salary', $employee?->monthly_salary) }}"></div>
                        @foreach(['children_count'=>'Nombre d’enfant','sursalary'=>'Sursalaire','seniority_bonus'=>'Prime d’ancienneté','transport_allowance'=>'Prime de transport','overtime_hours'=>'Forfait d’heure supplémentaire','responsibility_bonus'=>'Prime de responsabilité','bonus'=>'Bonus','performance_bonus'=>'Prime de rendement','risk_bonus'=>'Prime de risque','attendance_bonus'=>'Prime d’assiduité','gratification'=>'Prime Gratification','leave_pay'=>'Congé','income_tax'=>'Imp. sur Trait. et Sal. (IS)','cmu'=>'CMU','other_deductions'=>'Autres retenues','indemnities'=>'Les indemnités'] as $name => $label)
                            <div class="col-md-3"><label class="form-label">{{ $label }}</label><input type="number" step="0.01" min="0" name="{{ $name }}" class="form-control" value="{{ old($name, $employee?->{$name} ?? 0) }}"></div>
                        @endforeach
                        <div class="col-md-3"><label class="form-label">Part IGR</label><input type="number" step="0.5" min="1" max="5" name="part_igr" class="form-control" value="{{ old('part_igr', $employee?->part_igr ?? 1) }}"></div>
                        <div class="col-md-3"><label class="form-label">Mode de paie</label><input name="payment_mode" class="form-control" value="{{ old('payment_mode', $employee?->payment_mode ?: 'Virement') }}"></div>
                        <div class="col-md-6"><label class="form-label">Qualification</label><input name="qualification" class="form-control" value="{{ old('qualification', $employee?->qualification) }}"></div>
                        <div class="col-md-6"><label class="form-label">Contact d'urgence</label><input name="emergency_contact" class="form-control" value="{{ old('emergency_contact', $employee?->emergency_contact) }}"></div>
                        <div class="col-12"><label class="form-label">Expérience professionnelle</label><textarea name="experience" class="form-control" rows="4">{{ old('experience', $employee?->experience) }}</textarea></div>
                        <div class="col-12"><label class="form-label">Remarque</label><textarea name="remark" class="form-control" rows="3">{{ old('remark', $employee?->remark) }}</textarea></div>
                    </div>
                </section>

                <section class="wizard-panel" data-step="2">
                    <h3 class="wizard-section-title">Parents et congés</h3>
                    <div class="row g-5">
                        <div class="col-md-6"><label class="form-label">Nom du père</label><input name="father_name" class="form-control" value="{{ old('father_name', $employee?->father_name) }}"></div>
                        <div class="col-md-6"><label class="form-label">Nom de la mère</label><input name="mother_name" class="form-control" value="{{ old('mother_name', $employee?->mother_name) }}"></div>
                        <div class="col-md-4"><label class="form-label">Congé de paternité</label><input name="paternity_leave" class="form-control" value="{{ old('paternity_leave', $employee?->paternity_leave) }}"></div>
                        <div class="col-md-4"><label class="form-label">Congé de maternité</label><input name="maternity_leave" class="form-control" value="{{ old('maternity_leave', $employee?->maternity_leave) }}"></div>
                        <div class="col-md-4"><label class="form-label">Congé annuel</label><input name="annual_leave" class="form-control" value="{{ old('annual_leave', $employee?->annual_leave) }}"></div>
                        <div class="col-12"><div class="alert alert-light-primary mb-0">Les informations de congés pourront être complétées et suivies dans les modules RH dédiés.</div></div>
                    </div>
                </section>

                <section class="wizard-panel" data-step="3">
                    <h3 class="wizard-section-title">Réseaux sociaux et compte bancaire</h3>
                    <div class="row g-5">
                        @foreach(['facebook_url'=>'Facebook','twitter_url'=>'Twitter','linkedin_url'=>'LinkedIn','instagram_url'=>'Instagram'] as $name => $label)
                            <div class="col-md-6"><label class="form-label">URL {{ $label }}</label><input type="url" name="{{ $name }}" class="form-control" value="{{ old($name, $employee?->{$name}) }}"></div>
                        @endforeach
                        @foreach(['account_title'=>'Titre de compte','bank_account_number'=>'Numéro de compte bancaire','bank_name'=>'Nom de banque','ifsc_code'=>'Code IFSC','bank_branch'=>'Succursale bancaire'] as $name => $label)
                            <div class="col-md-4"><label class="form-label">{{ $label }}</label><input name="{{ $name }}" class="form-control" value="{{ old($name, $employee?->{$name}) }}"></div>
                        @endforeach
                    </div>
                </section>

                <section class="wizard-panel" data-step="4">
                    <h3 class="wizard-section-title">Télécharger des dossiers</h3>
                    <p class="text-muted">Vous pouvez joindre jusqu'à 3 documents au dossier de l'employé.</p>
                    <div class="row g-5">
                        @if($employee?->documents)
                            <div class="col-12"><div class="alert alert-light-info mb-0">Documents déjà enregistrés : {{ collect($employee->documents)->pluck('name')->join(', ') }}. Les nouveaux fichiers seront ajoutés au dossier.</div></div>
                        @endif
                        @for($document = 1; $document <= 3; $document++)
                            <div class="col-md-4"><label class="form-label">Document {{ $document }}</label><input type="file" name="documents[]" class="form-control"></div>
                        @endfor
                    </div>
                    <div class="alert alert-light-success mt-6">Après validation, un compte sera créé selon le profil choisi et les accès seront envoyés par email.</div>
                </section>

                <div class="wizard-actions">
                    <button type="button" class="btn btn-light" id="wizardPrev" disabled>← Précédent</button>
                    <button type="button" class="btn btn-primary" id="wizardNext">Continuer →</button>
                    <button type="submit" class="btn btn-primary d-none" id="wizardSubmit">Enregistrer et envoyer les accès</button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
(() => {
    const form = document.getElementById('employeeWizard');
    const panels = [...form.querySelectorAll('.wizard-panel')];
    const indicators = [...form.querySelectorAll('[data-step-indicator]')];
    const prev = document.getElementById('wizardPrev');
    const next = document.getElementById('wizardNext');
    const submit = document.getElementById('wizardSubmit');
    let current = 0;

    const render = () => {
        panels.forEach((panel, index) => panel.classList.toggle('active', index === current));
        indicators.forEach((indicator, index) => indicator.classList.toggle('active', index === current));
        prev.disabled = current === 0;
        next.classList.toggle('d-none', current === panels.length - 1);
        submit.classList.toggle('d-none', current !== panels.length - 1);
    };

    next.addEventListener('click', () => {
        const fields = [...panels[current].querySelectorAll('input, select, textarea')];
        if (!fields.every(field => field.checkValidity())) {
            form.reportValidity();
            return;
        }
        current = Math.min(current + 1, panels.length - 1);
        render();
    });
    prev.addEventListener('click', () => { current = Math.max(current - 1, 0); render(); });
    render();
})();
</script>
@endsection
