<?php

use App\Http\Controllers\InventoryExportController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::middleware('auth')->group(function (): void {
    Route::get('/exports/current-stock.csv', [InventoryExportController::class, 'currentStock'])
        ->name('exports.current-stock');
    Route::get('/exports/movement-history.csv', [InventoryExportController::class, 'movements'])
        ->name('exports.movement-history');
});
