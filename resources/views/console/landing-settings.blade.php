@extends('admin.layout')

@section('content')
<style>
    .landing-config-shell { max-width: 1200px; margin: 0 auto; }
    .landing-card { background: #fff; border: 1px solid #edf2f7; border-radius: 20px; box-shadow: 0 14px 34px rgba(15,23,42,.04); }
    .landing-header { border-bottom: 1px solid #edf2f7; }
    .landing-form textarea { min-height: 180px; resize: vertical; }
    .landing-form input, .landing-form textarea { border-radius: 12px; border: 1px solid #dfe7f3; padding: 12px 14px; }
</style>

<div class="landing-config-shell">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <div class="text-uppercase text-muted small fw-bold">Console</div>
            <h1 class="h3 fw-bold mb-1">Paramétrage du site landing</h1>
            <p class="text-muted mb-0">Personnalisez le branding, le contenu, les modules et les packs pour la page publique.</p>
        </div>
        <a href="{{ route('console.index') }}" class="btn btn-light">Retour au tableau de bord</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success mb-4">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('console.landing.update') }}" class="landing-form">
        @csrf

        <div class="landing-card p-4 p-md-5 mb-4">
            <div class="d-flex align-items-center gap-3 mb-4">
                <i class="bi bi-globe fs-1"></i>
                <div>
                    <div class="text-uppercase text-muted small fw-bold">Accueil</div>
                    <h2 class="h4 fw-bold mb-0">Branding et SEO</h2>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Nom de la marque</label>
                    <input type="text" name="brand_name" value="{{ old('brand_name', $settings['brand_name'] ?? 'Diagoma') }}" class="form-control" />
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Titre de page</label>
                    <input type="text" name="page_title" value="{{ old('page_title', $settings['page_title'] ?? 'Diagoma | ERP multi-entreprises') }}" class="form-control" />
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Description méta</label>
                    <textarea name="meta_description" class="form-control">{{ old('meta_description', $settings['meta_description'] ?? '') }}</textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Couleur primaire</label>
                    <input type="color" name="primary_color" value="{{ old('primary_color', $settings['primary_color'] ?? '#00a3ff') }}" class="form-control form-control-color w-100" />
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Couleur secondaire</label>
                    <input type="color" name="secondary_color" value="{{ old('secondary_color', $settings['secondary_color'] ?? '#4d68ff') }}" class="form-control form-control-color w-100" />
                </div>
            </div>
        </div>

        <div class="landing-card p-4 p-md-5 mb-4">
            <div class="d-flex align-items-center gap-3 mb-4">
                <i class="bi bi-stars fs-1"></i>
                <div>
                    <div class="text-uppercase text-muted small fw-bold">Hero</div>
                    <h2 class="h4 fw-bold mb-0">Texte d’accroche</h2>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Badge</label>
                    <input type="text" name="hero_badge" value="{{ old('hero_badge', $settings['hero_badge'] ?? 'ERP intelligent • multi-tenant') }}" class="form-control" />
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Bouton principal</label>
                    <input type="text" name="primary_cta_label" value="{{ old('primary_cta_label', $settings['primary_cta_label'] ?? 'Obtenir un accès démo') }}" class="form-control" />
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Lien CTA principal</label>
                    <input type="text" name="primary_cta_url" value="{{ old('primary_cta_url', $settings['primary_cta_url'] ?? '#demo') }}" class="form-control" />
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Bouton secondaire</label>
                    <input type="text" name="secondary_cta_label" value="{{ old('secondary_cta_label', $settings['secondary_cta_label'] ?? 'Découvrir les modules') }}" class="form-control" />
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Lien CTA secondaire</label>
                    <input type="text" name="secondary_cta_url" value="{{ old('secondary_cta_url', $settings['secondary_cta_url'] ?? '#modules') }}" class="form-control" />
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Titre hero</label>
                    <textarea name="hero_title" class="form-control">{{ old('hero_title', $settings['hero_title'] ?? 'Un ERP qui <span class="gradient-text">simplifie</span> la gestion de votre entreprise.') }}</textarea>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Sous-titre hero</label>
                    <textarea name="hero_subtitle" class="form-control">{{ old('hero_subtitle', $settings['hero_subtitle'] ?? '') }}</textarea>
                </div>
            </div>
        </div>

        <div class="landing-card p-4 p-md-5 mb-4">
            <div class="d-flex align-items-center gap-3 mb-4">
                <i class="bi bi-images fs-1"></i>
                <div>
                    <div class="text-uppercase text-muted small fw-bold">Carousel</div>
                    <h2 class="h4 fw-bold mb-0">Slides du header</h2>
                </div>
            </div>
            <textarea name="slides_json" class="form-control">{{ old('slides_json', json_encode($settings['slides'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) }}</textarea>
        </div>

        <div class="landing-card p-4 p-md-5 mb-4">
            <div class="d-flex align-items-center gap-3 mb-4">
                <i class="bi bi-grid fs-1"></i>
                <div>
                    <div class="text-uppercase text-muted small fw-bold">Contenu</div>
                    <h2 class="h4 fw-bold mb-0">Modules et packs</h2>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-lg-6">
                    <label class="form-label fw-semibold">Modules (JSON)</label>
                    <textarea name="modules_json" class="form-control">{{ old('modules_json', json_encode($settings['modules'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) }}</textarea>
                </div>
                <div class="col-lg-6">
                    <label class="form-label fw-semibold">Packs (JSON)</label>
                    <textarea name="packs_json" class="form-control">{{ old('packs_json', json_encode($settings['packs'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) }}</textarea>
                </div>
            </div>
        </div>

        <div class="landing-card p-4 p-md-5 mb-4">
            <div class="d-flex align-items-center gap-3 mb-4">
                <i class="bi bi-envelope-paper fs-1"></i>
                <div>
                    <div class="text-uppercase text-muted small fw-bold">Footer</div>
                    <h2 class="h4 fw-bold mb-0">Coordonnées et CTA</h2>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Email de contact</label>
                    <input type="email" name="contact_email" value="{{ old('contact_email', $settings['contact_email'] ?? 'contact@diagoma.com') }}" class="form-control" />
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Téléphone</label>
                    <input type="text" name="contact_phone" value="{{ old('contact_phone', $settings['contact_phone'] ?? '+225 00 00 00 00') }}" class="form-control" />
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Texte du footer</label>
                    <textarea name="footer_text" class="form-control">{{ old('footer_text', $settings['footer_text'] ?? '') }}</textarea>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end mt-4">
            <button type="submit" class="btn btn-primary px-5">Enregistrer le landing</button>
        </div>
    </form>
</div>
@endsection
