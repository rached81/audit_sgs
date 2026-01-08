<?php

namespace App\Imports;

use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithChunkReading;

use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\Importable;

class StockImport implements ToModel, WithHeadingRow, SkipsEmptyRows, WithChunkReading, ShouldQueue
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
        return 1000; // évite l’overhead mémoire
    }

    public function model(array $row)
    {

        // Helper to get value based on mapping or fallback
        // We reuse the public resolveValue helper now
        $getValue = fn($field) => $this->resolveValue($field, $row);

        $article     = trim((string)$getValue('article'));
        $designation  = trim((string)$getValue('designation'));
        $initial      = $this->toDecimal($getValue('initial'));
        $entree       = $this->toDecimal($getValue('entree'));
        $sortie       = $this->toDecimal($getValue('sortie'));
        $finale       = $this->toDecimal($getValue('finale'));
        $pump         = $this->toDecimal($getValue('pump'));
        $valeur       = $this->toDecimal($getValue('valeur'));


        if ($article === '') {
            return null;
        }
        // --------- RÈGLES DE FILTRAGE : ignorer les lignes "groupe" / "total" / titres ----------
        if ($this->shouldSkip($row)) {
            return null; // skip
        }

        // Si nécessaire, filtre aussi les lignes où l’article n’est pas un code attendu
        // (ex: garder uniquement numériques)
        // if (!ctype_digit($article)) return null;
//        dd($row, $entree, $sortie, $finale, $pump, $valeur);
        $model = new \App\Models\DynamicStock();
        $model->setTable($this->tableName);
        $model->article      = $article;
        $model->designation  = $designation;
        $model->initial      = $initial;
        $model->entree       = $entree;
        $model->sortie       = $sortie;
        $model->finale       = $finale;
        $model->pump    = $pump;   // adapte le nom de colonne selon ta migration
        $model->valeur       = $valeur;

        return $model;
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
        // If we have a mapping for this field, use it.
        if (!empty($this->mapping[$field])) {
            // Maatwebsite Excel slugs the headers in the row keys (separator is usually _)
            $slug = Str::slug($this->mapping[$field], '_');
            return $row[$slug] ?? null;
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

    private function toDecimal($v): ?float
    {
        if ($v === null) return null;
        // Remplace virgule par point si besoin
        $s = str_replace([' ', "\u{00A0}"], '', (string)$v); // supprime espaces/nbsp
        $s = str_replace(',', '.', $s);
        return is_numeric($s) ? (float)$s : null;
    }


}

