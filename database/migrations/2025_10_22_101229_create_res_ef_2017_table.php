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

Schema::create('RES_EF_2017', function (Blueprint $table) {
        // Moteur/charset (optionnel mais recommandé)
        $table->engine    = 'InnoDB';
        $table->charset   = 'utf8mb4';
        $table->collation = 'utf8mb4_general_ci';

        // PK technique
        $table->bigIncrements('id');

        // Tes colonnes
        $table->unsignedInteger('ARTICLE')->index();       // ARTCOD (8 chiffres)
        $table->string('DESIGNATION', 255);                // ARTLIB
        $table->decimal('INITIAL', 12, 3)->default(0);     // DEPART
        $table->decimal('ENTREE', 12, 3)->default(0);      // ENTRE
        $table->decimal('SORTIE', 12, 3)->default(0);      // SORTIE
        $table->decimal('FINALE', 12, 3)->default(0);      // ACTUEL
        $table->decimal('PUMP',   15, 5)->default(0);      // PUMP.N
        $table->decimal('VALEUR', 15, 3)->default(0);      // Valorisation

        // Pas de timestamps si tu n’en veux pas :
        // $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('RES_EF_2017');
    }
};
