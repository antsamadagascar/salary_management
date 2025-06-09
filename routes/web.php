<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ErpController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PayrollController;

use App\Http\Controllers\SalaryDetailsController;
use App\Http\Controllers\ResetDataController;
use App\Http\Controllers\PayrollStatsController;

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

    //Routes pour les fonctionnalites listes employes avec filtrages
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
    
    Route::prefix('payroll')->name('payroll.')->group(function () {
        Route::get('/', [PayrollController::class, 'index'])->name('index');
        Route::get('/search', [PayrollController::class, 'search'])->name('search');
        Route::get('/employee/{employeeId}', [PayrollController::class, 'show'])->name('employee.show');
        
        Route::get('/salary-slip/{salarySlipId}', [PayrollController::class, 'showSalarySlip'])
            ->name('salary-slip.show')
            ->where('salarySlipId', '.*');
        
        Route::get('/employee/{employeeId}/month/{month}/pdf', [PayrollController::class, 'exportMonthlyPdf'])->name('employee.monthly.pdf');
        Route::get('/export/excel', [PayrollController::class, 'exportEmployeesExcel'])->name('export.excel');

         // Routes pour les statistiques 
        Route::prefix('stats')->name('stats.')->group(function () {
            Route::get('/', [PayrollStatsController::class, 'index'])->name('index');
            Route::get('/month/{month}', [PayrollStatsController::class, 'showMonthDetails'])->name('month-details');
            Route::get('/export', [PayrollStatsController::class, 'exportMonthlyStats'])->name('export');
            Route::get('/export/month/{month}', [PayrollStatsController::class, 'exportMonthDetails'])->name('export-month');
            Route::get('/graphs', [PayrollStatsController::class, 'graphsIndex'])->name('graphs');
            Route::get('/chart-data', [PayrollStatsController::class, 'getChartData'])->name('chart-data');
            Route::get('/api/chart-data', [PayrollStatsController::class, 'getChartData'])->name('api.chart-data');
            Route::get('/api/yearly-stats', [PayrollStatsController::class, 'getYearlyStats'])->name('api.yearly-stats');
            Route::get('/salary-details', [SalaryDetailsController::class, 'getSalaryDetails'])->name('salary-details');
        });

    });
    
    Route::prefix('stats')->name('stats.')->group(function () {
        Route::get('/', [SalaryDetailsController::class, 'index'])->name('index');
        Route::get('/data', [SalaryDetailsController::class, 'getPayrollData'])->name('data');
        Route::get('/export', [SalaryDetailsController::class, 'exportCsv'])->name('export');
    });

    Route::prefix('reset-data')->name('reset-data.')->group(function () {
        Route::get('/', [ResetDataController::class, 'showConfirmation'])->name('show');
        Route::get('/check', [ResetDataController::class, 'checkData'])->name('check');
        Route::post('/all', [ResetDataController::class, 'resetAllData'])->name('all');
        Route::post('/table/{table}', [ResetDataController::class, 'resetSpecificTable'])->name('table');
        Route::post('/confirm', [ResetDataController::class, 'confirmReset'])->name('confirm');
    });
    
});