@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4">
    <div class="mb-6 flex justify-between items-center">
        <div>
            <a href="{{ route('audit.index') }}" class="text-blue-600 hover:text-blue-800 font-semibold">&larr; Retour</a>
            <h1 class="text-2xl font-bold text-gray-800 mt-2">Résultats de l'Audit : {{ $reseau }} {{ $annee }} ({{ ucfirst($type) }})</h1>
            <p class="text-sm text-gray-500">Comparaison entre <span class="font-mono text-gray-700">{{ $efTable }}</span> et <span class="font-mono text-gray-700">{{ $gdTable }}</span></p>
        </div>
        <div class="flex items-center space-x-4">
            <a href="{{ route('audit.export', ['annee' => $annee, 'reseau' => $reseau, 'type' => $type]) }}" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded shadow transition">
                Export Excel
            </a>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-right">
                <span class="block text-sm text-gray-500 uppercase font-bold tracking-wider">Total Écart</span>
                <span class="block text-3xl font-bold {{ $totalEcart != 0 ? 'text-red-600' : 'text-green-600' }}">
                    {{ number_format($totalEcart, 3, ',', ' ') }}
                </span>
            </div>
        </div>
    </div>

    <!-- Summary Stats could go here -->
    
    <div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-xs md:text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Article</th>
                        <th class="px-3 py-3 text-left font-medium text-gray-500 uppercase tracking-wider w-1/4">Désignation</th>
                        
                        @if ($type === 'valeur')
                            <th class="px-2 py-3 text-right font-medium text-blue-600 bg-blue-50 uppercase tracking-wider border-l">EF Finale</th>
                            <th class="px-2 py-3 text-right font-medium text-blue-600 bg-blue-50 uppercase tracking-wider">EF Valeur</th>
                            <th class="px-2 py-3 text-right font-medium text-green-600 bg-green-50 uppercase tracking-wider border-l">GD Finale</th>
                            <th class="px-2 py-3 text-right font-medium text-green-600 bg-green-50 uppercase tracking-wider">GD Valeur</th>
                        @else
                            <th class="px-2 py-3 text-right font-medium text-blue-600 bg-blue-50 uppercase tracking-wider border-l">EF {{ ucfirst($type) }}</th>
                            <th class="px-2 py-3 text-right font-medium text-green-600 bg-green-50 uppercase tracking-wider border-l">GD {{ ucfirst($type) }} (Calc)</th>
                        @endif

                        <th class="px-3 py-3 text-right font-bold text-red-600 bg-red-50 uppercase tracking-wider border-l">Ecart</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse ($results as $row)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-3 py-2 whitespace-nowrap font-mono text-gray-900">{{ $row->ARTICLE }}</td>
                            <td class="px-3 py-2 text-gray-700 truncate max-w-xs" title="{{ $row->designation }}">{{ $row->designation }}</td>
                            
                            @if ($type === 'valeur')
                                <td class="px-2 py-2 whitespace-nowrap text-right text-blue-800 bg-blue-50/30 border-l">{{ number_format($row->ef_finale ?? 0, 3, ',', ' ') }}</td>
                                <td class="px-2 py-2 whitespace-nowrap text-right text-blue-800 bg-blue-50/30 font-medium">{{ number_format($row->val_ef, 3, ',', ' ') }}</td>
                                <td class="px-2 py-2 whitespace-nowrap text-right text-green-800 bg-green-50/30 border-l">{{ number_format($row->gd_finale ?? 0, 3, ',', ' ') }}</td>
                                <td class="px-2 py-2 whitespace-nowrap text-right text-green-800 bg-green-50/30 font-medium">{{ number_format($row->val_gd, 3, ',', ' ') }}</td>
                            @else
                                <td class="px-2 py-2 whitespace-nowrap text-right text-blue-800 bg-blue-50/30 border-l font-medium">{{ number_format($row->val_ef, 3, ',', ' ') }}</td>
                                <td class="px-2 py-2 whitespace-nowrap text-right text-green-800 bg-green-50/30 border-l font-medium">{{ number_format($row->val_gd, 3, ',', ' ') }}</td>
                            @endif

                            <td class="px-3 py-2 whitespace-nowrap text-right font-bold {{ abs($row->ecart) > 0.001 ? 'text-white bg-red-500' : 'text-red-600 bg-red-50' }} border-l">
                                {{ number_format($row->ecart, 3, ',', ' ') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $type === 'valeur' ? 7 : 5 }}" class="px-6 py-10 text-center text-gray-500 font-medium bg-green-50 text-green-700">
                                <svg class="w-12 h-12 mx-auto mb-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                Aucun écart significatif trouvé ! Les tables correspondent parfaitement.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
