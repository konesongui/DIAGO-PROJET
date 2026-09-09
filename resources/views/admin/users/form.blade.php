@extends('admin.layout')

@section('content')
<div class="card border-0 mx-auto" style="max-width: 900px;">
    <div class="card-header border-0 px-5 py-4"><div class="card-title fs-4 fw-bold text-dark">{{ $title }}</div></div>
    <div class="card-body">
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        @if(isset($user) && ($isOwnProfile ?? false))
            <form method="POST" enctype="multipart/form-data" action="{{ route('admin.profile.update') }}" class="mb-8">
                @csrf @method('PATCH')
                <h4 class="mb-5">Informations personnelles</h4>
                <div class="row g-5">
                    <div class="col-12"><label class="form-label fw-semibold text-muted">Changer la photo de profil</label><div class="d-flex align-items-center gap-4"><img src="{{ $user->avatar_path ? asset('storage/' . $user->avatar_path) : asset('assets/media/avatars/300-1.jpg') }}" alt="Photo de profil" class="rounded-circle" style="width:72px;height:72px;object-fit:cover;"><input type="file" name="avatar" class="form-control" accept="image/jpeg,image/png,image/webp"></div><div class="form-text">Choisissez une nouvelle image JPG, PNG ou WEBP (2 Mo maximum), puis cliquez sur « Enregistrer les informations ».</div></div>
                    <div class="col-md-6"><label class="form-label fw-semibold text-muted">Nom complet</label><input type="text" name="name" value="{{ old('name', $user->name) }}" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label fw-semibold text-muted">Email</label><input type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control" required></div>
                    <div class="col-12"><label class="form-label fw-semibold text-muted">Adresse</label><input type="text" name="address" value="{{ old('address', $user->address) }}" class="form-control"></div>
                </div>
                <div class="text-end pt-5"><button type="submit" class="btn btn-primary">Enregistrer les informations</button></div>
            </form>
            <form method="POST" action="{{ route('admin.profile.password.update') }}" class="border-top pt-8">
                @csrf @method('PATCH')
                <h4 class="mb-5">Mot de passe</h4>
                <div class="row g-5"><div class="col-md-6"><label class="form-label fw-semibold text-muted">Nouveau mot de passe</label><input type="password" name="password" class="form-control" minlength="8"></div><div class="col-md-6"><label class="form-label fw-semibold text-muted">Confirmation</label><input type="password" name="password_confirmation" class="form-control" minlength="8"></div></div>
                <div class="text-end pt-5"><button type="submit" class="btn btn-light-primary">Modifier le mot de passe</button></div>
            </form>
        @else
            <form method="POST" action="{{ isset($user) ? route('admin.users.update', $user) : route('admin.users.store') }}">
                @csrf @if(isset($user)) @method('PUT') @endif
                <div class="row g-5">
                    <div class="col-md-6"><label class="form-label fw-semibold text-muted">Nom complet</label><input type="text" name="name" value="{{ old('name', $user->name ?? '') }}" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label fw-semibold text-muted">Email</label><input type="email" name="email" value="{{ old('email', $user->email ?? '') }}" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label fw-semibold text-muted">Rôle</label><select name="role_id" class="form-select" required>@foreach($roles as $role)<option value="{{ $role->id }}" {{ old('role_id', $user->role_id ?? '') == $role->id ? 'selected' : '' }}>{{ $role->label }}</option>@endforeach</select></div>
                    <div class="col-md-6"><label class="form-label fw-semibold text-muted">Entreprise</label><input type="text" class="form-control" value="{{ auth()->user()->entreprise->name ?? 'Entreprise courante' }}" disabled><div class="form-text">L'utilisateur est automatiquement rattaché à votre entreprise.</div></div>
                    <div class="col-md-6"><label class="form-label fw-semibold text-muted">Mot de passe</label><input type="password" name="password" class="form-control" {{ isset($user) ? '' : 'required' }}></div>
                    <div class="col-md-6"><label class="form-label fw-semibold text-muted">Confirmation</label><input type="password" name="password_confirmation" class="form-control"></div>
                </div>
                <div class="d-flex justify-content-end gap-3 pt-5"><a href="{{ route('admin.users.index') }}" class="btn btn-light">Annuler</a><button type="submit" class="btn btn-primary">{{ isset($user) ? 'Enregistrer' : 'Créer' }}</button></div>
            </form>
        @endif
    </div>
</div>
@endsection
