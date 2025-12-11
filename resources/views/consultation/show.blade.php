@extends('layouts.app')

@section('content')
<div class="container mx-auto">
    <div class="flex flex-col md:flex-row justify-between items-center mb-6">
        <div>
            <a href="{{ route('consultation.index') }}" class="text-indigo-600 hover:text-indigo-800 text-sm font-semibold mb-2 inline-block">&larr; Retour à la liste</a>
            <h1 class="text-3xl font-bold text-gray-800">{{ $tableName }}</h1>
            <p class="text-gray-500 text-sm mt-1">Affichage du contenu de la table</p>
        </div>
        <div class="flex items-center space-x-3 mt-4 md:mt-0">
             <form action="{{ route('consultation.show', $tableName) }}" method="GET" class="flex items-center">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Rechercher..." class="rounded-l-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 text-sm">
                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-r-md hover:bg-indigo-700 text-sm font-medium transition-colors">
                    Rechercher
                </button>
             </form>
             <a href="{{ route('consultation.export', ['tableName' => $tableName, 'search' => request('search')]) }}" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 text-sm font-medium transition-colors flex items-center shadow-sm">
                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 011.414.586l4 4a1 1 0 01.586 1.414V19a2 2 0 01-2 2z"></path>
                </svg>
                Excel
            </a>
        </div>
    </div>

    <div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        @foreach ($columns as $column)
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                {{ $column }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse ($rows as $row)
                        <tr class="hover:bg-gray-50 transition-colors">
                            @foreach ($columns as $column)
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $row->$column }}
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columns) }}" class="px-6 py-10 text-center text-gray-500 font-medium">
                                Aucune donnée trouvée{{ request('search') ? ' pour la recherche "'.request('search').'"' : '' }}.
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
