<?php


use App\Http\Controllers\Api\ClientImportExportController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\InvoicesController;
use Illuminate\Support\Facades\Route; // <-- FIXED
use App\Models\User;
use App\Http\Controllers\QuotesController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\ServicesController;
use App\Http\Controllers\SignatureController;
use Illuminate\Http\Request;
use App\Http\Controllers\csvController;

Route::post('/register', [RegisteredUserController::class, 'store']);
Route::post('/login', [AuthenticatedSessionController::class, 'store']);
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth:sanctum');
Route::get('/export', [ClientImportExportController::class, 'export']);
Route::post('/import', [ClientImportExportController::class, 'import']);

// routes for admin only
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('clients/', [ClientController::class, 'index']);
    Route::get('clients/{id}', [ClientController::class, 'show']);
    Route::put('clients/{id}', [ClientController::class, 'update']);
    Route::delete('clients/{id}', [ClientController::class, 'destroy']);
});

// routes for client only
Route::middleware(['auth:sanctum', 'role:client'])->group(function () {
    Route::get('profile', function () {
        return "Hello Client";
    });
});

// routes for both client and admin
Route::middleware(['auth:sanctum',   'role:admin,client'])->group(function () {
    Route::get('/user', function (Request $request) {
        return [
            'name' => $request->user()->name,
            'email' => $request->user()->email,
            'role' => $request->user()->role,
        ];
    });
    Route::post('/quotes/{quote}/signature', [SignatureController::class, 'upload']);
    Route::apiResource('quotes', QuotesController::class);
    Route::apiResource('invoices', InvoicesController::class);
    Route::apiResource('services', ServicesController::class);
});



Route::post('/invoices/{id}/send-email', [EmailController::class, 'sendInvoice']);
Route::post('/quotes/{id}/send-email', [EmailController::class, 'sendQuote']);

Route::post('/uploadClients',[csvController::class,'uploadClients']);
Route::post('/uploadInvoices',[csvController::class,'uploadInvoices']);
Route::post('/uploadServices',[csvController::class,'uploadServices']);