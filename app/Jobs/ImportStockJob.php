<?php

namespace App\Jobs;

use App\Services\ImportOperationLogger;
use App\Services\StockCsvImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImportStockJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $fullPath;
    public $relativePath;
    public $tableName;
    public $mapping;
    public $headingRow;
    public $runId;
    public $initiatorId;
    public $initiatorMatricule;
    public $initiatorName;
    public $initiatorIp;

    public $timeout = 3600;

    public function __construct(
        $fullPath,
        $relativePath,
        $tableName,
        $mapping,
        $headingRow,
        $runId = null,
        $initiatorId = null,
        $initiatorMatricule = null,
        $initiatorName = null,
        $initiatorIp = null
    )
    {
        $this->fullPath = $fullPath;
        $this->relativePath = $relativePath;
        $this->tableName = $tableName;
        $this->mapping = $mapping;
        $this->headingRow = $headingRow;
        $this->runId = $runId ?: (string) str()->uuid();
        $this->initiatorId = $initiatorId;
        $this->initiatorMatricule = $initiatorMatricule;
        $this->initiatorName = $initiatorName;
        $this->initiatorIp = $initiatorIp;
    }

    public function handle()
    {
        @set_time_limit(0);

        $jobStart = microtime(true);
        $operationLogger = app(ImportOperationLogger::class);
        $normalized = null;
        $debugRelativeDir = null;
        $debugEnabled = (bool) config('import_perf.debug_enabled', false);
        $ttlSeconds = 3600;

        $this->putRunState([
            'run_id' => $this->runId,
            'table' => $this->tableName,
            'status' => 'running',
            'stage' => 'starting',
            'overall_percent' => 0,
            'stage_percent' => 0,
            'processed' => 0,
            'total' => 0,
            'eta_seconds' => null,
            'started_at' => time(),
            'updated_at' => time(),
        ], $ttlSeconds);
        $this->putTableState([
            'status' => 'running',
            'stage' => 'starting',
            'overall_percent' => 0,
            'stage_percent' => 0,
            'processed' => 0,
            'total' => 0,
            'eta_seconds' => null,
            'started_at' => time(),
            'updated_at' => time(),
        ], $ttlSeconds);

        if ($debugEnabled) {
            $debugRelativeDir = "import_debug/{$this->tableName}/{$this->runId}";
            Log::channel('import')->info('import.job.debug.enabled', [
                'run_id' => $this->runId,
                'table' => $this->tableName,
                'debug_relative_dir' => $debugRelativeDir,
                'source_relative_path' => $this->relativePath,
            ]);
        }

        Log::channel('import')->info('import.job.started', [
            'run_id' => $this->runId,
            'table' => $this->tableName,
            'heading_row' => $this->headingRow,
            'path' => $this->relativePath ?: $this->fullPath,
            'queue_connection' => config('queue.default'),
        ]);

        try {
            $importer = app(StockCsvImporter::class);

            $stepStart = microtime(true);
            $cleaningStartAt = microtime(true);
            $this->putRunState([
                'stage' => 'cleaning',
                'stage_percent' => 0,
                'overall_percent' => 0,
                'eta_seconds' => null,
            ], $ttlSeconds);
            $this->putTableState([
                'status' => 'running',
                'stage' => 'cleaning',
                'overall_percent' => 0,
                'stage_percent' => 0,
                'processed' => 0,
                'total' => 0,
                'eta_seconds' => null,
                'updated_at' => time(),
            ], $ttlSeconds);

            $normalized = $importer->normalizeToCsv(
                $this->fullPath,
                $this->mapping,
                $this->headingRow,
                $debugRelativeDir,
                function (array $p) use ($ttlSeconds, $cleaningStartAt): void {
                    $highest = (int) ($p['highest_row'] ?? 0);
                    $scanned = (int) ($p['scanned_rows'] ?? 0);
                    $stagePercent = $highest > 0 ? (int) min(100, round(($scanned / $highest) * 100)) : 0;

                    $elapsed = max(0.001, microtime(true) - $cleaningStartAt);
                    $rate = $scanned > 0 ? ($scanned / $elapsed) : 0.0;
                    $remaining = max(0, $highest - $scanned);
                    $eta = $rate > 0 ? (int) round($remaining / $rate) : null;

                    // Cleaning is roughly 20% of the overall progress.
                    $overallPercent = (int) min(20, round($stagePercent * 0.2));

                    $this->putRunState([
                        'status' => 'running',
                        'stage' => 'cleaning',
                        'stage_percent' => $stagePercent,
                        'overall_percent' => $overallPercent,
                        'processed' => (int) ($p['written_rows'] ?? 0),
                        'total' => 0,
                        'eta_seconds' => $eta,
                        'updated_at' => time(),
                    ], $ttlSeconds);
                    $this->putTableState([
                        'status' => 'running',
                        'stage' => 'cleaning',
                        'stage_percent' => $stagePercent,
                        'overall_percent' => $overallPercent,
                        'processed' => (int) ($p['written_rows'] ?? 0),
                        'total' => 0,
                        'eta_seconds' => $eta,
                        'updated_at' => time(),
                    ], $ttlSeconds);
                }
            );
            Log::channel('import')->info('import.job.step.normalize_to_csv.done', [
                'run_id' => $this->runId,
                'table' => $this->tableName,
                'csv_path' => $normalized['relative_csv_path'],
                'rows_ready' => $normalized['rows'],
                'duration_ms' => $this->toMs($stepStart),
            ]);

            $stepStart = microtime(true);
            Cache::put("import_total_{$this->tableName}", $normalized['rows'], 3600);
            Cache::put("import_processed_{$this->tableName}", 0, 3600);
            Cache::forget("import_error_{$this->tableName}");
            Cache::forget("import_done_{$this->tableName}");
            Cache::put("import_status_{$this->tableName}", 'running', 3600);
            Log::channel('import')->info('import.job.step.cache_init.done', [
                'run_id' => $this->runId,
                'table' => $this->tableName,
                'total' => $normalized['rows'],
                'duration_ms' => $this->toMs($stepStart),
            ]);

            $total = (int) ($normalized['rows'] ?? 0);
            $this->putRunState([
                'stage' => 'inserting',
                'stage_percent' => 0,
                'overall_percent' => 20,
                'processed' => 0,
                'total' => $total,
                'eta_seconds' => null,
                'inserting_started_at' => time(),
                'updated_at' => time(),
            ], $ttlSeconds);
            $this->putTableState([
                'status' => 'running',
                'stage' => 'inserting',
                'stage_percent' => 0,
                'overall_percent' => 20,
                'processed' => 0,
                'total' => $total,
                'eta_seconds' => null,
                'updated_at' => time(),
            ], $ttlSeconds);

            $stepStart = microtime(true);
            $insertingStartAt = microtime(true);
            $inserted = $importer->importNormalizedCsv(
                $normalized['csv_path'],
                $this->tableName,
                function (int $delta) use ($ttlSeconds, $insertingStartAt, $total): void {
                    Cache::increment("import_processed_{$this->tableName}", $delta);

                    $processed = (int) (Cache::get("import_processed_{$this->tableName}") ?? 0);
                    $stagePercent = $total > 0 ? (int) min(100, round(($processed / $total) * 100)) : 0;

                    $elapsed = max(0.001, microtime(true) - $insertingStartAt);
                    $rate = $processed > 0 ? ($processed / $elapsed) : 0.0;
                    $remaining = max(0, $total - $processed);
                    $eta = $rate > 0 ? (int) round($remaining / $rate) : null;

                    // Inserting is roughly 80% of the overall progress.
                    $overallPercent = 20 + (int) min(80, round(($stagePercent * 80) / 100));

                    $this->putRunState([
                        'status' => 'running',
                        'stage' => 'inserting',
                        'stage_percent' => $stagePercent,
                        'overall_percent' => min(99, $overallPercent),
                        'processed' => $processed,
                        'total' => $total,
                        'eta_seconds' => $eta,
                        'updated_at' => time(),
                    ], $ttlSeconds);
                    $this->putTableState([
                        'status' => 'running',
                        'stage' => 'inserting',
                        'stage_percent' => $stagePercent,
                        'overall_percent' => min(99, $overallPercent),
                        'processed' => $processed,
                        'total' => $total,
                        'eta_seconds' => $eta,
                        'updated_at' => time(),
                    ], $ttlSeconds);
                }
            );

            if ($debugRelativeDir) {
                try {
                    $result = [
                        'run_id' => $this->runId,
                        'table' => $this->tableName,
                        'rows_inserted_returned' => $inserted,
                        'import_processed_cache' => Cache::get("import_processed_{$this->tableName}"),
                        'import_total_cache' => Cache::get("import_total_{$this->tableName}"),
                    ];
                    Storage::put($debugRelativeDir . '/result.json', json_encode($result, JSON_UNESCAPED_SLASHES));
                } catch (\Throwable $e) {
                    Log::channel('import')->warning('import.job.debug.write_result.failed', [
                        'run_id' => $this->runId,
                        'table' => $this->tableName,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
            Log::channel('import')->info('import.job.step.sql_injection.done', [
                'run_id' => $this->runId,
                'table' => $this->tableName,
                'rows_inserted' => $inserted,
                'duration_ms' => $this->toMs($stepStart),
            ]);

            Cache::put("import_done_{$this->tableName}", true, 3600);
            Cache::put("import_status_{$this->tableName}", 'done', 3600);
            $this->putTableState([
                'status' => 'done',
                'stage' => 'done',
                'stage_percent' => 100,
                'overall_percent' => 100,
                'processed' => (int) (Cache::get("import_processed_{$this->tableName}") ?? 0),
                'total' => (int) (Cache::get("import_total_{$this->tableName}") ?? 0),
                'eta_seconds' => 0,
                'updated_at' => time(),
                'finished_at' => time(),
            ], $ttlSeconds);
            $this->putRunState([
                'status' => 'done',
                'stage' => 'done',
                'stage_percent' => 100,
                'overall_percent' => 100,
                'processed' => (int) (Cache::get("import_processed_{$this->tableName}") ?? 0),
                'total' => (int) (Cache::get("import_total_{$this->tableName}") ?? 0),
                'eta_seconds' => 0,
                'updated_at' => time(),
                'finished_at' => time(),
            ], $ttlSeconds);

            Log::channel('import')->info('import.job.finished', [
                'run_id' => $this->runId,
                'table' => $this->tableName,
                'processed' => Cache::get("import_processed_{$this->tableName}"),
                'total' => Cache::get("import_total_{$this->tableName}"),
                'status' => Cache::get("import_status_{$this->tableName}"),
                'duration_ms' => $this->toMs($jobStart),
            ]);
        } catch (\Exception $e) {
            Cache::put("import_error_{$this->tableName}", $e->getMessage(), 3600);
            Cache::put("import_status_{$this->tableName}", 'failed', 3600);
            Cache::put("import_done_{$this->tableName}", false, 3600);
            $this->putTableState([
                'status' => 'failed',
                'stage' => 'failed',
                'error' => $e->getMessage(),
                'eta_seconds' => null,
                'updated_at' => time(),
                'finished_at' => time(),
            ], $ttlSeconds);
            $this->putRunState([
                'status' => 'failed',
                'stage' => 'failed',
                'error' => $e->getMessage(),
                'updated_at' => time(),
                'finished_at' => time(),
            ], $ttlSeconds);
            Log::channel('import')->error('import.job.failed', [
                'run_id' => $this->runId,
                'table' => $this->tableName,
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'duration_ms' => $this->toMs($jobStart),
            ]);
            throw $e;
        } finally {
            // If debug is enabled, keep copies of the original + cleaned files.
            if ($debugRelativeDir) {
                if ($this->relativePath) {
                    try {
                        $dest = $debugRelativeDir . '/original_' . basename($this->relativePath);
                        Storage::copy($this->relativePath, $dest);
                    } catch (\Throwable $e) {
                        Log::channel('import')->warning('import.job.debug.copy.original.failed', [
                            'run_id' => $this->runId,
                            'table' => $this->tableName,
                            'message' => $e->getMessage(),
                        ]);
                    }
                }

                if (is_array($normalized) && !empty($normalized['relative_csv_path'])) {
                    try {
                        $dest = $debugRelativeDir . '/cleaned.csv';
                        Storage::copy($normalized['relative_csv_path'], $dest);
                    } catch (\Throwable $e) {
                        Log::channel('import')->warning('import.job.debug.copy.cleaned.failed', [
                            'run_id' => $this->runId,
                            'table' => $this->tableName,
                            'message' => $e->getMessage(),
                        ]);
                    }
                }

                // Keep only the latest archived runs for this table.
                try {
                    $keepRuns = (int) config('import_perf.debug_keep_runs_per_table', 1);
                    $this->pruneDebugRuns($this->tableName, $keepRuns);
                } catch (\Throwable $e) {
                    Log::channel('import')->warning('import.job.debug.prune.failed', [
                        'run_id' => $this->runId,
                        'table' => $this->tableName,
                        'message' => $e->getMessage(),
                    ]);
                }

                // In debug mode, we don't delete temp files to allow investigation.
                return;
            }

            if ($this->relativePath) {
                Log::channel('import')->info('import.job.cleanup.source_file', [
                    'run_id' => $this->runId,
                    'table' => $this->tableName,
                    'relative_path' => $this->relativePath,
                ]);
                $deleted = Storage::delete($this->relativePath);
                $operationLogger->log([
                    'run_id' => $this->runId,
                    'table_name' => $this->tableName,
                    'operation' => 'delete_source_file',
                    'status' => $deleted ? 'success' : 'failed',
                    'user_id' => $this->initiatorId,
                    'user_matricule' => $this->initiatorMatricule,
                    'user_name' => $this->initiatorName,
                    'ip_address' => $this->initiatorIp,
                    'file_path' => $this->relativePath,
                    'message' => $deleted
                        ? 'Suppression du fichier source temporaire apres import.'
                        : 'Echec suppression du fichier source temporaire.',
                ]);
            }

            if (is_array($normalized) && !empty($normalized['relative_csv_path'])) {
                Log::channel('import')->info('import.job.cleanup.normalized_csv', [
                    'run_id' => $this->runId,
                    'table' => $this->tableName,
                    'relative_csv_path' => $normalized['relative_csv_path'],
                ]);
                $deleted = Storage::delete($normalized['relative_csv_path']);
                $operationLogger->log([
                    'run_id' => $this->runId,
                    'table_name' => $this->tableName,
                    'operation' => 'delete_normalized_csv',
                    'status' => $deleted ? 'success' : 'failed',
                    'user_id' => $this->initiatorId,
                    'user_matricule' => $this->initiatorMatricule,
                    'user_name' => $this->initiatorName,
                    'ip_address' => $this->initiatorIp,
                    'file_path' => (string) $normalized['relative_csv_path'],
                    'message' => $deleted
                        ? 'Suppression du CSV normalise temporaire apres import.'
                        : 'Echec suppression du CSV normalise temporaire.',
                ]);
            }
        }
    }

    private function toMs(float $start): int
    {
        return (int) round((microtime(true) - $start) * 1000);
    }

    private function runCacheKey(): string
    {
        return "import_run_{$this->runId}";
    }

    private function putRunState(array $data, int $ttlSeconds): void
    {
        $existing = Cache::get($this->runCacheKey());
        if (!is_array($existing)) {
            $existing = [];
        }

        $merged = array_merge($existing, $data);
        $merged['run_id'] = $this->runId;
        $merged['table'] = $this->tableName;
        $merged['updated_at'] = $merged['updated_at'] ?? time();
        Cache::put($this->runCacheKey(), $merged, $ttlSeconds);
    }

    private function putTableState(array $data, int $ttlSeconds): void
    {
        $key = "import_table_state_{$this->tableName}";
        $existing = Cache::get($key);
        if (!is_array($existing)) {
            $existing = [];
        }

        $merged = array_merge($existing, $data);
        $merged['run_id'] = $this->runId;
        $merged['table'] = $this->tableName;
        $merged['updated_at'] = $merged['updated_at'] ?? time();
        Cache::put($key, $merged, $ttlSeconds);
    }

    private function pruneDebugRuns(string $tableName, int $keepRuns): void
    {
        if ($keepRuns < 1) {
            $keepRuns = 1;
        }

        $tableDir = "import_debug/{$tableName}";
        if (!Storage::exists($tableDir)) {
            return;
        }

        $runDirs = Storage::directories($tableDir);
        if (count($runDirs) <= $keepRuns) {
            return;
        }

        // Sort by newest modification timestamp first.
        usort($runDirs, function (string $a, string $b): int {
            return $this->runDirLastModified($b) <=> $this->runDirLastModified($a);
        });

        $toDelete = array_slice($runDirs, $keepRuns);
        foreach ($toDelete as $dir) {
            Storage::deleteDirectory($dir);
            Log::channel('import')->info('import.job.debug.prune.deleted', [
                'run_id' => $this->runId,
                'table' => $this->tableName,
                'deleted_dir' => $dir,
            ]);
            app(ImportOperationLogger::class)->log([
                'run_id' => $this->runId,
                'table_name' => $this->tableName,
                'operation' => 'delete_debug_run',
                'status' => 'success',
                'user_id' => $this->initiatorId,
                'user_matricule' => $this->initiatorMatricule,
                'user_name' => $this->initiatorName,
                'ip_address' => $this->initiatorIp,
                'file_path' => $dir,
                'message' => 'Suppression automatique d un ancien run debug.',
            ]);
        }
    }

    private function runDirLastModified(string $dir): int
    {
        $files = Storage::allFiles($dir);
        $last = 0;
        foreach ($files as $file) {
            try {
                $ts = (int) Storage::lastModified($file);
                if ($ts > $last) {
                    $last = $ts;
                }
            } catch (\Throwable $e) {
                // ignore unreadable file and continue
            }
        }

        // If empty, keep deterministic but low timestamp.
        return $last;
    }
}
