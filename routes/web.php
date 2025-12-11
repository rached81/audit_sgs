<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/import', [\App\Http\Controllers\StockImportController::class, 'index'])->name('import.index');
Route::post('/import', [\App\Http\Controllers\StockImportController::class, 'store'])->name('import.store');

Route::get('/consultation', [\App\Http\Controllers\StockConsultationController::class, 'index'])->name('consultation.index');
