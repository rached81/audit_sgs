<!DOCTYPE html>
<html lang="fr" class="h-full bg-gray-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Premiere connexion - Changer mot de passe</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-full flex items-center justify-center px-4">
    <div class="w-full max-w-md bg-white shadow rounded-lg p-6">
        <h1 class="text-xl font-bold text-gray-800 mb-2">Premiere connexion</h1>
        <p class="text-sm text-gray-600 mb-5">Vous devez changer votre mot de passe avant de continuer.</p>

        @if ($errors->any())
            <div class="mb-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form action="{{ route('password.first.update') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label for="password" class="block text-sm font-semibold text-gray-700 mb-1">Nouveau mot de passe</label>
                <input id="password" name="password" type="password" required class="w-full border rounded px-3 py-2">
            </div>
            <div>
                <label for="password_confirmation" class="block text-sm font-semibold text-gray-700 mb-1">Confirmer mot de passe</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required class="w-full border rounded px-3 py-2">
            </div>
            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 rounded">
                Mettre a jour
            </button>
        </form>
    </div>
</body>
</html>

