<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('RES_GD_2017', function (Blueprint $table) {
            $table->engine    = 'InnoDB';
            $table->charset   = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->bigIncrements('id');

            $table->unsignedInteger('ARTICLE')->index();
            $table->string('DESIGNATION', 255);
            $table->decimal('INITIAL', 12, 3)->default(0);
            $table->decimal('ENTREE', 12, 3)->default(0);
            $table->decimal('SORTIE', 12, 3)->default(0);
            $table->decimal('FINALE', 12, 3)->default(0);
            $table->decimal('PUMP',   15, 5)->default(0);
            $table->decimal('VALEUR', 15, 3)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('RES_GD_2017');
    }
};
