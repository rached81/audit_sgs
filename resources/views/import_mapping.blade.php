@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-4 text-indigo-700">Vérification du Mappage des Colonnes</h1>
    
    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-yellow-700">
                    Les entêtes de votre fichier ne correspondent pas exactement au standard. 
                    Notre assistant intelligent a suggéré des correspondances. Merci de les vérifier.
                </p>
            </div>
        </div>
    </div>

    <form action="{{ route('import.process_mapping') }}" method="POST" class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
        @csrf
        <input type="hidden" name="file_path" value="{{ $file_path }}">
        <input type="hidden" name="table_name" value="{{ $table_name }}">
        <input type="hidden" name="heading_row" value="{{ $heading_row }}">
        
        <!-- Pass original params implicitly via table naming, or if logic needs them later? 
             The controller processMappedImport only needs file and table name usually, 
             but if table creation happens then, we rely on table name parsing or existing logic.
             Controller uses 'table_name' directly. -->

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
            @foreach($required_columns as $col)
            <div class="border rounded-lg p-6 shadow-sm @if(($analysis['confidence'][$col] ?? 0) < 80) bg-red-50 border-red-200 @else bg-green-50 border-green-200 @endif">
                <div class="flex items-center justify-between gap-4 mb-2">
                    <label class="text-gray-700 text-sm font-bold uppercase w-1/3 text-right">
                        {{ $col }}
                    </label>

                    <svg class="w-6 h-6 text-indigo-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
                    </svg>
                    
                    <div class="flex-1">
                        <select name="mapping[{{ $col }}]" class="block appearance-none w-full bg-white border border-gray-400 hover:border-gray-500 px-4 py-2 pr-8 rounded shadow leading-tight focus:outline-none focus:shadow-outline">
                            <option value="">-- Ignorer / Non trouvé --</option>
                            @foreach($analysis['file_headers'] as $header)
                                <option value="{{ $header }}" 
                                    @if(
                                        (isset($analysis['mapping'][$col]) && $analysis['mapping'][$col] === $header)
                                    ) selected @endif
                                >
                                    {{ $header }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                
                <div class="text-xs text-center text-gray-500 mt-2">
                    Confiance IA: 
                    <span class="font-bold @if(($analysis['confidence'][$col] ?? 0) > 90) text-green-600 @elseif(($analysis['confidence'][$col] ?? 0) > 60) text-yellow-600 @else text-red-600 @endif">
                        {{ $analysis['confidence'][$col] ?? 0 }}%
                    </span>
                </div>
            </div>
            @endforeach
        </div>

        <div class="flex items-center justify-between">
            <a href="{{ route('import.form') }}" class="text-gray-600 hover:text-gray-800 font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Annuler
            </a>
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded focus:outline-none focus:shadow-outline transition duration-300">
                Confirmer l'Importation
            </button>
        </div>
    </form>
</div>
@endsection
