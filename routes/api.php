<?php

use App\Http\Controllers\Api\ClientImportExportController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\ClientController;
use Illuminate\Support\Facades\Route; // <-- FIXED
use App\Models\User;
use App\Http\Controllers\QuotesController;
use App\Http\Controllers\ServicesController;

Route::post('/register', [RegisteredUserController::class, 'store']);
Route::post('/login', [AuthenticatedSessionController::class, 'store']);
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth:sanctum');
Route::get('/export', [ClientImportExportController::class, 'export']);
Route::post('/import', [ClientImportExportController::class, 'import']);

// Route::middleware('auth:sanctum')->prefix('users')->group(function () {
//     Route::get('/', [UserController::class, 'index']);
//     Route::get('/{id}', [UserController::class, 'show']);
//     Route::put('/{id}', [UserController::class, 'update']);
//     Route::delete('/{id}', [UserController::class, 'destroy']);
//     Route::get('/me', [UserController::class, 'me']);
// });

Route::get('clients/', [ClientController::class, 'index']);
Route::get('clients/{id}', [ClientController::class, 'show']);
Route::put('clients/{id}', [ClientController::class, 'update']);
Route::delete('clients/{id}', [ClientController::class, 'destroy']);
Route::get('clients/me', [ClientController::class, 'me']);

//////////////////// Qotations Section \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

Route::apiResource('quotes', QuotesController::class);
//////////////////// services Section \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
Route::apiResource('services', ServicesController::class);
