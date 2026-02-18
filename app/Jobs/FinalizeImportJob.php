<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FinalizeImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ?string $relativePath,
        public string $tableName,
        public string $runId
    ) {
    }

    public function handle(): void
    {
        $start = microtime(true);

        if ($this->relativePath) {
            Storage::delete($this->relativePath);
        }

        $processed = (int) (Cache::get("import_processed_{$this->tableName}") ?? 0);
        $total = (int) (Cache::get("import_total_{$this->tableName}") ?? 0);
        Cache::put("import_done_{$this->tableName}", true, 3600);

        Log::channel('import')->info('import.finalize.done', [
            'run_id' => $this->runId,
            'table' => $this->tableName,
            'processed' => $processed,
            'total' => $total,
            'duration_ms' => (int) round((microtime(true) - $start) * 1000),
        ]);
    }
}
