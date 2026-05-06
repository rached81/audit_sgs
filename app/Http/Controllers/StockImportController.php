<?php

namespace App\Http\Controllers;

use App\Jobs\ImportStockJob;
use App\Services\ColumnMapper;
use App\Services\FastHeaderDetector;
use App\Services\ImportOperationLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use SplFileObject;

class StockImportController extends Controller
{
    public function __construct(
        private ImportOperationLogger $operationLogger
    ) {
    }

    public function showForm()
    {
        return view('import');
    }

    public function import(Request $request, ColumnMapper $mapper)
    {
        Log::channel('import')->info('import.controller.request.received', [
            'programme' => $request->input('programme'),
            'reseau' => $request->input('reseau'),
            'annee' => $request->input('annee'),
            'original_name' => $request->file('file')?->getClientOriginalName(),
            'mime' => $request->file('file')?->getClientMimeType(),
            'size_bytes' => $request->file('file')?->getSize(),
        ]);

        $request->validate([
            'annee' => 'required|numeric|digits:4',
            'programme' => 'required|string|in:EF,GD',
            'reseau' => 'required|string|in:BUS,FERRE',
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $annee = $request->input('annee');
        $programme = strtoupper($request->input('programme'));
        $reseau = strtoupper($request->input('reseau'));
        $tableName = "RES_{$programme}_{$reseau}_{$annee}";

        if (Schema::hasTable($tableName) && DB::table($tableName)->count() > 0) {
            Log::channel('import')->warning('import.controller.table.already_filled', [
                'table' => $tableName,
            ]);

            if ($request->ajax() || $request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => "La table '$tableName' existe deja et contient des donnees.",
                ], 422);
            }

            return back()->withErrors([
                'table_name' => "La table '$tableName' existe deja et contient des donnees.",
            ]);
        }

        $file = $request->file('file');
        $path = $file->store('temp_imports');
        $fullPath = Storage::path($path);

        Log::channel('import')->info('import.controller.file.stored', [
            'table' => $tableName,
            'relative_path' => $path,
            'full_path' => $fullPath,
        ]);

        try {
            $requiredColumns = ['article', 'designation', 'initial', 'entree', 'sortie', 'finale', 'pump', 'valeur'];
            $runId = (string) str()->uuid();
            $this->logImportCreation($request, $runId, $tableName, $path, $file->getSize());

            $mappingStartedAt = microtime(true);
            Log::channel('import')->info('import.controller.mapping_detection.started', [
                'run_id' => $runId,
                'table' => $tableName,
                'source_path' => $fullPath,
                'max_lines' => 10,
            ]);
            $detector = new FastHeaderDetector($mapper);
            $result = $detector->detect($fullPath, $requiredColumns, 10);
            $bestRowIndex = $result['bestRow'];
            $bestAnalysis = $result['bestAnalysis'];
            Log::channel('import')->info('import.controller.mapping_detection.finished', [
                'run_id' => $runId,
                'table' => $tableName,
                'duration_ms' => (int) round((microtime(true) - $mappingStartedAt) * 1000),
                'best_row' => $bestRowIndex,
                'best_score' => $result['bestScore'] ?? null,
            ]);

            Log::channel('import')->info('import.controller.header.detected', [
                'table' => $tableName,
                'best_row' => $bestRowIndex,
                'best_score' => $result['bestScore'] ?? null,
                'mapping' => $bestAnalysis['mapping'] ?? [],
                'confidence' => $bestAnalysis['confidence'] ?? [],
            ]);

            $fileHeaders = $result['bestHeaders'] ?? [];
            $fileHeaders = array_values(array_filter(array_map(
                fn($h) => trim((string) $h),
                $fileHeaders
            )));

            [$strictOk, $strictMessage] = $this->validateStrictHeaders($fileHeaders, $requiredColumns);
            if (!$strictOk) {
                $strictDetected = $this->findStrictHeaderRow($fullPath, $requiredColumns, 20);
                if (is_array($strictDetected)) {
                    $bestRowIndex = (int) ($strictDetected['row'] ?? $bestRowIndex);
                    $fileHeaders = (array) ($strictDetected['headers'] ?? $fileHeaders);
                    [$strictOk, $strictMessage] = $this->validateStrictHeaders($fileHeaders, $requiredColumns);
                }
            }
            if (!$strictOk) {
                Log::channel('import')->warning('import.controller.header.strict_validation_failed', [
                    'table' => $tableName,
                    'heading_row' => $bestRowIndex,
                    'headers' => $fileHeaders,
                    'message' => $strictMessage,
                ]);

                if ($request->ajax() || $request->expectsJson()) {
                    return response()->json(['ok' => false, 'message' => $strictMessage], 422);
                }

                Storage::delete($path);
                return back()->withErrors(['file' => $strictMessage]);
            }

            $strictMapping = $this->buildStrictMapping($fileHeaders, $requiredColumns);
            Log::channel('import')->info('import.controller.mapping.strict_auto_confirmed', [
                'table' => $tableName,
                'heading_row' => $bestRowIndex,
                'mapping' => $strictMapping,
            ]);

            return $this->doImport(
                $fullPath,
                $tableName,
                $strictMapping,
                $bestRowIndex,
                $path,
                $request->ajax() || $request->expectsJson(),
                $runId,
                $request
            );
        } catch (\Exception $e) {
            Storage::delete($path);

            Log::channel('import')->error('import.controller.analysis.failed', [
                'table' => $tableName,
                'relative_path' => $path,
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return back()->withErrors(['file' => $e->getMessage()]);
        }
    }

    public function fixImportCache(Request $request)
    {
        $issues = $this->detectImportCacheIssues();
        if ($issues === []) {
            return back()->with('success', 'Aucun cache d\'import bloquant à corriger.');
        }

        foreach ($issues as $tableName) {
            Cache::forget("import_total_{$tableName}");
            Cache::forget("import_processed_{$tableName}");
            Cache::forget("import_error_{$tableName}");
            Cache::forget("import_status_{$tableName}");
            Cache::forget("import_done_{$tableName}");
            Cache::forget("import_cancel_{$tableName}");
            Cache::forget("import_table_state_{$tableName}");
        }

        Log::channel('import')->warning('import.cache.fix.executed', [
            'tables' => $issues,
            'count' => count($issues),
            'by_user_id' => $request->user()?->id,
            'by_user_matricule' => $request->user()?->matricule,
        ]);

        return back()->with('success', 'Correction cache appliquée sur ' . count($issues) . ' import(s) bloqué(s).');
    }

    private function doImport(
        $fullPath,
        $tableName,
        $mapping,
        $headingRow = 1,
        $relativePath = null,
        bool $asJson = false,
        ?string $runId = null,
        ?Request $request = null
    )
    {
        @set_time_limit(0);

        Log::channel('import')->info('import.controller.pipeline.start', [
            'table' => $tableName,
            'heading_row' => $headingRow,
            'relative_path' => $relativePath,
            'full_path' => $fullPath,
            'mapping' => $mapping,
            'queue_connection' => config('queue.default'),
        ]);

        // Init progress cache early so the front can poll "initialisation"
        Cache::put("import_total_{$tableName}", 0, 3600);
        Cache::put("import_processed_{$tableName}", 0, 3600);
        Cache::forget("import_cancel_{$tableName}");
        Cache::forget("import_error_{$tableName}");
        Cache::forget("import_done_{$tableName}");
        Cache::put("import_status_{$tableName}", 'running', 3600);

        try {
            $articleType = strtolower((string) Schema::getColumnType($tableName, 'ARTICLE'));
            $numericTypes = ['integer', 'int', 'bigint', 'mediumint', 'smallint', 'tinyint'];

            Log::channel('import')->info('import.controller.table.schema_checked', [
                'table' => $tableName,
                'article_type' => $articleType,
            ]);

            if (in_array($articleType, $numericTypes, true)) {
                $existingRows = DB::table($tableName)->count();

                Log::channel('import')->warning('import.controller.table.schema_incompatible', [
                    'table' => $tableName,
                    'article_type' => $articleType,
                    'existing_rows' => $existingRows,
                ]);

                if ($existingRows > 0) {
                    if ($asJson) {
                        return response()
                        ->json([
                            'ok' => false,
                            'message' => "Schema incompatible sur $tableName: colonne ARTICLE numerique et table non vide. Videz ou recreez la table avant import.",
                        ], 422);
                    }
                    return redirect()
                    ->route('import.form')
                    ->withErrors([
                        'table_name' => "Schema incompatible sur $tableName: colonne ARTICLE numerique et table non vide. Videz ou recreez la table avant import.",
                    ]);
                }

                $recreateExitCode = Artisan::call('stock:create-table', [
                    'table' => $tableName,
                    '--force' => true,
                ]);

                Log::channel('import')->info('import.controller.table.recreated_for_schema_fix', [
                    'table' => $tableName,
                    'exit_code' => $recreateExitCode,
                    'artisan_output' => trim(Artisan::output()),
                ]);

                if ($recreateExitCode !== 0) {
                    if ($asJson) {
                        return response()->json([
                            'ok' => false,
                            'message' => "Impossible de recreer automatiquement la table $tableName avec le bon schema.",
                        ], 500);
                    }
                    return redirect()->route('import.form')->withErrors([
                        'table_name' => "Impossible de recreer automatiquement la table $tableName avec le bon schema.",
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::channel('import')->warning('import.controller.table.schema_check_skipped', [
                'table' => $tableName,
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
        }

        try {
            $runId = $runId ?: (string) str()->uuid();
            $initiator = Auth::user();
            $initiatorId = $initiator?->id;
            $initiatorMatricule = (string) ($initiator?->matricule ?? '');
            $initiatorName = trim((string) (($initiator?->prenom ?? '') . ' ' . ($initiator?->nom ?? '')));
            $initiatorIp = (string) ($request?->ip() ?? request()->ip());

            Log::channel('import')->info('import.controller.job.scheduled_background_process', [
                'run_id' => $runId,
                'table' => $tableName,
                'queue_connection' => config('queue.default'),
            ]);

            // No queue/worker: execute the import in a detached PHP process.
            // This keeps polling responsive on single-threaded dev servers.
            if (!Schema::hasTable($tableName)) {
                $exitCode = Artisan::call('stock:create-table', [
                    'table' => $tableName,
                ]);

                Log::channel('import')->info('import.controller.table.create_attempted', [
                    'run_id' => $runId,
                    'table' => $tableName,
                    'exit_code' => $exitCode,
                    'artisan_output' => trim(Artisan::output()),
                ]);

                if ($exitCode !== 0) {
                    Cache::put("import_error_{$tableName}", 'Echec de la creation de la table.', 3600);
                    Cache::put("import_status_{$tableName}", 'failed', 3600);
                    Cache::put("import_done_{$tableName}", false, 3600);

                    if ($asJson) {
                        return response()->json([
                            'ok' => false,
                            'message' => "Echec de la creation de la table $tableName.",
                        ], 500);
                    }
                    return redirect()->route('import.form')->withErrors([
                        'table_name' => "Echec de la creation de la table $tableName.",
                    ]);
                }
            }

            $queueConnection = (string) config('queue.default', 'sync');
            $preferQueue = $queueConnection !== 'sync';

            if ($preferQueue) {
                ImportStockJob::dispatch(
                    $fullPath,
                    $relativePath,
                    $tableName,
                    $mapping,
                    (int) $headingRow,
                    $runId,
                    $initiatorId,
                    $initiatorMatricule,
                    $initiatorName,
                    $initiatorIp
                )->onQueue('imports');

                Log::channel('import')->info('import.controller.job.dispatched_to_queue', [
                    'run_id' => $runId,
                    'table' => $tableName,
                    'queue_connection' => $queueConnection,
                    'queue_name' => 'imports',
                ]);
            } else {
                $mappingBase64 = base64_encode(json_encode($mapping, JSON_UNESCAPED_UNICODE));
                $phpCliBinary = $this->resolvePhpCliBinary();
                $artisanCmd =
                    escapeshellarg($phpCliBinary) . ' ' .
                    escapeshellarg(base_path('artisan')) . ' stock:run-import-job ' .
                    '--full-path=' . escapeshellarg($fullPath) . ' ' .
                    '--relative-path=' . escapeshellarg((string) ($relativePath ?? '')) . ' ' .
                    '--table=' . escapeshellarg($tableName) . ' ' .
                    '--heading-row=' . escapeshellarg((string) ((int) $headingRow)) . ' ' .
                    '--run-id=' . escapeshellarg($runId) . ' ' .
                    '--mapping=' . escapeshellarg($mappingBase64) . ' ' .
                    '--initiator-id=' . escapeshellarg((string) ($initiatorId ?? '')) . ' ' .
                    '--initiator-matricule=' . escapeshellarg($initiatorMatricule) . ' ' .
                    '--initiator-name=' . escapeshellarg($initiatorName) . ' ' .
                    '--initiator-ip=' . escapeshellarg($initiatorIp);

                if (DIRECTORY_SEPARATOR === '\\') {
                    pclose(popen('start /B "" ' . $artisanCmd . ' > NUL 2>&1', 'r'));
                } else {
                    exec($artisanCmd . ' > /dev/null 2>&1 &');
                }

                Log::channel('import')->info('import.controller.job.background_process.spawned', [
                    'run_id' => $runId,
                    'table' => $tableName,
                    'php_cli_binary_used' => $phpCliBinary,
                ]);
            }

            if ($asJson) {
                return response()->json([
                    'ok' => true,
                    'table_name' => $tableName,
                    'run_id' => $runId,
                    'status_url' => route('import.status', [], false),
                ]);
            }

            return redirect()->route('import.form')
                ->with('import_table', $tableName) // backward compatibility for old polling
                // Keep empty to force legacy polling (no SSE needed).
                ->with('import_run_id', '');
        } catch (\Exception $e) {
            Log::channel('import')->error('import.controller.pipeline.failed', [
                'table' => $tableName,
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            if ($asJson) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Erreur lors de l\'import : ' . $e->getMessage(),
                ], 500);
            }
            return redirect()->route('import.form')->withErrors(['file' => 'Erreur lors de l\'import : ' . $e->getMessage()]);
        }
    }

    public function checkStatus(Request $request)
    {
        $runId = (string) $request->input('run_id', '');
        if ($runId !== '') {
            $runState = Cache::get("import_run_{$runId}");
            if (is_array($runState)) {
                return response()->json([
                    'count' => (int) ($runState['processed'] ?? 0),
                    'percent' => (int) ($runState['overall_percent'] ?? 0),
                    'total' => (int) ($runState['total'] ?? 0),
                    'status' => (string) ($runState['status'] ?? 'running'),
                    'error' => $runState['error'] ?? null,
                ]);
            }
            return response()->json(['count' => 0, 'percent' => 0, 'total' => 0, 'status' => 'idle', 'error' => null]);
        }

        $tableName = $request->input('table');
        if (!$tableName) {
            return response()->json(['count' => 0, 'percent' => 0, 'total' => 0, 'status' => 'idle', 'error' => null]);
        }

        if (Cache::get("import_cancel_{$tableName}") === true) {
            return response()->json([
                'count' => (int) (Cache::get("import_processed_{$tableName}") ?? 0),
                'percent' => 0,
                'total' => (int) (Cache::get("import_total_{$tableName}") ?? 0),
                'status' => 'cancelled',
                'error' => Cache::get("import_error_{$tableName}") ?? "Import annule par l'utilisateur.",
                'stage' => 'cancelled',
                'eta_seconds' => null,
                'stage_percent' => null,
                'overall_percent' => null,
            ]);
        }

        $processed = Cache::get("import_processed_{$tableName}");
        $total = Cache::get("import_total_{$tableName}");
        $error = Cache::get("import_error_{$tableName}");
        $status = Cache::get("import_status_{$tableName}");
        $tableState = Cache::get("import_table_state_{$tableName}");
        if (!is_array($tableState)) {
            $tableState = [];
        }
        $stage = (string) ($tableState['stage'] ?? '');
        $etaSeconds = $tableState['eta_seconds'] ?? null;
        $stagePercent = $tableState['stage_percent'] ?? null;
        $overallPercent = $tableState['overall_percent'] ?? null;

        // If cache exists (even before table creation), return it so UI isn't stuck.
        if ($status !== null || $processed !== null || $total !== null || $error !== null) {
            $processed = $processed ?? 0;
            $total = $total ?? 0;

            $percent = 0;
            if ($total > 0) {
                $percent = round(($processed / $total) * 100);
                if ($percent > 100) {
                    $percent = 100;
                }
            }

            $status = $status ?? ($error ? 'failed' : 'running');
            if (Cache::get("import_done_{$tableName}") === true && !$error) {
                $percent = 100;
                $status = 'done';
            }

            return response()->json([
                'count' => $processed,
                'percent' => $percent,
                'total' => $total,
                'status' => $status,
                'error' => $error,
                'stage' => $stage !== '' ? $stage : ($total > 0 ? 'inserting' : 'starting'),
                'eta_seconds' => $etaSeconds,
                'stage_percent' => $stagePercent,
                'overall_percent' => $overallPercent,
                'skipped_csv_path' => $tableState['skipped_csv_path'] ?? null,
            ]);
        }

        // Fallback: if cache absent but table exists, infer progress.
        if (Schema::hasTable($tableName)) {
            $processed = DB::table($tableName)->count();
            return response()->json(['count' => $processed, 'percent' => 0, 'total' => 0, 'status' => 'running', 'error' => null]);
        }

        return response()->json(['count' => 0, 'percent' => 0, 'total' => 0, 'status' => 'idle', 'error' => null]);
    }

    private function logImportCreation(Request $request, string $runId, string $tableName, string $relativePath, ?int $fileSizeBytes): void
    {
        $user = $request->user();
        $userName = trim((string) (($user?->prenom ?? '') . ' ' . ($user?->nom ?? '')));

        $this->operationLogger->log([
            'run_id' => $runId,
            'table_name' => $tableName,
            'operation' => 'create_import',
            'status' => 'success',
            'user_id' => $user?->id,
            'user_matricule' => $user?->matricule,
            'user_name' => $userName !== '' ? $userName : null,
            'ip_address' => $request->ip(),
            'file_path' => $relativePath,
            'file_size_bytes' => $fileSizeBytes,
            'message' => 'Creation de l import (upload initial).',
            'context' => [
                'route' => $request->route()?->getName(),
                'programme' => $request->input('programme'),
                'reseau' => $request->input('reseau'),
                'annee' => $request->input('annee'),
                'original_name' => $request->file('file')?->getClientOriginalName(),
            ],
        ]);
    }

    private function normalizeHeader(string $header): string
    {
        $h = trim(Str::ascii($header));
        $h = mb_strtolower($h, 'UTF-8');
        return preg_replace('/[^a-z0-9]+/u', '', $h) ?? '';
    }

    private function validateStrictHeaders(array $fileHeaders, array $requiredColumns): array
    {
        $normalizedFile = [];
        foreach ($fileHeaders as $h) {
            $n = $this->normalizeHeader((string) $h);
            if ($n !== '') {
                $normalizedFile[] = $n;
            }
        }
        $normalizedFile = array_values(array_unique($normalizedFile));

        $normalizedRequired = array_values(array_unique(array_map(
            fn(string $c) => $this->normalizeHeader($c),
            $requiredColumns
        )));

        $missing = array_diff($normalizedRequired, $normalizedFile);
        if (!empty($missing)) {

            // Map normalized back to original labels for display
            $normalizedToRequired = [];
            foreach ($requiredColumns as $c) {
                $normalizedToRequired[$this->normalizeHeader($c)] = strtoupper($c);
            }
            $parts = ['En-tetes du fichier invalides.'];

            if (!empty($missing)) {
                $missingLabels = array_map(fn($n) => ($normalizedToRequired[$n] ?? strtoupper($n)), $missing);
                $parts[] = 'Colonnes obligatoires non trouvees : ' . implode(', ', $missingLabels) . '.';
            }

            $parts[] = 'Colonnes attendues : ' . strtoupper(implode(', ', $requiredColumns)) . '.';
            $parts[] = 'Corrigez les en-tetes du fichier puis relancez un nouvel import.';

            $msg = implode(' ', $parts);
            return [false, $msg];
        }

        return [true, null];
    }

    private function buildStrictMapping(array $fileHeaders, array $requiredColumns): array
    {
        $byNormalized = [];
        foreach ($fileHeaders as $h) {
            $n = $this->normalizeHeader((string) $h);
            if ($n !== '' && !isset($byNormalized[$n])) {
                $byNormalized[$n] = (string) $h;
            }
        }

        $mapping = [];
        foreach ($requiredColumns as $col) {
            $mapping[$col] = $byNormalized[$this->normalizeHeader($col)] ?? $col;
        }
        return $mapping;
    }

    private function findStrictHeaderRow(string $fullPath, array $requiredColumns, int $maxLines = 20): ?array
    {
        $requiredNorm = array_values(array_unique(array_map(
            fn(string $c) => $this->normalizeHeader($c),
            $requiredColumns
        )));
        $ext = strtolower((string) pathinfo($fullPath, PATHINFO_EXTENSION));

        if (in_array($ext, ['csv', 'txt'], true)) {
            $file = new SplFileObject($fullPath, 'r');
            $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
            $best = null;
            $bestScore = -1;
            $line = 0;
            while (!$file->eof() && $line < $maxLines) {
                $line++;
                $row = $file->fgetcsv();
                if (!is_array($row)) {
                    continue;
                }
                $headers = array_values(array_filter(array_map(
                    fn($v) => trim((string) ($v ?? '')),
                    $row
                ), fn($v) => $v !== ''));
                if ($headers === []) {
                    continue;
                }
                $norm = array_values(array_unique(array_map(fn($h) => $this->normalizeHeader($h), $headers)));
                $score = count(array_intersect($requiredNorm, $norm));
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = ['row' => $line, 'headers' => $headers];
                }
                if ($score >= count($requiredNorm)) {
                    return $best;
                }
            }
            return $best;
        }

        $reader = IOFactory::createReaderForFile($fullPath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($fullPath);
        $sheet = $spreadsheet->getSheet(0);
        $highestColumn = $sheet->getHighestDataColumn();

        $best = null;
        $bestScore = -1;
        for ($r = 1; $r <= $maxLines; $r++) {
            $row = $sheet->rangeToArray("A{$r}:{$highestColumn}{$r}", null, true, false)[0] ?? [];
            $headers = array_values(array_filter(array_map(
                fn($v) => trim((string) ($v ?? '')),
                $row
            ), fn($v) => $v !== ''));
            if ($headers === []) {
                continue;
            }
            $norm = array_values(array_unique(array_map(fn($h) => $this->normalizeHeader($h), $headers)));
            $score = count(array_intersect($requiredNorm, $norm));
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = ['row' => $r, 'headers' => $headers];
            }
            if ($score >= count($requiredNorm)) {
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
                return $best;
            }
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        return $best;
    }

    private function resolvePhpCliBinary(): string
    {
        // Allow explicit override in production (e.g. IMPORT_PHP_CLI_BINARY=/usr/bin/php8.3).
        $configured = trim((string) env('IMPORT_PHP_CLI_BINARY', ''));
        if ($configured !== '' && @is_executable($configured)) {
            return $configured;
        }

        $current = (string) PHP_BINARY;
        $base = strtolower(basename($current));
        $looksLikeFpm = str_contains($base, 'php-fpm') || str_contains($current, 'php-fpm');

        if (!$looksLikeFpm && @is_executable($current)) {
            return $current;
        }

        foreach (['/usr/bin/php', '/usr/bin/php8.3', '/usr/local/bin/php'] as $candidate) {
            if (@is_executable($candidate)) {
                return $candidate;
            }
        }

        // Fallback: let shell resolve php from PATH.
        return 'php';
    }

    public static function hasFixableImportCache(): bool
    {
        return app(self::class)->detectImportCacheIssues() !== [];
    }

    private function detectImportCacheIssues(): array
    {
        $tablesRaw = DB::select('SHOW TABLES');
        $dbName = DB::getDatabaseName();
        $key = "Tables_in_" . $dbName;

        $issues = [];
        foreach ($tablesRaw as $tableObj) {
            $tableName = (string) ($tableObj->$key ?? $tableObj->{'Tables_in_audit_sgs'} ?? reset($tableObj));
            if (stripos($tableName, 'RES_') !== 0) {
                continue;
            }

            $status = Cache::get("import_status_{$tableName}");
            $tableState = Cache::get("import_table_state_{$tableName}");
            $updatedAt = is_array($tableState) ? (int) ($tableState['updated_at'] ?? 0) : 0;
            $ageSeconds = $updatedAt > 0 ? (time() - $updatedAt) : null;

            // Running imports are never auto-fixed unless stale for long time.
            if ($status === 'running' && ($ageSeconds === null || $ageSeconds < 600)) {
                continue;
            }

            $hasAnyImportCache =
                Cache::has("import_total_{$tableName}") ||
                Cache::has("import_processed_{$tableName}") ||
                Cache::has("import_error_{$tableName}") ||
                Cache::has("import_status_{$tableName}") ||
                Cache::has("import_done_{$tableName}") ||
                Cache::has("import_cancel_{$tableName}") ||
                Cache::has("import_table_state_{$tableName}");

            if (!$hasAnyImportCache) {
                continue;
            }

            $isProblematic =
                in_array((string) $status, ['failed', 'cancelled'], true) ||
                (($status === 'running') && $ageSeconds !== null && $ageSeconds >= 600);

            if ($isProblematic) {
                $issues[] = $tableName;
            }
        }

        return array_values(array_unique($issues));
    }
}
