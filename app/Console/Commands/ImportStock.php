<?php

namespace App\Console\Commands;

use App\Services\StockCsvImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportStock extends Command
{
//php artisan stock:import storage/app/res_ef_2017.xlsx
// RES_EF_2017 --truncate
//php artisan stock:import storage/app/res_gd_2018.xlsx RES_GD_2017 --truncate
    protected $signature = 'stock:import {file} {table} {--truncate}';
    protected $description = 'Import Excel into RES_EF_2017 or RES_GD_2017';

    public function handle()
    {
        $file  = $this->argument('file');
        $table = strtoupper($this->argument('table'));
        $fullPath = is_file($file) ? $file : storage_path('app/' . $file);

        if (!Schema::hasTable($table)) {
            $this->error("Table '$table' does not exist. Please run 'php artisan stock:create-table $table' first.");
            return 1;
        }

        if ($this->option('truncate')) {
            DB::table($table)->truncate();
        }

        $mapping = [
            'article' => 'ARTICLE',
            'designation' => 'DESIGNATION',
            'initial' => 'INITIAL',
            'entree' => 'ENTREE',
            'sortie' => 'SORTIE',
            'finale' => 'FINALE',
            'pump' => 'PUMP',
            'valeur' => 'VALEUR',
        ];

        $importer = app(StockCsvImporter::class);
        $normalized = $importer->normalizeToCsv($fullPath, $mapping, 1, null);
        $rows = $importer->importNormalizedCsv($normalized['csv_path'], $table);

        \Illuminate\Support\Facades\Storage::delete($normalized['relative_csv_path']);

        $this->info("Import OK vers $table ({$rows} lignes)");
        return 0;
    }
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
//    protected $signature = 'app:import-stock';
//
//    /**
//     * The console command description.
//     *
//     * @var string
//     */
//    protected $description = 'Command description';
//
//    /**
//     * Execute the console command.
//     */
//    public function handle()
//    {
//        //
//    }
}
