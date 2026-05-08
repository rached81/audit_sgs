<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_operation_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('run_id')->index();
            $table->string('table_name')->nullable()->index();
            $table->string('operation', 64)->index();
            $table->string('status', 32)->default('success');
            // Keep nullable user reference without FK to support legacy/provisioned user tables
            // where users.id may be missing PK/AI constraints.
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_matricule')->nullable();
            $table->string('user_name')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->text('message')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_operation_logs');
    }
};

