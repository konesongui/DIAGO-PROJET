<?php

namespace App\Http\Controllers\Admin;

class HubController extends AdminController
{
    public function index()
    {
        return $this->page('hub', [
            'title' => 'Administration',
            'cards' => [
                ['title' => 'Utilisateurs', 'description' => 'Gestion des comptes internes', 'link' => route('admin.users.index'), 'icon' => '👤'],
                ['title' => 'Profils & permissions', 'description' => 'Rôles et accès', 'link' => route('admin.settings.module', 'roles'), 'icon' => '🔐'],
                ['title' => 'Établissements', 'description' => 'Paramétrage multi-tenant', 'link' => route('admin.entreprises.index'), 'icon' => '🏢'],
                ['title' => 'Logs & sécurité', 'description' => 'Audit et traçabilité', 'link' => route('admin.settings'), 'icon' => '🛡️'],
                ['title' => 'Visiteurs', 'description' => 'Suivi des visiteurs à 360°', 'link' => route('admin.administration.module', 'visiteurs'), 'icon' => '📋'],
                ['title' => 'Appels', 'description' => 'Journal des appels', 'link' => route('admin.administration.module', 'appels'), 'icon' => '📞'],
                ['title' => 'Courriers', 'description' => 'Suivi des courriers entrants et sortants', 'link' => route('admin.administration.module', 'courriers'), 'icon' => '✉️'],
                ['title' => 'Réunions', 'description' => 'Planification et comptes-rendus', 'link' => route('admin.administration.module', 'reunions'), 'icon' => '📅'],
                ['title' => 'Documents', 'description' => 'Documents administratifs centralisés', 'link' => route('admin.administration.module', 'documents'), 'icon' => '📁'],
            ],
        ]);
    }
}
