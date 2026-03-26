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

        // Prefer streaming XLSX reader for big files (faster & low memory).
        $ext = strtolower((string) pathinfo($sourcePath, PATHINFO_EXTENSION));
        $spoutFactory = 'OpenSpout\\Reader\\Common\\Creator\\ReaderFactory';
        if ($ext === 'xlsx' && class_exists($spoutFactory)) {
            $spoutResult = $this->normalizeToCsvWithSpout(
                $sourcePath,
                $mapping,
                $headingRow,
                $relativeCsvPath,
                $csvPath,
                $debugRelativeDir,
                $debugWriteSkipped,
                $debugMaxSkippedRows,
                $skippedHandle,
                $progress
            );
            // Safety net: if OpenSpout produced 0 data rows, fall back to PhpSpreadsheet.
            // Some XLSX files contain sparse rows (many empty cells); OpenSpout's cell iteration
            // can still lead to misalignment depending on the sheet structure.
            if (($spoutResult['rows'] ?? 0) > 0) {
                return $spoutResult;
            }

            Log::channel('import')->warning('import.csv.normalize.openspout.zero_rows_fallback_to_phpspreadsheet', [
                'source_path' => $sourcePath,
                'heading_row' => $headingRow,
                'mapping' => $mapping,
                'relative_csv_path' => $relativeCsvPath,
                'debug_relative_dir' => $debugRelativeDir,
                'spout_scanned_rows' => $spoutResult['scanned_rows'] ?? null,
                'spout_written_rows' => $spoutResult['rows'] ?? null,
            ]);
        }

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

            // Read the whole chunk in one call (much faster than per-row rangeToArray).
            $rows = $sheet->rangeToArray(
                "A{$startRow}:{$highestColumn}{$endRow}",
                null,
                true,
                false
            ) ?? [];

            foreach ($rows as $offset => $row) {
                $rowNumber = $startRow + $offset;
                $scannedRows++;

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

    private function normalizeToCsvWithSpout(
        string $sourcePath,
        array $mapping,
        int $headingRow,
        string $relativeCsvPath,
        string $csvPath,
        ?string $debugRelativeDir,
        bool $debugWriteSkipped,
        int $debugMaxSkippedRows,
        $skippedHandle,
        ?callable $progress
    ): array {
        $spoutFactory = 'OpenSpout\\Reader\\Common\\Creator\\ReaderFactory';

        $writtenRows = 0;
        $scannedRows = 0;
        // OpenSpout doesn't provide total row count cheaply; we can still read it
        // from the XLSX metadata via PhpSpreadsheet (fast) for accurate % progress.
        $highestRow = 0;
        try {
            $metaReader = IOFactory::createReaderForFile($sourcePath);
            if (method_exists($metaReader, 'listWorksheetInfo')) {
                $info = call_user_func([$metaReader, 'listWorksheetInfo'], $sourcePath);
                $highestRow = (int) ($info[0]['totalRows'] ?? 0);
            }
        } catch (\Throwable $e) {
            $highestRow = 0;
        }

        $skipCounters = [
            'empty_article' => 0,
            'group' => 0,
            'total' => 0,
            'sparse' => 0,
        ];
        $skipSamples = [];
        $skippedDebugCount = 0;

        $handle = fopen($csvPath, 'wb');
        fputcsv($handle, self::TARGET_COLUMNS, ',', '"', '');

        /** @var \OpenSpout\Reader\ReaderInterface $reader */
        $reader = call_user_func([$spoutFactory, 'createFromFile'], $sourcePath);
        $reader->open($sourcePath);

        $headerIndex = null;
        $fieldIndex = null;
        $headers = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $rowNumber = 0;
                foreach ($sheet->getRowIterator() as $row) {
                    $rowNumber++;
                    if ($rowNumber < $headingRow) {
                        continue;
                    }

                    // IMPORTANT: OpenSpout's getCells() iteration is sparse (it may omit empty cells),
                    // which would shift indexes and break our header->index mapping.
                    // Row::toArray() preserves column positions by including empty cells.
                    $values = $row->toArray();

                    if ($rowNumber === $headingRow) {
                        $headers = $values;
                        $headerIndex = $this->buildHeaderIndexMap($headers);
                        $fieldIndex = $this->buildFieldIndexMap($mapping, $headerIndex);
                        continue;
                    }

                    $scannedRows++;
                    $transformed = $this->mapRowFast($values, $fieldIndex);
                    if ($transformed['row'] === null) {
                        $reason = $transformed['skip_reason'] ?? 'unknown';
                        if (isset($skipCounters[$reason])) {
                            $skipCounters[$reason]++;
                        }

                        if ($debugWriteSkipped && is_resource($skippedHandle)) {
                            $canWrite = $debugMaxSkippedRows === 0 || $skippedDebugCount < $debugMaxSkippedRows;
                            if ($canWrite) {
                                $nonEmptyCellsSample = [];
                                foreach ($values as $cellIndex => $cellValue) {
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

                    if ($progress && ($scannedRows % 2000 === 0)) {
                        $progress([
                            'stage' => 'cleaning',
                            'scanned_rows' => $scannedRows,
                            'written_rows' => $writtenRows,
                            'highest_row' => $highestRow,
                        ]);
                    }
                }
                // Only first sheet
                break;
            }
        } finally {
            $reader->close();
            fclose($handle);
            if (is_resource($skippedHandle)) {
                fclose($skippedHandle);
            }
        }

        Log::channel('import')->info('import.csv.normalize.finished', [
            'source_path' => $sourcePath,
            'relative_csv_path' => $relativeCsvPath,
            'scanned_rows' => $scannedRows,
            'written_rows' => $writtenRows,
            'skip_counters' => $skipCounters,
            'skip_samples' => $skipSamples,
            'debug_relative_dir' => $debugRelativeDir,
            'reader' => 'openspout',
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
                'reader' => 'openspout',
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

    private function buildHeaderIndexMap(array $headers): array
    {
        $map = [];
        foreach ($headers as $i => $header) {
            // Normalize header labels from spreadsheet exports (NBSP, newlines, tabs).
            $h = (string) $header;
            $h = str_replace(["\u{00A0}", "\r", "\n", "\t"], ' ', $h);
            $h = trim(preg_replace('/\s+/u', ' ', $h) ?? $h);
            if ($h === '') continue;

            $keys = [
                $h,
                mb_strtolower($h),
                Str::slug($h, '_'),
                Str::slug($h, ''),
            ];

            foreach ($keys as $k) {
                if ($k === '') continue;
                $map[$k] = $i;
            }
        }
        return $map;
    }

    private function buildFieldIndexMap(array $mapping, array $headerIndex): array
    {
        $defaults = [
            'article' => ['article', 'artcod'],
            'designation' => ['designation', 'libelle'],
            'initial' => ['initial'],
            'entree' => ['entree'],
            'sortie' => ['sortie'],
            'finale' => ['finale'],
            'pump' => ['pump', 'pmp'],
            'valeur' => ['valeur'],
        ];

        $out = [];
        foreach ($defaults as $field => $fallbacks) {
            $mapped = $mapping[$field] ?? null;
            $candidates = [];
            if (is_string($mapped) && $mapped !== '') {
                $mappedNorm = (string) $mapped;
                $mappedNorm = str_replace(["\u{00A0}", "\r", "\n", "\t"], ' ', $mappedNorm);
                $mappedNorm = trim(preg_replace('/\s+/u', ' ', $mappedNorm) ?? $mappedNorm);
                $candidates[] = $mappedNorm;
                $candidates[] = mb_strtolower($mappedNorm);
                $candidates[] = Str::slug($mappedNorm, '_');
                $candidates[] = Str::slug($mappedNorm, '');
            } else {
                $candidates = $fallbacks;
            }

            $idx = null;
            foreach ($candidates as $c) {
                if (array_key_exists($c, $headerIndex)) {
                    $idx = (int) $headerIndex[$c];
                    break;
                }
            }
            $out[$field] = $idx;
        }
        return $out;
    }

    private function mapRowFast(array $rowValues, array $fieldIndex): array
    {
        $article = $this->normalizeTrimString(($fieldIndex['article'] ?? null) === null ? null : ($rowValues[$fieldIndex['article']] ?? null));
        $designation = $this->normalizeTrimString(($fieldIndex['designation'] ?? null) === null ? null : ($rowValues[$fieldIndex['designation']] ?? null));
        $skipReason = $this->detectSkipReason($article, $designation, $rowValues);

        if ($skipReason !== null) {
            return [
                'row' => null,
                'skip_reason' => $skipReason,
                'article' => $article,
                'designation' => $designation,
            ];
        }

        $get = function (string $field) use ($rowValues, $fieldIndex) {
            $idx = $fieldIndex[$field] ?? null;
            return $idx === null ? null : ($rowValues[$idx] ?? null);
        };

        return [
            'row' => [
                $article,
                $designation,
                $this->toDecimal($get('initial'), 0.0),
                $this->toDecimal($get('entree'), 0.0),
                $this->toDecimal($get('sortie'), 0.0),
                $this->toDecimal($get('finale'), 0.0),
                $this->toDecimal($get('pump'), 0.0),
                $this->toDecimal($get('valeur'), 0.0),
            ],
            'skip_reason' => null,
            'article' => $article,
            'designation' => $designation,
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
ESCAPED BY ''
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
