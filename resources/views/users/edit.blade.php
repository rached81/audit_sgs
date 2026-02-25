@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 mt-6 max-w-2xl">
    <div class="bg-white shadow-md rounded-lg p-6 border border-gray-200">
        <h1 class="text-2xl font-bold text-gray-800 mb-6 border-b pb-2">Modifier Utilisateur : {{ $user->nom }} {{ $user->prenom }}</h1>

        @if ($errors->any())
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded" role="alert">
                <p class="font-bold">Veuillez corriger les erreurs suivantes :</p>
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('users.update', $user) }}" method="POST" class="space-y-4">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="nom">Nom</label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="nom" name="nom" type="text" value="{{ old('nom', $user->nom) }}" required>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="prenom">Prénom</label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="prenom" name="prenom" type="text" value="{{ old('prenom', $user->prenom) }}" required>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="matricule">Matricule</label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="matricule" name="matricule" type="text" value="{{ old('matricule', $user->matricule) }}" required>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="unite">Unité</label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="unite" name="unite" type="text" value="{{ old('unite', $user->unite) }}">
                </div>
            </div>

            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="email">Email</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required>
            </div>

            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="username">Nom d'utilisateur (Username)</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="username" name="username" type="text" value="{{ old('username', $user->username) }}" required>
            </div>

            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="profile">Profil</label>
                <select class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="profile" name="profile" required>
                    <option value="user" {{ old('profile', $user->profile) == 'user' ? 'selected' : '' }}>Utilisateur Standard</option>
                    <option value="admin" {{ old('profile', $user->profile) == 'admin' ? 'selected' : '' }}>Administrateur</option>
                </select>
            </div>

            <div class="bg-yellow-50 p-4 rounded border border-yellow-200 mt-4">
                <h3 class="font-bold text-yellow-800 text-sm mb-2">Changer le mot de passe (Laisser vide pour ne pas changer)</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="password">Nouveau Mot de passe</label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="password" name="password" type="password">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="password_confirmation">Confirmer Mot de passe</label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="password_confirmation" name="password_confirmation" type="password">
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between pt-4">
                <a href="{{ route('users.index') }}" class="text-gray-600 hover:text-gray-800 font-bold">Annuler</a>
                <button class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" type="submit">
                    Mettre à jour
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
