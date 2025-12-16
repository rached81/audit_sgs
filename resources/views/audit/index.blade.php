@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 mt-6">
    
    <!-- Inline Audit Form -->
    <div class="bg-white shadow-md rounded-lg p-6 border border-gray-200 mb-8">
        <h1 class="text-2xl font-bold text-gray-800 mb-4 border-b pb-2">Audit des Stocks</h1>
        
        @if ($errors->any())
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded" role="alert">
                <p class="font-bold">Erreur</p>
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form action="{{ route('audit.compare') }}" method="POST" class="flex flex-col md:flex-row md:items-end gap-4">
            @csrf
            
            <div class="w-full md:w-32">
                <label for="annee" class="block text-gray-700 text-sm font-bold mb-2">Année</label>
                <input type="number" name="annee" id="annee" 
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500" 
                       placeholder="2018" required min="2000" max="2100" 
                       value="{{ old('annee', $annee ?? date('Y')-1) }}">
            </div>

            <div class="w-full md:w-40">
                <label for="reseau" class="block text-gray-700 text-sm font-bold mb-2">Réseau</label>
                <select name="reseau" id="reseau" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
                    <option value="BUS" {{ (old('reseau', $reseau ?? '') == 'BUS') ? 'selected' : '' }}>BUS</option>
                    <option value="FERRE" {{ (old('reseau', $reseau ?? '') == 'FERRE') ? 'selected' : '' }}>FERRÉ</option>
                </select>
            </div>

            <div class="flex-grow">
                <label class="block text-gray-700 text-sm font-bold mb-2">Lancer une comparaison :</label>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" name="type" value="valeur" 
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded shadow focus:outline-none focus:ring-2 focus:ring-blue-500 transition {{ ($type ?? 'valeur') == 'valeur' ? 'ring-2 ring-offset-2 ring-blue-600' : '' }}">
                        ⚖️ Finale & Valeur (Standard)
                    </button>
                    <button type="submit" name="type" value="initial" 
                            class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded shadow focus:outline-none focus:ring-2 focus:ring-indigo-500 transition {{ ($type ?? '') == 'initial' ? 'ring-2 ring-offset-2 ring-indigo-600' : '' }}">
                        ⏮️ Stock Initial (Valeur)
                    </button>
                    <button type="submit" name="type" value="pump" 
                            class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded shadow focus:outline-none focus:ring-2 focus:ring-purple-500 transition {{ ($type ?? '') == 'pump' ? 'ring-2 ring-offset-2 ring-purple-600' : '' }}">
                        🏷️ P.U.M.P
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Results Section -->
    @if(isset($results))
        <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h2 class="text-xl font-bold text-gray-800">
                    Résultats : {{ $reseau }} {{ $annee }} <span class="text-gray-500 text-base font-normal">({{ ucfirst($type) }})</span>
                </h2>
                <p class="text-sm text-gray-500">
                    <span class="font-mono">{{ $efTable }}</span> (EF) vs <span class="font-mono">{{ $gdTable }}</span> (GD)
                </p>
            </div>

            <div class="flex items-center space-x-4">
                <a href="{{ route('audit.export', ['annee' => $annee, 'reseau' => $reseau, 'type' => $type]) }}" 
                   class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded shadow transition flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                    Exporter Excel
                </a>
                
                <div class="bg-white border rounded-lg p-3 shadow-sm text-right min-w-[150px]">
                    <span class="block text-xs text-gray-500 uppercase font-bold tracking-wider">Total Écart</span>
                    <span class="block text-xl font-bold {{ $totalEcart != 0 ? 'text-red-600' : 'text-green-600' }}">
                        {{ number_format($totalEcart, 3, ',', ' ') }}
                    </span>
                </div>
            </div>
        </div>

        <div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-xs">
                    <thead class="bg-gray-50">
                        <tr>
                            <th rowspan="2" class="px-2 py-3 text-left font-medium text-gray-500 uppercase tracking-wider sticky left-0 bg-gray-50 z-10 border-r">Article</th>
                            <th rowspan="2" class="px-2 py-3 text-left font-medium text-gray-500 uppercase tracking-wider w-40 border-r">Désignation</th>
                            
                            <th colspan="6" class="px-2 py-1 text-center font-bold text-blue-700 bg-blue-100 border-r border-b">Etat Final (EF)</th>
                            <th colspan="6" class="px-2 py-1 text-center font-bold text-green-700 bg-green-100 border-r border-b">Générateur (GD)</th>

                            <th rowspan="2" class="px-2 py-3 text-right font-bold text-red-600 bg-red-50 uppercase tracking-wider sticky right-0 z-10 border-l">Ecart</th>
                        </tr>
                        <tr>
                            <!-- EF Sub-headers -->
                            <th class="px-1 py-1 text-right text-blue-600 bg-blue-50 font-medium border-r" title="Initial">Init</th>
                            <th class="px-1 py-1 text-right text-blue-600 bg-blue-50 font-medium border-r" title="Entrée">Ent</th>
                            <th class="px-1 py-1 text-right text-blue-600 bg-blue-50 font-medium border-r" title="Sortie">Sort</th>
                            <th class="px-1 py-1 text-right text-blue-600 bg-blue-50 font-medium border-r" title="Finale">Fin</th>
                            <th class="px-1 py-1 text-right text-blue-600 bg-blue-50 font-medium border-r" title="PUMP">PUMP</th>
                            <th class="px-1 py-1 text-right text-blue-800 bg-blue-100 font-bold border-r" title="Valeur">VAL</th>

                            <!-- GD Sub-headers -->
                            <th class="px-1 py-1 text-right text-green-600 bg-green-50 font-medium border-r" title="Initial">Init</th>
                            <th class="px-1 py-1 text-right text-green-600 bg-green-50 font-medium border-r" title="Entrée">Ent</th>
                            <th class="px-1 py-1 text-right text-green-600 bg-green-50 font-medium border-r" title="Sortie">Sort</th>
                            <th class="px-1 py-1 text-right text-green-600 bg-green-50 font-medium border-r" title="Finale">Fin</th>
                            <th class="px-1 py-1 text-right text-green-600 bg-green-50 font-medium border-r" title="PUMP">PUMP</th>
                            <th class="px-1 py-1 text-right text-green-800 bg-green-100 font-bold border-r" title="Valeur">VAL</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($results as $row)
                            <tr class="hover:bg-yellow-50 transition-colors">
                                <td class="px-2 py-1 whitespace-nowrap font-mono text-gray-900 border-r sticky left-0 bg-inherit font-bold">{{ $row->ARTICLE }}</td>
                                <td class="px-2 py-1 text-gray-700 truncate max-w-xs border-r" title="{{ $row->designation }}">{{ $row->designation }}</td>
                                
                                <!-- EF Data -->
                                <td class="px-1 py-1 text-right text-gray-600 border-r">{{ number_format($row->ef_initial, 0, ',', ' ') }}</td>
                                <td class="px-1 py-1 text-right text-gray-600 border-r">{{ number_format($row->ef_entree, 0, ',', ' ') }}</td>
                                <td class="px-1 py-1 text-right text-gray-600 border-r">{{ number_format($row->ef_sortie, 0, ',', ' ') }}</td>
                                <td class="px-1 py-1 text-right font-medium text-blue-700 bg-blue-50/20 border-r">{{ number_format($row->ef_finale, 0, ',', ' ') }}</td>
                                <td class="px-1 py-1 text-right text-gray-500 italic border-r text-[10px]">{{ number_format($row->ef_pump, 4, ',', ' ') }}</td>
                                <td class="px-1 py-1 text-right font-bold text-blue-800 bg-blue-50/50 border-r">{{ number_format($row->ef_valeur, 2, ',', ' ') }}</td>

                                <!-- GD Data -->
                                <td class="px-1 py-1 text-right text-gray-600 border-r">{{ number_format($row->gd_initial, 0, ',', ' ') }}</td>
                                <td class="px-1 py-1 text-right text-gray-600 border-r">{{ number_format($row->gd_entree, 0, ',', ' ') }}</td>
                                <td class="px-1 py-1 text-right text-gray-600 border-r">{{ number_format($row->gd_sortie, 0, ',', ' ') }}</td>
                                <td class="px-1 py-1 text-right font-medium text-green-700 bg-green-50/20 border-r">{{ number_format($row->gd_finale, 0, ',', ' ') }}</td>
                                <td class="px-1 py-1 text-right text-gray-500 italic border-r text-[10px]">{{ number_format($row->gd_pump, 4, ',', ' ') }}</td>
                                <td class="px-1 py-1 text-right font-bold text-green-800 bg-green-50/50 border-r">{{ number_format($row->gd_valeur, 2, ',', ' ') }}</td>

                                <!-- Ecart -->
                                <td class="px-2 py-1 text-right font-bold sticky right-0 z-10 border-l {{ abs($row->ecart) > 0.001 ? 'text-white bg-red-500' : 'text-gray-400 bg-gray-50' }}">
                                    {{ number_format($row->ecart, 3, ',', ' ') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="15" class="px-6 py-10 text-center text-gray-500 font-medium bg-green-50 text-green-700">
                                    <svg class="w-12 h-12 mx-auto mb-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    Toutes les lignes correspondent aux critères d'exactitude !
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="bg-gray-50 px-4 py-2 text-xs text-gray-500 border-t">
                * Les quantités sont arrondies à l'unité pour l'affichage, mais les calculs utilisent toute la précision.
            </div>
        </div>
    @endif
</div>
@endsection
