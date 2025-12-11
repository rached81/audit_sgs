<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StockConsultationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Get all tables
        // MySQL specific: SHOW TABLES
        // But Laravel 'Schema::getTables()' is available in newer versions, checking version might be needed.
        // Assuming current Laravel, Schema::getTables() returns array of objects {name, schema, etc}
        // If not available, we use DB::select('SHOW TABLES') logic for raw query.
        // Let's use flexible DB approach.

        $tables = DB::select('SHOW TABLES');
        $dbName = DB::getDatabaseName();
        $key = "Tables_in_" . $dbName;

        $stockTables = [];

        foreach ($tables as $tableObj) {
            $tableName = $tableObj->$key ?? $tableObj->{'Tables_in_audit_sgs'} ?? reset($tableObj);
            
            // Filter by convention "RES_" (case insensitive)
            if (stripos($tableName, 'RES_') === 0) {
                // Determine if table has "ARTICLE", "INITIAL" etc or just assume based on name?
                // For performance, we can just assume name convention is enough or catch exception.
                
                try {
                     $count = DB::table($tableName)->count();
                     // Only add if it has data? User requested: "veullez afficher que les table contient des données"
                     // This could mean "show ONLY tables with data" OR "show tables AND indicate they have data".
                     // "afficher que les table contient des données" -> Display THAT the tables contain data.
                     // I will filter only those with data > 0 as implied by "contains data" context usually.
                     // But showing empty tables with "0" is also useful info. 
                     // Let's show all RES_ tables and their count.
                     
                     if ($count > 0) {
                         $stockTables[] = [
                             'name' => $tableName,
                             'count' => $count,
                             'updated_at' => null // We don't have timestamps on these tables usually
                         ];
                     }
                } catch (\Exception $e) {
                    // Ignore tables that look like RES_ but aren't readable
                }
            }
        }

        return view('consultation', compact('stockTables'));
    }
}
