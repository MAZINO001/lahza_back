<?php

use App\Http\Controllers\Api\ClientImportExportController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ProfileController;
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
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\Ai\CalanderSummaryController;
use Gemini\Laravel\Facades\Gemini;
use App\Http\Controllers\Ai\TaskUpdateController;
use App\Http\Controllers\OtpController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\PackController;
use App\Http\Controllers\PlanController;


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
Route::post('/forgot-password', [PasswordResetLinkController::class, 'store']);
Route::post('/reset-password', [NewPasswordController::class, 'store']);

// Stripe webhook endpoint (no auth middleware)
Route::post('/stripe/webhook', [PaymentController::class, 'handleWebhook']);
Route::get('/ai-summaries', [CalanderSummaryController::class, 'getDailyAiSummaries']);
Route::get('/project/tasks/ai-update/{project}', [TaskUpdateController::class, 'generate']);

// Public Email Verification Route (users click link before login)
Route::post('/email/verify', [EmailVerificationController::class, 'verify']);
Route::post('/email/resend', [EmailVerificationController::class, 'resend']);

// OTP Routes (auth but NO otp middleware - users need these to verify)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/email/send-verification', [EmailVerificationController::class, 'sendVerificationEmail']);
    Route::post('/otp/send',  [OtpController::class, 'sendCode']);
    Route::post('/otp/verify',[OtpController::class, 'verifyCode']);
    Route::get('/otp/status', [OtpController::class, 'checkStatus']);
});
// -----------------------------
// Authenticated routes (with OTP verification requirement)
// -----------------------------
Route::middleware(['auth:sanctum','verified', 'api.otp'])->group(function () {
    // Logout
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);

    // Logged-in user info (both admin & client)
    Route::get('/user', function (Request $request) {
        return [
            'id' => $request->user()->id,
            'name' => $request->user()->name,
            'email' => $request->user()->email,
            'role' => $request->user()->role,
            'user_type' => $request->user()->user_type,
            'profile_image'=> $request->user()->profile_image,
            'preferences' => $request->user()->preferences,
        ];
    });

    Route::put('/user/password_update', [UserController::class, 'updateUserPassword']);
    Route::put('/user/email_update', [UserController::class, 'updateUserEmail']);
    Route::get('/user/profile', [ProfileController::class, 'show']);
    Route::put('/user/profile', [ProfileController::class, 'uploadProfile']);
    // -------------------------------------------------
    // SHARED READ ROUTES (admin + client)
    // -------------------------------------------------
    Route::middleware('role:admin,client')->group(function () {
Route::post('/file-search', [FileController::class, 'search']);
        Route::get('/company-info', [CompanyInfoController::class, 'index']);
        Route::get('/certifications', [CertificationController::class, 'index']);
        Route::get('/certifications/{certification}', [CertificationController::class, 'show']);

        Route::prefix('pdf')->controller(PdfController::class)->group(function () {
            Route::get('/invoice/{id}', 'invoice');
            Route::get('/quote/{id}', 'quote');
        });
        Route::prefix('pdf')->controller(ReceiptController::class)->group(function () {
            Route::get('/receipt/{id}', 'receipt');
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
        Route::get('/projects/{project}/invoices', [ProjectController::class, 'getALLProjectInvoices']);
        Route::get('/projects/{project}/services', [ProjectController::class, 'getProjectServices']);

        Route::delete('/projects/{project}/invoices/{invoice}', [ProjectController::class, 'deleteProjectInvoices']);
        Route::delete('/projects/{project}/services/{service}', [ProjectController::class, 'deleteProjectServices']);
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

        Route::get('/project/team/{project}', [ProjectAssignmentController::class, 'getProjectTeamMembers']);
        Route::delete('/project/team/{project}/{user}', [ProjectAssignmentController::class, 'DeleteProjectTeamMember']);
        Route::get('/payments/project/{project}', [PaymentController::class, 'getProjectPayments']);

        Route::get('/storage/{path}', [FileController::class, 'download'])->where('path', '.*');
        Route::apiResource('tickets', TicketController::class);
        Route::get('/tickets/{ticketId}/download/{fileId}', [TicketController::class, 'downloadAttachment']);
    });

    // -------------------------------------------------
    // Admin-only routes
    // -------------------------------------------------
    Route::middleware('role:admin')->group(function () {
        // get users
        Route::get('/users-all', [UserController::class, 'getAll']);
        Route::get('/get-team-users', [UserController::class, 'getTeamUsers']);
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
        Route::get('/payments/{payment}', [PaymentController::class, 'show']);

        Route::put('/cancelPayment/{payment}', [PaymentController::class, 'cancelPayment']);
        Route::get('getRemaining/{invoice}', [PaymentController::class, 'getRemaining']);

        Route::post('/invoices/pay/{invoice}/{percentage}', [PaymentController::class, 'createAdditionalPayment']);
        Route::put('/payment/date/{payment}', [PaymentController::class, 'updatePaymentDate']);
        
        // Projects & tasks (WRITE)
        Route::post('/projects', [ProjectController::class, 'store']);
        Route::put('/project/{project}', [ProjectController::class, 'update']);
        Route::delete('/project/{project}', [ProjectController::class, 'destroy']);

        Route::post('project/invoice/assign', [ProjectController::class, 'assignProjectToInvoice']);
        Route::post('project/service/assign', [ProjectController::class, 'assignServiceToproject']);


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

        Route::post('/company-info', [CompanyInfoController::class, 'store']);
        Route::put('/company-info/{companyinfo}', [CompanyInfoController::class, 'update']);

        Route::post('/certifications', [CertificationController::class, 'store']);
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

        // Send invoice/quote emails
        Route::put('/invoices/{invoice}/send', [InvoicesController::class, 'sendInvoice']);
        Route::put('/quotes/{quote}/send', [QuotesController::class, 'sendQuote']);
        });
        
    // -------------------------------------------------
    // Client-only routes
    // -------------------------------------------------
    Route::middleware('role:client')->group(function () {
    
    });
    // -----------------------------
    // Subscription routes
    // ----------------------------- 
    });
    // Packs
    Route::prefix('packs')->group(function () {
        Route::get('/', [PackController::class, 'index']);
        Route::post('/', [PackController::class, 'store']);
        Route::get('/active', [PackController::class, 'activePacks']);
        Route::get('/{pack}', [PackController::class, 'show']);
        Route::put('/{pack}', [PackController::class, 'update']);
        Route::delete('/{pack}', [PackController::class, 'destroy']);
    });
    
    // Plans
    Route::prefix('plans')->group(function () {
        Route::get('/', [PlanController::class, 'index']);
        Route::post('/', [PlanController::class, 'store']);
        Route::get('/{plan}', [PlanController::class, 'show']);
        Route::put('/{plan}', [PlanController::class, 'update']);
        Route::delete('/{plan}', [PlanController::class, 'destroy']);
        
        // Plan prices
        Route::post('/{plan}/prices', [PlanController::class, 'addPrice']);
        Route::put('/{plan}/prices/{price}', [PlanController::class, 'updatePrice']);
        
        // Plan custom fields
        Route::post('/{plan}/custom-fields', [PlanController::class, 'addCustomField']);
        Route::put('/{plan}/custom-fields/{customField}', [PlanController::class, 'updateCustomField']);
        Route::delete('/{plan}/custom-fields/{customField}', [PlanController::class, 'deleteCustomField']);
    });
    
    // Subscriptions
    Route::prefix('subscriptions')->group(function () {
        Route::get('/', [SubscriptionController::class, 'index']);
        Route::post('/', [SubscriptionController::class, 'store']);
        Route::get('/stats', [SubscriptionController::class, 'stats']);
        Route::get('/{subscription}', [SubscriptionController::class, 'show']);
        Route::put('/{subscription}', [SubscriptionController::class, 'update']);
        Route::delete('/{subscription}', [SubscriptionController::class, 'destroy']);
        
        // Subscription actions
        Route::post('/{subscription}/cancel', [SubscriptionController::class, 'cancel']);
        Route::post('/{subscription}/renew', [SubscriptionController::class, 'renew']);
        Route::post('/{subscription}/change-plan', [SubscriptionController::class, 'changePlan']);
        Route::post('/{subscription}/check-limit', [SubscriptionController::class, 'checkLimit']);
    });
    
    // Client subscriptions
    Route::get('/clients/{client}/subscription', [SubscriptionController::class, 'getActiveSubscription']);
        Route::put('/validatePayments/{payment}', [PaymentController::class, 'handleManualPayment']);
