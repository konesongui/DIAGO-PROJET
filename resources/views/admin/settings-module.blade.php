@extends('admin.layout')

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-body p-5">
        <div class="d-flex align-items-center gap-3 mb-4">
            <span class="fs-1">{{ $module['icon'] }}</span>
            <div>
                <div class="text-uppercase text-muted fs-8 fw-bold">{{ $module['category'] }}</div>
                <h2 class="h4 fw-bold text-dark mb-0">{{ $module['title'] }}</h2>
            </div>
        </div>
        <p class="text-muted mb-4">{{ $module['description'] }}</p>
        @if(($module['key'] ?? '') === 'roles')
            <div class="alert alert-light-primary border-0 mb-4">
                Ce module est séparé de la gestion des comptes utilisateurs. Configurez ici les rôles et les droits d’accès par module.
            </div>
            <a href="{{ route('admin.users.index') }}" class="btn btn-primary me-2">Gérer les utilisateurs</a>
        @else
            <div class="alert alert-light-primary border-0 mb-4">
                Ce module est prêt à être configuré dans votre espace Laravel. Les données restent limitées à l’entreprise connectée.
            </div>
        @endif
        <a href="{{ route('admin.settings') }}" class="btn btn-light">Retour aux paramètres</a>
    </div>
</div>
@endsection
