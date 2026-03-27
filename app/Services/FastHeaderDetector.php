<?php

namespace App\Services;

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
        $extension = strtolower((string) pathinfo($fullPath, PATHINFO_EXTENSION));
        if (in_array($extension, ['csv', 'txt'], true)) {
            return $this->detectFromCsv($fullPath, $requiredColumns, $maxLines);
        }

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

        $spreadsheet = $reader->load($fullPath);
        $sheet = $spreadsheet->getSheet(0);

        $bestScore = -1;
        $bestRow = 1;
        $bestAnalysis = [];
        $bestHeaders = [];
        $highestColumn = $sheet->getHighestDataColumn();

        for ($r = 1; $r <= $maxLines; $r++) {
            $row = $sheet->rangeToArray(
                "A{$r}:{$highestColumn}{$r}",
                null,
                true,
                false
            )[0] ?? [];

            if (empty(array_filter($row, fn($v) => trim((string) $v) !== ''))) {
                continue;
            }

            $analysis = $this->mapper->mapHeaders($row, $requiredColumns);
            $score = array_sum($analysis['confidence']);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestRow = $r;
                $bestAnalysis = $analysis;
                $bestHeaders = $row;
            }

            $maxPossible = count($requiredColumns) * 100;
            if ($score >= $maxPossible) {
                break;
            }
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return [
            'bestRow' => $bestRow,
            'bestAnalysis' => $bestAnalysis,
            'bestScore' => $bestScore,
            'bestHeaders' => $bestHeaders,
        ];
    }

    private function detectFromCsv(string $fullPath, array $requiredColumns, int $maxLines): array
    {
        $file = new SplFileObject($fullPath, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);

        $delimiter = $this->detectCsvDelimiter($fullPath);
        $file->setCsvControl($delimiter);

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
