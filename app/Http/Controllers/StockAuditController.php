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

        // --- Ecart Calculation Expression ---
        $ecartExpr = "0";
        $whereClause = "1=1";

        if ($type === 'initial') {
            // Ecart = (EF_INITIAL * EF_PUMP) - (GD_SUM_INITIAL * GD_MAX_PUMP)
            $ecartExpr = "(ef.INITIAL * ef.PUMP) - (COALESCE(gd.gd_initial,0) * COALESCE(gd.gd_pump,0))";
//            $whereClause = "ABS($ecartExpr) > 0.005";
            $whereClause = "ABS(ef.INITIAL  - (COALESCE(gd.gd_initial,0))) > 0.005";
        } elseif ($type === 'pump') {
             // Ecart = EF_PUMP - GD_MAX_PUMP
            $ecartExpr = "ef.PUMP - COALESCE(gd.gd_pump,0)";
            $whereClause = "ABS($ecartExpr) > 0.0005";
        } else {
            // Default: Valeur
            // Ecart = EF_VALEUR - GD_SUM_VALEUR
            $ecartExpr = "ef.VALEUR - COALESCE(gd.gd_valeur,0)";
            $whereClause = "(ABS($ecartExpr) > 0.005 OR ABS(ef.FINALE - COALESCE(gd.gd_finale, 0)) > 0.001)";
        }

        // Construct Dynamic Query to fetch ALL columns
        $sql = "
            SELECT
                ef.ARTICLE,
                ef.DESIGNATION as designation,

                -- EF Columns
                ef.INITIAL as ef_initial,
                ef.ENTREE as ef_entree,
                ef.SORTIE as ef_sortie,
                ef.FINALE as ef_finale,
                ef.PUMP as ef_pump,
                ef.VALEUR as ef_valeur,

                -- GD Columns
                COALESCE(gd.gd_initial, 0) as gd_initial,
                COALESCE(gd.gd_entree, 0) as gd_entree,
                COALESCE(gd.gd_sortie, 0) as gd_sortie,
                COALESCE(gd.gd_finale, 0) as gd_finale,
                COALESCE(gd.gd_pump, 0) as gd_pump,
                COALESCE(gd.gd_valeur, 0) as gd_valeur,

                -- Calculated Ecart
                ROUND($ecartExpr, 3) AS ecart

            FROM
                $efTable AS ef
            LEFT JOIN
                (
                    SELECT
                        ARTICLE,
                        SUM(INITIAL) as gd_initial,
                        SUM(ENTREE) as gd_entree,
                        SUM(SORTIE) as gd_sortie,
                        SUM(FINALE) as gd_finale,
                        MAX(PUMP) as gd_pump,
                        SUM(VALEUR) as gd_valeur
                    FROM
                        $gdTable
                    GROUP BY
                        ARTICLE
                ) AS gd ON gd.ARTICLE = ef.ARTICLE
            WHERE
               $whereClause
            ORDER BY
                ABS(ecart) DESC
        ";

        // Note: Optimized to query tables directly instead of subquery for EF, as EF is already unique by Article usually?
        // Or if EF needs aggregation, we assume EF is already 'Etat Final' one row per article.
        // The previous code had `SELECT * FROM efTable` inside a subquery, which is redundant if we alias the table directly.
        // Assuming EF table has unique ARTICLE key.

        $results = DB::select($sql);

        // Calculate Total Ecart
        $totalEcart = array_sum(array_column($results, 'ecart'));

        return view('audit.index', compact('results', 'annee', 'reseau', 'efTable', 'gdTable', 'totalEcart', 'type'));
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

        // --- Ecart Calculation Expression (Mirrors compare method) ---
        $ecartExpr = "0";
        $whereClause = "1=1";

        if ($type === 'initial') {
            // Ecart = (EF_INITIAL * EF_PUMP) - (GD_SUM_INITIAL * GD_MAX_PUMP)
            $ecartExpr = "(ef.INITIAL * ef.PUMP) - (COALESCE(gd.gd_initial,0) * COALESCE(gd.gd_pump,0))";
            $whereClause = "ABS(ef.INITIAL  - (COALESCE(gd.gd_initial,0))) > 0.005";
        } elseif ($type === 'pump') {
             // Ecart = EF_PUMP - GD_MAX_PUMP
            $ecartExpr = "ef.PUMP - COALESCE(gd.gd_pump,0)";
            $whereClause = "ABS($ecartExpr) > 0.0005";
        } else {
            // Default: Valeur
            // Ecart = EF_VALEUR - GD_SUM_VALEUR
            $ecartExpr = "ef.VALEUR - COALESCE(gd.gd_valeur,0)";
            $whereClause = "(ABS($ecartExpr) > 0.005 OR ABS(ef.FINALE - COALESCE(gd.gd_finale, 0)) > 0.001)";
        }

        // Construct Dynamic Query to fetch ALL columns (Mirrors compare method)
        $sql = "
            SELECT
                ef.ARTICLE,
                ef.DESIGNATION as designation,

                -- EF Columns
                ef.INITIAL as ef_initial,
                ef.ENTREE as ef_entree,
                ef.SORTIE as ef_sortie,
                ef.FINALE as ef_finale,
                ef.PUMP as ef_pump,
                ef.VALEUR as ef_valeur,

                -- GD Columns
                COALESCE(gd.gd_initial, 0) as gd_initial,
                COALESCE(gd.gd_entree, 0) as gd_entree,
                COALESCE(gd.gd_sortie, 0) as gd_sortie,
                COALESCE(gd.gd_finale, 0) as gd_finale,
                COALESCE(gd.gd_pump, 0) as gd_pump,
                COALESCE(gd.gd_valeur, 0) as gd_valeur,

                -- Calculated Ecart
                ROUND($ecartExpr, 3) AS ecart

            FROM
                $efTable AS ef
            LEFT JOIN
                (
                    SELECT
                        ARTICLE,
                        SUM(INITIAL) as gd_initial,
                        SUM(ENTREE) as gd_entree,
                        SUM(SORTIE) as gd_sortie,
                        SUM(FINALE) as gd_finale,
                        MAX(PUMP) as gd_pump,
                        SUM(VALEUR) as gd_valeur
                    FROM
                        $gdTable
                    GROUP BY
                        ARTICLE
                ) AS gd ON gd.ARTICLE = ef.ARTICLE
            WHERE
               $whereClause
            ORDER BY
                ABS(ecart) DESC
        ";

        $results = DB::select($sql);

        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\AuditExport($results, $type, $efTable, $gdTable), "audit_{$reseau}_{$annee}_{$type}.xlsx");
    }
}
