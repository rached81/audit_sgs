<?php

namespace App\Http\Controllers;

use App\Jobs\ImportStockJob;
use App\Services\ColumnMapper;
use App\Services\FastHeaderDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

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
        // Etape 1: valider les parametres metier et le type du fichier.
        $request->validate([
            'annee' => 'required|numeric|digits:4',
            'programme' => 'required|string|in:EF,GD',
            'reseau' => 'required|string|in:BUS,FERRE',
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        // Etape 2: construire le nom cible de la table.
        $annee = $request->input('annee');
        $programme = strtoupper($request->input('programme'));
        $reseau = strtoupper($request->input('reseau'));
        $tableName = strtolower("RES_{$programme}_{$reseau}_{$annee}");

        // Etape 3: bloquer si la table existe deja avec donnees.
        if (Schema::hasTable($tableName) && DB::table($tableName)->count() > 0) {
            return back()->withErrors([
                'table_name' => "La table '$tableName' existe deja et contient des donnees.",
            ]);
        }

        // Etape 4: stocker le fichier dans un emplacement temporaire.
        $file = $request->file('file');
        $path = $file->store('temp_imports');
        $fullPath = Storage::path($path);

        try {
            // Etape 5: definir les colonnes obligatoires du modele de stock.
            $requiredColumns = ['article', 'designation', 'initial', 'entree', 'sortie', 'finale', 'pump', 'valeur'];

            // Etape 6: detecter automatiquement la ligne d'entetes la plus probable.
            $detector = new FastHeaderDetector($mapper);
            $result = $detector->detect($fullPath, $requiredColumns, 10);
            $bestRowIndex = $result['bestRow'];
            $bestAnalysis = $result['bestAnalysis'];

            // Etape 7: reutiliser les en-tetes deja lus pendant la detection.
            $fileHeaders = $result['bestHeaders'] ?? [];
            $fileHeaders = array_values(array_filter(array_map(
                fn($h) => trim((string) $h),
                $fileHeaders
            )));

            // Etape 8: autoriser l'import auto uniquement si confiance >= 90 pour chaque champ.
            $perfectMatch = true;
            foreach ($requiredColumns as $col) {
                if (($bestAnalysis['confidence'][$col] ?? 0) < 90) {
                    $perfectMatch = false;
                    break;
                }
            }

            if ($perfectMatch) {
                // Etape 9A: lancer directement le pipeline d'import.
                return $this->doImport(
                    $fullPath,
                    $tableName,
                    $bestAnalysis['mapping'],
                    $bestRowIndex,
                    $path
                );
            }

            // Etape 9B: afficher l'ecran de validation manuelle du mapping.
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
            // Etape 10: nettoyer le fichier temporaire en cas d'echec d'analyse.
            Storage::delete($path);
            return back()->withErrors(['file' => $e->getMessage()]);
        }
    }

    public function processMappedImport(Request $request)
    {
        // Etape 1: valider les donnees soumises depuis l'ecran de mapping.
        $request->validate([
            'file_path' => 'required|string',
            'table_name' => 'required|string',
            'mapping' => 'required|array',
            'heading_row' => 'required|integer',
        ]);

        // Etape 2: reconstruire le contexte d'import.
        $path = $request->input('file_path');
        $tableName = $request->input('table_name');
        $mapping = $request->input('mapping');
        $headingRow = (int) $request->input('heading_row');
        $fullPath = Storage::path($path);

        // Etape 3: verifier la disponibilite du fichier temporaire.
        if (!file_exists($fullPath)) {
            return redirect()->route('import.form')->withErrors(['file' => 'Le fichier temporaire a expire. Veuillez reessayer.']);
        }

        // Etape 4: deleguer au pipeline commun.
        return $this->doImport($fullPath, $tableName, $mapping, $headingRow, $path);
    }

    private function doImport($fullPath, $tableName, $mapping, $headingRow = 1, $relativePath = null)
    {
        // Etape 1: creer la table cible si elle n'existe pas.
        if (!Schema::hasTable($tableName)) {
            $exitCode = Artisan::call('stock:create-table', [
                'table' => $tableName,
            ]);

            if ($exitCode !== 0) {
                return redirect()->route('import.form')->withErrors(['table_name' => 'Echec de la creation de la table.']);
            }
        }
        try {
            $articleType = strtolower((string) Schema::getColumnType($tableName, 'ARTICLE'));
            $numericTypes = ['integer', 'int', 'bigint', 'mediumint', 'smallint', 'tinyint'];
            if (in_array($articleType, $numericTypes, true)) {
                return redirect()->route('import.form')->withErrors([
                    'table_name' => "Schema incompatible sur $tableName: colonne ARTICLE numerique. Recréez la table pour accepter les codes alphanumeriques.",
                ]);
            }
        } catch (\Throwable $e) {
            // Ignorer silencieusement si introspection non supportee par le driver.
        }

        try {
            // Etape 2: lancer un job asynchrone pour executer l'import.
            $runId = (string) str()->uuid();
            ImportStockJob::dispatch($fullPath, $relativePath, $tableName, $mapping, $headingRow, $runId)
                ->onQueue(config('import_perf.queue_name', 'imports'));

            // Etape 3: retourner la table au front pour le suivi de progression.
            return redirect()->route('import.form')
                ->with('success', "Importation lancee vers $tableName. Le traitement se fait en arriere-plan. Suivi en cours...")
                ->with('import_table', $tableName);
        } catch (\Exception $e) {
            // Etape 4: reporter explicitement l'erreur de demarrage.
            return redirect()->route('import.form')->withErrors(['file' => 'Erreur lors du lancement de l\'import : ' . $e->getMessage()]);
        }
    }

    public function checkStatus(Request $request)
    {
        // Etape 1: lire la table suivie depuis la requete de polling.
        $tableName = $request->input('table');
        if (!$tableName) {
            return response()->json(['count' => 0, 'percent' => 0, 'total' => 0, 'status' => 'idle', 'error' => null]);
        }

        if (Schema::hasTable($tableName)) {
            // Etape 2: prioriser le compteur cache, plus representatif pendant l'import.
            $processed = Cache::get("import_processed_{$tableName}");

            // Etape 3: fallback DB si le cache n'est plus disponible.
            if ($processed === null) {
                $processed = DB::table($tableName)->count();
            }

            // Etape 4: calculer le pourcentage a partir du total estime.
            $total = Cache::get("import_total_{$tableName}") ?? 0;
            $percent = 0;

            if ($total > 0) {
                $percent = round(($processed / $total) * 100);
                if ($percent > 100) {
                    $percent = 100;
                }
            }

            $error = Cache::get("import_error_{$tableName}");
            $status = Cache::get("import_status_{$tableName}") ?? 'running';

            if ($error) {
                $status = 'failed';
            }

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
            ]);
        }

        // Etape 5: reponse neutre si table absente.
        return response()->json(['count' => 0, 'percent' => 0, 'total' => 0, 'status' => 'idle', 'error' => null]);
    }
}
