<?php

namespace App\Imports;

use Illuminate\Support\Str;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithChunkReading;

use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\ImportFailed;
use Illuminate\Support\Facades\Cache;

class StockImport implements ToModel, WithHeadingRow, SkipsEmptyRows, WithChunkReading, ShouldQueue, WithEvents
{
    /** @var string */
    protected string $tableName;

    /**
     * @param string $tableName
     */
    public function __construct(string $tableName)
    {
        $this->tableName = $tableName;
    }

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function(BeforeImport $event) {
                $totalRows = $event->getReader()->getTotalRows();
                $count = 0;
                // getTotalRows returns array defined by worksheet name/index
                if (!empty($totalRows)) {
                    $count = reset($totalRows);
                }
                // Subtract 1 for header if needed, but heuristic is fine
                Cache::put('import_total_' . $this->tableName, $count, 3600);
                Cache::put('import_status_' . $this->tableName, 'processing', 3600);
            },
            AfterImport::class => function(AfterImport $event) {
                 Cache::put('import_status_' . $this->tableName, 'completed', 3600);
            },
            ImportFailed::class => function(ImportFailed $event) {
                 Cache::put('import_status_' . $this->tableName, 'failed', 3600);
            },
        ];
    }

    public function headingRow(): int
    {
        // Ajuste si tes entêtes commencent à une autre ligne
        return 1;
    }

    public function chunkSize(): int
    {
        return 1000; // évite l’overhead mémoire
    }

    public function model(array $row)
    {
        // Normalise clés (au cas où l’entête diffère légèrement)

        $article     = trim((string)($row['article'] ?? $row['artcod'] ?? ''));
        $designation  = trim((string)($row['désignation']  ?? $row['designation'] ?? $row['Désignation'] ?? ''));
        $initial      = $this->toDecimal($row['initial']   ?? $row['Initial']  ?? null);
        $entree       = $this->toDecimal($row['entree']  ?? $row['Entrée'] ?? $row['entrée']  ?? null);
        $sortie       = $this->toDecimal($row['sortie']    ?? 0);
        $finale       = $this->toDecimal($row['finale']    ?? $row['final']  ?? $row['Final']   ?? $row['actuel'] ?? $row['Finale']  ?? null);
        $pump         = $this->toDecimal( $row['pump']?? $row['PUMP'] ?? null);
        $valeur       = $this->toDecimal($row['valeur']  ?? $row['Valeur']  ?? null);


        if ($article === '') {
            return null;
        }
        // --------- RÈGLES DE FILTRAGE : ignorer les lignes "groupe" / "total" / titres ----------
        if ($this->skipRow($article, $designation, $row)) {
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
     * Détecte les lignes à ignorer (Groupe, Total, ou <= 2 colonnes remplies).
     */
    private function skipRow(string $article, string $designation, array $row): bool
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

    private function toDecimal($v): ?float
    {
        if ($v === null) return null;
        // Remplace virgule par point si besoin
        $s = str_replace([' ', "\u{00A0}"], '', (string)$v); // supprime espaces/nbsp
        $s = str_replace(',', '.', $s);
        return is_numeric($s) ? (float)$s : null;
    }
}

