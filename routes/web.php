<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ErpController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PayrollController;

use App\Http\Controllers\StatsSalaryController;
use App\Http\Controllers\ResetDataController;

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
    
    // Routes pour la gestion de la paie
    Route::prefix('payroll')->name('payroll.')->group(function () {
        
        // Liste des employés
        Route::get('/', [PayrollController::class, 'index'])->name('index');
        
        // Recherche d'employés
        Route::get('/search', [PayrollController::class, 'search'])->name('search');
        
        // Fiche employé avec salaires par mois
        Route::get('/employee/{employeeId}', [PayrollController::class, 'show'])->name('employee.show');
        
        // Afficher une fiche de paie spécifique
        Route::get('/salary-slip/{salarySlipId}', [PayrollController::class, 'showSalarySlip'])->name('salary-slip.show');
        
        // Exports PDF
        Route::get('/salary-slip/{salarySlipId}/pdf', [PayrollController::class, 'exportSalarySlipPdf'])->name('salary-slip.pdf');
        Route::get('/employee/{employeeId}/month/{month}/pdf', [PayrollController::class, 'exportMonthlyPdf'])->name('employee.monthly.pdf');
        
        // Export Excel
        Route::get('/export/excel', [PayrollController::class, 'exportEmployeesExcel'])->name('export.excel');
        
    });

    Route::prefix('stats')->name('stats.')->group(function () {
        Route::get('/', [StatsSalaryController::class, 'index'])->name('index');
        Route::get('/data', [StatsSalaryController::class, 'getPayrollData'])->name('data');
        Route::get('/export', [StatsSalaryController::class, 'exportCsv'])->name('export');
    });

    Route::get('reset-data', [ResetDataController::class, 'showConfirmation'])
        ->name('reset-data.show');
    
    // Vérifier les données existantes
    Route::get('reset-data/check', [ResetDataController::class, 'checkData'])
        ->name('reset-data.check');
    
    // Réinitialiser toutes les données
    Route::post('reset-data/all', [ResetDataController::class, 'resetAllData'])
        ->name('reset-data.all');
    
    // Réinitialiser une table spécifique
    Route::post('reset-data/table/{table}', [ResetDataController::class, 'resetSpecificTable'])
        ->name('reset-data.table');
    
    // API de confirmation avec double vérification
    Route::post('reset-data/confirm', [ResetDataController::class, 'confirmReset'])
        ->name('reset-data.confirm');
});