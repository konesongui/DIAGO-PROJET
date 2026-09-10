<?php

namespace App\Http\Controllers\Admin;

class HubController extends AdminController
{
    public function index()
    {
        return $this->page('hub', [
            'title' => 'Administration',
            'cards' => [
                ['title' => 'Utilisateurs', 'description' => 'Gestion des comptes internes', 'link' => route('admin.users.index'), 'icon' => 'bi-person'],
                ['title' => 'Profils & permissions', 'description' => 'Rôles et accès', 'link' => route('admin.settings.module', 'roles'), 'icon' => 'bi-shield-lock'],
                ['title' => 'Établissements', 'description' => 'Paramétrage multi-tenant', 'link' => route('admin.entreprises.index'), 'icon' => 'bi-building'],
                ['title' => 'Logs & sécurité', 'description' => 'Audit et traçabilité', 'link' => route('admin.settings'), 'icon' => 'bi-shield-check'],
                ['title' => 'Visiteurs', 'description' => 'Suivi des visiteurs à 360°', 'link' => route('admin.administration.module', 'visiteurs'), 'icon' => 'bi-person-badge'],
                ['title' => 'Appels', 'description' => 'Journal des appels', 'link' => route('admin.administration.module', 'appels'), 'icon' => 'bi-telephone'],
                ['title' => 'Courriers', 'description' => 'Suivi des courriers entrants et sortants', 'link' => route('admin.administration.module', 'courriers'), 'icon' => 'bi-envelope'],
                ['title' => 'Réunions', 'description' => 'Planification et comptes-rendus', 'link' => route('admin.administration.module', 'reunions'), 'icon' => 'bi-calendar-event'],
                ['title' => 'Documents', 'description' => 'Documents administratifs centralisés', 'link' => route('admin.administration.module', 'documents'), 'icon' => 'bi-folder'],
            ],
        ]);
    }
}
