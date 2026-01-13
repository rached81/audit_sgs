<?php

namespace App\Services;

use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use App\Services\ColumnMapper;
use Exception;

class HeaderScanner implements ToModel, WithChunkReading
{
    /** @var ColumnMapper */
    private $mapper;
    /** @var array */
    private $requiredColumns;
    public $bestScore = -1;
    public $bestRow = 1;
    public $bestAnalysis = [];

    public function __construct(ColumnMapper $mapper, array $requiredColumns)
    {
        $this->mapper = $mapper;
        $this->requiredColumns = $requiredColumns;
    }

    /**
     * Process each row of the preview (first 20 rows).
     */
//    public function _model(array $row)
//    {
//        static $line = 0;
//        $line++;
//        // Only consider first 20 rows
//        if ($line > 20) {
//            // Throw to stop further processing
//            throw new Exception('Header detection completed');
//        }
//        // Skip empty rows
//        if (empty(array_filter($row))) {
//            return null;
//        }
//        $analysis = $this->mapper->mapHeaders($row, $this->requiredColumns);
//        $score = 0;
//        foreach ($analysis['confidence'] as $conf) {
//            if ($conf > 60) {
//                $score++;
//            }
//        }
//        if ($score > $this->bestScore) {
//            $this->bestScore = $score;
//            $this->bestRow = $line;
//            $this->bestAnalysis = $analysis;
//        }
//        // If we already have a strong match, stop early
//        if ($score >= 6) {
//            throw new Exception('Header detection completed');
//        }
//        return null;
//    }
    public function model(array $row)
    {
        static $line = 0;
        $line++;

        // ✅ Seulement 10 premières lignes
        if ($line > 10) {
            throw new Exception('Header detection completed');
        }

        // Skip empty rows
        if (empty(array_filter($row, fn($v) => trim((string)$v) !== ''))) {
            return null;
        }

        $analysis = $this->mapper->mapHeaders($row, $this->requiredColumns);

        $score = 0;
        foreach ($analysis['confidence'] as $conf) {
            if ($conf > 60) $score++;
        }

        if ($score > $this->bestScore) {
            $this->bestScore = $score;
            $this->bestRow = $line;
            $this->bestAnalysis = $analysis;
        }

        // stop early si match fort
        if ($score >= 6) {
            throw new Exception('Header detection completed');
        }

        return null;
    }

    public function chunkSize(): int
    {
        return 2000;
    }
}
?>
