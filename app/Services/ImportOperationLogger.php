<?php

namespace App\Services;

use App\Models\ImportOperationLog;
use Throwable;

class ImportOperationLogger
{
    public function log(array $payload): void
    {
        try {
            ImportOperationLog::create([
                'run_id' => (string) ($payload['run_id'] ?? ''),
                'table_name' => $payload['table_name'] ?? null,
                'operation' => (string) ($payload['operation'] ?? 'unknown'),
                'status' => (string) ($payload['status'] ?? 'success'),
                'user_id' => $payload['user_id'] ?? null,
                'user_matricule' => $payload['user_matricule'] ?? null,
                'user_name' => $payload['user_name'] ?? null,
                'ip_address' => $payload['ip_address'] ?? null,
                'file_path' => $payload['file_path'] ?? null,
                'file_size_bytes' => $payload['file_size_bytes'] ?? null,
                'message' => $payload['message'] ?? null,
                'context' => is_array($payload['context'] ?? null) ? $payload['context'] : null,
            ]);
        } catch (Throwable) {
            // Fail silently: logs must not break import pipeline.
        }
    }
}

