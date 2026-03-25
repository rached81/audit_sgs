<?php

namespace App\Imports;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\AfterChunk;
use Maatwebsite\Excel\Events\ImportFailed;

class StockImport implements ToCollection, WithHeadingRow, WithChunkReading, WithEvents, ShouldQueue
{
    use Importable, Queueable;

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
        $this->queue = config('import_perf.queue_name', 'default');
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

        // Etape 1: preparer un buffer d'insertion SQL en lot.
        $mappingStart = microtime(true);
        $insertData = [];
        $skippedEmptyArticle = 0;
        $skippedByRules = 0;

        foreach ($rows as $row) {
            $rowArray = $row->toArray();

            // Etape 2: resoudre les champs metier via mapping + fallback.
            $article = trim((string) $this->resolveValue('article', $rowArray));
            $designation = trim((string) $this->resolveValue('designation', $rowArray));
            $initial = $this->toDecimal($this->resolveValue('initial', $rowArray), 0.0);
            $entree = $this->toDecimal($this->resolveValue('entree', $rowArray), 0.0);
            $sortie = $this->toDecimal($this->resolveValue('sortie', $rowArray), 0.0);
            $finale = $this->toDecimal($this->resolveValue('finale', $rowArray), 0.0);
            $pump = $this->toDecimal($this->resolveValue('pump', $rowArray), 0.0);
            $valeur = $this->toDecimal($this->resolveValue('valeur', $rowArray), 0.0);

            // Etape 3: ignorer les lignes sans article (cle minimale).
            if ($article === '') {
                $skippedEmptyArticle++;
                continue;
            }

            // Etape 4: appliquer les regles d'exclusion (groupe/total/lignes quasi vides).
            if ($this->shouldSkip($rowArray)) {
                $skippedByRules++;
                continue;
            }

            // Etape 5: accumuler les donnees valides pour insertion grouppee.
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

        // Etape 6: inserer le chunk en base seulement s'il contient des lignes valides.
        $insertStart = microtime(true);
        $insertedRows = 0;
        if (!empty($insertData)) {
            DB::table($this->tableName)->insert($insertData);
            $insertedRows = count($insertData);
            Cache::increment("import_processed_{$this->tableName}", $insertedRows);
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
                // Hook conserve pour extensions futures.
            },
            ImportFailed::class => function (ImportFailed $event) {
                $message = $event->getException()->getMessage();
                Cache::put("import_error_{$this->tableName}", $message, 3600);
                Cache::put("import_status_{$this->tableName}", 'failed', 3600);
                Cache::put("import_done_{$this->tableName}", false, 3600);

                Log::channel('import')->error('import.chunk.failed', [
                    'run_id' => $this->runId,
                    'table' => $this->tableName,
                    'message' => $message,
                ]);
            },
        ];
    }

    private function toDecimal($v, ?float $default = null): ?float
    {
        // Etape 1: valeur par defaut pour les champs numeriques absents.
        if ($v === null) {
            return $default;
        }

        // Etape 2: normaliser espaces + separateur decimal.
        $s = str_replace([' ', "\u{00A0}"], '', (string) $v);
        // If the source includes backslashes, sanitize them before numeric parsing.
        $s = str_replace('\\', '', $s);
        $s = str_replace(',', '.', $s);

        // Etape 3: retourner un float strict ou la valeur par defaut si non numerique.
        return is_numeric($s) ? (float) $s : $default;
    }

    private function toMs(float $start): int
    {
        return (int) round((microtime(true) - $start) * 1000);
    }
}
