<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Client;
use Illuminate\Support\Facades\Hash;
use App\Models\Service;

class csvController extends Controller
{
    public function uploadClients (Request $request ){
     // Validate file 
        $request->validate([
            'file' => 'required|mimes:csv,txt',
        ]);

        $file = $request->file('file');

        // Read CSV
        $rows = array_map('str_getcsv', file($file->getRealPath()));
        $header = array_map('trim', $rows[0]);
        unset($rows[0]); // remove header

        $created = 0;
        $skipped = 0;

        foreach ($rows as $row) {

            if (count($row) !== count($header)) {
                continue; // skip malformed rows
            }

            $data = array_combine($header, $row);

            // ---- 1️⃣ CREATE USER ----
            // If email exists, skip to avoid duplicates
            if (User::where('email', $data['email'])->exists()) {
                $skipped++;
                continue;
            }

            $user = User::create([
                'name'      => $data['name'] ?? $data['company'], // fallback
                'email'     => $data['email'],
                'password'  => Hash::make("lahzaapp2025"),
                'role'      => 'client',
                'user_type' => 'client',
            ]);

            // ---- 2️⃣ CREATE CLIENT ----
            Client::create([
                'user_id'        => $user->id,
                'client_number'  => $data['client_number'] ?? null,
                'client_type'    => $data['client_type'] ?? null,
                'company'        => $data['company'] ?? null,
                'phone'          => $data['phone'] ?? null,
                'address'        => $data['address'] ?? null,
                'city'           => $data['city'] ?? null,
                'country'        => $data['country'] ?? null,
                'currency'       => $data['currency'] ?? null,
                'vat'            => $data['vat'] ?? null,
                'siren'          => $data['siren'] ?? null,
                'ice'            => $data['ice'] ?? null,
            ]);

            $created++;
        }

        return response()->json([
            'message' => 'File imported successfully',
            'created' => $created,
            'skipped' => $skipped,
        ]);
    }

   public function uploadInvoices(Request $request)
{
    $request->validate([
        'file' => 'required|mimes:csv,txt',
    ]);

    $file = $request->file('file');

    // Read CSV
    $rows = array_map('str_getcsv', file($file->getRealPath()));
    $header = array_map('trim', array_shift($rows));

    $inserted = 0;
    $skipped = 0;

    foreach ($rows as $row) {
        $rowData = array_combine($header, $row);

        // Create checksum for this row
        $checksum = md5(json_encode([
            $rowData['invoice_number'] ?? '',
            $rowData['invoice_date'] ?? '',
            $rowData['due_date'] ?? '',
            $rowData['total_amount'] ?? 0,
            $rowData['balance_due'] ?? 0,
            $rowData['notes'] ?? '',
        ]));

        // Check if this row already exists
        if (Invoice::where('checksum', $checksum)->exists()) {
            $skipped++;
            continue; // skip only this row
        }

        $newInvoiceNumber = Invoice::generateInvoiceNumber();
 

        Invoice::create([
            'client_id'      => null,
            'quote_id'       => null,
            'invoice_number' => $newInvoiceNumber,
            'invoice_date'   => $rowData['invoice_date'] ?? now(),
            'due_date'       => $rowData['due_date'] ?? now()->addDays(30),
            'status'         => $rowData['status'] ?? 'unpaid',
            'notes'          => $rowData['notes'] ?? null,
            'total_amount'   => $rowData['total_amount'] ?? 0,
            'balance_due'    => $rowData['balance_due'] ?? 0,
            'checksum'       => $checksum,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $inserted++;
    }

    return response()->json([
        'message' => 'Invoices processed',
        'inserted' => $inserted,
        'skipped_duplicates' => $skipped,
        'total_rows' => count($rows),
    ]);
}


    public function uploadServices (Request $request ){
         $request->validate([
        'file' => 'required|mimes:csv,txt',
    ]);

    $file = $request->file('file');

    // Read CSV
    $rows = array_map('str_getcsv', file($file->getRealPath()));
    $header = array_map('trim', array_shift($rows));

    $inserted = 0;
    $skipped = 0;

    foreach ($rows as $index => $row) {
        $rowData = array_combine($header, $row);

        // Check for duplicate by name
        if (Service::where('name', $rowData['name'])->exists()) {
            $skipped++;
            continue;
        }

        // Optional checksum for exact row duplicate check
        $checksum = md5(json_encode([
            $rowData['name'] ?? '',
            $rowData['description'] ?? '',
            $rowData['base_price'] ?? 0
        ]));

        if (Service::where('checksum', $checksum)->exists()) {
            $skipped++;
            continue;
        }

        Service::create([
            'name'        => $rowData['name'] ?? 'Unnamed Service',
            'description' => $rowData['description'] ?? null,
            'base_price'  => $rowData['base_price'] ?? 0,
            'checksum'    => $checksum,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $inserted++;
    }

    return response()->json([
        'message' => 'Services processed',
        'inserted' => $inserted,
        'skipped_duplicates' => $skipped,
        'total_rows' => count($rows),
    ]);
    }
}
