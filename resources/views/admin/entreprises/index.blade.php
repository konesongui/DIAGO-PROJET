@extends('admin.layout')

@section('content')
@php
    $expiryOf = fn ($entreprise) => data_get($entreprise->settings, 'subscription_expires_at');
    $expired = $entreprises->filter(function ($entreprise) use ($expiryOf) {
        $date = $expiryOf($entreprise);
        return $date && \Illuminate\Support\Carbon::parse($date)->endOfDay()->isPast();
    });
    $active = $entreprises->where('is_active', true);

    $stats = [
        ['Entreprises', $entreprises->count(), 'bi-buildings', 'indigo', $entreprises->count() - $active->count() . ' désactivée(s)'],
        ['Actives', $active->count(), 'bi-check-circle', 'green', 'accès ouvert à leurs comptes'],
        ['Abonnements échus', $expired->count(), 'bi-calendar-x', 'red', $expired->isNotEmpty() ? 'à renouveler' : 'aucun retard'],
        ['Comptes utilisateurs', $entreprises->sum('users_count'), 'bi-people', 'purple', 'toutes entreprises confondues'],
    ];
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" :subtitle="$subtitle">
        <x-slot:actions>
            <a href="{{ route('admin.entreprises.create') }}" class="dg-btn dg-btn--primary"><i class="bi bi-plus-lg"></i>Nouvelle entreprise</a>
        </x-slot:actions>
    </x-dg.page-header>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="dg-kpi-grid">
        @foreach($stats as [$label, $value, $icon, $color, $hint])
            <x-dg.kpi :label="$label" :value="$value" :icon="$icon" :color="$color" :hint="$hint" />
        @endforeach
    </div>

    <div class="dg-card dg-card--table">
        <div class="dg-card__header flex-wrap">
            <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-indigo"><i class="bi bi-buildings"></i></span>Entités juridiques</h2>
            @if($entreprises->isNotEmpty())
                <label class="dg-search" style="max-width:260px">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input id="entrepriseSearch" type="search" placeholder="Rechercher une entreprise…" aria-label="Rechercher une entreprise">
                </label>
            @endif
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0 entreprises-table no-export" id="entreprisesTable">
                <thead>
                    <tr>
                        <th>Raison sociale</th>
                        <th>Secteur d’activité</th>
                        <th>Contact principal</th>
                        <th>Siège social</th>
                        <th>Créée le</th>
                        <th>Statut</th>
                        <th class="text-end"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($entreprises as $entreprise)
                    @php
                        $settings = $entreprise->settings ?? [];
                        $expiry = $expiryOf($entreprise) ? \Illuminate\Support\Carbon::parse($expiryOf($entreprise)) : null;
                        $isExpired = $expiry && $expiry->endOfDay()->isPast();
                        $city = data_get($settings, 'city');
                        $address = data_get($settings, 'address');
                    @endphp
                    <tr>
                        <td>
                            <span class="d-block fw-semibold" style="max-width:170px">{{ $entreprise->name }}</span>
                            <span class="d-block dg-muted" style="font-size:12.5px">{{ $entreprise->users_count }} compte(s) · {{ $entreprise->slug }}</span>
                        </td>
                        <td><span class="d-block" style="max-width:140px">{{ data_get($settings, 'activity') ?: 'Non renseigné' }}</span></td>
                        <td>
                            <span class="d-block text-truncate" style="max-width:150px" title="{{ data_get($settings, 'email') }}">{{ data_get($settings, 'email') ?: '—' }}</span>
                            <span class="d-block dg-muted" style="font-size:12.5px">{{ data_get($settings, 'phone') ?: 'Téléphone non renseigné' }}</span>
                        </td>
                        <td>
                            <span class="d-block">{{ $city ?: ($address ? '—' : 'Non renseigné') }}</span>
                            @if($address)<span class="d-block dg-muted" style="font-size:12.5px;max-width:140px">{{ $address }}</span>@endif
                        </td>
                        <td class="text-nowrap">{{ $entreprise->created_at?->format('d/m/Y') ?? '—' }}</td>
                        <td>
                            <span class="dg-badge dg-badge--{{ $entreprise->is_active ? 'success' : 'neutral' }}">
                                <i class="bi {{ $entreprise->is_active ? 'bi-check-circle' : 'bi-slash-circle' }}"></i>{{ $entreprise->is_active ? 'Active' : 'Désactivée' }}
                            </span>
                            @if($expiry)
                                <span class="d-block {{ $isExpired ? 'dg-amount-negative' : 'dg-muted' }}" style="font-size:12px">
                                    {{ $isExpired ? 'Échu le ' : 'Jusqu’au ' }}{{ $expiry->format('d/m/Y') }}
                                </span>
                            @else
                                <span class="d-block dg-muted" style="font-size:12px">Sans échéance</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="d-flex align-items-center justify-content-end gap-2">
                                <a href="{{ route('admin.entreprises.edit', $entreprise) }}" class="dg-icon-btn" title="Modifier {{ $entreprise->name }}" aria-label="Modifier {{ $entreprise->name }}"><i class="bi bi-pencil"></i></a>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light action-menu-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions" aria-label="Actions pour {{ $entreprise->name }}"></button>
                                    <ul class="dropdown-menu dropdown-menu-end dg-dropdown">
                                        <li>
                                            <form method="POST" action="{{ route('admin.entreprises.toggle', $entreprise) }}">
                                                @csrf @method('PATCH')
                                                <button class="dropdown-item"><i class="bi {{ $entreprise->is_active ? 'bi-slash-circle' : 'bi-check-circle' }}"></i>{{ $entreprise->is_active ? 'Désactiver' : 'Réactiver' }}</button>
                                            </form>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form method="POST" action="{{ route('admin.entreprises.destroy', $entreprise) }}" onsubmit="return confirm('Supprimer définitivement « {{ addslashes($entreprise->name) }} » ?')">
                                                @csrf @method('DELETE')
                                                <button class="dropdown-item text-danger"><i class="bi bi-trash"></i>Supprimer</button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-5">
                            <div class="dg-chart-empty" style="min-height:200px">
                                <span class="dg-tile dg-tone-indigo"><i class="bi bi-buildings"></i></span>
                                <div><strong>Aucune entreprise enregistrée</strong>Créez la première entité : sa fiche, son compte administrateur et les rubriques ouvertes se règlent d’un seul formulaire.</div>
                                <a href="{{ route('admin.entreprises.create') }}" class="dg-btn dg-btn--primary dg-btn--sm"><i class="bi bi-plus-lg"></i>Nouvelle entreprise</a>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="dg-chart-empty mt-4 d-none" id="entreprisesNoResult" style="min-height:140px">
            <span class="dg-tile dg-tone-indigo"><i class="bi bi-search"></i></span>
            <div><strong>Aucune entreprise ne correspond</strong>Modifiez la recherche.</div>
        </div>
        <p class="dg-muted mt-3 mb-0" style="font-size:12.5px"><i class="bi bi-info-circle me-1"></i>Désactiver une entreprise ferme aussi l’accès de tous ses comptes, sans rien effacer. Une entreprise qui porte des données ne se supprime pas.</p>
    </div>
</div>

<style>
    .entreprises-table { min-width: 940px; }
    .dg-scope .entreprises-table > thead > tr > th, .dg-scope .entreprises-table > tbody > tr > td { padding-left: 10px !important; padding-right: 10px !important; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const rows = Array.from(document.querySelectorAll('#entreprisesTable tbody tr'));
    const search = document.getElementById('entrepriseSearch');
    const noResult = document.getElementById('entreprisesNoResult');
    search?.addEventListener('input', function () {
        const term = search.value.trim().toLowerCase();
        let visible = 0;
        rows.forEach(function (row) {
            const show = !term || row.textContent.toLowerCase().includes(term);
            row.style.display = show ? '' : 'none';
            visible += show ? 1 : 0;
        });
        noResult.classList.toggle('d-none', !rows.length || visible > 0);
    });
});
</script>
@endsection
