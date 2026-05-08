<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $indexNames = collect(DB::select('SHOW INDEX FROM users'))
            ->pluck('Key_name')
            ->unique()
            ->values()
            ->all();

        Schema::table('users', function (Blueprint $table) {
            // Legacy-provisioned DBs may not have these original unique indexes.
        });

        Schema::table('users', function (Blueprint $table) use ($indexNames) {
            if (in_array('users_email_unique', $indexNames, true)) {
                $table->dropUnique('users_email_unique');
            }
            if (in_array('users_matricule_unique', $indexNames, true)) {
                $table->dropUnique('users_matricule_unique');
            }
            if (in_array('users_username_unique', $indexNames, true)) {
                $table->dropUnique('users_username_unique');
            }
        });

        // Use prefix length to remain compatible with legacy InnoDB index limits.
        DB::statement('CREATE UNIQUE INDEX users_email_deleted_at_unique ON users (email(191), deleted_at)');
        DB::statement('CREATE UNIQUE INDEX users_matricule_deleted_at_unique ON users (matricule(191), deleted_at)');
        DB::statement('CREATE UNIQUE INDEX users_username_deleted_at_unique ON users (username(191), deleted_at)');
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

