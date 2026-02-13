<?php

namespace App\Http\Controllers;



use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache; // Added
use Maatwebsite\Excel\Facades\Excel;
use App\Services\HeaderScanner;
use Maatwebsite\Excel\HeadingRowImport;
use App\Imports\StockImport;
use App\Services\ColumnMapper;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithLimit;
use App\Jobs\ImportStockJob;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use App\Services\FastHeaderDetector;

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

        if (Schema::hasTable($tableName) && DB::table($tableName)->count() > 0) {
            return back()->withErrors([
                'table_name' => "La table '$tableName' existe déjà et contient des données."
            ]);
        }

        $file = $request->file('file');
        $path = $file->store('temp_imports');
        $fullPath = Storage::path($path);

        try {
            $requiredColumns = ['article','designation','initial','entree','sortie','finale','pump','valeur'];

//            $scanner = new HeaderScanner($mapper, $requiredColumns);
//            try {
//                Excel::import($scanner, $fullPath);
//            } catch (\Exception $e) {}
            $detector = new \App\Services\FastHeaderDetector($mapper);
            $result = $detector->detect($fullPath, $requiredColumns, 10);
            $bestRowIndex = $result['bestRow'];
            $bestAnalysis = $result['bestAnalysis'];
//            $bestRowIndex = $scanner->bestRow ?: 1;
//            $bestAnalysis = $scanner->bestAnalysis;

            /*  NOUVEAU : lecture réelle des titres */
            // On laisse le formatter par défaut (slug)
            $headings = (new HeadingRowImport($bestRowIndex))->toArray($fullPath);
            $fileHeaders = $headings[0][0] ?? [];

            $fileHeaders = array_values(array_filter(array_map(
                fn($h) => trim((string)$h),
                $fileHeaders
            )));

            $perfectMatch = true;
            foreach ($requiredColumns as $col) {
                // On accepte soit Exact (100) soit Synonyme (95) comme "Automatique"
                // On demande validation si c'est Fuzzy (< 90)
                if (($bestAnalysis['confidence'][$col] ?? 0) < 90) {
                    $perfectMatch = false;
                    break;
                }
            }

            if ($perfectMatch) {
                return $this->doImport(
                    $fullPath,
                    $tableName,
                    $bestAnalysis['mapping'],
                    $bestRowIndex,
                    $path
                );
            }

            return view('import_mapping', [
                'analysis' => $bestAnalysis,
//                'file_headers' => $fileHeaders, // ✅ IMPORTANT
                'file_headers' => $fileHeaders ?? [],
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
            return back()->withErrors(['file' => $e->getMessage()]);
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

        return $this->doImport($fullPath, $tableName, $mapping, $headingRow, $path);
    }

    private function doImport($fullPath, $tableName, $mapping, $headingRow = 1, $relativePath = null)
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
            // Dispatch the job to handle import in background
            ImportStockJob::dispatch($fullPath, $relativePath, $tableName, $mapping, $headingRow);

            return redirect()->route('import.form')
                ->with('success', "Importation lancée vers $tableName. Le traitement se fait en arrière-plan. Suivi en cours...")
                ->with('import_table', $tableName);

        } catch (\Exception $e) {
            return redirect()->route('import.form')->withErrors(['file' => 'Erreur lors du lancement de l\'import : ' . $e->getMessage()]);
        }
    }

    public function checkStatus(Request $request)
    {
        $tableName = $request->input('table');
        if (!$tableName) {
            return response()->json(['count' => 0, 'percent' => 0, 'total' => 0]);
        }

        if (Schema::hasTable($tableName)) {
            // Count from Cache is more accurate for progress (includes skipped rows)
            $processed = Cache::get("import_processed_{$tableName}");

            // Fallback to DB count if cache is empty (e.g. page refresh after long time)
            if ($processed === null) {
                $processed = DB::table($tableName)->count();
            }

            $total = Cache::get("import_total_{$tableName}") ?? 0;

            $percent = 0;
            if ($total > 0) {
                $percent = round(($processed / $total) * 100);
                if ($percent > 100) $percent = 100;
            }

            return response()->json(['count' => $processed, 'percent' => $percent, 'total' => $total]);
        }

        return response()->json(['count' => 0, 'percent' => 0, 'total' => 0]);
    }
}
