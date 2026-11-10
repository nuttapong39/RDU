<?php

use App\Http\Controllers\Admin\DatabaseConfigController;
use App\Http\Controllers\Admin\DrugCodeEditorController;
use App\Http\Controllers\Admin\FacilityController;
use App\Http\Controllers\Admin\FontConfigController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CountingMethodController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DrugCodeSetController;
use App\Http\Controllers\FacilityLogoController;
use App\Http\Controllers\HdcComparisonController;
use Illuminate\Support\Facades\Route;

// ---- Public ----
Route::get('/', fn () => redirect()->route('dashboard'));

// The hospital's logo is shown on the login page too, so it is served without signing in.
Route::get('/facility/logo', FacilityLogoController::class)->name('facility.logo');

// ---- Auth ----
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:5,1');
});
Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// ---- Dashboard (auth required) ----
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/kpi/{code}', [DashboardController::class, 'kpi'])->name('dashboard.kpi');
    Route::get('/dashboard/kpi/{code}/hdc', [HdcComparisonController::class, 'show'])->name('dashboard.kpi.hdc');
    Route::get('/dashboard/drug-code-sets', [DrugCodeSetController::class, 'index'])->name('drug-code-sets.index');
    Route::middleware('can:edit-drug-code-sets')->group(function () {
        Route::get('/dashboard/drug-code-sets/catalog', [DrugCodeSetController::class, 'catalog'])->name('drug-code-sets.catalog');
        Route::get('/dashboard/drug-code-sets/lab-items', [DrugCodeSetController::class, 'labItems'])->name('drug-code-sets.lab-items');
        Route::put('/dashboard/drug-code-sets/{set}', [DrugCodeSetController::class, 'update'])->name('drug-code-sets.update');
    });
    Route::get('/dashboard/counting-methods', [CountingMethodController::class, 'index'])->name('dashboard.counting-methods');
});

// ---- Admin (auth required) ----
Route::middleware(['auth', 'can:manage-settings'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/settings/database', [DatabaseConfigController::class, 'show'])->name('database.show');
    Route::post('/settings/database', [DatabaseConfigController::class, 'update'])->name('database.update');
    Route::post('/settings/database/test', [DatabaseConfigController::class, 'test'])->name('database.test');
    Route::post('/settings/fonts', [FontConfigController::class, 'update'])->name('fonts.update');
    Route::post('/settings/fonts/reset', [FontConfigController::class, 'reset'])->name('fonts.reset');
    Route::get('/facility', [FacilityController::class, 'show'])->name('facility.show');
    Route::post('/facility', [FacilityController::class, 'update'])->name('facility.update');
    Route::get('/drug-code-editors', [DrugCodeEditorController::class, 'index'])->name('drug-code-editors.index');
    Route::post('/drug-code-editors', [DrugCodeEditorController::class, 'store'])->name('drug-code-editors.store');
    Route::delete('/drug-code-editors/{editor}', [DrugCodeEditorController::class, 'destroy'])->name('drug-code-editors.destroy');
});
