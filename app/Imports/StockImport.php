<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
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
    protected string $runId;
    protected int $chunkIndex = 0;

    /**
     * @param string $tableName
     * @param array $mapping
     * @param int $headingRow
     */
    public function __construct(string $tableName, array $mapping = [], int $headingRow = 1, ?string $runId = null)
    {
        $this->tableName = $tableName;
        $this->mapping = $mapping;
        $this->headingRow = $headingRow;
        $this->runId = $runId ?: (string) str()->uuid();
    }

    public function headingRow(): int
    {
        return $this->headingRow;
    }

    public function chunkSize(): int
    {
        $size = (int) config('import_perf.chunk_size', 1000);
        return $size > 0 ? $size : 1000;
    }

    /**
     * @param Collection $rows
     */
    public function collection(Collection $rows)
    {
        $chunkStart = microtime(true);
        $this->chunkIndex++;
        $rowsCount = $rows->count();

        // Etape 1: mettre a jour la progression avec le volume du chunk lu.
        Cache::increment("import_processed_{$this->tableName}", $rowsCount);

        // Etape 2: preparer un buffer d'insertion SQL en lot.
        $mappingStart = microtime(true);
        $insertData = [];
        $skippedEmptyArticle = 0;
        $skippedByRules = 0;

        foreach ($rows as $row) {
            $rowArray = $row->toArray();

            // Etape 3: resoudre les champs metier via mapping + fallback.
            $article = trim((string) $this->resolveValue('article', $rowArray));
            $designation = trim((string) $this->resolveValue('designation', $rowArray));
            $initial = $this->toDecimal($this->resolveValue('initial', $rowArray));
            $entree = $this->toDecimal($this->resolveValue('entree', $rowArray));
            $sortie = $this->toDecimal($this->resolveValue('sortie', $rowArray));
            $finale = $this->toDecimal($this->resolveValue('finale', $rowArray));
            $pump = $this->toDecimal($this->resolveValue('pump', $rowArray));
            $valeur = $this->toDecimal($this->resolveValue('valeur', $rowArray));

            // Etape 4: ignorer les lignes sans article (cle minimale).
            if ($article === '') {
                $skippedEmptyArticle++;
                continue;
            }

            // Etape 5: appliquer les regles d'exclusion (groupe/total/lignes quasi vides).
            if ($this->shouldSkip($rowArray)) {
                $skippedByRules++;
                continue;
            }

            // Etape 6: accumuler les donnees valides pour insertion grouppee.
            $insertData[] = [
                'article' => $article,
                'designation' => $designation,
                'initial' => $initial,
                'entree' => $entree,
                'sortie' => $sortie,
                'finale' => $finale,
                'pump' => $pump,
                'valeur' => $valeur,
            ];
        }
        $mappingDurationMs = $this->toMs($mappingStart);

        // Etape 7: inserer le chunk en base seulement s'il contient des lignes valides.
        $insertStart = microtime(true);
        $insertedRows = 0;
        if (!empty($insertData)) {
            DB::table($this->tableName)->insert($insertData);
            $insertedRows = count($insertData);
        }
        $insertDurationMs = $this->toMs($insertStart);

        if (config('import_perf.enable_chunk_logs', true)) {
            Log::channel('import')->info('import.chunk.processed', [
                'run_id' => $this->runId,
                'table' => $this->tableName,
                'chunk_index' => $this->chunkIndex,
                'chunk_size' => $this->chunkSize(),
                'rows_read' => $rowsCount,
                'rows_inserted' => $insertedRows,
                'rows_skipped_empty_article' => $skippedEmptyArticle,
                'rows_skipped_rules' => $skippedByRules,
                'mapping_duration_ms' => $mappingDurationMs,
                'insert_duration_ms' => $insertDurationMs,
                'total_chunk_duration_ms' => $this->toMs($chunkStart),
                'memory_mb' => round(memory_get_usage(true) / 1048576, 2),
            ]);
        }
    }

    /**
     * Public wrapper to check if a row should be skipped.
     * Useful for counting valid rows before import.
     */
    public function shouldSkip(array $row): bool
    {
        // Etape 1: extraire les champs utiles a la decision.
        $article = trim((string) $this->resolveValue('article', $row));
        $designation = trim((string) $this->resolveValue('designation', $row));

        // Etape 2: rejeter les lignes sans article.
        if ($article === '') {
            return true;
        }

        // Etape 3: deleguer la logique de filtrage detaillee.
        return $this->skipRow($article, $designation, $row);
    }

    /**
     * Detecte les lignes a ignorer (Groupe, Total, ou <= 2 colonnes remplies).
     */
    protected function skipRow(string $article, string $designation, array $row): bool
    {
        // Etape 1: ignorer les lignes de regroupement.
        if ($article !== '' && preg_match('/^\s*g(?:roupe)?\s*:?/iu', $article)) {
            return true;
        }

        // Etape 2: ignorer les lignes de total.
        if ($designation !== '' && preg_match('/^\s*total\b/iu', $designation)) {
            return true;
        }

        // Etape 3: ignorer les lignes trop peu renseignees (souvent des sous-totaux).
        $nonEmpty = 0;
        foreach ($row as $v) {
            if (trim((string) $v) !== '') {
                $nonEmpty++;
                if ($nonEmpty > 2) {
                    break;
                }
            }
        }

        return $nonEmpty <= 2;
    }

    /**
     * Resolves a field value from the row using mapping or fallbacks.
     */
    public function resolveValue(string $field, array $row)
    {
        // Etape 1: utiliser le mapping manuel/auto s'il est fourni.
        if (!empty($this->mapping[$field])) {
            $key = $this->mapping[$field];

            // 1) Tentative cle exacte.
            if (isset($row[$key])) {
                return $row[$key];
            }

            // 2) Tentative cle sluggee avec separateur.
            $slug = Str::slug($key, '_');
            if (isset($row[$slug])) {
                return $row[$slug];
            }

            // 3) Tentative cle sluggee sans separateur.
            $slugNoSep = Str::slug($key, '');
            if (isset($row[$slugNoSep])) {
                return $row[$slugNoSep];
            }

            return null;
        }

        // Etape 2: fallback de compatibilite si aucun mapping n'est present.
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
        // Version legacy conservee pour retro-compatibilite.
        if (!empty($this->mapping[$field])) {
            return $row[$this->mapping[$field]] ?? null;
        }

        switch ($field) {
            case 'article':
                return $row['article'] ?? $row['artcod'] ?? null;
            case 'designation':
                return $row['designation'] ?? $row['libelle'] ?? null;
            case 'initial':
                return $row['initial'] ?? $row['init'] ?? null;
            case 'entree':
                return $row['entree'] ?? $row['achat'] ?? null;
            case 'sortie':
                return $row['sortie'] ?? $row['vente'] ?? null;
            case 'finale':
                return $row['finale'] ?? $row['final'] ?? null;
            case 'pump':
                return $row['pump'] ?? $row['pmp'] ?? null;
            case 'valeur':
                return $row['valeur'] ?? $row['montant'] ?? null;
            default:
                return null;
        }
    }

    public function registerEvents(): array
    {
        return [
            AfterChunk::class => function (AfterChunk $event) {
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
        // Etape 1: conserver null tel quel.
        if ($v === null) {
            return null;
        }

        // Etape 2: normaliser espaces + separateur decimal.
        $s = str_replace([' ', "\u{00A0}"], '', (string) $v);
        $s = str_replace(',', '.', $s);

        // Etape 3: retourner un float strict ou null si non numerique.
        return is_numeric($s) ? (float) $s : null;
    }

    private function toMs(float $start): int
    {
        return (int) round((microtime(true) - $start) * 1000);
    }
}
