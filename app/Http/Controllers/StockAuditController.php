<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StockAuditController extends Controller
{
    /**
     * Display the audit selection form.
     */
    public function index()
    {
        return view('audit.index');
    }

    /**
     * Process the comparison request.
     */
    public function compare(Request $request)
    {
        $request->validate([
            'annee' => 'required|numeric|digits:4',
            'reseau' => 'required|string|in:BUS,FERRE',
            'type' => 'nullable|string|in:valeur,initial,pump',
        ]);

        $annee = $request->input('annee');
        $reseau = strtoupper($request->input('reseau'));
        $type = $request->input('type', 'valeur');

        $efTable = "RES_EF_{$reseau}_{$annee}";
        $gdTable = "RES_GD_{$reseau}_{$annee}";

        if (!Schema::hasTable($efTable)) {
            return back()->withErrors(['tables' => "La table EF '$efTable' est introuvable."]);
        }
        if (!Schema::hasTable($gdTable)) {
            return back()->withErrors(['tables' => "La table GD '$gdTable' est introuvable."]);
        }

        // --- Logic Selection based on Type ---
        $selectEF = "";
        $selectGD = "";
        $whereClause = "";
        $orderBy = "ABS(ecart) DESC";

        if ($type === 'initial') {
            // Initial Comparison (Valeur Initiale)
            // EF: INITIAL * PUMP
            // GD: SUM(INITIAL) * MAX(PUMP)
            $selectEF = "ARTICLE, DESIGNATION as design, (INITIAL * PUMP) as col_ef";
            $selectGD = "ARTICLE, (SUM(INITIAL) * MAX(PUMP)) as col_gd";
            $whereClause = "ABS(ef.col_ef - COALESCE(gd.col_gd, 0)) > 0.0005";
        } elseif ($type === 'pump') {
            // PUMP Comparison (GD uses MAX because PUMP is unique per article)
            $selectEF = "ARTICLE, DESIGNATION as design, PUMP as col_ef";
            $selectGD = "ARTICLE, MAX(PUMP) as col_gd";
            $whereClause = "ABS(ef.col_ef - COALESCE(gd.col_gd, 0)) > 0.0005";
        } else {
            // Default: Finale & Valeur Comparison
            $selectEF = "ARTICLE, DESIGNATION as design, FINALE as ef_finale, VALEUR as col_ef";
            $selectGD = "ARTICLE, SUM(FINALE) as gd_finale, SUM(VALEUR) as col_gd";
            // Check both Finale and Valeur differences
            $whereClause = "(ABS(ef.col_ef - COALESCE(gd.col_gd, 0)) > 0.0005 OR ABS(ef.ef_finale - COALESCE(gd.gd_finale, 0)) > 0.001)";
        }

        // Construct Dynamic Query
        $sql = "
            SELECT
                ef.ARTICLE,
                ef.design as designation,
                " . ($type === 'valeur' ? 'ef.ef_finale, gd.gd_finale,' : '') . "
                ROUND(ef.col_ef, 3) AS val_ef,
                ROUND(COALESCE(gd.col_gd, 0), 3) AS val_gd,
                ROUND(ef.col_ef - COALESCE(gd.col_gd, 0), 3) AS ecart
            FROM
                ( SELECT $selectEF FROM $efTable ) AS ef
            LEFT JOIN
                ( SELECT $selectGD FROM $gdTable GROUP BY ARTICLE ) AS gd 
            ON gd.ARTICLE = ef.ARTICLE
            WHERE $whereClause
            ORDER BY $orderBy
        ";

        $results = DB::select($sql);

        // Calculate Total Ecart
        $totalEcart = array_sum(array_column($results, 'ecart'));

        return view('audit.results', compact('results', 'annee', 'reseau', 'efTable', 'gdTable', 'totalEcart', 'type'));
    }

    public function export(Request $request)
    {
        $request->validate([
            'annee' => 'required|numeric|digits:4',
            'reseau' => 'required|string|in:BUS,FERRE',
            'type' => 'nullable|string|in:valeur,initial,pump',
        ]);

        $annee = $request->input('annee');
        $reseau = strtoupper($request->input('reseau'));
        $type = $request->input('type', 'valeur');

        $efTable = "RES_EF_{$reseau}_{$annee}";
        $gdTable = "RES_GD_{$reseau}_{$annee}";

        if (!Schema::hasTable($efTable) || !Schema::hasTable($gdTable)) {
             return back()->withErrors(['tables' => "Tables introuvables pour l'export."]);
        }

         // Reuse Logic (refactor ideal, but copying for safety/speed now)
         $selectEF = "";
         $selectGD = "";
         $whereClause = "";
         $orderBy = "ABS(ecart) DESC";
 
         if ($type === 'initial') {
             $selectEF = "ARTICLE, DESIGNATION as design, (INITIAL * PUMP) as col_ef";
             $selectGD = "ARTICLE, (SUM(INITIAL) * MAX(PUMP)) as col_gd";
             $whereClause = "ABS(ef.col_ef - COALESCE(gd.col_gd, 0)) > 0.0005";
         } elseif ($type === 'pump') {
             $selectEF = "ARTICLE, DESIGNATION as design, PUMP as col_ef";
             $selectGD = "ARTICLE, MAX(PUMP) as col_gd";
             $whereClause = "ABS(ef.col_ef - COALESCE(gd.col_gd, 0)) > 0.0005";
         } else {
             $selectEF = "ARTICLE, DESIGNATION as design, FINALE as ef_finale, VALEUR as col_ef";
             $selectGD = "ARTICLE, SUM(FINALE) as gd_finale, SUM(VALEUR) as col_gd";
             $whereClause = "(ABS(ef.col_ef - COALESCE(gd.col_gd, 0)) > 0.0005 OR ABS(ef.ef_finale - COALESCE(gd.gd_finale, 0)) > 0.001)";
         }
 
         $sql = "
             SELECT
                 ef.ARTICLE,
                 ef.design as designation,
                 " . ($type === 'valeur' ? 'ef.ef_finale, gd.gd_finale,' : '') . "
                 ROUND(ef.col_ef, 3) AS val_ef,
                 ROUND(COALESCE(gd.col_gd, 0), 3) AS val_gd,
                 ROUND(ef.col_ef - COALESCE(gd.col_gd, 0), 3) AS ecart
             FROM
                 ( SELECT $selectEF FROM $efTable ) AS ef
             LEFT JOIN
                 ( SELECT $selectGD FROM $gdTable GROUP BY ARTICLE ) AS gd 
             ON gd.ARTICLE = ef.ARTICLE
             WHERE $whereClause
             ORDER BY $orderBy
         ";
 
         $results = DB::select($sql);

         return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\AuditExport($results, $type, $efTable, $gdTable), "audit_{$reseau}_{$annee}_{$type}.xlsx");
    }
}
