<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Client;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\Service;
use App\Mail\WelcomeMail;

class csvController extends Controller
{
    // public function uploadClients(Request $request)
    // {

    //     $request->validate([
    //         'file' => 'required|mimes:csv,txt',
    //     ]);

    //     $file = $request->file('file');


    //     $rows = array_map('str_getcsv', file($file->getRealPath()));
    //     $header = array_map('trim', $rows[0]);
    //     unset($rows[0]);

    //     $created = 0;
    //     $skipped = 0;

    //     $results = DB::transaction(function () use ($rows, $header) {
    //         $created = 0;
    //         $skipped = 0;
    //         $createdUsers = [];

    //         foreach ($rows as $row) {

    //             if (count($row) !== count($header)) {
    //                 continue;
    //             }

    //             $data = array_combine($header, $row);

    //             if (empty($data['email']) || User::where('email', $data['email'])->exists()) {
    //                 $skipped++;
    //                 continue;
    //             }

    //             $user = User::create([
    //                 'name'      => $data['name'] ?? $data['company'] ?? 'Client',
    //                 'email'     => $data['email'],
    //                 'password'  => Hash::make("lahzaapp2025"),
    //                 'role'      => 'client',
    //                 'user_type' => 'client',
    //             ]);

    //             Client::create([
    //                 'user_id'        => $user->id,
    //                 'client_type'    => $data['client_type'] ?? null,
    //                 'company'        => $data['company'] ?? null,
    //                 'phone'          => $data['phone'] ?? null,
    //                 'address'        => $data['address'] ?? null,
    //                 'city'           => $data['city'] ?? null,
    //                 'country'        => $data['country'] ?? null,
    //                 'currency'       => $data['currency'] ?? null,
    //                 'vat'            => $data['vat'] ?? null,
    //                 'siren'          => $data['siren'] ?? null,
    //                 'ice'            => $data['ice'] ?? null,
    //             ]);

    //             $created++;
    //             $createdUsers[] = $user;
    //         }

    //         return [
    //             'created' => $created,
    //             'skipped' => $skipped,
    //             'users' => $createdUsers,
    //         ];
    //     });

    //     $created = $results['created'] ?? 0;
    //     $skipped = $results['skipped'] ?? 0;

    //     // Send welcome email to each created user after successful commit
    //     if (!empty($results['users'])) {
    //         foreach ($results['users'] as $user) {
    //             try {
    //                 $password = 'lahzaapp2025';
    //                 Mail::to($user->email)->send(new WelcomeMail($user, $password));
    //                 Log::info('Welcome email sent successfully', ['email' => $user->email]);
    //             } catch (\Exception $e) {
    //                 Log::error('Failed to send welcome email', ['email' => $user->email, 'error' => $e->getMessage()]);
    //             }
    //         }
    //     }

    //     return response()->json([
    //         'message' => 'File imported successfully',
    //         'created' => $created,
    //         'skipped' => $skipped,
    //     ]);
    // }
public function uploadClients(Request $request)
{
    $request->validate([
        'file' => 'required|mimes:csv,txt',
    ]);

    $file = $request->file('file');
    $path = $file->getRealPath();

    $createdUsers = [];
    $created = 0;
    $skipped = 0;

    // STEP 1: Read file using streaming (no memory overload)
    if (($handle = fopen($path, 'r')) !== false) {

        $header = array_map('trim', fgetcsv($handle));

        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) === count($header)) {
                $rows[] = array_combine($header, $row);
            }
        }

        fclose($handle);
    } else {
        return response()->json(['message' => 'Could not open file'], 400);
    }

    // STEP 2: Get all emails from CSV
    $emails = collect($rows)
        ->pluck('email')
        ->filter()
        ->toArray();

    // STEP 3: Get existing emails in ONE query (performance fix)
    $existingEmails = User::whereIn('email', $emails)
        ->pluck('email')
        ->toArray();

    DB::transaction(function () use ($rows, &$created, &$skipped, &$createdUsers, $existingEmails) {

        foreach ($rows as $data) {

            // Validate email
            if (
                empty($data['email']) ||
                !filter_var($data['email'], FILTER_VALIDATE_EMAIL) ||
                in_array($data['email'], $existingEmails)
            ) {
                $skipped++;
                continue;
            }

            $clientName = $data['name'] ?? $data['company'] ?? 'client';

            // Generate password: clientname@lahza@2026
            $cleanName = strtolower(str_replace(' ', '', $clientName));
            $plainPassword = $cleanName . '@lahza@2026';

            $user = User::create([
                'name'      => $clientName,
                'email'     => $data['email'],
                'password'  => Hash::make($plainPassword),
                'role'      => 'client',
                'user_type' => 'client',
            ]);

            Client::create([
                'user_id'        => $user->id,
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

            $createdUsers[] = [
                'user' => $user,
                'password' => $plainPassword
            ];
        }
    });

    // STEP 4: Send emails AFTER transaction
    foreach ($createdUsers as $item) {
        try {
            Mail::to($item['user']->email)
                ->send(new WelcomeMail($item['user'], $item['password']));

            Log::info('Welcome email sent', ['email' => $item['user']->email]);
        } catch (\Exception $e) {
            Log::error('Failed to send welcome email', [
                'email' => $item['user']->email,
                'error' => $e->getMessage()
            ]);
        }
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
        'file' => 'required|file|mimes:csv,txt'
    ]);

    $file = fopen($request->file('file'), 'r');
    $header = fgetcsv($file);
    $skipped = 0;
    $created = 0;

    DB::beginTransaction();

    try {
        while (($row = fgetcsv($file)) !== false) {
            $data = array_combine($header, $row);
            /*
            |--------------------------------------------------------------------------
            | Validate Required Fields
            |--------------------------------------------------------------------------
            */
            validator($data, [
                'invoice_date' => 'required|date',
                'due_date' => 'required|date',
                'status' => 'required|in:draft,sent,unpaid,partially_paid,paid,overdue',
                'total_amount' => 'required|numeric',
                'balance_due' => 'required|numeric',
            ])->validate();


            /*
            |--------------------------------------------------------------------------
            | Find Client Using USER NAME
            |--------------------------------------------------------------------------
            */

            $client = null;

            $clientName = $data['client_name'] ?? null;

            if (!empty($clientName)) {

                $client = Client::whereHas('user', function ($q) use ($clientName) {
                    $q->whereRaw('LOWER(name) = ?', [
                        strtolower(trim($clientName))
                    ]);
                })->first();
            }
            /*
            |--------------------------------------------------------------------------
            | Generate Checksum and Skip Duplicates
            |--------------------------------------------------------------------------
            */
            // -------------------------
// Generate normalized checksum 
// -------------------------
$normalizedData = [
    'client_name'  => strtolower(trim($data['client_name'] ?? '')),
    'invoice_date' => $data['invoice_date'],
    'due_date'     => $data['due_date'],
    'total_amount' => round(floatval($data['total_amount']), 2), // normalize number
];

$checksum = md5(json_encode($normalizedData));

// -------------------------
// Skip duplicates
// -------------------------
if (Invoice::where('checksum', $checksum)->exists()) {
    $skipped++;
    continue;
}

// -------------------------
// Create invoice
// -------------------------
$invoice = Invoice::create([
    'client_id'   => $client?->id,
    'invoice_date'=> $data['invoice_date'],
    'due_date'    => $data['due_date'],
    'status'      => $data['status'],
    'total_amount'=> $data['total_amount'],
    'balance_due' => $data['balance_due'],
    'description' => $data['description'] ?? null,
    'notes'       => $data['notes'] ?? null,
    'checksum'    => $checksum, // ✅ use the same checksum
]);

$created++;
            /*
            |--------------------------------------------------------------------------
            | Optional Admin Signature
            |--------------------------------------------------------------------------
            */

            // $this->autoSignAdminSignature($invoice);
        }

        DB::commit();

        return response()->json([
            'message' => 'Invoices imported successfully',
            'created' => $created,
            'skipped' => $skipped,
        ]);

    } catch (\Throwable $e) {

        DB::rollBack();

        return response()->json([
            'error' => $e->getMessage()
        ], 500);
    }
}

    public function uploadServices(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt',
        ]);

        $file = $request->file('file');


        $rows = array_map('str_getcsv', file($file->getRealPath()));
        $header = array_map('trim', array_shift($rows));

        $inserted = 0;
        $skipped = 0;

        foreach ($rows as $index => $row) {
            $rowData = array_combine($header, $row);


            if (Service::where('name', $rowData['name'])->exists()) {
                $skipped++;
                continue;
            }


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
