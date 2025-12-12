<?php

namespace App\Http\Controllers;

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
                                 $groupedTables[$annee][$reseau][] = [
                                     'name' => $tableName,
                                     'programme' => $programme,
                                     'count' => $count
                                 ];
                                 
                                 continue; // Skip the fallback
                             }
                         }

                         // Fallback for non-standard RES tables
                         $groupedTables['Autres']['Divers'][] = [
                             'name' => $tableName,
                             'programme' => 'N/A',
                             'count' => $count
                         ];
                     }
                } catch (\Exception $e) {
                    // Ignore
                }
            }
        }
        
        // Sort years descending
        krsort($groupedTables);

        return view('consultation', compact('groupedTables'));
    }
    /**
     * Display the specified resource.
     *
     * @param  string  $tableName
     * @return \Illuminate\Http\Response
     */
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
        if ($filters = $request->input('filters')) {
            foreach ($filters as $column => $value) {
                if (($value !== null && $value !== '') && in_array($column, $columns)) {
                    // Exact match for numerical/specific columns
                    if (in_array(strtoupper($column), ['PUMP', 'INITIAL','ENTREE','SORTIE','FINALE','VALEUR'])) {
                        // Use whereRaw with +0 to force numeric comparison (handles 0 vs 0.000)
                        $query->whereRaw("$column + 0 = ?", [$value]);
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


        // Pagination
        $rows = $query->paginate(50)->withQueryString();

        return view('consultation.show', compact('tableName', 'rows', 'columns', 'isGdTable'));
    }

    public function export(Request $request, $tableName)
    {
       
        if (!Schema::hasTable($tableName) || stripos($tableName, 'RES_') !== 0) {
            abort(404, "Table not found or access denied.");
        }

        $columns = Schema::getColumnListing($tableName);
        $query = DB::table($tableName);

        // Advanced Filtering
        if ($filters = $request->input('filters')) {
            foreach ($filters as $column => $value) {
               if (($value !== null && $value !== '') && in_array($column, $columns)) {
                    // Exact match for numerical/specific columns
                    if (in_array(strtoupper($column), ['PUMP', 'INITIAL', 'ENTREE', 'SORTIE', 'FINALE', 'VALEUR'])) {
                        // Use whereRaw with +0 to force numeric comparison
                        $query->whereRaw("$column + 0 = ?", [$value]);
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
}
