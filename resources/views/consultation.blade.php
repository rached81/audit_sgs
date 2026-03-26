@extends('layouts.app')

@section('content')
<div class="container mx-auto">
    <h1 class="text-3xl font-bold mb-8 text-green-700">Etats Disponibles</h1>

    @if (count($groupedTables) > 0)
        @foreach ($groupedTables as $annee => $reseaux)
            <div class="mb-10">
                <h2 class="text-2xl font-bold text-gray-800 border-b-2 border-yellow-200 pb-2 mb-6 flex items-center">
                    <span class="bg-green-600 text-white rounded-lg px-3 py-1 mr-3 text-lg">{{ $annee }}</span>
                    <span>Exercice {{ $annee }}</span>
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @foreach ($reseaux as $reseau => $tables)
                        <div class="bg-white rounded-xl shadow-md overflow-hidden hover:shadow-lg transition-shadow duration-300 border border-gray-100">
                            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                                <h3 class="text-lg font-bold text-gray-700 uppercase tracking-wide">
                                    @if($reseau == 'BUS')
                                        🚍 Réseau BUS
                                    @elseif($reseau == 'FERRE')
                                        🚆 Réseau FERRÉ
                                    @else
                                        📁 {{ $reseau }}
                                    @endif
                                </h3>
                                <span class="text-xs font-semibold bg-gray-200 text-gray-600 px-2 py-1 rounded-full">{{ count($tables) }} tables</span>
                            </div>
                            
                            <ul class="divide-y divide-gray-100">
                                @foreach ($tables as $table)
                                    <li class="px-6 py-4 hover:bg-gray-50 flex justify-between items-center">
                                        <div>
                                            <a href="{{ route('consultation.show', $table['name']) }}" class="text-sm font-medium text-green-600 truncate hover:text-green-800 hover:underline" title="{{ $table['name'] }}">
                                                {{ $table['name'] }}
                                            </a>
                                            <p class="text-xs text-gray-500 mt-0.5">
                                                Programme : <span class="font-semibold text-gray-700">{{ $table['programme'] }}</span>
                                            </p>
                                        </div>
                                        <div class="text-right flex items-center space-x-4">
                                            <div>
                                                <span class="block text-sm font-bold text-gray-800">{{ number_format($table['count'], 0, ',', ' ') }}</span>
                                                <span class="text-xs text-gray-400">lignes</span>
                                            </div>
                                            @if(Auth::user()->profile === 'admin')
                                            <form action="{{ route('consultation.destroy', $table['name']) }}" method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer la table {{ $table['name'] }} ? Cette action est irréversible.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-400 hover:text-red-600 p-1" title="Supprimer la table">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </form>
                                            @endif
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    @else
        <div class="bg-white shadow rounded-lg p-12 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">Aucune donnée</h3>
            <p class="mt-1 text-sm text-gray-500">Aucune table de données trouvée. Commencez par importer un fichier.</p>
            <div class="mt-6">
                <a href="{{ route('import.form') }}" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                    </svg>
                    Nouvel Import
                </a>
            </div>
        </div>
    @endif
</div>
@endsection
