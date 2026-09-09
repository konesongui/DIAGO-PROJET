<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-lg bg-white rounded-2xl shadow-lg p-8">
        <h1 class="text-2xl font-bold text-center mb-2">Créer un compte</h1>
        <p class="text-center text-slate-500 mb-6">Configurez votre premier établissement Diagoma</p>

        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('register.post') }}" class="space-y-4">
            @csrf

            <div>
                <label for="name" class="block text-sm font-medium text-slate-700">Nom de l'établissement</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-slate-700">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700">Mot de passe</label>
                    <input id="password" name="password" type="password" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-slate-700">Confirmation</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>

            <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-white font-medium hover:bg-indigo-500">
                Créer mon compte
            </button>

            <p class="text-center text-sm text-slate-500">
                Déjà inscrit ? <a href="{{ route('login') }}" class="text-indigo-600 hover:text-indigo-500">Se connecter</a>
            </p>
        </form>
    </div>
</body>
</html>
