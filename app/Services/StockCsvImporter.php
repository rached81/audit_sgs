<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

class StockCsvImporter
{
    private const TARGET_COLUMNS = [
        'ARTICLE',
        'DESIGNATION',
        'INITIAL',
        'ENTREE',
        'SORTIE',
        'FINALE',
        'PUMP',
        'VALEUR',
    ];

    public function normalizeToCsv(
        string $sourcePath,
        array $mapping,
        int $headingRow,
        ?string $debugRelativeDir = null,
        ?callable $progress = null
    ): array
    {
        Storage::makeDirectory('temp_imports');

        $relativeCsvPath = 'temp_imports/normalized_' . Str::uuid() . '.csv';
        $csvPath = Storage::path($relativeCsvPath);
        $chunkSize = max(1000, (int) config('import_perf.normalize_chunk_size', 20000));

        $debugMaxSkippedRows = (int) config('import_perf.debug_max_skipped_rows', 0);
        $debugWriteSkipped = !empty($debugRelativeDir);
        $skippedDebugCount = 0;
        $skippedHandle = null;

        if ($debugWriteSkipped) {
            Storage::makeDirectory($debugRelativeDir);
            $skippedCsvFullPath = Storage::path($debugRelativeDir . '/skipped_rows.csv');
            $skippedHandle = fopen($skippedCsvFullPath, 'wb');
            fputcsv($skippedHandle, ['row_number', 'reason', 'article', 'designation', 'non_empty_cells_sample']);
        }

        Log::channel('import')->info('import.csv.normalize.started', [
            'source_path' => $sourcePath,
            'heading_row' => $headingRow,
            'mapping' => $mapping,
            'relative_csv_path' => $relativeCsvPath,
            'chunk_size' => $chunkSize,
            'debug_relative_dir' => $debugRelativeDir,
        ]);

        $reader = IOFactory::createReaderForFile($sourcePath);
        $reader->setReadDataOnly(true);
        if (method_exists($reader, 'setReadEmptyCells')) {
            $reader->setReadEmptyCells(false);
        }
        if (method_exists($reader, 'setContiguous')) {
            call_user_func([$reader, 'setContiguous'], true);
        }

        $worksheetInfo = call_user_func([$reader, 'listWorksheetInfo'], $sourcePath);
        $highestRow = (int) ($worksheetInfo[0]['totalRows'] ?? 0);
        $highestColumn = (string) ($worksheetInfo[0]['lastColumnLetter'] ?? 'A');
        $firstSheetName = $worksheetInfo[0]['worksheetName'] ?? null;
        if ($firstSheetName && method_exists($reader, 'setLoadSheetsOnly')) {
            $reader->setLoadSheetsOnly([$firstSheetName]);
        }

        $reader->setReadFilter($this->makeRowFilter($headingRow, 1));
        $spreadsheet = $reader->load($sourcePath);
        $sheet = $spreadsheet->getSheet(0);
        $headers = $sheet->rangeToArray(
            "A{$headingRow}:{$highestColumn}{$headingRow}",
            null,
            true,
            false
        )[0] ?? [];
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        Log::channel('import')->info('import.csv.normalize.headers_detected', [
            'source_path' => $sourcePath,
            'heading_row' => $headingRow,
            'headers' => $headers,
            'highest_column' => $highestColumn,
            'highest_row' => $highestRow,
        ]);

        $writtenRows = 0;
        $scannedRows = 0;
        $skipCounters = [
            'empty_article' => 0,
            'group' => 0,
            'total' => 0,
            'sparse' => 0,
        ];
        $skipSamples = [];

        $handle = fopen($csvPath, 'wb');
        fputcsv($handle, self::TARGET_COLUMNS, ',', '"', '');

        for ($startRow = $headingRow + 1; $startRow <= $highestRow; $startRow += $chunkSize) {
            $reader->setReadFilter($this->makeRowFilter($startRow, $chunkSize));
            $spreadsheet = $reader->load($sourcePath);
            $sheet = $spreadsheet->getSheet(0);
            $endRow = min($highestRow, $startRow + $chunkSize - 1);

            Log::channel('import')->info('import.csv.normalize.chunk.started', [
                'source_path' => $sourcePath,
                'start_row' => $startRow,
                'end_row' => $endRow,
            ]);

            for ($rowNumber = $startRow; $rowNumber <= $endRow; $rowNumber++) {
                $scannedRows++;

                $row = $sheet->rangeToArray(
                    "A{$rowNumber}:{$highestColumn}{$rowNumber}",
                    null,
                    true,
                    false
                )[0] ?? [];

                $transformed = $this->mapRow($row, $mapping, $headers);
                if ($transformed['row'] === null) {
                    $reason = $transformed['skip_reason'] ?? 'unknown';
                    if (isset($skipCounters[$reason])) {
                        $skipCounters[$reason]++;
                    }

                    // Optional debug: persist each skipped line so we can compare counts/totals.
                    if ($debugWriteSkipped && is_resource($skippedHandle)) {
                        $canWrite = $debugMaxSkippedRows === 0 || $skippedDebugCount < $debugMaxSkippedRows;
                        if ($canWrite) {
                            $nonEmptyCellsSample = [];
                            foreach ($row as $cellIndex => $cellValue) {
                                $txt = $this->normalizeCellText($cellValue);
                                if ($txt !== '') {
                                    $nonEmptyCellsSample[] = [$cellIndex, $txt];
                                    if (count($nonEmptyCellsSample) >= 10) break;
                                }
                            }

                            fputcsv(
                                $skippedHandle,
                                [
                                    $rowNumber,
                                    $reason,
                                    $transformed['article'] ?? '',
                                    $transformed['designation'] ?? '',
                                    json_encode($nonEmptyCellsSample),
                                ],
                                ',',
                                '"'
                            );
                            $skippedDebugCount++;
                        }
                    }

                    if (count($skipSamples) < 10) {
                        $skipSamples[] = [
                            'row_number' => $rowNumber,
                            'reason' => $reason,
                            'article' => $transformed['article'] ?? null,
                            'designation' => $transformed['designation'] ?? null,
                        ];
                    }

                    continue;
                }

                fputcsv($handle, $transformed['row'], ',', '"', '');
                $writtenRows++;
            }

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            Log::channel('import')->info('import.csv.normalize.chunk.finished', [
                'source_path' => $sourcePath,
                'start_row' => $startRow,
                'end_row' => $endRow,
                'scanned_rows_total' => $scannedRows,
                'written_rows_total' => $writtenRows,
            ]);

            if ($progress) {
                $progress([
                    'stage' => 'cleaning',
                    'scanned_rows' => $scannedRows,
                    'written_rows' => $writtenRows,
                    'highest_row' => $highestRow,
                    'start_row' => $startRow,
                    'end_row' => $endRow,
                ]);
            }
        }

        fclose($handle);

        if (is_resource($skippedHandle)) {
            fclose($skippedHandle);
        }

        Log::channel('import')->info('import.csv.normalize.finished', [
            'source_path' => $sourcePath,
            'relative_csv_path' => $relativeCsvPath,
            'scanned_rows' => $scannedRows,
            'written_rows' => $writtenRows,
            'skip_counters' => $skipCounters,
            'skip_samples' => $skipSamples,
            'debug_relative_dir' => $debugRelativeDir,
        ]);

        if ($debugWriteSkipped) {
            $meta = [
                'source_path' => $sourcePath,
                'heading_row' => $headingRow,
                'relative_csv_path' => $relativeCsvPath,
                'scanned_rows' => $scannedRows,
                'written_rows' => $writtenRows,
                'skip_counters' => $skipCounters,
                'skip_samples' => $skipSamples,
                'skipped_debug_count' => $skippedDebugCount,
                'debug_max_skipped_rows' => $debugMaxSkippedRows,
            ];
            Storage::put($debugRelativeDir . '/meta.json', json_encode($meta, JSON_UNESCAPED_SLASHES));
        }

        return [
            'csv_path' => $csvPath,
            'relative_csv_path' => $relativeCsvPath,
            'rows' => $writtenRows,
            'scanned_rows' => $scannedRows,
            'highest_row' => $highestRow,
        ];
    }

    /**
     * Debug helper: normalize cell content so that NBSP/extra spaces don't
     * bypass skip rules (common Excel export issue).
     */
    private function normalizeCellText($value): string
    {
        if ($value === null) return '';

        // Remove both regular spaces and NBSP, then trim.
        // Using the same NBSP approach as in toDecimal().
        $s = str_replace([' ', "\u{00A0}"], '', (string) $value);
        // Backslash can appear as an escape artifact from Excel exports.
        $s = str_replace('\\', '', $s);
        $s = trim($s);
        return $s;
    }

    private function normalizeTrimString($value): string
    {
        if ($value === null) return '';
        // trim() alone may not remove NBSP from Excel exports.
        return $this->normalizeCellText($value);
    }

    public function importNormalizedCsv(string $csvPath, string $tableName, ?callable $progress = null): int
    {
        $driver = DB::connection()->getDriverName();

        Log::channel('import')->info('import.csv.sql.started', [
            'table' => $tableName,
            'csv_path' => $csvPath,
            'driver' => $driver,
            'file_size_bytes' => is_file($csvPath) ? filesize($csvPath) : null,
        ]);

        if ($driver === 'mysql') {
            try {
                $inserted = $this->loadCsvWithMysql($csvPath, $tableName);
                if ($progress) {
                    $progress($inserted);
                }

                Log::channel('import')->info('import.csv.sql.mysql_load.done', [
                    'table' => $tableName,
                    'csv_path' => $csvPath,
                    'rows_inserted' => $inserted,
                ]);

                return $inserted;
            } catch (\Throwable $e) {
                Log::channel('import')->warning('import.csv.sql.mysql_load.failed', [
                    'table' => $tableName,
                    'csv_path' => $csvPath,
                    'message' => $e->getMessage(),
                    'exception' => get_class($e),
                ]);
            }
        }

        Log::channel('import')->info('import.csv.sql.fallback.batch_insert', [
            'table' => $tableName,
            'csv_path' => $csvPath,
        ]);

        return $this->loadCsvWithBatchedSql($csvPath, $tableName, $progress);
    }

    private function mapRow(array $row, array $mapping, array $headers): array
    {
        $values = [];
        foreach ($headers as $index => $header) {
            $headerKey = trim((string) $header);
            if ($headerKey === '') {
                continue;
            }

            $values[$headerKey] = $row[$index] ?? null;
            $values[Str::slug($headerKey, '_')] = $row[$index] ?? null;
            $values[Str::slug($headerKey, '')] = $row[$index] ?? null;
        }

        $article = $this->normalizeTrimString($this->resolveValue('article', $values, $mapping));
        $designation = $this->normalizeTrimString($this->resolveValue('designation', $values, $mapping));
        $skipReason = $this->detectSkipReason($article, $designation, $row);

        if ($skipReason !== null) {
            return [
                'row' => null,
                'skip_reason' => $skipReason,
                'article' => $article,
                'designation' => $designation,
            ];
        }

        return [
            'row' => [
                $article,
                $designation,
                $this->toDecimal($this->resolveValue('initial', $values, $mapping), 0.0),
                $this->toDecimal($this->resolveValue('entree', $values, $mapping), 0.0),
                $this->toDecimal($this->resolveValue('sortie', $values, $mapping), 0.0),
                $this->toDecimal($this->resolveValue('finale', $values, $mapping), 0.0),
                $this->toDecimal($this->resolveValue('pump', $values, $mapping), 0.0),
                $this->toDecimal($this->resolveValue('valeur', $values, $mapping), 0.0),
            ],
            'skip_reason' => null,
            'article' => $article,
            'designation' => $designation,
        ];
    }

    private function resolveValue(string $field, array $values, array $mapping)
    {
        $mappedHeader = $mapping[$field] ?? null;
        if (is_string($mappedHeader) && $mappedHeader !== '') {
            $candidates = [
                trim($mappedHeader),
                Str::slug($mappedHeader, '_'),
                Str::slug($mappedHeader, ''),
            ];

            foreach ($candidates as $candidate) {
                if (array_key_exists($candidate, $values)) {
                    return $values[$candidate];
                }
            }
        }

        return match ($field) {
            'article' => $values['article'] ?? $values['artcod'] ?? null,
            'designation' => $values['designation'] ?? $values['libelle'] ?? null,
            'initial' => $values['initial'] ?? null,
            'entree' => $values['entree'] ?? null,
            'sortie' => $values['sortie'] ?? null,
            'finale' => $values['finale'] ?? null,
            'pump' => $values['pump'] ?? $values['pmp'] ?? null,
            'valeur' => $values['valeur'] ?? null,
            default => null,
        };
    }

    private function detectSkipReason(string $article, string $designation, array $row): ?string
    {
        if ($article === '') {
            return 'empty_article';
        }

        if ($article !== '' && preg_match('/^\s*g(?:roupe)?\s*:?/iu', $article)) {
            return 'group';
        }

        if ($designation !== '' && preg_match('/^\s*total\b/iu', $designation)) {
            return 'total';
        }

        $nonEmpty = 0;
        foreach ($row as $value) {
            if ($this->normalizeCellText($value) !== '') {
                $nonEmpty++;
                if ($nonEmpty > 2) {
                    break;
                }
            }
        }

        return $nonEmpty <= 2 ? 'sparse' : null;
    }

    private function toDecimal($value, ?float $default = null): ?float
    {
        if ($value === null) {
            return $default;
        }

        $string = str_replace([' ', "\u{00A0}"], '', (string) $value);
        // Excel peut exporter des separateurs "\" (thousands separators / escape artifacts).
        // Les supprimer avant le parse numeric pour eviter des valeurs NULL en MySQL.
        $string = str_replace('\\', '', $string);
        $string = str_replace(',', '.', $string);

        return is_numeric($string) ? (float) $string : $default;
    }

    private function loadCsvWithMysql(string $csvPath, string $tableName): int
    {
        $escapedPath = str_replace(
            ["\\", "'"],
            ["\\\\", "''"],
            $csvPath
        );
        $quotedTable = DB::getQueryGrammar()->wrapTable($tableName);

        $sql = <<<SQL
LOAD DATA LOCAL INFILE '{$escapedPath}'
INTO TABLE {$quotedTable}
FIELDS TERMINATED BY ','
OPTIONALLY ENCLOSED BY '"'
NULL DEFINED AS ''
LINES TERMINATED BY '\n'
IGNORE 1 LINES
(`ARTICLE`, `DESIGNATION`, `INITIAL`, `ENTREE`, `SORTIE`, `FINALE`, `PUMP`, `VALEUR`)
SQL;

        Log::channel('import')->info('import.csv.sql.mysql_load.query', [
            'table' => $tableName,
            'csv_path' => $csvPath,
        ]);

        return DB::affectingStatement($sql);
    }

    private function loadCsvWithBatchedSql(string $csvPath, string $tableName, ?callable $progress = null): int
    {
        $file = new \SplFileObject($csvPath, 'rb');
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);
        $file->setCsvControl(',', '"', '');

        $columns = self::TARGET_COLUMNS;
        $batchSize = max(500, (int) config('import_perf.sql_batch_size', 5000));
        $batch = [];
        $inserted = 0;
        $batchIndex = 0;

        DB::transaction(function () use ($file, $tableName, $columns, $batchSize, $progress, &$batch, &$inserted, &$batchIndex) {
            foreach ($file as $lineNumber => $row) {
                if ($lineNumber === 0 || $row === [null] || $row === false) {
                    continue;
                }

                $batch[] = array_slice(array_pad($row, count($columns), null), 0, count($columns));

                if (count($batch) >= $batchSize) {
                    $batchIndex++;
                    $delta = $this->insertBatch($tableName, $columns, $batch);
                    $inserted += $delta;
                    if ($progress) {
                        $progress($delta);
                    }

                    Log::channel('import')->info('import.csv.sql.batch_insert.done', [
                        'table' => $tableName,
                        'batch_index' => $batchIndex,
                        'rows_inserted' => $delta,
                        'rows_inserted_total' => $inserted,
                    ]);

                    $batch = [];
                }
            }

            if ($batch !== []) {
                $batchIndex++;
                $delta = $this->insertBatch($tableName, $columns, $batch);
                $inserted += $delta;
                if ($progress) {
                    $progress($delta);
                }

                Log::channel('import')->info('import.csv.sql.batch_insert.done', [
                    'table' => $tableName,
                    'batch_index' => $batchIndex,
                    'rows_inserted' => $delta,
                    'rows_inserted_total' => $inserted,
                ]);
            }
        });

        Log::channel('import')->info('import.csv.sql.batch_insert.finished', [
            'table' => $tableName,
            'rows_inserted' => $inserted,
            'batches' => $batchIndex,
            'batch_size' => $batchSize,
        ]);

        return $inserted;
    }

    private function insertBatch(string $tableName, array $columns, array $rows): int
    {
        $quotedTable = DB::getQueryGrammar()->wrapTable($tableName);
        $quotedColumns = implode(', ', array_map(
            fn(string $column) => DB::getQueryGrammar()->wrap($column),
            $columns
        ));

        $valuePlaceholders = [];
        $bindings = [];

        foreach ($rows as $row) {
            $valuePlaceholders[] = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
            foreach ($row as $value) {
                $bindings[] = $value;
            }
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s',
            $quotedTable,
            $quotedColumns,
            implode(', ', $valuePlaceholders)
        );

        DB::insert($sql, $bindings);

        return count($rows);
    }

    private function makeRowFilter(int $startRow, int $rowCount): IReadFilter
    {
        return new class($startRow, $rowCount) implements IReadFilter {
            public function __construct(
                private int $startRow,
                private int $rowCount
            ) {
            }

            public function readCell($columnAddress, $row, $worksheetName = ''): bool
            {
                return $row >= $this->startRow && $row < ($this->startRow + $this->rowCount);
            }
        };
    }
}
