<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
            $table->dropUnique('users_matricule_unique');
            $table->dropUnique('users_username_unique');

            $table->unique(['email', 'deleted_at'], 'users_email_deleted_at_unique');
            $table->unique(['matricule', 'deleted_at'], 'users_matricule_deleted_at_unique');
            $table->unique(['username', 'deleted_at'], 'users_username_deleted_at_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_deleted_at_unique');
            $table->dropUnique('users_matricule_deleted_at_unique');
            $table->dropUnique('users_username_deleted_at_unique');

            $table->unique('email', 'users_email_unique');
            $table->unique('matricule', 'users_matricule_unique');
            $table->unique('username', 'users_username_unique');
        });
    }
};

