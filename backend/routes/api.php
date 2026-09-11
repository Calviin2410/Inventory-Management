<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BarrelController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\DashboardController;
use Illuminate\Support\Facades\Route;

// ---- 公开路由(不需要登录) ----
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// ---- 需要登录(Sanctum token)的路由 ----
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/dashboard-summary',[DashboardController::class, 'summary']);

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::apiResource('products', ProductController::class);

    Route::get('/products/{product}/stock-movements', [StockMovementController::class, 'index']);
    Route::post('/products/{product}/stock-movements', [StockMovementController::class, 'store']);

    Route::apiResource('customers', CustomerController::class)->only(['index', 'store']);
    Route::get('/customers/options',[CustomerController::class, 'options']);
    Route::apiResource('barrels', BarrelController::class)->only(['index', 'store', 'update','destroy']);
    Route::post('/barrels/{barrel}/return', [BarrelController::class, 'markReturned']);
    Route::get('/barrels/available',[BarrelController::class, 'available']);

    Route::get('/invoices-next-number',[InvoiceController::class, 'nextInvoiceNo']);
    Route::apiResource('invoices', InvoiceController::class)->only(['index', 'store', 'show', 'update']);
    Route::apiResource('drivers',DriverController::class)->only(['index','store']);
    Route::apiResource('vehicles',VehicleController::class)->only(['index','store']);
    Route::get('/vehicles/{vehicle}',[VehicleController::class, 'show']);
    Route::delete('/vehicles/{vehicle}',[VehicleController::class, 'destroy']);
    Route::put('/vehicles/{vehicle}',[VehicleController::class, 'update']);

    Route::get('/reports/rentals',[ReportController::class, 'rentalReport']);
});