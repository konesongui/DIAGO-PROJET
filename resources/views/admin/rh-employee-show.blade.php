@extends('admin.layout')
@section('content')
<div class="card border-0">
    <div class="card-header border-0 p-5 d-flex justify-content-between align-items-center">
        <div><div class="text-muted text-uppercase fs-8 fw-bold">Fiche employé</div><h2 class="fs-2 fw-bold mb-0">{{ $employee->full_name }}</h2></div>
        <a href="{{ route('admin.rh.module', 'personnel') }}" class="btn btn-light">Retour</a>
    </div>
    <div class="card-body p-5">
        <div class="row g-5">
            @foreach(['matricule'=>'Matricule','civility'=>'Civilité','department'=>'Services','position'=>'Fonction','nationality'=>'Nationalité','contract_type'=>'Contrat','address'=>'Adresse','birth_date'=>'Date de naissance','registered_at'=>"Date d'entrée",'contract_end_date'=>'Date de sortie','salary_category'=>'Catégorie salariale','cnps_number'=>'CNPS','phone'=>'Téléphone','status'=>'Statut'] as $field => $label)
                <div class="col-md-3"><div class="text-muted fs-7">{{ $label }}</div><div class="fw-semibold">{{ $employee->{$field} ?: '-' }}</div></div>
            @endforeach
        </div>
        @if(auth()->user()->id === $employee->user_id)
            <hr class="my-6">
            <h3 class="fs-4 fw-bold">Demander une permission</h3>
            <form method="POST" action="{{ route('admin.rh.permissions.store') }}" class="row g-4">
                @csrf
                <div class="col-md-3"><label class="form-label">Type</label><select name="type" class="form-select" required><option value="">Sélectionner</option><option>Permission exceptionnelle</option><option>Permission familiale</option><option>Permission médicale</option><option>Autre</option></select></div>
                <div class="col-md-3"><label class="form-label">Du</label><input type="date" name="start_date" class="form-control" required></div>
                <div class="col-md-3"><label class="form-label">Au</label><input type="date" name="end_date" class="form-control" required></div>
                <div class="col-12"><label class="form-label">Motif</label><textarea name="reason" class="form-control" rows="3" required></textarea></div>
                <div class="col-12"><button class="btn btn-primary">Envoyer la demande</button><a href="{{ route('admin.rh.permissions') }}" class="btn btn-light ms-2">Voir mes demandes</a></div>
            </form>
        @endif
        <hr class="my-6">
        <div class="d-flex justify-content-between align-items-center mb-3"><h3 class="fs-4 fw-bold mb-0">Bulletins de salaire</h3><a href="{{ route('admin.rh.payroll.create') }}" class="btn btn-sm btn-primary">Générer un bulletin</a></div>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Période</th><th>Brut</th><th>Net à payer</th><th>Actions</th></tr></thead><tbody>
        @forelse($payrolls as $payroll)
            <tr><td>{{ \Carbon\Carbon::create()->month($payroll->month)->translatedFormat('F') }} {{ $payroll->year }}</td><td>{{ money($payroll->gross_salary) }}</td><td class="fw-bold">{{ money($payroll->net_salary) }}</td><td><a target="_blank" class="btn btn-sm btn-light-primary" href="{{ route('admin.rh.payroll.show', $payroll) }}">Voir / imprimer</a><a target="_blank" class="btn btn-sm btn-light ms-2" href="{{ route('admin.rh.payroll.pdf', $payroll) }}">PDF</a></td></tr>
        @empty <tr><td colspan="4" class="text-muted text-center">Aucun bulletin généré.</td></tr>@endforelse
        </tbody></table></div>
        @if(auth()->user()->id === $employee->user_id)
            <a href="{{ route('admin.rh.leaves') }}" class="btn btn-light-primary mt-4">Demander un congé</a>
        @endif
    </div>
</div>
@endsection
