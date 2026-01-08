<?php

namespace App\Http\Controllers;



use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache; // Added
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;
use App\Imports\StockImport;
use App\Services\ColumnMapper;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithLimit;

class StockImportController extends Controller
{
    /**
     * Display the import form.
     */
    public function showForm()
    {
        return view('import');
    }

    /**
     * Handle the file upload and initial analysis.
     */
    public function import(Request $request, ColumnMapper $mapper)
    {
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

        // 1. Check Table Status (Fail early if table exists and has data)
        if (Schema::hasTable($tableName)) {
            $count = DB::table($tableName)->count();
            if ($count > 0) {
                 return back()->withErrors(['table_name' => "La table '$tableName' existe déjà et contient $count lignes. Veuillez choisir un autre nom ou vider la table manuellement."]);
            }
        }

        $file = $request->file('file');

        // 2. Store file temporarily
        $path = $file->store('temp_imports');
        $fullPath = Storage::path($path);

        // 3. Analyze Headers - Scan first 20 rows to find best header candidate
        try {
            $requiredColumns = ['article', 'designation', 'initial', 'entree', 'sortie', 'finale', 'pump', 'valeur'];

            $bestRowIndex = 1;
            $bestScore = -1;
            $bestAnalysis = [];


            try {
                // OPTIMIZATION: Read first 20 rows ONCE instead of looping 20 times opening the file
                $start = microtime(true);
                $preview = Excel::toArray(new class implements ToArray, WithLimit {
                    public function array(array $array){}
                    public function limit(): int { return 10; }
                }, $fullPath);

                // Get first sheet
                $rows = $preview[0] ?? [];
            } catch (\Exception $e) {
                 // Fallback or just empty
                 $rows = [];
            }

            // Iterate rows 1 to 20 (or fewer if file is small)
            foreach ($rows as $index => $row) {
                $i = $index + 1; // Excel row number (1-based)

                $fileHeaders = $row;

                // Skip empty rows
                if (empty(array_filter($fileHeaders))) continue;

                $analysis = $mapper->mapHeaders($fileHeaders, $requiredColumns);

                // Score = number of columns with decent confidence (> 60%)
                $score = 0;
                foreach ($analysis['confidence'] as $conf) {
                    if ($conf > 60) $score++;
                }

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestRowIndex = $i;
                    $bestAnalysis = $analysis;
                }

                // If we found a row with > 5 matches, it's likely the one, stop early optimization
                if ($score >= 6) {
                    break;
                }
            }

            // If analysis failed totally (empty file?), fallback to row 1
            if (empty($bestAnalysis)) {
                $headings = (new HeadingRowImport(1))->toArray($fullPath);
                $fileHeaders = $headings[0][0] ?? [];
                $bestAnalysis = $mapper->mapHeaders($fileHeaders, $requiredColumns);
                $bestRowIndex = 1;
            }

            // Check if we have a perfect match on the BEST row
            $perfectMatch = true;
            foreach ($requiredColumns as $col) {
                if (($bestAnalysis['confidence'][$col] ?? 0) < 100) {
                    $perfectMatch = false;
                    break;
                }
            }

            if ($perfectMatch) {
                // Determine mapping from analysis (it's just key => value)
                $mapping = $bestAnalysis['mapping'];
                return $this->doImport($fullPath, $tableName, $mapping, $bestRowIndex);
            } else {
                // Redirect to mapping verification
                return view('import_mapping', [
                    'analysis' => $bestAnalysis,
                    'file_path' => $path,
                    'table_name' => $tableName,
                    'annee' => $annee, 'programme' => $programme,
                    'reseau' => $reseau,
                    'required_columns' => $requiredColumns,
                    'heading_row' => $bestRowIndex
                ]);
            }

        } catch (\Exception $e) {
            Storage::delete($path);
            return back()->withErrors(['file' => 'Impossible de lire le fichier : ' . $e->getMessage()]);
        }
    }

    public function     processMappedImport(Request $request)
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
        $headingRow = (int)$request->input('heading_row');

        $fullPath = Storage::path($path);

        if (!file_exists($fullPath)) {
            return redirect()->route('import.form')->withErrors(['file' => 'Le fichier temporaire a expiré. Veuillez réessayer.']);
        }

        return $this->doImport($fullPath, $tableName, $mapping, $headingRow);
    }

    private function doImport($fullPath, $tableName, $mapping, $headingRow = 1)
    {
        // Ensure table creation logic
        if (!Schema::hasTable($tableName)) {
            $exitCode = Artisan::call('stock:create-table', [
                'table' => $tableName
            ]);

            if ($exitCode !== 0) {
                return redirect()->route('import.form')->withErrors(['table_name' => 'Échec de la création de la table.']);
            }
        }

        try {
            // Calculate Total Rows for Progress tracking efficiently
            // Wde use a separate lightweight import just for counting to avoid loading everything into memory
            // Or we check if we can get total rows from metadata (not always reliable with Excel)

            $importer = new StockImport($tableName, $mapping, $headingRow);

            // OPTIMIZED COUNTING: Don't use toArray() which loads everything.
            // Use a custom import that just counts.
            $counter = new class($importer) implements \Maatwebsite\Excel\Concerns\ToModel, \Maatwebsite\Excel\Concerns\WithHeadingRow, \Maatwebsite\Excel\Concerns\WithChunkReading {
                public int $count = 0;
                private $parentImporter;

                public function __construct($importer) { $this->parentImporter = $importer; }

                public function model(array $row) {
                    if (!$this->parentImporter->shouldSkip($row)) {
                        $this->count++;
                    }
                    return null;
                }
                public function headingRow(): int { return $this->parentImporter->headingRow(); }
                public function chunkSize(): int { return 2000; }
            };

            // Run the counting import (synchronous but chunked, low memory)
            Excel::import($counter, $fullPath);

            $totalRows = $counter->count;

            Cache::put("import_total_{$tableName}", $totalRows, 3600); // Store for 1 hour

            // ✅ workaround bug Laravel-Excel
            Excel::clearResolvedInstances();
            // Pass the mapping AND heading row to the Import class
            Excel::import(new StockImport($tableName, $mapping, $headingRow), $fullPath);
//            dd($data,$totalRows);
            return redirect()->route('import.form')->with('success', "Importation lancée vers $tableName. Suivi en cours...")->with('import_table', $tableName);
        } catch (\Exception $e) {
            return redirect()->route('import.form')->withErrors(['file' => 'Erreur lors de l\'import : ' . $e->getMessage()]);
        }
    }

    public function checkStatus(Request $request)
    {
        $tableName = $request->input('table');
        if (!$tableName) {
            return response()->json(['count' => 0, 'percent' => 0, 'total' => 0]);
        }

        if (Schema::hasTable($tableName)) {
            $count = DB::table($tableName)->count();
            $total = Cache::get("import_total_{$tableName}") ?? 0;

            $percent = 0;
            if ($total > 0) {
                $percent = round(($count / $total) * 100);
                if ($percent > 100) $percent = 100;
            }

            return response()->json(['count' => $count, 'percent' => $percent, 'total' => $total]);
        }

        return response()->json(['count' => 0, 'percent' => 0, 'total' => 0]);
    }
}
