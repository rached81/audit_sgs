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
