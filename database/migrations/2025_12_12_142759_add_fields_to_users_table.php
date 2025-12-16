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
        Schema::table('users', function (Blueprint $table) {
            $table->string('nom')->after('name')->nullable();
            $table->string('prenom')->after('nom')->nullable();
            $table->string('unite')->after('prenom')->nullable();
            $table->string('matricule')->after('email')->unique();
            $table->string('username')->after('matricule')->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['nom', 'prenom', 'unite', 'matricule', 'username']);
        });
    }
};
