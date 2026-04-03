<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use SplFileObject;

class FastHeaderDetector
{
    public function __construct(
        private ColumnMapper $mapper
    ) {
    }

    public function detect(string $fullPath, array $requiredColumns, int $maxLines = 10): array
    {
        $startedAt = microtime(true);
        $debugEnabled = (bool) config('import_perf.debug_mapping_logs', true);
        $extension = strtolower((string) pathinfo($fullPath, PATHINFO_EXTENSION));
        if ($debugEnabled) {
            Log::channel('import')->info('import.mapping.detect.started', [
                'source_path' => $fullPath,
                'extension' => $extension,
                'max_lines' => $maxLines,
                'required_columns' => $requiredColumns,
            ]);
        }

        if (in_array($extension, ['csv', 'txt'], true)) {
            $result = $this->detectFromCsv($fullPath, $requiredColumns, $maxLines);
            if ($debugEnabled) {
                Log::channel('import')->info('import.mapping.detect.finished', [
                    'source_path' => $fullPath,
                    'mode' => 'csv',
                    'best_row' => $result['bestRow'] ?? null,
                    'best_score' => $result['bestScore'] ?? null,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ]);
            }

            return $result;
        }

        $readerBuildAt = microtime(true);
        $reader = IOFactory::createReaderForFile($fullPath);
        $reader->setReadDataOnly(true);
        if (method_exists($reader, 'setReadFilter')) {
            $reader->setReadFilter(new class($maxLines) implements IReadFilter {
                public function __construct(private int $maxLines)
                {
                }

                public function readCell($columnAddress, $row, $worksheetName = ''): bool
                {
                    return $row >= 1 && $row <= $this->maxLines;
                }
            });
        }
        if (method_exists($reader, 'setReadEmptyCells')) {
            $reader->setReadEmptyCells(false);
        }
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }
        if (method_exists($reader, 'setContiguous')) {
            $method = 'setContiguous';
            $reader->{$method}(true);
        }
        // Avoid loading all worksheets for large workbooks.
        $sheetNames = [];
        if (method_exists($reader, 'listWorksheetNames')) {
            $method = 'listWorksheetNames';
            $sheetNames = (array) $reader->{$method}($fullPath);
        }
        $firstSheetName = $sheetNames[0] ?? null;
        if ($firstSheetName && method_exists($reader, 'setLoadSheetsOnly')) {
            $reader->setLoadSheetsOnly([$firstSheetName]);
        }
        if ($debugEnabled) {
            Log::channel('import')->info('import.mapping.detect.reader_ready', [
                'source_path' => $fullPath,
                'first_sheet' => $firstSheetName,
                'sheet_names_count' => count($sheetNames),
                'duration_ms' => (int) round((microtime(true) - $readerBuildAt) * 1000),
            ]);
        }

        $loadAt = microtime(true);
        $spreadsheet = $reader->load($fullPath);
        $sheet = $spreadsheet->getSheet(0);
        if ($debugEnabled) {
            Log::channel('import')->info('import.mapping.detect.sheet_loaded', [
                'source_path' => $fullPath,
                'duration_ms' => (int) round((microtime(true) - $loadAt) * 1000),
                'memory_mb' => round(memory_get_usage(true) / 1048576, 2),
            ]);
        }

        $bestScore = -1;
        $bestRow = 1;
        $bestAnalysis = [];
        $bestHeaders = [];
        $highestColumn = $sheet->getHighestDataColumn();
        if ($debugEnabled) {
            Log::channel('import')->info('import.mapping.detect.scan_started', [
                'source_path' => $fullPath,
                'highest_data_column' => $highestColumn,
                'max_lines' => $maxLines,
            ]);
        }

        for ($r = 1; $r <= $maxLines; $r++) {
            $rowAt = microtime(true);
            $row = $sheet->rangeToArray(
                "A{$r}:{$highestColumn}{$r}",
                null,
                true,
                false
            )[0] ?? [];

            if (empty(array_filter($row, fn($v) => trim((string) $v) !== ''))) {
                if ($debugEnabled) {
                    Log::channel('import')->info('import.mapping.detect.row_skipped_empty', [
                        'row' => $r,
                        'duration_ms' => (int) round((microtime(true) - $rowAt) * 1000),
                    ]);
                }
                continue;
            }

            $analysis = $this->mapper->mapHeaders($row, $requiredColumns);
            $score = array_sum($analysis['confidence']);
            if ($debugEnabled) {
                Log::channel('import')->info('import.mapping.detect.row_scored', [
                    'row' => $r,
                    'score' => $score,
                    'confidence' => $analysis['confidence'] ?? [],
                    'mapping' => $analysis['mapping'] ?? [],
                    'duration_ms' => (int) round((microtime(true) - $rowAt) * 1000),
                ]);
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestRow = $r;
                $bestAnalysis = $analysis;
                $bestHeaders = $row;
            }

            $maxPossible = count($requiredColumns) * 100;
            if ($score >= $maxPossible) {
                if ($debugEnabled) {
                    Log::channel('import')->info('import.mapping.detect.early_stop_perfect_score', [
                        'row' => $r,
                        'score' => $score,
                        'max_possible' => $maxPossible,
                    ]);
                }
                break;
            }
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        if ($debugEnabled) {
            Log::channel('import')->info('import.mapping.detect.finished', [
                'source_path' => $fullPath,
                'mode' => 'spreadsheet',
                'best_row' => $bestRow,
                'best_score' => $bestScore,
                'best_mapping' => $bestAnalysis['mapping'] ?? [],
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
            ]);
        }

        return [
            'bestRow' => $bestRow,
            'bestAnalysis' => $bestAnalysis,
            'bestScore' => $bestScore,
            'bestHeaders' => $bestHeaders,
        ];
    }

    private function detectFromCsv(string $fullPath, array $requiredColumns, int $maxLines): array
    {
        $startedAt = microtime(true);
        $debugEnabled = (bool) config('import_perf.debug_mapping_logs', true);
        $file = new SplFileObject($fullPath, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);

        $delimiter = $this->detectCsvDelimiter($fullPath);
        $file->setCsvControl($delimiter);
        if ($debugEnabled) {
            Log::channel('import')->info('import.mapping.detect.csv.started', [
                'source_path' => $fullPath,
                'delimiter' => $delimiter,
                'max_lines' => $maxLines,
            ]);
        }

        $bestScore = -1;
        $bestRow = 1;
        $bestAnalysis = [];
        $bestHeaders = [];
        $rowIndex = 0;

        while (!$file->eof() && $rowIndex < $maxLines) {
            $rowIndex++;
            $row = $file->fgetcsv();
            if (!is_array($row)) {
                continue;
            }

            if ($rowIndex === 1 && isset($row[0]) && is_string($row[0])) {
                $row[0] = preg_replace('/^\xEF\xBB\xBF/u', '', $row[0]) ?? $row[0];
            }

            $cleanRow = array_map(static fn($v) => trim((string) ($v ?? '')), $row);
            if (empty(array_filter($cleanRow, static fn($v) => $v !== ''))) {
                continue;
            }

            $analysis = $this->mapper->mapHeaders($cleanRow, $requiredColumns);
            $score = (int) array_sum($analysis['confidence'] ?? []);
            if ($debugEnabled) {
                Log::channel('import')->info('import.mapping.detect.csv.row_scored', [
                    'row' => $rowIndex,
                    'score' => $score,
                    'confidence' => $analysis['confidence'] ?? [],
                ]);
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestRow = $rowIndex;
                $bestAnalysis = $analysis;
                $bestHeaders = $cleanRow;
            }

            $maxPossible = count($requiredColumns) * 100;
            if ($score >= $maxPossible) {
                break;
            }
        }
        if ($debugEnabled) {
            Log::channel('import')->info('import.mapping.detect.csv.finished', [
                'source_path' => $fullPath,
                'best_row' => $bestRow,
                'best_score' => $bestScore,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);
        }

        return [
            'bestRow' => $bestRow,
            'bestAnalysis' => $bestAnalysis,
            'bestScore' => $bestScore,
            'bestHeaders' => $bestHeaders,
        ];
    }

    private function detectCsvDelimiter(string $fullPath): string
    {
        $line = '';
        $handle = @fopen($fullPath, 'r');
        if ($handle !== false) {
            $line = (string) fgets($handle);
            fclose($handle);
        }

        $candidates = [';', ',', "\t", '|'];
        $bestDelimiter = ';';
        $bestCount = -1;
        foreach ($candidates as $candidate) {
            $count = substr_count($line, $candidate);
            if ($count > $bestCount) {
                $bestCount = $count;
                $bestDelimiter = $candidate;
            }
        }

        return $bestDelimiter;
    }
}
