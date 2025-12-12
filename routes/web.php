<?php

use Illuminate\Support\Facades\Route;


Route::get('/', function () {
    return view('welcome');
});

Route::get('/import', [\App\Http\Controllers\StockImportController::class, 'index'])->name('import.index');
Route::post('/import', [\App\Http\Controllers\StockImportController::class, 'store'])->name('import.store');

Route::get('/consultation', [\App\Http\Controllers\StockConsultationController::class, 'index'])->name('consultation.index');
Route::get('/consultation/{tableName}', [\App\Http\Controllers\StockConsultationController::class, 'show'])->name('consultation.show');
Route::get('/consultation/{tableName}/export', [\App\Http\Controllers\StockConsultationController::class, 'export'])->name('consultation.export');

Route::get('/audit', [\App\Http\Controllers\StockAuditController::class, 'index'])->name('audit.index');
Route::post('/audit/compare', [\App\Http\Controllers\StockAuditController::class, 'compare'])->name('audit.compare');
Route::get('/audit/export', [\App\Http\Controllers\StockAuditController::class, 'export'])->name('audit.export');