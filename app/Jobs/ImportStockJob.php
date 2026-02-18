<?php

namespace App\Jobs;

use App\Imports\StockImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportStockJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $fullPath;
    public $relativePath;
    public $tableName;
    public $mapping;
    public $headingRow;
    public $runId;

    public $timeout = 3600; // 1 hour

    public function __construct($fullPath, $relativePath, $tableName, $mapping, $headingRow, $runId = null)
    {
        $this->fullPath = $fullPath;
        $this->relativePath = $relativePath;
        $this->tableName = $tableName;
        $this->mapping = $mapping;
        $this->headingRow = $headingRow;
        $this->runId = $runId ?: (string) str()->uuid();
    }

    public function handle()
    {
        $jobStart = microtime(true);

        Log::channel('import')->info('import.job.started', [
            'run_id' => $this->runId,
            'table' => $this->tableName,
            'heading_row' => $this->headingRow,
            'path' => $this->relativePath ?: $this->fullPath,
        ]);

        try {
            // Etape 1: estimer rapidement le nombre total de lignes de donnees.
            $stepStart = microtime(true);
            $reader = IOFactory::createReaderForFile($this->fullPath);
            $info = $reader->listWorksheetInfo($this->fullPath);

            $totalRows = $info[0]['totalRows'] ?? 0;
            $dataRows = max(0, $totalRows - $this->headingRow);
            Log::channel('import')->info('import.job.step.count_rows.done', [
                'run_id' => $this->runId,
                'table' => $this->tableName,
                'total_rows' => $totalRows,
                'data_rows' => $dataRows,
                'duration_ms' => $this->toMs($stepStart),
            ]);

            // Etape 2: initialiser les compteurs de progression en cache.
            $stepStart = microtime(true);
            Cache::put("import_total_{$this->tableName}", $dataRows, 3600);
            Cache::forget("import_processed_{$this->tableName}");
            Cache::forget("import_error_{$this->tableName}");
            Log::channel('import')->info('import.job.step.cache_init.done', [
                'run_id' => $this->runId,
                'table' => $this->tableName,
                'duration_ms' => $this->toMs($stepStart),
            ]);

            // Etape 3: executer l'import reel via la classe metier StockImport.
            $stepStart = microtime(true);
            Excel::import(
                new StockImport($this->tableName, $this->mapping, $this->headingRow, $this->runId),
                $this->fullPath
            );
            Log::channel('import')->info('import.job.step.excel_import.done', [
                'run_id' => $this->runId,
                'table' => $this->tableName,
                'duration_ms' => $this->toMs($stepStart),
            ]);

            // Etape 4: supprimer le fichier temporaire une fois le traitement termine.
            $stepStart = microtime(true);
            if ($this->relativePath) {
                Storage::delete($this->relativePath);
            }
            Log::channel('import')->info('import.job.step.cleanup.done', [
                'run_id' => $this->runId,
                'table' => $this->tableName,
                'duration_ms' => $this->toMs($stepStart),
            ]);

            Log::channel('import')->info('import.job.finished', [
                'run_id' => $this->runId,
                'table' => $this->tableName,
                'duration_ms' => $this->toMs($jobStart),
            ]);
        } catch (\Exception $e) {
            // Etape 5: conserver l'erreur pour inspection cote UI/logs puis rethrow.
            Cache::put("import_error_{$this->tableName}", $e->getMessage(), 3600);
            Log::channel('import')->error('import.job.failed', [
                'run_id' => $this->runId,
                'table' => $this->tableName,
                'message' => $e->getMessage(),
                'duration_ms' => $this->toMs($jobStart),
            ]);
            throw $e;
        }
    }

    private function toMs(float $start): int
    {
        return (int) round((microtime(true) - $start) * 1000);
    }
}
