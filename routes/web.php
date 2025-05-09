<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\ReportController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware(['auth'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Products
    Route::get('/products/sync', [ProductController::class, 'sync'])->name('products.sync');
    Route::resource('products', ProductController::class);
    
    // Transactions
    Route::get('/transactions/sync', [TransactionController::class, 'sync'])->name('transactions.sync');
    Route::resource('transactions', TransactionController::class)->except(['edit', 'update', 'destroy']);
    
    // Reports
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/inventory', [ReportController::class, 'inventory'])->name('reports.inventory');
    Route::get('/reports/transactions', [ReportController::class, 'transactions'])->name('reports.transactions');
    Route::get('/reports/sales', [ReportController::class, 'sales'])->name('reports.sales');
});

require __DIR__.'/auth.php';