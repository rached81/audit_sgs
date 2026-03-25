@extends('layouts.app')

@section('content')
    <div class="bg-white p-6 rounded-lg shadow">
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Archive</h1>
                <p class="text-sm text-gray-500 mt-1">
                    Consultation des fichiers originaux uploadés (conservés uniquement si le mode debug import est activé).
                </p>
            </div>

            <form method="GET" action="{{ route('archive.index') }}" class="flex items-end gap-2">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Table</label>
                    <select name="table" class="border rounded px-3 py-2 text-sm w-64">
                        <option value="">Toutes</option>
                        @foreach($tables as $t)
                            <option value="{{ $t }}" {{ $table_filter === $t ? 'selected' : '' }}>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="bg-green-700 text-white px-4 py-2 rounded text-sm hover:bg-green-600">Filtrer</button>
                <a href="{{ route('archive.index') }}" class="text-sm text-gray-600 hover:text-gray-900 px-2 py-2">Réinitialiser</a>
            </form>
        </div>

        <div class="mt-6">
            @if(empty($entries))
                <div class="text-sm text-gray-600 bg-gray-50 border border-gray-200 rounded p-4">
                    Aucun fichier original trouvé.
                </div>
            @else
                <div class="space-y-4">
                    @foreach($entries as $e)
                        <div class="border rounded-lg p-4">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                                <div class="font-semibold text-gray-800">
                                    {{ $e['table'] }}
                                    <span class="text-gray-400 font-normal">/</span>
                                    <span class="text-gray-600 font-mono text-sm">{{ $e['run_id'] }}</span>
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ count($e['originals']) }} fichier(s)
                                </div>
                            </div>

                            <div class="mt-3 overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead>
                                        <tr class="text-left text-gray-500 border-b">
                                            <th class="py-2 pr-4">Fichier</th>
                                            <th class="py-2 pr-4">Taille</th>
                                            <th class="py-2 pr-4">Dernière modification</th>
                                            <th class="py-2 pr-4"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y">
                                        @foreach($e['originals'] as $f)
                                            <tr>
                                                <td class="py-2 pr-4 font-mono text-xs text-gray-700">{{ $f['name'] }}</td>
                                                <td class="py-2 pr-4 text-gray-700">{{ number_format(($f['size'] ?? 0) / 1024, 2) }} KB</td>
                                                <td class="py-2 pr-4 text-gray-700">
                                                    {{ $f['last_modified'] ? \Carbon\Carbon::createFromTimestamp($f['last_modified'])->format('Y-m-d H:i:s') : '' }}
                                                </td>
                                                <td class="py-2 pr-4 text-right">
                                                    <a href="{{ $f['download_route'] }}"
                                                       class="inline-flex items-center bg-green-700 text-white px-3 py-1 rounded text-xs hover:bg-green-600">
                                                        Télécharger
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection

