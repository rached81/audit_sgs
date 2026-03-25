<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StockImportController;
use App\Http\Controllers\StockConsultationController;
use App\Http\Controllers\StockAuditController;
use App\Http\Controllers\ImportArchiveController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Auth Routes
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Protected Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/', function () {
        return redirect()->route('import.form');
    });

    // Import
    Route::get('/import', [StockImportController::class, 'showForm'])->name('import.form');
    Route::post('/import', [StockImportController::class, 'import'])->name('import.process');
    Route::post('/import/mapping', [StockImportController::class, 'processMappedImport'])->name('import.process_mapping');
    Route::get('/import/status', [StockImportController::class, 'checkStatus'])->name('import.status');
    Route::get('/import/events', [StockImportController::class, 'events'])->name('import.events');

    // Consultation
    Route::get('/consultation', [StockConsultationController::class, 'index'])->name('consultation.index');
    Route::get('/consultation/{tableName}', [StockConsultationController::class, 'show'])->name('consultation.show');
    Route::get('/consultation/{tableName}/export', [StockConsultationController::class, 'export'])->name('consultation.export');

    // Audit
    Route::get('/audit', [StockAuditController::class, 'index'])->name('audit.index');
    Route::get('/audit/compare', [StockAuditController::class, 'compare'])->name('audit.compare');
    Route::get('/audit/export', [StockAuditController::class, 'export'])->name('audit.export');

    // Archive (original uploaded files kept in import_debug when debug is enabled)
    Route::get('/archive', [ImportArchiveController::class, 'index'])->name('archive.index');
    Route::get('/archive/{table}/{runId}/{filename}', [ImportArchiveController::class, 'download'])
        ->where([
            'table' => '[A-Za-z0-9_]+',
            'runId' => '[A-Fa-f0-9\-]+',
            'filename' => '.*',
        ])
        ->name('archive.download');

    // User Management
    Route::middleware(['admin'])->group(function () {
        Route::delete('/consultation/{tableName}', [StockConsultationController::class, 'destroy'])->name('consultation.destroy');
        Route::resource('users', UserController::class);
    });
});