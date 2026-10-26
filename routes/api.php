<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\VisitController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware(['auth:sanctum', 'tenant']);

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware(['auth:sanctum', 'tenant']);

Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
    Route::middleware('tenant.admin')->group(function () {
        Route::get('/team', [TeamController::class, 'index']);
        Route::post('/team', [TeamController::class, 'store']);
        Route::get('/plans', [PlanController::class, 'index']);
        Route::post('/plans', [PlanController::class, 'store']);
        Route::patch('/plans/{plan}', [PlanController::class, 'update']);
        Route::delete('/plans/{plan}', [PlanController::class, 'destroy']);
        Route::get('/subscriptions', [SubscriptionController::class, 'index']);
        Route::post('/customers/{customer}/subscription', [SubscriptionController::class, 'store']);
        Route::delete('/customers/{customer}/subscription', [SubscriptionController::class, 'destroy']);
        Route::post('/visits/{visit}/invoice', [InvoiceController::class, 'storeForVisit']);
        Route::get('/invoices', [InvoiceController::class, 'index']);
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
        Route::get('/invoices/{invoice}/download', [InvoiceController::class, 'download']);

        Route::apiResource('customers', CustomerController::class);
        Route::apiResource('properties', PropertyController::class);
        Route::post('/visits', [VisitController::class, 'store']);
        Route::match(['put', 'patch'], '/visits/{visit}', [VisitController::class, 'update']);
        Route::delete('/visits/{visit}', [VisitController::class, 'destroy']);
    });

    Route::get('/visits', [VisitController::class, 'index']);
    Route::get('/visits/{visit}', [VisitController::class, 'show']);
    Route::get('/visits/{visit}/report', [VisitController::class, 'downloadReport']);
    Route::get('/visits/{visit}/photos/{photo}', [VisitController::class, 'showPhoto'])->whereNumber('photo');
    Route::post('/visits/{visit}/start', [VisitController::class, 'start']);
    Route::post('/visits/{visit}/complete', [VisitController::class, 'complete']);
});
