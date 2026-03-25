<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportArchiveController extends Controller
{
    private const BASE_DIR = 'import_debug';

    public function index(Request $request)
    {
        $tableFilter = trim((string) $request->query('table', ''));

        $tables = [];
        if (Storage::exists(self::BASE_DIR)) {
            $tables = array_map('basename', Storage::directories(self::BASE_DIR));
            sort($tables, SORT_NATURAL | SORT_FLAG_CASE);
        }

        $selectedTables = $tables;
        if ($tableFilter !== '') {
            $selectedTables = array_values(array_filter(
                $tables,
                fn (string $t) => mb_strtolower($t) === mb_strtolower($tableFilter)
            ));
        }

        $entries = [];
        foreach ($selectedTables as $table) {
            $tableDir = self::BASE_DIR . '/' . $table;
            foreach (Storage::directories($tableDir) as $runDir) {
                $runId = basename($runDir);
                $files = Storage::files($runDir);

                $originals = [];
                foreach ($files as $path) {
                    $name = basename($path);
                    if (!str_starts_with($name, 'original_')) {
                        continue;
                    }

                    $originals[] = [
                        'name' => $name,
                        'size' => Storage::size($path),
                        'last_modified' => Storage::lastModified($path),
                        'download_route' => route('archive.download', [
                            'table' => $table,
                            'runId' => $runId,
                            'filename' => $name,
                        ]),
                    ];
                }

                if ($originals === []) {
                    continue;
                }

                usort($originals, fn ($a, $b) => strcmp($a['name'], $b['name']));

                $entries[] = [
                    'table' => $table,
                    'run_id' => $runId,
                    'dir' => $runDir,
                    'originals' => $originals,
                ];
            }
        }

        usort($entries, fn ($a, $b) => strcmp($b['run_id'], $a['run_id']));

        return view('archive.index', [
            'tables' => $tables,
            'table_filter' => $tableFilter,
            'entries' => $entries,
        ]);
    }

    public function download(string $table, string $runId, string $filename): StreamedResponse
    {
        $filename = basename($filename);
        if ($filename === '' || !str_starts_with($filename, 'original_')) {
            abort(404);
        }

        $path = self::BASE_DIR . '/' . $table . '/' . $runId . '/' . $filename;
        if (!Storage::exists($path)) {
            abort(404);
        }

        // Download using the original client filename when possible.
        $downloadName = preg_replace('/^original_/', '', $filename) ?: $filename;

        return Storage::download($path, $downloadName);
    }
}

