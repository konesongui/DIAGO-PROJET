<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandingSetting extends Model
{
    use HasFactory;

    protected $table = 'landing_settings';

    protected $fillable = [
        'key',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    public static function current(): array
    {
        $setting = static::firstOrCreate(
            ['key' => 'landing'],
            ['settings' => static::defaultSettings()]
        );

        $settings = $setting->settings ?? static::defaultSettings();
        $defaultSlides = static::defaultSettings()['slides'];
        $legacyImages = [
            'https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=1600&q=80',
            'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?auto=format&fit=crop&w=1600&q=80',
            'https://images.unsplash.com/photo-1554224155-6726b3ff858f?auto=format&fit=crop&w=1600&q=80',
        ];

        foreach (($settings['slides'] ?? []) as $index => $slide) {
            if (isset($defaultSlides[$index])
                && (empty($slide['image']) || in_array($slide['image'], $legacyImages, true))) {
                $settings['slides'][$index]['image'] = $defaultSlides[$index]['image'];
            }
        }

        return $settings;
    }

    public static function persist(array $settings): array
    {
        $setting = static::firstOrCreate(['key' => 'landing']);
        $setting->settings = array_replace_recursive(static::defaultSettings(), $settings);
        $setting->save();

        return $setting->settings;
    }

    public static function defaultSettings(): array
    {
        return [
            'brand_name' => 'Diagoma',
            'page_title' => 'Diagoma | ERP multi-entreprises',
            'meta_description' => 'Diagoma ERP - modules, packs et accès démo.',
            'hero_badge' => 'ERP intelligent • multi-tenant',
            'hero_title' => 'Un ERP qui <span class="gradient-text">simplifie</span> la gestion de votre entreprise.',
            'hero_subtitle' => 'Centralisez votre finance, votre RH, votre commercial, votre administration et la supervision de vos opérations, dans un espace prêt à l’emploi et prêt à être déployé rapidement.',
            'primary_cta_label' => 'Obtenir un accès démo',
            'primary_cta_url' => '#demo',
            'secondary_cta_label' => 'Découvrir les modules',
            'secondary_cta_url' => '#modules',
            'stats' => [
                ['value' => '250+', 'label' => 'entreprises'],
                ['value' => '20+', 'label' => 'modules'],
                ['value' => '99,9%', 'label' => 'disponibilité'],
            ],
            'dashboard_stats' => [
                ['value' => '42.8K', 'label' => 'CA réalisé'],
                ['value' => '96%', 'label' => 'Taux de satisfaction'],
                ['value' => '38', 'label' => 'Mouvements aujourd’hui'],
                ['value' => '7', 'label' => 'Départements'],
            ],
            'slides' => [
                [
                    'label' => 'Finance & Reporting',
                    'title' => 'Une vision claire de votre performance',
                    'text' => 'Suivez vos ventes, vos dépenses et vos performances en temps réel.',
                    'image' => 'https://images.unsplash.com/photo-1529156069898-49953e39b3ac?auto=format&fit=crop&w=1600&q=85',
                ],
                [
                    'label' => 'RH & Paie',
                    'title' => 'Gérez les équipes et les absences',
                    'text' => 'Automatisez la paie, les congés et les flux de présence sans friction.',
                    'image' => 'https://images.unsplash.com/photo-1531123897727-8f129e1688ce?auto=format&fit=crop&w=1600&q=85',
                ],
                [
                    'label' => 'Commercial & Logistique',
                    'title' => 'Un ERP prêt pour la croissance',
                    'text' => 'Centralisez devis, stock, clients, fournisseurs et suivis de vente.',
                    'image' => 'https://images.unsplash.com/photo-1509099836639-18ba1795216d?auto=format&fit=crop&w=1600&q=85',
                ],
            ],
            'modules' => [
                ['title' => 'Finance', 'icon' => '💰', 'description' => 'Comptabilité, caisse, banques, rapports et trésorerie centralisée.'],
                ['title' => 'RH', 'icon' => '👥', 'description' => 'Gestion du personnel, congés, paie, présences et attendances.'],
                ['title' => 'Commercial', 'icon' => '📦', 'description' => 'Devis, factures, stock, clients, fournisseurs et ventes.'],
                ['title' => 'Administration', 'icon' => '🏢', 'description' => 'Visiteurs, appels, courriers, réunions et documents.'],
                ['title' => 'Succursales', 'icon' => '🧭', 'description' => 'Multi-entreprise, filiales et organisation territoriale.'],
                ['title' => 'Reporting', 'icon' => '📊', 'description' => 'KPI, tableaux de bord, suivi et pilotage en temps réel.'],
            ],
            'packs' => [
                ['name' => 'Starter', 'price' => '29', 'currency' => '€', 'description' => 'Pour les petites structures.', 'features' => ['1 entreprise', '3 utilisateurs', 'Modules de base', 'Support email']],
                ['name' => 'Business', 'price' => '79', 'currency' => '€', 'description' => 'Idéal pour le pilotage d’activité.', 'features' => ['5 entreprises', '20 utilisateurs', 'Tous les modules', 'Support prioritaire'], 'featured' => true],
                ['name' => 'Enterprise', 'price' => '149', 'currency' => '€', 'description' => 'Pour les groupes multi-sites.', 'features' => ['Entreprises illimitées', 'Utilisateurs illimités', 'Customisation avancée', 'Assistance dédiée']],
            ],
            'footer_text' => 'Une plateforme ERP moderne pour gérer les finances, le RH, le commercial, les opérations et la croissance de votre entreprise.',
            'contact_email' => 'contact@diagoma.com',
            'contact_phone' => '+225 00 00 00 00',
            'primary_color' => '#00a3ff',
            'secondary_color' => '#4d68ff',
            'demo_title' => 'Essayez notre espace démo',
            'demo_description' => 'Renseignez votre adresse email pour obtenir un accès immédiat à une version de démonstration de l’ERP, avec les modules et interfaces les plus utilisés.',
            'demo_submit_label' => 'Accéder à la démo',
            'demo_success_label' => 'Accès démo activé pour',
        ];
    }
}
