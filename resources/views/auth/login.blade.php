<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-lg p-8">
        <h1 class="text-2xl font-bold text-center mb-2">Connexion</h1>
        <p class="text-center text-slate-500 mb-6">Accédez à votre espace Diagoma</p>

        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.post') }}" class="space-y-4">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium text-slate-700">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-slate-700">Mot de passe</label>
                <input id="password" name="password" type="password" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="flex items-center justify-between text-sm">
                <label class="flex items-center gap-2 text-slate-600">
                    <input type="checkbox" name="remember" value="1" class="rounded border-slate-300"> Se souvenir
                </label>
               <!-- <a href="{{ route('register') }}" class="text-indigo-600 hover:text-indigo-500">Créer un compte</a> -->
            </div>

            <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-white font-medium hover:bg-indigo-500">
                Se connecter
            </button>
        </form>
    </div>
</body>
</html>
