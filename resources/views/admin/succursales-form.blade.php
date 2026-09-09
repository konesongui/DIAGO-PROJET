<div class="mb-3"><label class="form-label">Nom *</label><input name="name" class="form-control" value="{{ old('name', $succursale->name ?? '') }}" required></div>
<div class="mb-3"><label class="form-label">Code *</label><input name="code" class="form-control" value="{{ old('code', $succursale->code ?? '') }}" required></div>
<div class="mb-3"><label class="form-label">Adresse</label><input name="address" class="form-control" value="{{ old('address', $succursale->address ?? '') }}"></div>
<div class="row g-3"><div class="col-md-6"><label class="form-label">Ville</label><input name="city" class="form-control" value="{{ old('city', $succursale->city ?? '') }}"></div><div class="col-md-6"><label class="form-label">Téléphone</label><input name="phone" class="form-control" value="{{ old('phone', $succursale->phone ?? '') }}"></div></div>
@if(!empty($includeAdmin))
<hr class="my-4"><h6 class="fw-bold">Compte administrateur de la succursale</h6>
<div class="mb-3"><label class="form-label">Nom complet *</label><input name="admin_name" class="form-control" required value="{{ old('admin_name') }}"></div>
<div class="mb-3"><label class="form-label">Email *</label><input type="email" name="admin_email" class="form-control" required value="{{ old('admin_email') }}"></div>
<div class="row g-3"><div class="col-md-6"><label class="form-label">Mot de passe *</label><input type="password" name="admin_password" class="form-control" required minlength="8"></div><div class="col-md-6"><label class="form-label">Confirmation *</label><input type="password" name="admin_password_confirmation" class="form-control" required minlength="8"></div></div>
@endif
