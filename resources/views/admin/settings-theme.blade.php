@extends('admin.layout')

@section('content')
<style>
    .theme-shell { max-width: 980px; margin: 0 auto; }
    .theme-card { border: 1px solid #e8eef5; border-radius: 18px; background: #fff; box-shadow: 0 12px 30px rgba(15,23,42,.05); }
    .theme-option { position: relative; height: 100%; padding: 18px; border: 2px solid #edf1f5; border-radius: 15px; cursor: pointer; transition: .2s ease; }
    .theme-option:hover { transform: translateY(-2px); box-shadow: 0 10px 22px rgba(15,23,42,.08); }
    .theme-option.selected { border-color: var(--theme-color); background: #f8fbff; }
    .theme-option input { position: absolute; opacity: 0; }
    .theme-preview { height: 62px; margin-bottom: 14px; overflow: hidden; border-radius: 10px; background: linear-gradient(135deg, var(--theme-color), var(--theme-dark)); }
    .theme-preview::after { content: ""; display: block; width: 42%; height: 100%; background: rgba(255,255,255,.18); }
    .theme-name { color: #172b4d; font-weight: 800; }
    .theme-description { color: #8a99ad; font-size: 12px; }
</style>
<div class="theme-shell">
    <div class="theme-card p-4 p-md-5">
        <div class="d-flex align-items-center gap-3 mb-2">
            <i class="bi bi-palette fs-1"></i>
            <div><div class="text-uppercase text-muted fs-8 fw-bold">Apparence</div><h2 class="h4 fw-bold text-dark mb-0">Thème de couleurs</h2></div>
        </div>
        <p class="text-muted mb-4">Choisissez la couleur principale de votre espace. La préférence est appliquée à tous les utilisateurs de votre entreprise.</p>
        <form id="themeForm" method="POST" action="{{ route('admin.settings.theme.update') }}">
            @csrf @method('PATCH')
            <div class="row g-3">
                @foreach([
                    'diago' => ['Diago (charte CME)', '#273772', '#1f2d61', 'Bleu marine et jaune, charte officielle'],
                    'ocean' => ['Bleu océan', '#4d68ff', '#293fba', 'Classique et professionnel'],
                    'emerald' => ['Émeraude', '#0f9f78', '#08785c', 'Frais et dynamique'],
                    'royal' => ['Violet royal', '#7048e8', '#4c2aa6', 'Élégant et premium'],
                    'amber' => ['Ambre', '#d88900', '#a96000', 'Chaleureux et énergique'],
                    'slate' => ['Ardoise', '#475569', '#273449', 'Sobre et minimaliste'],
                    'midnight' => ['Minuit cyan', '#06b6d4', '#164e63', 'Technologique et lumineux'],
                    'coral' => ['Corail prestige', '#f05d5e', '#b9364f', 'Chic et chaleureux'],
                    'lavender' => ['Lavande', '#a855f7', '#6b21a8', 'Doux et créatif'],
                    'teal' => ['Turquoise', '#0d9488', '#115e59', 'Frais et raffiné'],
                    'graphite' => ['Graphite', '#64748b', '#1e293b', 'Épuré et contemporain'],
                    'ruby' => ['Rubis', '#dc3655', '#8f1836', 'Audacieux et élégant'],
                    'forest' => ['Forêt', '#25804b', '#155d38', 'Naturel et apaisant'],
                    'sand' => ['Sable', '#b7791f', '#805516', 'Chaleureux et sans dégradé'],
                    'metronic_black' => ['Metronic noir', '#00a3ff', '#07111f', 'Sombre, chic et contrasté'],
                ] as $key => [$label, $color, $dark, $description])
                    <div class="col-md-6 col-lg-4">
                        <label class="theme-option {{ $theme === $key ? 'selected' : '' }}" style="--theme-color:{{ $color }};--theme-dark:{{ $dark }}">
                            <input type="radio" name="theme" value="{{ $key }}" {{ $theme === $key ? 'checked' : '' }}>
                            <div class="theme-preview"></div><div class="theme-name">{{ $label }}</div><div class="theme-description">{{ $description }}</div>
                        </label>
                    </div>
                @endforeach
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4"><a href="{{ route('admin.settings') }}" class="btn btn-light">Retour</a><button class="btn btn-primary">Enregistrer le thème</button></div>
        </form>
    </div>
</div>
<script>
const themeColors = {
    diago: ['#273772', '#1f2d61', '#fadf2f', false],
    ocean: ['#4d68ff', '#293fba', '#8fa5ff', false],
    emerald: ['#0f9f78', '#08785c', '#62d8b5', false],
    royal: ['#7048e8', '#4c2aa6', '#b39bff', false],
    amber: ['#d88900', '#a96000', '#ffc65c', false],
    slate: ['#475569', '#273449', '#91a3bb', false],
    midnight: ['#06b6d4', '#164e63', '#67e8f9', false],
    coral: ['#f05d5e', '#b9364f', '#ffaaa0', false],
    lavender: ['#a855f7', '#6b21a8', '#d8b4fe', false],
    teal: ['#0d9488', '#115e59', '#5eead4', false],
    graphite: ['#64748b', '#1e293b', '#cbd5e1', true],
    ruby: ['#dc3655', '#8f1836', '#ff9bae', true],
    forest: ['#25804b', '#155d38', '#86d6a3', true],
    sand: ['#b7791f', '#805516', '#f0c674', true, false],
    metronic_black: ['#00a3ff', '#07111f', '#7dd3fc', true, true]
};
const applyThemePreview = key => {
    const colors = themeColors[key];
    if (!colors) return;
    document.documentElement.style.setProperty('--diagoma-primary', colors[0]);
    document.documentElement.style.setProperty('--diagoma-primary-dark', colors[1]);
    document.documentElement.style.setProperty('--diagoma-accent', colors[2]);
    document.documentElement.style.setProperty('--diagoma-icon', colors[0]);
    document.body.dataset.themeFlat = colors[3] ? '1' : '0';
    document.body.dataset.themeDark = colors[4] ? '1' : '0';
};
document.querySelectorAll('.theme-option input').forEach(input => input.addEventListener('change', () => {
    document.querySelectorAll('.theme-option').forEach(option => option.classList.remove('selected'));
    input.closest('.theme-option').classList.add('selected');
    applyThemePreview(input.value);
    window.setTimeout(() => document.getElementById('themeForm').submit(), 180);
}));
applyThemePreview(@json($theme));
</script>
@endsection
