@extends('admin.layout')

@section('content')
<style>
    .employee-columns-menu { min-width: 245px; max-height: 430px; overflow-y: auto; }
    .employee-columns-menu .form-check { padding: .65rem 1rem .65rem 2.5rem; margin: 0; cursor: pointer; }
    .employee-columns-menu .form-check:hover { background: #f5f8fa; }
    .employee-columns-menu .form-check-input { margin-left: -1.5rem; }
    .employee-columns-menu .form-check-label { width: 100%; cursor: pointer; }
    .employee-table th, .employee-table td { white-space: nowrap; padding: .75rem 1rem !important; }
    .employee-table-wrapper { display: block; width: 100%; max-height: 70vh; overflow-x: auto !important; overflow-y: auto; -webkit-overflow-scrolling: touch; scrollbar-color: #94a3b8 #f1f5f9; scrollbar-width: auto; }
    .employee-table-wrapper::-webkit-scrollbar { height: 12px; width: 12px; }
    .employee-table-wrapper::-webkit-scrollbar-track { background: #f1f5f9; }
    .employee-table-wrapper::-webkit-scrollbar-thumb { background: #94a3b8; border-radius: 8px; }
    .employee-table { width: max-content !important; min-width: 100%; table-layout: auto !important; }
    .employee-table th[data-column="matricule"] { min-width: 125px; }
    .employee-table th[data-column="civility"] { min-width: 90px; }
    .employee-table th[data-column="name"] { min-width: 220px; }
    .employee-table th[data-column="service"], .employee-table th[data-column="position"] { min-width: 150px; }
    .employee-table th[data-column="contract"] { min-width: 150px; }
    .employee-table th[data-column="status"] { min-width: 120px; }
    .employee-table th[data-column="actions"] { min-width: 90px; }
    .employee-table-wrapper thead th { position: sticky; top: 0; z-index: 2; background: #fff; }
</style>

<div class="card border-0 mb-6">
    <div class="card-body p-6">
        @if(session('success'))
            <div class="alert alert-success mb-5">{{ session('success') }}</div>
        @endif
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-4">
            <div class="d-flex align-items-center gap-3">
                <span class="fs-1"><i class="bi {{ $module['icon'] }}"></i></span>
                <div>
                    <div class="text-uppercase text-muted fs-8 fw-bold">RH &amp; Paie</div>
                    <h2 class="fs-2 fw-bold text-dark mb-1">{{ $module['title'] }}</h2>
                    <p class="text-muted mb-0">{{ $module['description'] }}</p>
                </div>
            </div>
            <a href="{{ route('admin.rh') }}" class="btn btn-light">Retour à RH &amp; Paie</a>
        </div>
    </div>
</div>

@if(request()->route('module') === 'personnel')
    <div class="card border-0">
        <div class="card-header border-0 px-5 py-4 d-flex justify-content-between align-items-center">
            <h3 class="card-title fs-4 fw-bold">Liste du personnel</h3>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.departments.index') }}" class="btn btn-light-primary btn-sm">Gérer les services</a>
                <div class="dropdown">
                    <button type="button" class="btn btn-light-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-layout-three-columns me-1"></i> Colonnes
                    </button>
                    <div class="dropdown-menu dropdown-menu-end employee-columns-menu p-0" data-employee-columns-menu>
                        <div class="px-4 py-3 border-bottom fw-bold">Afficher les colonnes</div>
                        @foreach([
                            'photo' => 'Photo',
                            'matricule' => 'Matricule',
                            'civility' => 'Civilité',
                            'name' => 'Nom et prénom',
                            'service' => 'Service',
                            'position' => 'Fonction',
                            'nationality' => 'Nationalité',
                            'contract' => 'Type de contrat',
                            'address' => 'Adresse',
                            'birth_date' => 'Date de naissance',
                            'entry_date' => "Date d'entrée",
                            'exit_date' => 'Date de sortie',
                            'salary_category' => 'Catégorie salariale',
                            'cnps' => 'CNPS',
                            'phone' => 'Téléphone',
                            'status' => 'Statut',
                            'actions' => 'Action'
                        ] as $key => $label)
                            <label class="form-check">
                                <input class="form-check-input employee-column-toggle" type="checkbox" value="{{ $key }}" data-column-toggle="{{ $key }}">
                                <span class="form-check-label">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <a href="{{ route('admin.rh.employees.create') }}" class="btn btn-primary btn-sm">Nouvel employé</a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive employee-table-wrapper">
                <table class="table align-middle mb-0 employee-table" data-employee-table>
                    <thead><tr><th data-column="photo">Photo</th><th data-column="matricule">Matricule</th><th data-column="civility">Civilité</th><th data-column="name">Nom et prénom</th><th data-column="service">Service</th><th data-column="position">Fonction</th><th data-column="nationality">Nationalité</th><th data-column="contract">Type de contrat</th><th data-column="address">Adresse</th><th data-column="birth_date">Date de naissance</th><th data-column="entry_date">Date d'entrée</th><th data-column="exit_date">Date de sortie</th><th data-column="salary_category">Catégorie salariale</th><th data-column="cnps">CNPS</th><th data-column="phone">Téléphone</th><th data-column="status">Statut</th><th data-column="actions">Action</th></tr></thead>
                    <tbody>
                    @forelse($employees as $employee)
                        <tr>
                            <td data-column="photo">-</td><td data-column="matricule"><span class="badge badge-light-primary">{{ $employee->matricule }}</span></td>
                            <td data-column="civility">{{ $employee->civility ?: '-' }}</td>
                            <td data-column="name" class="fw-semibold">{{ $employee->full_name }}<div class="text-muted fs-7">{{ $employee->email }}</div></td>
                            <td data-column="service">{{ $employee->department ?: '-' }}</td><td data-column="position">{{ $employee->position ?: '-' }}</td><td data-column="nationality">{{ $employee->nationality ?: '-' }}</td><td data-column="contract">{{ $employee->contract_type ?: '-' }}</td><td data-column="address">{{ $employee->address ?: '-' }}</td>
                            <td data-column="birth_date">{{ optional($employee->birth_date)->format('d/m/Y') ?: '-' }}</td><td data-column="entry_date">{{ optional($employee->registered_at)->format('d/m/Y') ?: '-' }}</td><td data-column="exit_date">{{ optional($employee->contract_end_date)->format('d/m/Y') ?: '-' }}</td><td data-column="salary_category">{{ $employee->salary_category ?: '-' }}</td><td data-column="cnps">{{ $employee->cnps_number ?: '-' }}</td><td data-column="phone">{{ $employee->phone ?: '-' }}</td>
                            <td data-column="status"><span class="badge {{ $employee->status === 'active' ? 'badge-light-success' : 'badge-light-danger' }}">{{ $employee->status === 'active' ? 'En poste' : 'Inactif' }}</span></td>
                            <td data-column="actions">
                                <div class="dropdown">
                                    <button type="button" class="btn btn-sm btn-light-primary px-3" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions pour {{ $employee->full_name }}">•••</button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="{{ route('admin.rh.employees.show', $employee) }}">Voir</a>
                                        <a class="dropdown-item" href="{{ route('admin.rh.employees.edit', $employee) }}">Modifier</a>
                                        <form method="POST" action="{{ route('admin.rh.employees.terminate', $employee) }}">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="dropdown-item text-warning" onclick="return confirm('Mettre fin au contrat ?')">Mettre fin au contrat</button>
                                        </form>
                                        <div class="dropdown-divider"></div>
                                        <form method="POST" action="{{ route('admin.rh.employees.destroy', $employee) }}">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="dropdown-item text-danger" onclick="return confirm('Supprimer cet employé ?')">Supprimer</button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="16" class="text-center text-muted py-8">Aucun employé enregistré.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@else
    <div class="card border-0">
        <div class="card-body p-8">
            <div class="alert alert-light-primary mb-0">
                Le module <strong>{{ $module['title'] }}</strong> est maintenant accessible dans RH &amp; Paie. Les données restent limitées à l’entreprise connectée.
            </div>
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script>
    (() => {
        const table = document.querySelector('[data-employee-table]');
        const menu = document.querySelector('[data-employee-columns-menu]');
        if (!table || !menu) return;

        const defaults = ['matricule', 'civility', 'name', 'service', 'position', 'contract', 'status', 'actions'];
        const storageKey = 'diagoma-rh-employee-columns-v2';
        let visible;
        try {
            const saved = JSON.parse(localStorage.getItem(storageKey));
            visible = Array.isArray(saved) && saved.length ? saved : defaults;
        } catch (error) {
            visible = defaults;
        }

        const applyVisibility = () => {
            table.querySelectorAll('[data-column]').forEach(cell => {
                cell.style.display = visible.includes(cell.dataset.column) ? '' : 'none';
            });
            menu.querySelectorAll('[data-column-toggle]').forEach(input => {
                input.checked = visible.includes(input.dataset.columnToggle);
            });
            localStorage.setItem(storageKey, JSON.stringify(visible));
        };

        menu.querySelectorAll('[data-column-toggle]').forEach(input => {
            input.addEventListener('change', () => {
                const key = input.dataset.columnToggle;
                if (input.checked) {
                    visible = [...visible, key];
                } else {
                    visible = visible.filter(item => item !== key);
                }
                applyVisibility();
            });
        });
        applyVisibility();
    })();
</script>
@endpush
