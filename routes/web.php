<?php

use Illuminate\Support\Facades\Route;
use App\Mail\TestMail;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\Pdf;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

require __DIR__ . '/auth.php';

// Route::get('/report', function () {
//     return view('pdf.report');
// });

Route::get('/report', function () {
    // data here
    // Generate PDF
    $pdf = Pdf::loadView('pdf.report');

    // Stream to browser
    return $pdf->stream('invoice.pdf');
});
Route::get('/gd-test', function () {
    return function_exists('gd_info') ? 'GD is enabled!' : 'GD NOT enabled!';
});
