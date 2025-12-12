@extends('layouts.app')

@section('content')
<div class="container mx-auto">
    <div class="flex flex-col md:flex-row justify-between items-center mb-6">
        <div>
            <a href="{{ route('consultation.index') }}" class="text-green-600 hover:text-green-800 text-sm font-semibold mb-2 inline-block">&larr; Retour à la liste</a>
            <h1 class="text-3xl font-bold text-gray-800">{{ $tableName }}</h1>
            <p class="text-gray-500 text-sm mt-1">Affichage du contenu de la table</p>
        </div>
        
        <div class="mt-4 md:mt-0">
             <a href="{{ route('consultation.export', array_merge(['tableName' => $tableName], request()->all())) }}" class="bg-yellow-500 text-white px-4 py-2 rounded-md hover:bg-yellow-600 text-sm font-medium transition-colors flex items-center shadow-sm">
                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 011.414.586l4 4a1 1 0 01.586 1.414V19a2 2 0 01-2 2z"></path>
                </svg>
                Exporter Filtré
            </a>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="bg-white shadow-sm rounded-lg border border-gray-200 mb-6 p-4">
        <form action="{{ route('consultation.show', $tableName) }}" method="GET">
            <div class="grid grid-cols-1 md:grid-cols-6 gap-6">
                <!-- Common Columns -->
                @foreach(['ARTICLE', 'DESIGNATION', 'INITIAL','FINALE','VALEUR','PUMP'] as $col)
                    @if(in_array($col, $columns))
                        <div>
                            <label for="filter_{{ $col }}" class="block text-sm font-medium text-gray-700 mb-1">{{ ucfirst(strtolower($col)) }}</label>
                            <input type="text" name="filters[{{ $col }}]" id="filter_{{ $col }}" value="{{ request('filters.'.$col) }}" class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50 text-sm">
                        </div>
                    @endif
                @endforeach
                
                <!-- Group By Option - Only for GD tables -->
                @if($isGdTable && in_array('ARTICLE', $columns))
                <div class="flex items-end pb-2">
                    <label class="inline-flex items-center">
                        <input type="checkbox" name="group_by_article" value="1" {{ request('group_by_article') ? 'checked' : '' }} class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">
                        <span class="ml-2 text-sm text-gray-700 font-medium">Grouper par Article (Sommes)</span>
                    </label>
                </div>
                @endif

                <div class="flex items-end">
                    <button type="submit" class="w-full bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 text-sm font-medium transition-colors">
                        Appliquer les filtres
                    </button>
                    @if(request()->has('filters') || request()->has('group_by_article'))
                        <a href="{{ route('consultation.show', $tableName) }}" class="ml-2 text-gray-500 hover:text-gray-700 text-sm underline self-center">Reset</a>
                    @endif
                </div>
            </div>
        </form>
    </div>

    <div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                         @if(request('group_by_article') && $isGdTable)
                             <!-- When grouped, we only show specific columns -->
                             <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">ARTICLE</th>
                             @if(in_array('DESIGNATION', $columns)) <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">DESIGNATION</th> @endif
                             @foreach(['INITIAL', 'ENTREE', 'SORTIE', 'FINALE', 'PUMP', 'VALEUR'] as $col)
                                @if(in_array($col, $columns))
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">{{ $col }}</th>
                                @endif
                             @endforeach
                         @else
                            @foreach ($columns as $column)
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                    {{ $column }}
                                </th>
                            @endforeach
                        @endif
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse ($rows as $row)
                        <tr class="hover:bg-gray-50 transition-colors">
                            @if(request('group_by_article') && $isGdTable)
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-green-600">{{ $row->ARTICLE }}</td>
                                @if(in_array('DESIGNATION', $columns)) <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $row->DESIGNATION }}</td> @endif
                                
                                @foreach(['INITIAL', 'ENTREE', 'SORTIE', 'FINALE', 'PUMP', 'VALEUR'] as $col)
                                    @if(in_array($col, $columns))
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ is_numeric($row->$col) ? number_format($row->$col, 2, ',', ' ') : $row->$col }}
                                        </td>
                                    @endif
                                @endforeach
                            @else
                                @foreach ($columns as $column)
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $row->$column }}
                                    </td>
                                @endforeach
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columns) }}" class="px-6 py-10 text-center text-gray-500 font-medium">
                                Aucune donnée trouvée.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
            {{ $rows->links() }}
        </div>
    </div>
</div>
@endsection
