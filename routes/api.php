<?php


use App\Http\Controllers\Api\ClientImportExportController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\LogsActivityController;
use App\Http\Controllers\InvoicesController;
use Illuminate\Support\Facades\Route; // <-- FIXED
use App\Models\User;
use App\Http\Controllers\QuotesController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\ServicesController;
use App\Http\Controllers\SignatureController;
use Illuminate\Http\Request;
use App\Http\Controllers\csvController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\PaymentController;
use App\Traits\LogsActivity;

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
    Route::post('/{model}/{id}/signature', [SignatureController::class, 'upload'])
        ->where('model', 'invoices|quotes');
    Route::delete('/{model}/{id}/signature', [SignatureController::class, 'destroy'])
        ->where('model', 'invoices|quotes');
    Route::post('quotes/{quote}/create-invoice', [QuotesController::class, 'createInvoiceFromQuote']);




    Route::post('/uploadClients', [csvController::class, 'uploadClients']);
    Route::post('/uploadInvoices', [csvController::class, 'uploadInvoices']);
    Route::post('/uploadServices', [csvController::class, 'uploadServices']);
});
Route::apiResource('invoices', InvoicesController::class);
Route::apiResource('quotes', QuotesController::class);
Route::apiResource('services', ServicesController::class);
Route::apiResource('offers', OfferController::class);
Route::post('/email/send', [EmailController::class, 'sendEmail']);


// Payment routes
Route::post('/quotes/{quote}/pay', [PaymentController::class, 'createPaymentLink']);
Route::post('/stripe/webhook', [PaymentController::class, 'handleWebhook']);
// Route parameter must be {payment} to match the controller's $payment argument
Route::put('/payments/{payment}', [PaymentController::class, 'updatePayment']);
Route::get('/payments', [PaymentController::class, 'getPayment']);


Route::get('logs', [LogsActivityController::class, 'index']);
