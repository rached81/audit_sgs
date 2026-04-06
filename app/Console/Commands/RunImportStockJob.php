<?php

namespace App\Console\Commands;

use App\Jobs\ImportStockJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RunImportStockJob extends Command
{
    protected $signature = 'stock:run-import-job
                            {--full-path= : Absolute source file path}
                            {--relative-path= : Relative storage source file path}
                            {--table= : Target table name}
                            {--heading-row=1 : Header row index}
                            {--run-id= : Run identifier}
                            {--mapping= : Base64 encoded JSON mapping}
                            {--initiator-id= : User id who started import}
                            {--initiator-matricule= : Matricule who started import}
                            {--initiator-name= : Name who started import}
                            {--initiator-ip= : IP address who started import}';

    protected $description = 'Run stock import job in a standalone process (no queue worker)';

    public function handle(): int
    {
        $fullPath = (string) $this->option('full-path');
        $relativePath = (string) $this->option('relative-path');
        $tableName = (string) $this->option('table');
        $headingRow = (int) $this->option('heading-row');
        $runId = (string) $this->option('run-id');
        $mappingBase64 = (string) $this->option('mapping');
        $initiatorIdRaw = trim((string) $this->option('initiator-id'));
        $initiatorId = $initiatorIdRaw !== '' ? (int) $initiatorIdRaw : null;
        $initiatorMatricule = trim((string) $this->option('initiator-matricule'));
        $initiatorName = trim((string) $this->option('initiator-name'));
        $initiatorIp = trim((string) $this->option('initiator-ip'));

        $mapping = [];
        if ($mappingBase64 !== '') {
            $json = base64_decode($mappingBase64, true);
            if ($json !== false) {
                $decoded = json_decode($json, true);
                if (is_array($decoded)) {
                    $mapping = $decoded;
                }
            }
        }

        if ($fullPath === '' || $tableName === '' || $runId === '' || $mapping === []) {
            Log::channel('import')->error('import.command.run_job.invalid_arguments', [
                'full_path_present' => $fullPath !== '',
                'table_present' => $tableName !== '',
                'run_id_present' => $runId !== '',
                'mapping_count' => count($mapping),
            ]);
            if ($tableName !== '') {
                Cache::put("import_error_{$tableName}", 'Arguments invalides pour le job d\'import.', 3600);
                Cache::put("import_status_{$tableName}", 'failed', 3600);
                Cache::put("import_done_{$tableName}", false, 3600);
            }
            return self::FAILURE;
        }

        try {
            Cache::put("import_spawn_ack_{$runId}", [
                'started_at' => time(),
                'pid' => function_exists('getmypid') ? getmypid() : null,
            ], 600);

            Log::channel('import')->info('import.command.run_job.started', [
                'run_id' => $runId,
                'table' => $tableName,
                'heading_row' => $headingRow,
                'full_path' => $fullPath,
                'relative_path' => $relativePath,
            ]);

            (new ImportStockJob(
                $fullPath,
                $relativePath !== '' ? $relativePath : null,
                $tableName,
                $mapping,
                $headingRow,
                $runId,
                $initiatorId,
                $initiatorMatricule,
                $initiatorName,
                $initiatorIp
            ))->handle();

            Log::channel('import')->info('import.command.run_job.finished', [
                'run_id' => $runId,
                'table' => $tableName,
            ]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Cache::put("import_error_{$tableName}", $e->getMessage(), 3600);
            Cache::put("import_status_{$tableName}", 'failed', 3600);
            Cache::put("import_done_{$tableName}", false, 3600);
            Log::channel('import')->error('import.command.run_job.failed', [
                'run_id' => $runId,
                'table' => $tableName,
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            return self::FAILURE;
        }
    }
}

