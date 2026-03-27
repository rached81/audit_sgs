@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 mt-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Logs detailles des operations d'import</h1>
    </div>

    <form method="GET" class="bg-white border border-gray-200 rounded-lg p-4 mb-4 grid grid-cols-1 md:grid-cols-4 gap-3">
        <input type="text" name="run_id" value="{{ $run_id }}" placeholder="Run ID" class="px-3 py-2 border rounded-md">
        <input type="text" name="table" value="{{ $table }}" placeholder="Table" class="px-3 py-2 border rounded-md">
        <input type="text" name="operation" value="{{ $operation }}" placeholder="Operation" class="px-3 py-2 border rounded-md">
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-2 rounded-md">Filtrer</button>
    </form>

    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left">Date/heure</th>
                    <th class="px-3 py-2 text-left">Run ID</th>
                    <th class="px-3 py-2 text-left">Table</th>
                    <th class="px-3 py-2 text-left">Operation</th>
                    <th class="px-3 py-2 text-left">Statut</th>
                    <th class="px-3 py-2 text-left">Utilisateur</th>
                    <th class="px-3 py-2 text-left">IP</th>
                    <th class="px-3 py-2 text-left">Fichier</th>
                    <th class="px-3 py-2 text-left">Taille</th>
                    <th class="px-3 py-2 text-left">Message</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            @forelse($logs as $log)
                <tr>
                    <td class="px-3 py-2 whitespace-nowrap">{{ optional($log->created_at)->format('Y-m-d H:i:s') }}</td>
                    <td class="px-3 py-2 font-mono text-xs">{{ $log->run_id }}</td>
                    <td class="px-3 py-2">{{ $log->table_name }}</td>
                    <td class="px-3 py-2 font-semibold">{{ $log->operation }}</td>
                    <td class="px-3 py-2">
                        <span class="px-2 py-0.5 rounded text-xs {{ $log->status === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                            {{ $log->status }}
                        </span>
                    </td>
                    <td class="px-3 py-2">{{ $log->user_name }} ({{ $log->user_matricule }})</td>
                    <td class="px-3 py-2 font-mono text-xs">{{ $log->ip_address }}</td>
                    <td class="px-3 py-2 font-mono text-xs">{{ $log->file_path }}</td>
                    <td class="px-3 py-2">{{ $log->file_size_bytes }}</td>
                    <td class="px-3 py-2">{{ $log->message }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="px-3 py-6 text-center text-gray-500">Aucun log trouve.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $logs->links() }}
    </div>
</div>
@endsection

