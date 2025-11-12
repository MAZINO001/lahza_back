<?php

// use App\Http\Controllers\Api\ClientImportExportController;
// use App\Http\Controllers\Auth\AuthenticatedSessionController;
// use App\Http\Controllers\Auth\RegisteredUserController;
// use App\Http\Controllers\ClientController;
// use Illuminate\Support\Facades\Route; // <-- FIXED
// use App\Models\User;
// use App\Http\Controllers\QuotesController;
// use App\Http\Controllers\ServicesController;
// use Illuminate\Http\Request;

// Route::post('/register', [RegisteredUserController::class, 'store']);
// Route::post('/login', [AuthenticatedSessionController::class, 'store']);
// Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth:sanctum');
// Route::get('/export', [ClientImportExportController::class, 'export']);
// Route::post('/import', [ClientImportExportController::class, 'import']);

// // Route::middleware('auth:sanctum')->prefix('users')->group(function () {
// //     Route::get('/', [UserController::class, 'index']);
// //     Route::get('/{id}', [UserController::class, 'show']);
// //     Route::put('/{id}', [UserController::class, 'update']);
// //     Route::delete('/{id}', [UserController::class, 'destroy']);
// //     Route::get('/me', [UserController::class, 'me']);
// // });
// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return response()->json([
//         'name' => $request->user()->name,
//         'email' => $request->user()->email,
//         'role' => $request->user()->role,
//     ]);
// });
// Route::get('clients/', [ClientController::class, 'index']);
// Route::get('clients/{id}', [ClientController::class, 'show']);
// Route::put('clients/{id}', [ClientController::class, 'update']);
// Route::delete('clients/{id}', [ClientController::class, 'destroy']);
// Route::get('clients/me', [ClientController::class, 'me']);

// //////////////////// Qotations Section \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

// Route::apiResource('quotes', QuotesController::class);
// //////////////////// services Section \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
// Route::apiResource('services', ServicesController::class);



use App\Http\Controllers\Api\ClientImportExportController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\InvoicesController;
use Illuminate\Support\Facades\Route; // <-- FIXED
use App\Models\User;
use App\Http\Controllers\QuotesController;
use App\Http\Controllers\ServicesController;
use Illuminate\Http\Request;

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
});
Route::apiResource('quotes', QuotesController::class);
Route::apiResource('invoices', InvoicesController::class);
Route::apiResource('services', ServicesController::class);




Route::post('/send-email', [EmailController::class, 'sendEmail']);

