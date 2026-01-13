<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;

class FastHeaderDetector
{
    public function __construct(
        private ColumnMapper $mapper
    ) {}

    public function detect(string $fullPath, array $requiredColumns, int $maxLines = 10): array
    {
        $reader = IOFactory::createReaderForFile($fullPath);
        $reader->setReadDataOnly(true);

        $spreadsheet = $reader->load($fullPath);
        $sheet = $spreadsheet->getSheet(0);

        $bestScore = -1;
        $bestRow = 1;
        $bestAnalysis = [];

        // Lis uniquement 10 lignes
        for ($r = 1; $r <= $maxLines; $r++) {
            $row = $sheet->rangeToArray(
                "A{$r}:" . $sheet->getHighestColumn() . "{$r}",
                null,
                true,
                false
            )[0] ?? [];

            if (empty(array_filter($row, fn($v) => trim((string)$v) !== ''))) {
                continue;
            }

            $analysis = $this->mapper->mapHeaders($row, $requiredColumns);

            $score = array_sum($analysis['confidence']);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestRow = $r;
                $bestAnalysis = $analysis;
            }

            // stop early si Perfect Match (toutes les colonnes exactes)
            // 8 colonnes * 100 = 800
            $maxPossible = count($requiredColumns) * 100;
            if ($score >= $maxPossible) {
                break;
            }
        }

        // Libère mémoire
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return [
            'bestRow' => $bestRow,
            'bestAnalysis' => $bestAnalysis,
            'bestScore' => $bestScore,
        ];
    }
}
