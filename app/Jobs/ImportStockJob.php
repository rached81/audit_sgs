<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use App\Imports\StockImport;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportStockJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $fullPath;
    public $relativePath;
    public $tableName;
    public $mapping;
    public $headingRow;

    public $timeout = 3600; // 1 hour

    public function __construct($fullPath, $relativePath, $tableName, $mapping, $headingRow)
    {
        $this->fullPath = $fullPath;
        $this->relativePath = $relativePath;
        $this->tableName = $tableName;
        $this->mapping = $mapping;
        $this->headingRow = $headingRow;
    }



    public function handle()
    {
        try {
            /* 🔥 NOUVEAU : comptage instantané */
            $reader = IOFactory::createReaderForFile($this->fullPath);
            $info = $reader->listWorksheetInfo($this->fullPath);

            $totalRows = $info[0]['totalRows'] ?? 0;
            $dataRows = max(0, $totalRows - $this->headingRow);

            Cache::put("import_total_{$this->tableName}", $dataRows, 3600);
            Cache::forget("import_processed_{$this->tableName}");

            /* Import réel */
            Excel::import(
                new StockImport($this->tableName, $this->mapping, $this->headingRow),
                $this->fullPath
            );

            if ($this->relativePath) {
                Storage::delete($this->relativePath);
            }

        } catch (\Exception $e) {
            Cache::put("import_error_{$this->tableName}", $e->getMessage(), 3600);
            throw $e;
        }
    }


//    public function handle()
//    {
//        try {
//            // 1. Calculate Total Rows for Progress tracking
//            $importer = new StockImport($this->tableName, $this->mapping, $this->headingRow);
//
//            $counter = new class($importer) implements \Maatwebsite\Excel\Concerns\ToModel, \Maatwebsite\Excel\Concerns\WithHeadingRow, \Maatwebsite\Excel\Concerns\WithChunkReading {
//                public int $count = 0;
//                private $parentImporter;
//
//                public function __construct($importer) { $this->parentImporter = $importer; }
//
//                public function model(array $row) {
//                    if (!$this->parentImporter->shouldSkip($row)) {
//                        $this->count++;
//                    }
//                    return null;
//                }
//                public function headingRow(): int { return $this->parentImporter->headingRow(); }
//                public function chunkSize(): int { return 2000; }
//            };
//
//            Excel::import($counter, $this->fullPath);
//            $totalRows = $counter->count;
//
//            Cache::put("import_total_{$this->tableName}", $totalRows, 3600);
//
//            // Clear any previous processed count
//            // Cache::forget("import_processed_{$this->tableName}");
//            // Actually, we might rely on DB count.
//
//            // 2. Run the actual import
//            Excel::import(new StockImport($this->tableName, $this->mapping, $this->headingRow), $this->fullPath);
//
//            // Cleanup
//            if ($this->relativePath) {
//                Storage::delete($this->relativePath);
//            } else {
//                @unlink($this->fullPath);
//            }
//
//            // Mark as complete? Cache TTL will handle it mostly.
//
//        } catch (\Exception $e) {
//            Cache::put("import_error_{$this->tableName}", $e->getMessage(), 3600);
//            throw $e;
//        }
//    }
}
