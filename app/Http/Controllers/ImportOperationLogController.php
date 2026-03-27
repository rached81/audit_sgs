<?php

namespace App\Http\Controllers;

use App\Models\ImportOperationLog;
use Illuminate\Http\Request;

class ImportOperationLogController extends Controller
{
    public function index(Request $request)
    {
        $runId = trim((string) $request->query('run_id', ''));
        $table = trim((string) $request->query('table', ''));
        $operation = trim((string) $request->query('operation', ''));

        $query = ImportOperationLog::query()->orderByDesc('id');
        if ($runId !== '') {
            $query->where('run_id', $runId);
        }
        if ($table !== '') {
            $query->where('table_name', $table);
        }
        if ($operation !== '') {
            $query->where('operation', $operation);
        }

        $logs = $query->paginate(50)->withQueryString();

        return view('import_logs.index', [
            'logs' => $logs,
            'run_id' => $runId,
            'table' => $table,
            'operation' => $operation,
        ]);
    }
}

