<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\EstimateController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SettingController;

Route::get('login', [AuthController::class, 'showLogin'])->name('login');
Route::post('login', [AuthController::class, 'login'])->name('login.post');
Route::post('logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/', function () {
        return redirect()->route('dashboard');
    });

    Route::resource('customers', CustomerController::class);

    // Route::resource('leads', LeadController::class);
    // Route::post('leads/{lead}/done', [LeadController::class, 'markAsDone'])->name('leads.done');

    // Deals
    Route::get('/deals', [DealController::class, 'index'])->name('deals.index');
    Route::post('/deals', [DealController::class, 'store'])->name('deals.store');
    Route::post('/deals/{deal}/stage', [DealController::class, 'updateStage'])->name('deals.updateStage');

    Route::resource('estimates', EstimateController::class);
    Route::post('estimates/{estimate}/accept', [EstimateController::class, 'markAsAccepted'])->name('estimates.accept');
    Route::post('estimates/{estimate}/reject', [EstimateController::class, 'markAsRejected'])->name('estimates.reject');
    Route::post('estimates/{estimate}/convert', [
        EstimateController::class,
        'convertToInvoice'
    ])->name('estimates.convert');
    Route::post('estimates/{estimate}/status', [EstimateController::class, 'updateStatus'])->name('estimates.updateStatus');

    Route::get('invoices/ready', [InvoiceController::class, 'ready'])->name('invoices.ready');
    Route::get('invoices/invoiced', [InvoiceController::class, 'invoiced'])->name('invoices.invoiced');
    Route::get('invoices/rejected', [InvoiceController::class, 'rejected'])->name('invoices.rejected');
    Route::get('invoices/proforma', [InvoiceController::class, 'proforma'])->name('invoices.proforma');
    Route::resource('invoices', InvoiceController::class);

    // Super Admin Only Routes
    Route::middleware(['role:Super Admin'])->group(function () {
        Route::resource('users', UserController::class);

        // Settings
        Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
        Route::post('settings/general', [SettingController::class, 'updateGeneral'])->name('settings.updateGeneral');
        Route::post('settings/tax', [SettingController::class, 'updateTax'])->name('settings.updateTax');
        Route::post('settings/managers', [SettingController::class, 'storeManager'])->name('settings.storeManager');
        Route::get('settings/managers/{manager}/delete', [SettingController::class, 'destroyManager'])->name('settings.destroyManager.get');
        Route::delete('settings/managers/{manager}', [SettingController::class, 'destroyManager'])->name('settings.destroyManager');
        Route::put('settings/managers/{manager}', [SettingController::class, 'updateManager'])->name('settings.updateManager');
        Route::post('settings/terms', [SettingController::class, 'storeTerm'])->name('settings.storeTerm');
        Route::get('settings/terms/{term}/delete', [SettingController::class, 'destroyTerm'])->name('settings.destroyTerm.get');
        Route::delete('settings/terms/{term}', [SettingController::class, 'destroyTerm'])->name('settings.destroyTerm');
        Route::put('settings/terms/{term}', [SettingController::class, 'updateTerm'])->name('settings.updateTerm');
        Route::post('settings/currencies', [SettingController::class, 'storeCurrency'])->name('settings.storeCurrency');
        Route::get('settings/currencies/{currency}/delete', [SettingController::class, 'destroyCurrency'])->name('settings.destroyCurrency.get');
        Route::delete('settings/currencies/{currency}', [SettingController::class, 'destroyCurrency'])->name('settings.destroyCurrency');
        Route::put('settings/currencies/{currency}', [SettingController::class, 'updateCurrency'])->name('settings.updateCurrency');
    });
});