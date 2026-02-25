<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;

class CreateStockTable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stock:create-table {table : The name of the table to create} {--force : Drop the table if it exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new stock table with the standard schema';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tableName = strtolower($this->argument('table'));
        $force = $this->option('force');

        if ($force) {
            Schema::dropIfExists($tableName);
            $this->info("Table '{$tableName}' dropped (force).");
        }

        // Check again after potential drop
        if (Schema::hasTable($tableName)) {
            $this->error("Table '{$tableName}' already exists.");
            return 1;
        }

        try {
            Schema::create($tableName, function (Blueprint $table) {
                $table->engine    = 'InnoDB';
                $table->charset   = 'utf8mb4';
                $table->collation = 'utf8mb4_general_ci';

                $table->bigIncrements('id');

                    $table->string('ARTICLE', 64)->index();
                $table->string('DESIGNATION', 255);
                $table->decimal('INITIAL', 12, 3)->default(0);
                $table->decimal('ENTREE', 12, 3)->default(0);
                $table->decimal('SORTIE', 12, 3)->default(0);
                $table->decimal('FINALE', 12, 3)->default(0);
                $table->decimal('PUMP',   15, 5)->default(0);
                $table->decimal('VALEUR', 15, 3)->default(0);
            });

            $this->info("Table '{$tableName}' created successfully.");
        } catch (QueryException $e) {
            // Check for "Table already exists" error code specifically
             if ($e->getCode() == '42S01') { // SQLSTATE[42S01]
                $this->error("Table '{$tableName}' already exists (caught exception).");
                return 1;
            }
            throw $e;
        }

        return 0;
    }
}
