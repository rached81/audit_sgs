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

    <!-- Filters Section - Separated Panel -->
    <div class="filter-section transition-all duration-300 ring-1 ring-gray-900/5">
        <div class="filter-header">
            <h2 class="text-xs font-bold text-gray-700 flex items-center uppercase tracking-tighter">
                <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                Filtres
            </h2>
            @if(request()->has('filters') || request()->has('group_by_article'))
                <a href="{{ route('consultation.show', $tableName) }}" class="text-[10px] text-red-500 hover:text-red-700 font-bold flex items-center uppercase">
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    Reset
                </a>
            @endif
        </div>
        <div class="filter-body">
            <form action="{{ route('consultation.show', $tableName) }}" method="GET" id="filterForm">
                <!-- Grid changed to 3 columns for balanced 2 rows (6 items) -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Text Filters -->
                    @foreach(['ARTICLE', 'DESIGNATION'] as $col)
                        @if(in_array($col, $columns))
                            <div class="space-y-1">
                                <label for="filter_{{ $col }}" class="block text-[10px] font-bold text-gray-500 uppercase">{{ $col }}</label>
                                <div class="relative">
                                    <input type="text" name="filters[{{ $col }}]" id="filter_{{ $col }}" value="{{ request('filters.'.$col) }}"
                                        placeholder="Filtrer..."
                                        class="input-compact w-full pl-8">
                                    <div class="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endforeach

                    <!-- Numeric Filters with Operators -->
                    @php
                        $numericCols = ['INITIAL',  'FINALE', 'PUMP', 'VALEUR'];
                    @endphp
                    @foreach($numericCols as $col)
                        @if(in_array($col, $columns))
                            <div class="space-y-1">
                                <label for="filter_{{ $col }}" class="block text-[10px] font-bold text-gray-500 uppercase">{{ $col }}</label>
                                <div class="flex shadow-sm rounded-md overflow-hidden">
                                    <select name="operators[{{ $col }}]" class="h-9 rounded-l-md border-r-0 border-gray-300 bg-gray-50 text-gray-600 text-[10px] font-bold focus:ring-green-500 focus:border-green-500 transition-all px-1">
                                        @foreach(['=' => '=', '>' => '>', '<' => '<', '>=' => '≥', '<=' => '≤', '!=' => '≠'] as $val => $label)
                                            <option value="{{ $val }}" {{ request('operators.'.$col) == $val ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <input type="number" step="any" name="filters[{{ $col }}]" id="filter_{{ $col }}" value="{{ request('filters.'.$col) }}"
                                        class="input-compact flex-1 min-w-0 block w-full rounded-none rounded-r-md border-l-0 px-2"
                                        placeholder="0.00">
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>

                <div class="mt-4 pt-4 border-t border-gray-100 flex flex-col sm:flex-row justify-between items-center gap-4">
                    <div class="w-full sm:w-auto">
                        @if($isGdTable && in_array('ARTICLE', $columns))
                        <label class="inline-flex items-center group cursor-pointer">
                            <input type="checkbox" name="group_by_article" value="1" {{ request('group_by_article') ? 'checked' : '' }}
                                class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-500 focus:ring-green-500 h-4 w-4 transition-all">
                            <span class="ml-2.5 text-xs text-gray-600 font-semibold group-hover:text-green-600 transition-colors uppercase tracking-tight">Grouper par Article</span>
                        </label>
                        @endif
                    </div>

                    <div class="flex gap-2 w-full sm:w-auto justify-end">
                        <button type="submit" class="w-full sm:w-auto justify-center bg-green-600 text-white px-6 py-2.5 rounded-full hover:bg-green-700 text-xs font-bold transition-all shadow-md hover:shadow-lg flex items-center uppercase tracking-wider">
                            <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            Appliquer les filtres
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
<div class="bg-white shadow-sm rounded-xl overflow-hidden ring-1 ring-gray-900/5">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 table-compact">
            <thead class="sticky top-0 z-10">
    <!-- <div class="bg-white shadow-sm rounded-lg overflow-hidden border border-gray-200">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 table-compact">
                <thead> -->
                    <tr>
                         @if(request('group_by_article') && $isGdTable)
                             <!-- When grouped, we only show specific columns -->
                             <th scope="col">ARTICLE</th>
                             @if(in_array('DESIGNATION', $columns)) <th scope="col">DESIGNATION</th> @endif
                             @foreach(['INITIAL', 'ENTREE', 'SORTIE', 'FINALE', 'PUMP', 'VALEUR'] as $col)
                                @if(in_array($col, $columns))
                                    <th scope="col">{{ $col }}</th>
                                @endif
                             @endforeach
                         @else
                            @foreach ($columns as $column)
                                <th scope="col">
                                    {{ $column }}
                                </th>
                            @endforeach
                        @endif
                    </tr>
                </thead>
                <!-- <tbody class="bg-white divide-y divide-gray-100"> -->
                    <tbody class="divide-y divide-gray-100">
                    @forelse ($rows as $row)
                        <tr>
                            @if(request('group_by_article') && $isGdTable)
                                <td class="font-bold text-green-700">{{ $row->ARTICLE }}</td>
                                @if(in_array('DESIGNATION', $columns)) <td>{{ $row->DESIGNATION }}</td> @endif

                                @foreach(['INITIAL', 'ENTREE', 'SORTIE', 'FINALE', 'PUMP', 'VALEUR'] as $col)
                                    @if(in_array($col, $columns))
                                        <td class="col-numeric {{ $row->$col < 0 ? 'text-red-600' : '' }}">
                                            {{ is_numeric($row->$col) ? number_format($row->$col, 2, ',', ' ') : $row->$col }}
                                        </td>
                                    @endif
                                @endforeach
                            @else
                                @foreach ($columns as $column)
                                    <td class="{{ is_numeric($row->$column) ? 'col-numeric' : '' }}">
                                        {{ $row->$column }}
                                    </td>
                                @endforeach
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columns) }}" class="px-6 py-10 text-center text-gray-400 italic">
                                Aucune donnée trouvée.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
            </div>
        </div>
        <!-- <div class="px-4 py-3 bg-gray-50 border-t border-gray-200"> -->
             <div class="px-4 py-3 bg-gray-50 border-t border-gray-200">
            {{ $rows->links() }}
        </div>
    </div>
</div>
@endsection
