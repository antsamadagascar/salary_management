<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ErpController;
use App\Http\Controllers\InvoiceController;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);

// Routes protégées par l'authentification Frappe
Route::middleware([\App\Http\Middleware\FrappeAuthMiddleware::class])->group(function () {
    Route::get('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/user', [AuthController::class, 'getLoggedUser'])->name('user.info');
    Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('import')->name('import.')->group(function () {
        Route::get('/', [App\Http\Controllers\ImportController::class, 'showImportForm'])->name('form');
        Route::post('/process', [App\Http\Controllers\ImportController::class, 'processImport'])->name('process');
        Route::post('/preview', [App\Http\Controllers\ImportController::class, 'previewFiles'])->name('preview');
    });

    Route::prefix('employees')->name('employees.')->group(function () {
        Route::get('/', [App\Http\Controllers\EmployeeController::class, 'index'])->name('index');
        Route::get('/search', [App\Http\Controllers\EmployeeController::class, 'search'])->name('search');
        Route::get('/create', [App\Http\Controllers\EmployeeController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\EmployeeController::class, 'store'])->name('store');
        Route::get('/{name}', [App\Http\Controllers\EmployeeController::class, 'show'])->name('show');
        Route::get('/{name}/edit', [App\Http\Controllers\EmployeeController::class, 'edit'])->name('edit');
        Route::put('/{name}', [App\Http\Controllers\EmployeeController::class, 'update'])->name('update');
        Route::delete('/{name}', [App\Http\Controllers\EmployeeController::class, 'destroy'])->name('destroy');
    });
    
    
});