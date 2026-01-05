<?php

use App\Http\Controllers\Api\ClientImportExportController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\LogsActivityController;
use App\Http\Controllers\InvoicesController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\QuotesController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\ServicesController;
use App\Http\Controllers\SignatureController;
use Illuminate\Http\Request;
use App\Http\Controllers\csvController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\ProjectAdditionalDataController;
use App\Http\Controllers\ProjectAssignmentController;
use App\Http\Controllers\ProjectProgressController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\CompanyInfoController;
use App\Http\Controllers\CertificationController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ObjectiveController;
use App\Http\Controllers\TeamAdditionalDataController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\AiController;
use Gemini\Laravel\Facades\Gemini;

Route::get('/check-models', function () {
    $response = Gemini::models()->list();
    
    return collect($response->models)->map(function ($model) {
        return [
            'name' => $model->name,
            'displayName' => $model->displayName,
            'supportedMethods' => $model->supportedGenerationMethods,
        ];
    });
});
// Public Auth Routes
Route::post('/register', [RegisteredUserController::class, 'store']);
Route::post('/login', [AuthenticatedSessionController::class, 'store']);
Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('guest')->name('password.email');
Route::post('/reset-password', [NewPasswordController::class, 'store'])->middleware('guest')->name('password.store');

// Stripe webhook endpoint (no auth middleware)
Route::post('/stripe/webhook', [PaymentController::class, 'handleWebhook']);
Route::get('/event/summary', [AiController::class,'calendarSummary']);
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
    // SHARED READ ROUTES (admin + client)
    // -------------------------------------------------
    Route::middleware('role:admin,client')->group(function () {


        Route::prefix('pdf')->controller(PdfController::class)->group(function () {
        Route::get('/invoice/{id}', 'invoice');
        Route::get('/quote/{id}', 'quote');
    });

        Route::get('signatures/{file}', function ($file) {
            $path = storage_path('app/' . $file);
            if (!file_exists($path)) abort(404);
            return response()->file($path);
    });
        // User preferences
        
        Route::put('/user/preferences', [UserController::class, 'updatePreferences']);
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
        Route::get('getproject/invoices', [ProjectController::class, 'getProjectInvoices']);

        // CSV export (read)
        Route::get('/export', [ClientImportExportController::class, 'export']);

        // Comments (READ)
        Route::get('comments/{type}/{id}', [CommentController::class, 'index']);
        Route::get('comments/user/{userId}', [CommentController::class, 'getUserComments']);
        Route::get('comments', [CommentController::class, 'getAllComments']);
        Route::post('comments/{type}/{id}', [CommentController::class, 'store']);

        
        // Signature routes for both admin and client
        Route::post('/{model}/{id}/signature', [SignatureController::class, 'upload'])
            ->where('model', 'invoices|quotes');
        Route::delete('/{model}/{id}/signature', [SignatureController::class, 'destroy'])
            ->where('model', 'invoices|quotes');

        // Events (accessible to both)
        Route::prefix('events')->group(function () {
            Route::get('/', [EventController::class, 'index']);
            Route::get('/{id}', [EventController::class, 'show']);
        });
        
        // payments
        Route::get('/payments', [PaymentController::class, 'getPayment']);
        Route::get('getInvoicePayments/{invoice}', [PaymentController::class, 'getInvoicePayments']);
        
        // Quotes → invoice conversion
        Route::post('quotes/{quote}/create-invoice', [QuotesController::class, 'createInvoiceFromQuote']);
        
        // Project additional data 
        Route::prefix('additional-data')->controller(ProjectAdditionalDataController::class)->group(function () {
            Route::get('/project/{project_id}', 'showByProject');
            Route::post('/', 'store');
            Route::put('/{id}', 'update');
            Route::delete('/{id}', 'destroy');
        });
    });

    // -------------------------------------------------
    // Admin-only routes
    // -------------------------------------------------
    Route::middleware('role:admin')->group(function () {

        // Clients (FULL)
        Route::get('clients', [ClientController::class, 'index']);
        Route::put('clients/{id}', [ClientController::class, 'update']);
        Route::delete('clients/{id}', [ClientController::class, 'destroy']);
        Route::get('clients/{id}/emails', [EmailController::class, 'getClientEmails']);
        Route::get('clients/{id}/history', [ClientController::class, 'getClientHistory']);

        // Resources (CREATE, UPDATE, DELETE)
        Route::apiResource('invoices', InvoicesController::class)->except(['index', 'show']);
        Route::get('invoice/projects', [InvoicesController::class, 'getInvoiceProjects']);
        Route::apiResource('quotes', QuotesController::class)->except(['index', 'show']);
        Route::apiResource('services', ServicesController::class)->except(['index', 'show']);
        Route::get('/services/{service}/invoices', [ServicesController::class, 'getInvoices']);
        Route::get('/services/{service}/quotes', [ServicesController::class, 'getQuotes']);
        Route::apiResource('offers', OfferController::class)->except(['index', 'show']);

        // CSV import & management
        Route::post('/import', [ClientImportExportController::class, 'import']);
        Route::post('/uploadClients', [csvController::class, 'uploadClients']);
        Route::post('/uploadInvoices', [csvController::class, 'uploadInvoices']);
        Route::post('/uploadServices', [csvController::class, 'uploadServices']);

        // Emails
        Route::post('/email/send', [EmailController::class, 'sendEmail']);

        // Payments
        Route::post('/quotes/{quote}/pay', [PaymentController::class, 'createPaymentLink']);
        Route::put('/payments/{payment}', [PaymentController::class, 'updatePayment']);
        
        Route::put('/validatePayments/{payment}', [PaymentController::class, 'handleManualPayment']);
        Route::put('/cancelPayment/{payment}', [PaymentController::class, 'cancelPayment']);
        Route::get('getRemaining/{invoice}', [PaymentController::class, 'getRemaining']);
        
        Route::post('/invoices/pay/{invoice}/{percentage}', [PaymentController::class, 'createAdditionalPayment']);
        Route::put('/payment/date/{payment}', [PaymentController::class, 'updatePaymentDate']);

        // Projects & tasks (WRITE)
        Route::post('/projects', [ProjectController::class, 'store']);
        Route::post('project/invoice/assign', [ProjectController::class, 'assignProjectToInvoice']);
        Route::prefix('projects/tasks/{project}')->controller(TaskController::class)->group(function () {
            Route::post('/', 'store');
            Route::put('/{task}', 'update');
            Route::delete('/{task}', 'destroy');
        });
        Route::post('/projects/{project}/complete', [ProjectController::class, 'completeProject']);
        Route::put('/task/{task}', [TaskController::class, 'updateStatus']);

        // Project assignments
        Route::post('addAssignment', [ProjectAssignmentController::class, 'store']);
        Route::delete('deleteAssignment', [ProjectAssignmentController::class, 'destroy']);

        // Activity logs
        Route::get('logs', [LogsActivityController::class, 'index']);
        Route::get('logs/{activityLog}', [LogsActivityController::class, 'show']);


        // Comments (DELETE only for admin)
        Route::delete('comments/{comment}', [CommentController::class, 'deletecomments']);

        // Events (full CRUD for admin)
        Route::prefix('events')->group(function () {
            Route::post('/', [EventController::class, 'store']);
            Route::put('/{id}', [EventController::class, 'update']);
            Route::delete('/{id}', [EventController::class, 'destroy']);
        });

        Route::get('/company-info', [CompanyInfoController::class, 'index']);
        Route::post('/company-info', [CompanyInfoController::class, 'store']);
        Route::put('/company-info/{companyinfo}', [CompanyInfoController::class, 'update']);


        Route::get('/certifications', [CertificationController::class, 'index']);
        Route::post('/certifications', [CertificationController::class, 'store']);
        Route::get('/certifications/{certification}', [CertificationController::class, 'show']);
        Route::put('/certifications/{certification}', [CertificationController::class, 'update']);
        Route::delete('/certifications/{certification}', [CertificationController::class, 'destroy']);

        Route::apiResource('expenses', ExpenseController::class);
        Route::apiResource('objectives', ObjectiveController::class);
        Route::post('objectives/{objective}/convert-to-event', [ObjectiveController::class, 'converObjecTtoEvent']);
        // Team Additional Data routes
        Route::prefix('team-additional-data')->controller(TeamAdditionalDataController::class)->group(function () {
            Route::post('/', 'store');
            Route::get('/{teamUserId}', 'show');
            Route::put('/{teamUserId}', 'update');
            Route::delete('/{teamUserId}', 'destroy');
        });
        Route::post('/convert-intern-to-eam-user/{internId}', [UserController::class, 'convertTeamUser']);
    });

    // -------------------------------------------------
    // Client-only routes
    // -------------------------------------------------
    Route::middleware('role:client')->group(function () {
    });
});