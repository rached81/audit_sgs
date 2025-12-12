@extends('layouts.app')

@section('content')
<div class="container mx-auto max-w-lg mt-10">
    <div class="bg-white shadow-md rounded-lg p-6 border border-gray-200">
        <h1 class="text-2xl font-bold text-gray-800 mb-6 text-center">Audit des Stocks</h1>
        
        @if ($errors->any())
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" role="alert">
                <p class="font-bold">Erreur</p>
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form action="{{ route('audit.compare') }}" method="POST">
            @csrf
            
            <div class="mb-4">
                <label for="annee" class="block text-gray-700 text-sm font-bold mb-2">Année de l'exercice</label>
                <input type="number" name="annee" id="annee" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" placeholder="Ex: 2018" required min="2000" max="2100" value="{{ old('annee', date('Y')-1) }}">
            </div>

            <div class="mb-6">
                <label for="reseau" class="block text-gray-700 text-sm font-bold mb-2">Réseau</label>
                <select name="reseau" id="reseau" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="BUS">BUS</option>
                    <option value="FERRE">FERRÉ</option>
                </select>
            </div>

            <div class="mb-6">
                <label for="type" class="block text-gray-700 text-sm font-bold mb-2">Type de Comparaison</label>
                <select name="type" id="type" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="valeur" selected>Finale & Valeur (Standard)</option>
                    <option value="initial">Stock Initial</option>
                    <option value="pump">P.U.M.P</option>
                </select>
            </div>

            <div class="flex items-center justify-between">
                <button class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-150" type="submit">
                    Lancer la Comparaison
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
