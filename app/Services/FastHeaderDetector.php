<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

class FastHeaderDetector
{
    public function __construct(
        private ColumnMapper $mapper
    ) {
    }

    public function detect(string $fullPath, array $requiredColumns, int $maxLines = 10): array
    {
        $reader = IOFactory::createReaderForFile($fullPath);
        $reader->setReadDataOnly(true);
        if (method_exists($reader, 'setReadEmptyCells')) {
            $reader->setReadEmptyCells(false);
        }
        if (method_exists($reader, 'setContiguous')) {
            $reader->setContiguous(true);
        }
        $worksheetInfo = $reader->listWorksheetInfo($fullPath);
        $firstSheetName = $worksheetInfo[0]['worksheetName'] ?? null;
        if ($firstSheetName && method_exists($reader, 'setLoadSheetsOnly')) {
            $reader->setLoadSheetsOnly([$firstSheetName]);
        }
        $reader->setReadFilter(new class($maxLines) implements IReadFilter {
            public function __construct(private int $maxLines)
            {
            }

            public function readCell($columnAddress, $row, $worksheetName = ''): bool
            {
                return $row >= 1 && $row <= $this->maxLines;
            }
        });

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
}
