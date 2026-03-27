<?php

namespace App\Http\Controllers;

use App\Services\ImportOperationLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use App\Exports\StockExport;
use Maatwebsite\Excel\Facades\Excel;

class StockConsultationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $tables = DB::select('SHOW TABLES');
        $dbName = DB::getDatabaseName();
        $key = "Tables_in_" . $dbName;

        // Structure: [ Year => [ Network => [ Tables ] ] ]
        $groupedTables = [];

        foreach ($tables as $tableObj) {
            $tableName = $tableObj->$key ?? $tableObj->{'Tables_in_audit_sgs'} ?? reset($tableObj);
            
            // Filter by convention "RES_" (case insensitive)
            if (stripos($tableName, 'RES_') === 0) {
                try {
                     $count = DB::table($tableName)->count();
                     
                     if ($count > 0) {
                         // Parse Name: RES_{PROGRAMME}_{RESEAU}_{ANNEE}
                         // Example: RES_EF_BUS_2025
                         
                         $parts = explode('_', $tableName);
                         
                         // Heuristic parsing
                         // Standard format should have 4 parts: RES, PROG, RESEAU, ANNEE
                         if (count($parts) >= 4) {
                             $annee = end($parts); // Last part is likely year
                             if (is_numeric($annee) && strlen($annee) == 4) {
                                 // It's a year.
                                 // Network is likely the one before? Or we search for known keywords?
                                 // Let's look for known networks: BUS, FERRE
                                 $reseau = 'AUTRE';
                                 foreach ($parts as $part) {
                                     if (in_array(strtoupper($part), ['BUS', 'FERRE'])) {
                                         $reseau = strtoupper($part);
                                         break;
                                     }
                                 }
                                 
                                 // Programme: EF, GD
                                  $programme = 'AUTRE';
                                 foreach ($parts as $part) {
                                     if (in_array(strtoupper($part), ['EF', 'GD'])) {
                                         $programme = strtoupper($part);
                                         break;
                                     }
                                 }

                                 // Add to group
                                 $stats = DB::table($tableName)->selectRaw('
                                     SUM(INITIAL) as total_initial,
                                     SUM(ENTREE) as total_entree,
                                     SUM(SORTIE) as total_sortie,
                                     SUM(FINALE) as total_finale,
                                     SUM(VALEUR) as total_valeur,
                                     SUM(INITIAL * PUMP) as total_initial_valorise,
                                     SUM(CASE WHEN PUMP = 0 THEN 1 ELSE 0 END) as count_pump_0,
                                     SUM(CASE WHEN INITIAL = 0 THEN 1 ELSE 0 END) as count_initial_0
                                 ')->first();

                                 $diff = ($stats->total_initial + $stats->total_entree) - ($stats->total_sortie + $stats->total_finale);

                                 $groupedTables[$annee][$reseau][] = [
                                     'name' => $tableName,
                                     'programme' => $programme,
                                     'count' => $count,
                                     'totals' => $stats,
                                     'diff' => $diff
                                 ];
                                 
                                 continue; // Skip the fallback
                             }
                         }

                         // Fallback for non-standard RES tables
                         $stats = DB::table($tableName)->selectRaw('
                             SUM(INITIAL) as total_initial,
                             SUM(ENTREE) as total_entree,
                             SUM(SORTIE) as total_sortie,
                             SUM(FINALE) as total_finale,
                             SUM(VALEUR) as total_valeur,
                             SUM(INITIAL * PUMP) as total_initial_valorise,
                             SUM(CASE WHEN PUMP = 0 THEN 1 ELSE 0 END) as count_pump_0,
                             SUM(CASE WHEN INITIAL = 0 THEN 1 ELSE 0 END) as count_initial_0
                         ')->first();
                         
                         $diff = ($stats->total_initial + $stats->total_entree) - ($stats->total_sortie + $stats->total_finale);

                         $groupedTables['Autres']['Divers'][] = [
                             'name' => $tableName,
                             'programme' => 'N/A',
                             'count' => $count,
                             'totals' => $stats,
                             'diff' => $diff
                         ];
                     }
                } catch (\Exception $e) {
                    // Ignore
                }
            }
        }
        
        // Sort years descending
        krsort($groupedTables);

        // Calculate Comparisons (EF vs GD) per Year/Network
        $comparisons = [];
        foreach ($groupedTables as $annee => $reseaux) {
            foreach ($reseaux as $reseau => $tables) {
                $efTable = null;
                $gdTable = null;

                foreach ($tables as $table) {
                    if ($table['programme'] === 'EF') {
                        $efTable = $table;
                    } elseif ($table['programme'] === 'GD') {
                        $gdTable = $table;
                    }
                }

                if ($efTable && $gdTable && isset($efTable['totals'], $gdTable['totals'])) {
                    $comparisons[$annee][$reseau] = [
                        // Ecart Initial = (Initial * Pump) EF - (Initial * Pump) GD
                        'diff_initial' => $efTable['totals']->total_initial_valorise - $gdTable['totals']->total_initial_valorise,
                        // Ecart Final = Valeur Totale EF - Valeur Totale GD
                        'diff_finale' => $efTable['totals']->total_valeur - $gdTable['totals']->total_valeur,
                        'ef_stats' => [
                            'pump_0' => $efTable['totals']->count_pump_0,
                            'initial_0' => $efTable['totals']->count_initial_0
                        ],
                        'gd_stats' => [
                            'pump_0' => $gdTable['totals']->count_pump_0,
                            'initial_0' => $gdTable['totals']->count_initial_0
                        ]
                    ];
                }
            }
        }

        return view('consultation', compact('groupedTables', 'comparisons'));
    }

    /**
     * Display the specified resource.
     *
     * @param  string  $tableName
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $tableName)
    {
        // Security check
        if (!Schema::hasTable($tableName) || stripos($tableName, 'RES_') !== 0) {
            abort(404, "Table not found or access denied.");
        }

        $columns = Schema::getColumnListing($tableName);
        $query = DB::table($tableName);

        // Advanced Filtering
        $operators = $request->input('operators', []);
        if ($filters = $request->input('filters')) {
            foreach ($filters as $column => $value) {
                if (($value !== null && $value !== '') && in_array($column, $columns)) {
                    // Precise match for numerical/specific columns
                    $numericColumns = ['PUMP', 'INITIAL', 'ENTREE', 'SORTIE', 'FINALE', 'VALEUR'];
                    if (in_array(strtoupper($column), $numericColumns)) {
                        $operator = $operators[$column] ?? '=';
                        // Validate operator
                        if (!in_array($operator, ['=', '>', '<', '>=', '<=', '!='])) {
                            $operator = '=';
                        }
                        // Use whereRaw with +0 to force numeric comparison (handles 0 vs 0.000)
                        $query->whereRaw("$column + 0 $operator ?", [$value]);
                    } else {
                        $query->where($column, 'LIKE', "%{$value}%");
                    }
                }
            }
        }
        
        // Global Search
        if ($search = $request->input('search')) {
            $query->where(function($q) use ($columns, $search) {
                foreach ($columns as $column) {
                    $q->orWhere($column, 'LIKE', "%{$search}%");
                }
            });
        }

        // Grouping Logic
        $isGdTable = stripos($tableName, 'GD') !== false;
        
        // Calculate Totals BEFORE Pagination and Grouping modification (if possible)
        // Note: If grouped, totals should reflect the grouped result.
        // However, SUM(SUM(col)) is just SUM(col). So base query works for global totals.
        
        // We clone the query to get totals for the current filtered set
        $totalsQuery = $query->clone();
        
        // We need to calculate sums for specific columns if they exist
        $totalSelects = [];
        $sumCols = ['INITIAL', 'ENTREE', 'SORTIE', 'FINALE', 'VALEUR'];
        foreach ($sumCols as $col) {
            if (in_array($col, $columns)) {
                 $totalSelects[] = "SUM($col) as total_" . strtolower($col);
            }
        }
        
        $totals = null;
        if (!empty($totalSelects)) {
             try {
                $totals = $totalsQuery->selectRaw(implode(', ', $totalSelects))->first();
             } catch (\Exception $e) {
                 // Fallback if error
             }
        }

        if ($isGdTable && $request->boolean('group_by_article') && in_array('ARTICLE', $columns)) {
            $selects = ['ARTICLE'];
            
            if (in_array('DESIGNATION', $columns)) $selects[] = DB::raw('MAX(DESIGNATION) as DESIGNATION');
            
            $sumColumns = ['INITIAL', 'ENTREE', 'SORTIE', 'FINALE', 'VALEUR'];
            foreach ($sumColumns as $col) {
                if (in_array($col, $columns)) {
                    $selects[] = DB::raw("SUM($col) as $col");
                }
            }
            
            if (in_array('PUMP', $columns)) {
                 $selects[] = DB::raw('MAX(PUMP) as PUMP');
            }

            $query->select($selects)->groupBy('ARTICLE');
        }


        // Pagination
        $rows = $query->paginate(50)->withQueryString();

        return view('consultation.show', compact('tableName', 'rows', 'columns', 'isGdTable', 'totals'));
    }

    public function export(Request $request, $tableName)
    {
       
        if (!Schema::hasTable($tableName) || stripos($tableName, 'RES_') !== 0) {
            abort(404, "Table not found or access denied.");
        }

        $columns = Schema::getColumnListing($tableName);
        $query = DB::table($tableName);

        // Advanced Filtering
        $operators = $request->input('operators', []);
        if ($filters = $request->input('filters')) {
            foreach ($filters as $column => $value) {
                if (($value !== null && $value !== '') && in_array($column, $columns)) {
                    // Precise match for numerical/specific columns
                    $numericColumns = ['PUMP', 'INITIAL', 'ENTREE', 'SORTIE', 'FINALE', 'VALEUR'];
                    if (in_array(strtoupper($column), $numericColumns)) {
                        $operator = $operators[$column] ?? '=';
                        if (!in_array($operator, ['=', '>', '<', '>=', '<=', '!='])) {
                            $operator = '=';
                        }
                        $query->whereRaw("$column + 0 $operator ?", [$value]);
                    } else {
                        $query->where($column, 'LIKE', "%{$value}%");
                    }
                }
            }
        }

        // Global Search
        if ($search = $request->input('search')) {
            $query->where(function($q) use ($columns, $search) {
                foreach ($columns as $column) {
                    $q->orWhere($column, 'LIKE', "%{$search}%");
                }
            });
        }
        
        // Grouping Logic - Restricted to 'GD' tables
        $isGdTable = stripos($tableName, 'GD') !== false;

        if ($isGdTable && $request->boolean('group_by_article') && in_array('ARTICLE', $columns)) {
            $selects = ['ARTICLE'];
            
            if (in_array('DESIGNATION', $columns)) $selects[] = DB::raw('MAX(DESIGNATION) as DESIGNATION');
            
            $sumColumns = ['INITIAL', 'ENTREE', 'SORTIE', 'FINALE', 'VALEUR'];
            foreach ($sumColumns as $col) {
                if (in_array($col, $columns)) {
                    $selects[] = DB::raw("SUM($col) as $col");
                }
            }
             if (in_array('PUMP', $columns)) {
                 $selects[] = DB::raw('MAX(PUMP) as PUMP');
            }

            $query->select($selects)->groupBy('ARTICLE');
        }

        return Excel::download(new StockExport($query, $columns), "{$tableName}.xlsx");
    }

    public function destroy($tableName)
    {
        // Security: Ensure it's a valid generated table
        if (stripos($tableName, 'RES_') !== 0) {
            abort(403, "Action non autorisée.");
        }

        $request = request();
        $user = $request->user();
        $logger = app(ImportOperationLogger::class);
        $runId = (string) str()->uuid();
        $userName = trim((string) (($user?->prenom ?? '') . ' ' . ($user?->nom ?? '')));

        try {
            Schema::dropIfExists($tableName);
            $logger->log([
                'run_id' => $runId,
                'table_name' => $tableName,
                'operation' => 'delete_import_table',
                'status' => 'success',
                'user_id' => $user?->id,
                'user_matricule' => $user?->matricule,
                'user_name' => $userName !== '' ? $userName : null,
                'ip_address' => $request->ip(),
                'message' => "Suppression de la table d'import {$tableName} depuis Consultation.",
                'context' => [
                    'route' => $request->route()?->getName(),
                    'method' => $request->method(),
                ],
            ]);
        } catch (\Throwable $e) {
            $logger->log([
                'run_id' => $runId,
                'table_name' => $tableName,
                'operation' => 'delete_import_table',
                'status' => 'failed',
                'user_id' => $user?->id,
                'user_matricule' => $user?->matricule,
                'user_name' => $userName !== '' ? $userName : null,
                'ip_address' => $request->ip(),
                'message' => "Echec suppression table d'import {$tableName}: " . $e->getMessage(),
                'context' => [
                    'route' => $request->route()?->getName(),
                    'method' => $request->method(),
                    'exception' => get_class($e),
                ],
            ]);

            return redirect()->route('consultation.index')
                ->withErrors(['table' => "Impossible de supprimer la table '{$tableName}'."]);
        }

        return redirect()->route('consultation.index')->with('success', "La table '$tableName' a été supprimée avec succès.");
    }
}
