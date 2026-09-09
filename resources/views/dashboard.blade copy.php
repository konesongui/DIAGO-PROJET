<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Dashboard' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-800">
    <div class="min-h-screen flex items-center justify-center p-6">
        <div class="w-full max-w-4xl bg-white shadow-xl rounded-2xl p-8">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <p class="text-sm uppercase tracking-[0.2em] text-indigo-500 font-semibold">Diagoma Laravel</p>
                    <h1 class="text-3xl font-bold mt-2">Tableau de bord</h1>
                </div>
                <span class="bg-indigo-100 text-indigo-700 px-3 py-1 rounded-full text-sm font-medium">
                    Tenant: {{ $tenantId ?? 'non défini' }}
                </span>
            </div>

            <div class="grid md:grid-cols-3 gap-4">
                <div class="rounded-xl border border-slate-200 p-5">
                    <p class="text-sm text-slate-500">Établissements</p>
                    <p class="text-2xl font-bold mt-2">1</p>
                </div>
                <div class="rounded-xl border border-slate-200 p-5">
                    <p class="text-sm text-slate-500">Utilisateurs</p>
                    <p class="text-2xl font-bold mt-2">0</p>
                </div>
                <div class="rounded-xl border border-slate-200 p-5">
                    <p class="text-sm text-slate-500">Modules</p>
                    <p class="text-2xl font-bold mt-2">ERP</p>
                </div>
            </div>

            <div class="mt-8 rounded-xl bg-slate-50 border border-slate-200 p-5">
                <h2 class="text-lg font-semibold mb-3">Structure de migration Laravel</h2>
                <ul class="list-disc pl-5 space-y-2 text-sm text-slate-600">
                    <li>Base Laravel créée dans le dossier <code class="bg-slate-200 px-1 rounded">/laravel</code>.</li>
                    <li>Modèle <code class="bg-slate-200 px-1 rounded">Entreprise</code> ajouté pour l’isolation multi-tenant.</li>
                    <li>Middleware <code class="bg-slate-200 px-1 rounded">TenantContext</code> ajouté pour gérer le contexte de l’entreprise.</li>
                    <li>Migrations de départ ajoutées pour les tables <code class="bg-slate-200 px-1 rounded">entreprises</code> et <code class="bg-slate-200 px-1 rounded">users.entreprise_id</code>.</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>
