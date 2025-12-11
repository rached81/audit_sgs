<?php

namespace App\Console\Commands;

use App\Imports\StockImport;
use App\Models\ResEf2016;
use App\Models\ResEf2017;
use App\Models\ResEf2018;
use App\Models\ResGd2016;
use App\Models\ResGd2017;
use App\Models\ResGd2018;
use Illuminate\Console\Command;
//use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel;

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

        $model = match ($table) {
            'RES_EF_2016' => ResEf2016::class,
            'RES_GD_2016' => ResGd2016::class,
            'RES_EF_2017' => ResEf2017::class,
            'RES_GD_2017' => ResGd2017::class,
            'RES_EF_2018' => ResEf2018::class,
            'RES_GD_2018' => ResGd2018::class,
            default       => null,
        };
        if (!$model) { $this->error('Table inconnue'); return 1; }

        if ($this->option('truncate')) { $model::truncate(); }

//        Excel::import(new StockImport($model), $file);
        Excel::import(new StockImport($model), is_file($file) ? $file : storage_path('app/'.$file));
        $this->info("Import OK vers $table");
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
