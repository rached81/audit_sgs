<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;
use App\Imports\StockImport;

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
     * Handle the file upload and import.
     */
    public function import(Request $request)
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
        $file = $request->file('file');

        // 1. Create Table if it doesn't exist
        // We can use the artisan command logic we created, or direct Schema calls.
        // Calling Artisan is compliant with our "reuse" strategy.
        // But Artisan::call might be slow/heavy. Let's use Schema directly or call the command if we want the "force" option logic.
        // For simplicity and reusing our "CreateStockTable" logic (which enforces specific schema), let's call the command.
        // But wait, user might not want to "Force" via web UI by default? Let's check existence first.

        // 0. Validate File Structure
        try {
            $headings = (new HeadingRowImport)->toArray($file);
            // $headings is array of sheets, each sheet has array of rows, we need first sheet, first row of headings
            $fileHeaders = $headings[0][0] ?? [];

            // Normalize headers
            $normalizedHeaders = array_map(function($h) {
                return \Illuminate\Support\Str::slug($h, '');
            }, $fileHeaders);

            $requiredColumns = ['article', 'designation', 'initial', 'entree', 'sortie', 'finale', 'pump', 'valeur'];
            
            // Check if all required columns are present
            $missingColumns = array_diff($requiredColumns, $normalizedHeaders);

            if (!empty($missingColumns)) {
                 return back()->withErrors(['file' => 'Structure incorrecte. Colonnes manquantes ou mal nommées : ' . implode(', ', $missingColumns)]);
            }

        } catch (\Exception $e) {
            return back()->withErrors(['file' => 'Impossible de lire le fichier pour validation : ' . $e->getMessage()]);
        }

        // 1. Check Table Status
        if (Schema::hasTable($tableName)) {
            $count = DB::table($tableName)->count();
            if ($count > 0) {
                 return back()->withErrors(['table_name' => "La table '$tableName' existe déjà et contient $count lignes. Veuillez choisir un autre nom ou vider la table manuellement."]);
            }
            // Table exists but is empty, we can proceed.
        } else {
             // Table does not exist, create it.
            $exitCode = Artisan::call('stock:create-table', [
                'table' => $tableName
            ]);

            if ($exitCode !== 0) {
                return back()->withErrors(['table_name' => 'Échec de la création de la table.']);
            }
        }

        // 2. Import File
        try {
            Excel::import(new StockImport($tableName), $file);
            return back()->with('success', "Succès ! Les données ont été importées dans la table [$tableName].");
        } catch (\Exception $e) {
            return back()->withErrors(['file' => 'Erreur lors de l\'import : ' . $e->getMessage()]);
        }
    }
}
