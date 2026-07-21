<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\RequisitionController;
use App\Http\Controllers\DeliveryController;

/*
|--------------------------------------------------------------------------
| Web Routes — Nexora ERP Procurement Suite
|--------------------------------------------------------------------------
| Each module (Purchase Orders, Suppliers, Requisitions, Deliveries) has
| its own controller and its own Blade view under resources/views/pages.
*/

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::prefix('purchase-orders')->name('purchase-orders.')->group(function () {
    Route::get('/', [PurchaseOrderController::class, 'index'])->name('index');
    Route::get('/approved', [PurchaseOrderController::class, 'approved'])->name('approved');
    Route::post('/', [PurchaseOrderController::class, 'store'])->name('store');
    Route::put('/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->name('update');
    Route::delete('/{purchaseOrder}', [PurchaseOrderController::class, 'destroy'])->name('destroy');
});

Route::prefix('suppliers')->name('suppliers.')->group(function () {
    Route::get('/', [SupplierController::class, 'index'])->name('index');
    Route::post('/', [SupplierController::class, 'store'])->name('store');
    Route::put('/{supplier}', [SupplierController::class, 'update'])->name('update');
    Route::delete('/{supplier}', [SupplierController::class, 'destroy'])->name('destroy');
});

Route::prefix('requisitions')->name('requisitions.')->group(function () {
    Route::get('/', [RequisitionController::class, 'index'])->name('index');
    Route::post('/', [RequisitionController::class, 'store'])->name('store');
    Route::put('/{requisition}', [RequisitionController::class, 'update'])->name('update');
    Route::delete('/{requisition}', [RequisitionController::class, 'destroy'])->name('destroy');
});

Route::prefix('deliveries')->name('deliveries.')->group(function () {
    Route::get('/', [DeliveryController::class, 'index'])->name('index');
    Route::post('/', [DeliveryController::class, 'store'])->name('store');
    Route::put('/{delivery}', [DeliveryController::class, 'update'])->name('update');
    Route::delete('/{delivery}', [DeliveryController::class, 'destroy'])->name('destroy');
});
