<?php

use App\Http\Controllers\PdfController;
use Illuminate\Support\Facades\Route;
use App\Mail\TestMail;
use Illuminate\Support\Facades\Mail;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

require __DIR__ . '/auth.php';



Route::prefix('pdf')->controller(PdfController::class)->group(function () {
    Route::get('/invoice/{id}', 'invoice');
    Route::get('/quote/{id}', 'quote');
});


Route::get('signatures/{file}', function ($file) {
    $path = storage_path('app/' . $file);
    if (!file_exists($path)) abort(404);
    return response()->file($path);
});
