<?php

namespace App\Http\Controllers;

use App\Services\ColumnMapper;
use App\Services\FastHeaderDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockImportController extends Controller
{
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
        $tableName = strtolower("RES_{$programme}_{$reseau}_{$annee}");

        if (Schema::hasTable($tableName) && DB::table($tableName)->count() > 0) {
            Log::channel('import')->warning('import.controller.table.already_filled', [
                'table' => $tableName,
            ]);

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

            $detector = new FastHeaderDetector($mapper);
            $result = $detector->detect($fullPath, $requiredColumns, 10);
            $bestRowIndex = $result['bestRow'];
            $bestAnalysis = $result['bestAnalysis'];

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

            $perfectMatch = true;
            foreach ($requiredColumns as $col) {
                if (($bestAnalysis['confidence'][$col] ?? 0) < 90) {
                    $perfectMatch = false;
                    break;
                }
            }

            if ($perfectMatch) {
                Log::channel('import')->info('import.controller.mapping.auto_confirmed', [
                    'table' => $tableName,
                    'heading_row' => $bestRowIndex,
                    'mapping' => $bestAnalysis['mapping'],
                ]);

                return $this->doImport(
                    $fullPath,
                    $tableName,
                    $bestAnalysis['mapping'],
                    $bestRowIndex,
                    $path,
                    $request->ajax() || $request->expectsJson()
                );
            }

            Log::channel('import')->info('import.controller.mapping.manual_required', [
                'table' => $tableName,
                'heading_row' => $bestRowIndex,
                'mapping' => $bestAnalysis['mapping'] ?? [],
                'confidence' => $bestAnalysis['confidence'] ?? [],
                'headers' => $fileHeaders,
            ]);

            return view('import_mapping', [
                'analysis' => $bestAnalysis,
                'file_headers' => $fileHeaders,
                'file_path' => $path,
                'table_name' => $tableName,
                'required_columns' => $requiredColumns,
                'heading_row' => $bestRowIndex,
                'annee' => $annee,
                'programme' => $programme,
                'reseau' => $reseau,
            ]);
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

    public function processMappedImport(Request $request)
    {
        $request->validate([
            'file_path' => 'required|string',
            'table_name' => 'required|string',
            'mapping' => 'required|array',
            'heading_row' => 'required|integer',
        ]);

        $path = $request->input('file_path');
        $tableName = $request->input('table_name');
        $mapping = $request->input('mapping');
        $headingRow = (int) $request->input('heading_row');
        $fullPath = Storage::path($path);

        Log::channel('import')->info('import.controller.mapping.confirmed', [
            'table' => $tableName,
            'heading_row' => $headingRow,
            'mapping' => $mapping,
            'relative_path' => $path,
            'full_path' => $fullPath,
        ]);

        if (!file_exists($fullPath)) {
            Log::channel('import')->warning('import.controller.temp_file.missing', [
                'table' => $tableName,
                'relative_path' => $path,
                'full_path' => $fullPath,
            ]);

            return redirect()->route('import.form')
                ->withErrors(['file' => 'Le fichier temporaire a expire. Veuillez reessayer.']);
        }

        return $this->doImport($fullPath, $tableName, $mapping, $headingRow, $path, false);
    }

    private function doImport($fullPath, $tableName, $mapping, $headingRow = 1, $relativePath = null, bool $asJson = false)
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
                        return response()->json([
                            'ok' => false,
                            'message' => "Schema incompatible sur $tableName: colonne ARTICLE numerique et table non vide. Videz ou recreez la table avant import.",
                        ], 422);
                    }
                    return redirect()->route('import.form')->withErrors([
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
            $runId = (string) str()->uuid();

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

            $mappingBase64 = base64_encode(json_encode($mapping, JSON_UNESCAPED_UNICODE));
            $artisanCmd =
                escapeshellarg(PHP_BINARY) . ' ' .
                escapeshellarg(base_path('artisan')) . ' stock:run-import-job ' .
                '--full-path=' . escapeshellarg($fullPath) . ' ' .
                '--relative-path=' . escapeshellarg((string) ($relativePath ?? '')) . ' ' .
                '--table=' . escapeshellarg($tableName) . ' ' .
                '--heading-row=' . escapeshellarg((string) ((int) $headingRow)) . ' ' .
                '--run-id=' . escapeshellarg($runId) . ' ' .
                '--mapping=' . escapeshellarg($mappingBase64);

            if (DIRECTORY_SEPARATOR === '\\') {
                // Windows: fully detached background launch.
                pclose(popen('start /B "" ' . $artisanCmd . ' > NUL 2>&1', 'r'));
            } else {
                // Linux/macOS
                exec($artisanCmd . ' > /dev/null 2>&1 &');
            }

            Log::channel('import')->info('import.controller.job.background_process.spawned', [
                'run_id' => $runId,
                'table' => $tableName,
            ]);

            if ($asJson) {
                return response()->json([
                    'ok' => true,
                    'table_name' => $tableName,
                    'run_id' => '',
                    'status_url' => route('import.status', [], false),
                    'events_url' => route('import.events', [], false),
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

    public function events(Request $request): StreamedResponse
    {
        $runId = (string) $request->query('runId', '');
        if ($runId === '') {
            abort(400, 'Missing runId');
        }

        $key = "import_run_{$runId}";

        return Response::stream(function () use ($key) {
            @set_time_limit(0);

            $lastJson = null;
            $start = time();

            while (true) {
                $state = Cache::get($key);
                if (!is_array($state)) {
                    $state = [
                        'status' => 'running',
                        'stage' => 'starting',
                        'overall_percent' => 0,
                        'stage_percent' => 0,
                        'processed' => 0,
                        'total' => 0,
                        'eta_seconds' => null,
                        'updated_at' => time(),
                    ];
                }

                $json = json_encode($state, JSON_UNESCAPED_SLASHES);
                if ($json !== $lastJson) {
                    echo "event: progress\n";
                    echo "data: {$json}\n\n";
                    $lastJson = $json;
                } else {
                    // keep-alive to avoid proxies closing the connection
                    echo ": ping\n\n";
                }

                if (function_exists('ob_flush')) {
                    @ob_flush();
                }
                @flush();

                $status = (string) ($state['status'] ?? 'running');
                if (in_array($status, ['done', 'failed'], true)) {
                    break;
                }

                // safety: stop after 2 hours
                if ((time() - $start) > 7200) {
                    break;
                }

                usleep(750000); // ~0.75s
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function checkStatus(Request $request)
    {
        $tableName = $request->input('table');
        if (!$tableName) {
            return response()->json(['count' => 0, 'percent' => 0, 'total' => 0, 'status' => 'idle', 'error' => null]);
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
            ]);
        }

        // Fallback: if cache absent but table exists, infer progress.
        if (Schema::hasTable($tableName)) {
            $processed = DB::table($tableName)->count();
            return response()->json(['count' => $processed, 'percent' => 0, 'total' => 0, 'status' => 'running', 'error' => null]);
        }

        return response()->json(['count' => 0, 'percent' => 0, 'total' => 0, 'status' => 'idle', 'error' => null]);
    }
}
