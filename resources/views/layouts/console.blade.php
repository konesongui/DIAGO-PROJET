<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Console Super Administrateur' }} - Diagoma</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body{background:#f4f7fb;color:#15243a}.console-nav{background:linear-gradient(135deg,#123f70,#1d72b8);color:#fff}.console-card{border:0;border-radius:18px;box-shadow:0 12px 30px rgba(15,23,42,.06)}.console-icon{width:54px;height:54px;border-radius:16px;background:#e8f2ff;display:flex;align-items:center;justify-content:center;font-size:1.5rem}
    </style>
</head>
<body>
<nav class="console-nav py-3">
    <div class="container-fluid px-4 d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-4"><strong class="fs-4">Diagoma Console</strong><span class="opacity-75">Super administrateur</span><a href="{{ route('console.dashboard') }}" class="text-white text-decoration-none">Dashboard</a><a href="{{ route('console.account-tracking') }}" class="text-white text-decoration-none">Suivi des comptes</a><a href="{{ route('admin.demorequests') }}" class="text-white text-decoration-none">Demandes de démo</a><a href="{{ route('admin.settings') }}" class="text-white text-decoration-none">Paramètres</a></div>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-outline-light btn-sm">Déconnexion</button></form>
    </div>
</nav>
<main class="container-fluid px-4 py-4">@yield('content')</main>
</body>
</html>
