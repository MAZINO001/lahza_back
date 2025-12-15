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
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\ProjectAdditionalDataController;
use App\Http\Controllers\ProjectAssignmentController;
use App\Http\Controllers\ProjectProgressController;
use App\Http\Controllers\CommentController;

// Route::post('/register', [RegisteredUserController::class, 'store']);
// Route::post('/login', [AuthenticatedSessionController::class, 'store']);
// // Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('auth:sanctum');
// Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth:sanctum');
// Route::get('/export', [ClientImportExportController::class, 'export']);
// Route::post('/import', [ClientImportExportController::class, 'import']);


// // routes for admin only
// Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
//     Route::get('clients/', [ClientController::class, 'index']);
//     Route::get('clients/{id}', [ClientController::class, 'show']);
//     Route::put('clients/{id}', [ClientController::class, 'update']);
//     Route::delete('clients/{id}', [ClientController::class, 'destroy']);



// });

// // routes for client only
// Route::middleware(['auth:sanctum', 'role:client'])->group(function () {
//     Route::get('profile', function () {
//         return "Hello Client";
//     });
// });

// // routes for both client and admin
// Route::middleware(['auth:sanctum',   'role:admin,client'])->group(function () {


//     Route::get('/user', function (Request $request) {
//         return [
//             'name' => $request->user()->name,
//             'email' => $request->user()->email,
//             'role' => $request->user()->role,
//         ];
//     });
//     Route::post('/{model}/{id}/signature', [SignatureController::class, 'upload'])
//         ->where('model', 'invoices|quotes');
//     Route::delete('/{model}/{id}/signature', [SignatureController::class, 'destroy'])
//         ->where('model', 'invoices|quotes');
//     Route::post('quotes/{quote}/create-invoice', [QuotesController::class, 'createInvoiceFromQuote']);




//     Route::post('/uploadClients', [csvController::class, 'uploadClients']);
//     Route::post('/uploadInvoices', [csvController::class, 'uploadInvoices']);
//     Route::post('/uploadServices', [csvController::class, 'uploadServices']);
// });
// Route::apiResource('invoices', InvoicesController::class);
// Route::apiResource('quotes', QuotesController::class);
// Route::apiResource('services', ServicesController::class);
// Route::apiResource('offers', OfferController::class);
// Route::post('/email/send', [EmailController::class, 'sendEmail']);


// // Payment routes
// Route::post('/quotes/{quote}/pay', [PaymentController::class, 'createPaymentLink']);
// Route::post('/stripe/webhook', [PaymentController::class, 'handleWebhook']);


// Route::put('/payments/{payment}', [PaymentController::class, 'updatePayment']);
// Route::get('/payments', [PaymentController::class, 'getPayment']);


// Route::get('logs', [LogsActivityController::class, 'index']);
// Route::get('logs/{activityLog}', [LogsActivityController::class, 'show']);
// Route::get('getRemaining/{invoice}', [PaymentController::class, 'getRemaining']);
// Route::get('getInvoicePayments/{invoice}', [PaymentController::class, 'getInvoicePayments']);


// Route::post('/invoices/pay/{invoice}/{percentage}', [PaymentController::class, 'createAdditionalPayment']);

// Route::get('/projects', [ProjectController::class, 'index']);
// Route::get('/project/{project}', [ProjectController::class, 'show']);



// Route::put('/validatePayments/{payment}/', [PaymentController::class, 'handleManualPayment']);
// Route::put('/cancelPayment/{payment}/', [PaymentController::class, 'cancelPayment']);

// Route::prefix('additional-data')->controller(ProjectAdditionalDataController::class)->group(function () {
//     Route::post('/', 'store');
//     Route::put('/{id}', 'update');
//     Route::delete('/{id}', 'destroy');

//     // Avoid route conflict:
// Route::get('/project/{project_id}', 'showByProject');
// });
// Route::prefix('projects/tasks/{project}')->controller(TaskController::class)->group(function () {
//     Route::get('/', 'index');       // get tasks for one project
//     Route::post('/', 'store');      // create task in a project
//     Route::put('/{task}', 'update'); // update task
//     Route::delete('/{task}', 'destroy'); // delete task
// });

// // Fetch all tasks (not tied to project)
// Route::get('/tasks', [TaskController::class, 'allTasks']);
// Route::put('/task/{task}', [TaskController::class, 'updateStatus']);

// Route::get('getProgress/{project}', [ProjectProgressController::class, 'index']);

// Route::post('addAssignment', [ProjectAssignmentController::class, 'store']);
// Route::delete('deleteAssignment', [ProjectAssignmentController::class, 'destroy']);


// Route::prefix('comments')->group(function () {
//     Route::get('/{type}/{id}', [CommentController::class, 'index']);
//     Route::get('/user/{userId}', [CommentController::class, 'getUserComments']);
//     Route::get('/', [CommentController::class, 'getAllComments']);
//     Route::delete('/{comment}', [CommentController::class, 'deletecomments']);
//     Route::post('/{type}/{id}', [CommentController::class, 'store']);

// });

Route::post('/register', [RegisteredUserController::class, 'store']);
Route::post('/login', [AuthenticatedSessionController::class, 'store']);

// -----------------------------
// Authenticated routes
// -----------------------------
Route::middleware('auth:sanctum')->group(function () {

    // Logout
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);

    // Logged-in user info (both admin & client)
    Route::get('/user', function (Request $request) {
        return [
            'name' => $request->user()->name,
            'email' => $request->user()->email,
            'role' => $request->user()->role,
        ];
    });

    // -------------------------------------------------
    // SHARED READ ROUTES (admin + client) ✅ FIX
    // -------------------------------------------------
    Route::middleware('role:admin,client')->group(function () {

        // Clients
        Route::get('clients/{id}', [ClientController::class, 'show']);

        // Resources (READ)
        Route::get('invoices', [InvoicesController::class, 'index']);
        Route::get('invoices/{invoice}', [InvoicesController::class, 'show']);
        Route::get('quotes', [QuotesController::class, 'index']);
        Route::get('quotes/{quote}', [QuotesController::class, 'show']);
        Route::get('services', [ServicesController::class, 'index']);
        Route::get('services/{service}', [ServicesController::class, 'show']);
        Route::get('offers', [OfferController::class, 'index']);
        Route::get('offers/{offer}', [OfferController::class, 'show']);

        // Projects & tasks (READ)
        Route::get('/projects', [ProjectController::class, 'index']);
        Route::get('/project/{project}', [ProjectController::class, 'show']);
        Route::get('/tasks', [TaskController::class, 'allTasks']);
        Route::get('getProgress/{project}', [ProjectProgressController::class, 'index']);

        // CSV export (read)
        Route::get('/export', [ClientImportExportController::class, 'export']);

        // Comments (READ)
        Route::get('comments/{type}/{id}', [CommentController::class, 'index']);
        Route::get('comments/user/{userId}', [CommentController::class, 'getUserComments']);
        Route::get('comments', [CommentController::class, 'getAllComments']);

        // Project additional data (READ)
        Route::get('additional-data/project/{project_id}', [ProjectAdditionalDataController::class, 'showByProject']);
    });

    // -----------------------------
    // Admin-only routes
    // -----------------------------
    Route::middleware('role:admin')->group(function () {

        // Clients (FULL)
        Route::get('clients', [ClientController::class, 'index']);
        Route::put('clients/{id}', [ClientController::class, 'update']);
        Route::delete('clients/{id}', [ClientController::class, 'destroy']);

        // Resources (FULL)
        Route::apiResource('invoices', InvoicesController::class)->except(['index', 'show']);
        Route::apiResource('quotes', QuotesController::class)->except(['index', 'show']);
        Route::apiResource('services', ServicesController::class)->except(['index', 'show']);
        Route::apiResource('offers', OfferController::class)->except(['index', 'show']);

        // CSV import
        Route::post('/import', [ClientImportExportController::class, 'import']);
        Route::post('/uploadClients', [csvController::class, 'uploadClients']);
        Route::post('/uploadInvoices', [csvController::class, 'uploadInvoices']);
        Route::post('/uploadServices', [csvController::class, 'uploadServices']);

        // Emails
        Route::post('/email/send', [EmailController::class, 'sendEmail']);

        // Payments
        Route::post('/quotes/{quote}/pay', [PaymentController::class, 'createPaymentLink']);
        Route::put('/payments/{payment}', [PaymentController::class, 'updatePayment']);
        Route::get('/payments', [PaymentController::class, 'getPayment']);
        Route::put('/validatePayments/{payment}', [PaymentController::class, 'handleManualPayment']);
        Route::put('/cancelPayment/{payment}', [PaymentController::class, 'cancelPayment']);
        Route::get('getRemaining/{invoice}', [PaymentController::class, 'getRemaining']);
        Route::get('getInvoicePayments/{invoice}', [PaymentController::class, 'getInvoicePayments']);
        Route::post('/invoices/pay/{invoice}/{percentage}', [PaymentController::class, 'createAdditionalPayment']);

        // Projects & tasks (WRITE)
        Route::prefix('projects/tasks/{project}')->controller(TaskController::class)->group(function () {
            Route::post('/', 'store');
            Route::put('/{task}', 'update');
            Route::delete('/{task}', 'destroy');
        });
        Route::put('/task/{task}', [TaskController::class, 'updateStatus']);

        // Project Additional Data (WRITE)
        Route::prefix('additional-data')->controller(ProjectAdditionalDataController::class)->group(function () {
            Route::post('/', 'store');
            Route::put('/{id}', 'update');
            Route::delete('/{id}', 'destroy');
        });

        // Project assignments
        Route::post('addAssignment', [ProjectAssignmentController::class, 'store']);
        Route::delete('deleteAssignment', [ProjectAssignmentController::class, 'destroy']);

        // Logs
        Route::get('logs', [LogsActivityController::class, 'index']);
        Route::get('logs/{activityLog}', [LogsActivityController::class, 'show']);

        // Signatures
        Route::post('/{model}/{id}/signature', [SignatureController::class, 'upload'])
            ->where('model', 'invoices|quotes');
        Route::delete('/{model}/{id}/signature', [SignatureController::class, 'destroy'])
            ->where('model', 'invoices|quotes');

        // Quotes → invoice
        Route::post('quotes/{quote}/create-invoice', [QuotesController::class, 'createInvoiceFromQuote']);

        // Comments (FULL)
        Route::post('comments/{type}/{id}', [CommentController::class, 'store']);
        Route::delete('comments/{comment}', [CommentController::class, 'deletecomments']);
    });

    // -----------------------------
    // Client-only routes
    // -----------------------------
    Route::middleware('role:client')->group(function () {

        // Comments (CREATE)
        Route::post('comments/{type}/{id}', [CommentController::class, 'store']);

        // Sign quotes
        Route::post('/{model}/{id}/signature', [SignatureController::class, 'upload'])
            ->where('model', 'quotes');

        // Project additional data (WRITE limited)
        Route::post('additional-data', [ProjectAdditionalDataController::class, 'store']);

        // Update additional data
        Route::put('additional-data/{id}', [ProjectAdditionalDataController::class, 'update']);
    });
});
