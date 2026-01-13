<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
// use Maatwebsite\Excel\Concerns\SkipsEmptyRows; // Removed
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Events\AfterChunk;



class StockImport implements ToCollection, WithHeadingRow, WithChunkReading
{
    use Importable;

    /** @var array */
    protected array $mapping;

    /** @var int */
    protected int $headingRow;

    /** @var string */
    public string $tableName;

    /**
     * @param string $tableName
     * @param array $mapping
     * @param int $headingRow
     */
    public function __construct(string $tableName, array $mapping = [], int $headingRow = 1)
    {
        $this->tableName = $tableName;
        $this->mapping = $mapping;
        $this->headingRow = $headingRow;
    }

    public function headingRow(): int
    {
        return $this->headingRow;
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    /**
     * @param Collection $rows
     */
    public function collection(Collection $rows)
    {
        // Update progress: count valid rows processed in this chunk (including skipped ones)
        Cache::increment("import_processed_{$this->tableName}", $rows->count());

        $insertData = [];

        foreach ($rows as $index => $row) {
            // Ensure array
            $rowArray = $row->toArray();

            // Resolve values
            $article     = trim((string)$this->resolveValue('article', $rowArray));
            $designation = trim((string)$this->resolveValue('designation', $rowArray));

            $initial     = $this->toDecimal($this->resolveValue('initial', $rowArray));
            $entree      = $this->toDecimal($this->resolveValue('entree', $rowArray));
            $sortie      = $this->toDecimal($this->resolveValue('sortie', $rowArray));
            $finale      = $this->toDecimal($this->resolveValue('finale', $rowArray));
            $pump        = $this->toDecimal($this->resolveValue('pump', $rowArray));
            $valeur      = $this->toDecimal($this->resolveValue('valeur', $rowArray));

            if ($article === '') {
                continue;
            }

            // Skip logic
            if ($this->shouldSkip($rowArray)) {
                continue;
            }

            $insertData[] = [
                'article'      => $article,
                'designation'  => $designation,
                'initial'      => $initial,
                'entree'       => $entree,
                'sortie'       => $sortie,
                'finale'       => $finale,
                'pump'         => $pump,
                'valeur'       => $valeur,
            ];
        }

        if (!empty($insertData)) {
            DB::table($this->tableName)->insert($insertData);
        }
    }

    /**
     * Public wrapper to check if a row should be skipped.
     * Useful for counting valid rows before import.
     */
    public function shouldSkip(array $row): bool
    {
        $article = trim((string)$this->resolveValue('article', $row));
        $designation = trim((string)$this->resolveValue('designation', $row));

        if ($article === '') return true;

        return $this->skipRow($article, $designation, $row);
    }

    /**
     * Détecte les lignes à ignorer (Groupe, Total, ou <= 2 colonnes remplies).
     */
    protected function skipRow(string $article, string $designation, array $row): bool
    {

        // 1) “Groupe: …” en colonne Article
        if ($article !== '' && preg_match('/^\s*g(?:roupe)?\s*:?/iu', $article)) {
            return true;
        }
        // 2) Lignes Total
        if ($designation !== '' && preg_match('/^\s*total\b/iu', $designation)) {
            return true;
        }

        // 3) Lignes avec peu de données (Total groupe, Valeur Total, etc...)
        // On compte les colonnes non vides
        $nonEmpty = 0;
        foreach ($row as $v) {
            if (trim((string)$v) !== '') {
                $nonEmpty++;
                if ($nonEmpty > 2) break;
            }
        }
        // Si 2 colonnes ou moins sont remplies, on ignore
        if ($nonEmpty <= 2) {
            return true;
        }

        return false;
    }

    /**
     * Resolves a field value from the row using mapping or fallbacks.
     */
    public function resolveValue(string $field, array $row)
    {
        if (!empty($this->mapping[$field])) {
            $key = $this->mapping[$field];

            // 1. Try exact match (if headers are not slugged)
            if (isset($row[$key])) {
                return $row[$key];
            }

            // 2. Try slugged match (standard Laravel Excel behavior)
            $slug = Str::slug($key, '_');
            if (isset($row[$slug])) {
                return $row[$slug];
            }
            
            // 3. Try no-separator slug (sometimes just lowercase)
             $slugNoSep = Str::slug($key, '');
            if (isset($row[$slugNoSep])) {
                return $row[$slugNoSep];
            }

            return null;
        }

        return match ($field) {
            'article' => $row['article'] ?? $row['artcod'] ?? null,
            'designation' => $row['designation'] ?? $row['libelle'] ?? null,
            'initial' => $row['initial'] ?? null,
            'entree' => $row['entree'] ?? null,
            'sortie' => $row['sortie'] ?? null,
            'finale' => $row['finale'] ?? null,
            'pump' => $row['pump'] ?? $row['pmp'] ?? null,
            'valeur' => $row['valeur'] ?? null,
            default => null,
        };
    }

    public function _resolveValue(string $field, array $row)
    {
        // If we have a mapping for this field, use it.
        if (!empty($this->mapping[$field])) {
            // Maatwebsite Excel slugs the headers in the row keys (separator is usually _)
            $slug = Str::slug($this->mapping[$field], '_');
            // return $row[$slug] ?? null;
            return $row[$this->mapping[$field]] ?? null;
        }

        // Fallback for backward compatibility
        switch ($field) {
            case 'article': return $row['article'] ?? $row['artcod'] ?? null;
            case 'designation': return $row['désignation']  ?? $row['designation'] ?? $row['libelle'] ?? null;
            case 'initial': return $row['initial'] ?? $row['init'] ?? null;
            case 'entree': return $row['entree'] ?? $row['achat'] ?? null;
            case 'sortie': return $row['sortie'] ?? $row['vente'] ?? null;
            case 'finale': return $row['finale'] ?? $row['final'] ?? null;
            case 'pump': return $row['pump'] ?? $row['pmp'] ?? null;
            case 'valeur': return $row['valeur'] ?? $row['montant'] ?? null;
            default: return null;
        }
    }
    public function registerEvents(): array
    {
        return [
            AfterChunk::class => function(AfterChunk $event) {
                // nombre de lignes lues dans ce chunk
//                $size = count($event->getConcernable()->getRows() ?? []);
//                Cache::increment("import_processed_{$this->tableName}", $size);
                static $buffer = 0;
                $buffer++;

                if ($buffer >= 200) {
                    Cache::increment("import_processed_{$this->tableName}", $buffer);
                    $buffer = 0;
                }
            },
        ];
    }

    private function toDecimal($v): ?float
    {
        if ($v === null) return null;
        // Remplace virgule par point si besoin
        $s = str_replace([' ', "\u{00A0}"], '', (string)$v); // supprime espaces/nbsp
        $s = str_replace(',', '.', $s);
        return is_numeric($s) ? (float)$s : null;
    }


}

