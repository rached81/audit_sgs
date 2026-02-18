<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;

class FastHeaderDetector
{
    public function __construct(
        private ColumnMapper $mapper
    ) {
    }

    public function detect(string $fullPath, array $requiredColumns, int $maxLines = 10): array
    {
        // Etape 1: ouvrir le fichier en mode lecture de donnees uniquement.
        $reader = IOFactory::createReaderForFile($fullPath);
        $reader->setReadDataOnly(true);

        $spreadsheet = $reader->load($fullPath);
        $sheet = $spreadsheet->getSheet(0);

        $bestScore = -1;
        $bestRow = 1;
        $bestAnalysis = [];

        // Etape 2: analyser les premieres lignes et scorer le mapping.
        for ($r = 1; $r <= $maxLines; $r++) {
            $row = $sheet->rangeToArray(
                "A{$r}:" . $sheet->getHighestColumn() . "{$r}",
                null,
                true,
                false
            )[0] ?? [];

            if (empty(array_filter($row, fn($v) => trim((string) $v) !== ''))) {
                continue;
            }

            $analysis = $this->mapper->mapHeaders($row, $requiredColumns);
            $score = array_sum($analysis['confidence']);

            // Etape 3: conserver la meilleure ligne detectee.
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestRow = $r;
                $bestAnalysis = $analysis;
            }

            // Etape 4: arreter tot si score maximal atteint.
            $maxPossible = count($requiredColumns) * 100;
            if ($score >= $maxPossible) {
                break;
            }
        }

        // Etape 5: liberer la memoire PhpSpreadsheet.
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return [
            'bestRow' => $bestRow,
            'bestAnalysis' => $bestAnalysis,
            'bestScore' => $bestScore,
        ];
    }
}
