<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Client;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\Service;

class csvController extends Controller
{
    // ─────────────────────────────────────────────────────────────
    // MAPPERS
    // ─────────────────────────────────────────────────────────────

    private function mapClientRow(array $data): array
    {
        $clientTypeMap = [
            'business'   => 'company',
            'company'    => 'company',
            'individual' => 'individual',
            'b2b'        => 'company',
            'b2c'        => 'individual',
        ];

        $rawType    = strtolower(trim($data['Customer Sub Type'] ?? $data['client_type'] ?? ''));
        $clientType = $clientTypeMap[$rawType] ?? null;

        // ✅ FIX: Display Name fallback chain → First+Last → Company → 'Client'
        $firstName = trim($data['First Name'] ?? '');
        $lastName  = trim($data['Last Name']  ?? '');
        $fullName  = trim("$firstName $lastName");

        $name = trim($data['Display Name'] ?? '')
            ?: $fullName
            ?: trim($data['Company Name'] ?? '')
            ?: 'Client';

        return [
            // ✅ FIX: email lowercased at source
            'email'       => strtolower(trim($data['EmailID'] ?? $data['email'] ?? '')),
            'name'        => $name,
            'company'     => $data['Company Name']    ?? $data['company']       ?? null,
            'client_type' => $clientType,
            'phone'       => $data['Phone']           ?? $data['Billing Phone'] ?? $data['phone'] ?? null,
            'address'     => $data['Billing Address'] ?? $data['address']       ?? null,
            'city'        => $data['Billing City']    ?? $data['city']          ?? null,
            'country'     => $data['Billing Country'] ?? $data['country']       ?? null,
            'currency'    => $data['Currency Code']   ?? $data['currency']      ?? null,
            // ✅ FIX: CF.TVA added (second CSV file has it, first doesn't — both handled)
            'vat'         => $data['CF.TVA']          ?? $data['vat']           ?? null,
            'siren'       => $data['CF.SIREN']        ?? $data['siren']         ?? null,
            'ice'         => $data['CF.ICE']          ?? $data['ice']           ?? null,
        ];
    }

    private function mapInvoiceRow(array $data): array
    {
        $statusMap = [
            'sent'           => 'sent',
            'draft'          => 'draft',
            'paid'           => 'paid',
            'unpaid'         => 'unpaid',
            'overdue'        => 'overdue',
            'partially paid' => 'partially_paid',
            'partially_paid' => 'partially_paid',
            'closed'         => 'paid', // ✅ Zoho "Closed" = paid
        ];

        $rawStatus = strtolower(trim($data['Invoice Status'] ?? $data['status'] ?? 'draft'));
        $status    = $statusMap[$rawStatus] ?? 'paid';

        return [
            'client_name'  => trim($data['Customer Name'] ?? $data['client_name'] ?? ''),
            'invoice_date' => $data['Invoice Date']       ?? $data['invoice_date'] ?? null,
            'due_date'     => $data['Due Date']           ?? $data['due_date']     ?? null,
            'status'       => $status,
            'total_amount' => $data['Total']              ?? $data['total_amount'] ?? 0,
            'balance_due'  => $data['Balance']            ?? $data['balance_due']  ?? 0,
            'notes'        => $data['Notes']              ?? $data['notes']        ?? null,
            // ✅ Service linking via Item Name
            'item_name'    => trim($data['Item Name']     ?? ''),
            'quantity'     => $data['Quantity']           ?? 1,      // ← add
            'unit_price'   => $data['Item Price']         ?? 0,      // ← add
            'tax'          => $data['Item Tax1 %']        ?? 0,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // UPLOAD CLIENTS
    // ─────────────────────────────────────────────────────────────

    public function uploadClients(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt',
        ]);

        $file = $request->file('file');
        $path = $file->getRealPath();

        $created = 0;
        $skipped = 0;

        // STEP 1: Read file using streaming (no memory overload)
        if (($handle = fopen($path, 'r')) !== false) {

            $header = array_map('trim', fgetcsv($handle));
            $rows   = [];

            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) === count($header)) {
                    $rows[] = array_combine($header, $row);
                }
            }

            fclose($handle);
        } else {
            return response()->json(['message' => 'Could not open file'], 400);
        }

        // STEP 2: Map all rows
        $mappedRows = array_map(fn($row) => $this->mapClientRow($row), $rows);

        // STEP 3: Get all emails from mapped rows — ✅ normalized to lowercase
        $emails = collect($mappedRows)
            ->pluck('email')
            ->filter()
            ->map(fn($e) => strtolower($e))
            ->toArray();

        // STEP 4: Get existing emails in ONE query — ✅ also lowercased
        $existingEmails = User::whereIn('email', $emails)
            ->pluck('email')
            ->map(fn($e) => strtolower($e))
            ->toArray();

        DB::transaction(function () use ($mappedRows, &$created, &$skipped, $existingEmails) {

            $seenEmails = []; // track duplicates within the CSV itself

            foreach ($mappedRows as $data) {

                // ✅ email already lowercased from mapper
                if (
                    empty($data['email']) ||
                    !filter_var($data['email'], FILTER_VALIDATE_EMAIL) ||
                    in_array($data['email'], $existingEmails) ||
                    in_array($data['email'], $seenEmails)
                ) {
                    $skipped++;
                    continue;
                }

                $seenEmails[] = $data['email'];

                $clientName    = $data['name'];
                $cleanName     = strtolower(str_replace(' ', '', $clientName));
                $plainPassword = $cleanName . '@lahza@2026';

                $user = User::create([
                    'name'      => $clientName,
                    'email'     => $data['email'],
                    'password'  => Hash::make($plainPassword),
                    'role'      => 'client',
                    'user_type' => 'client',
                ]);

                Client::create([
                    'user_id'     => $user->id,
                    'client_type' => $data['client_type'],
                    'company'     => $data['company'],
                    'phone'       => $data['phone'],
                    'address'     => $data['address'],
                    'city'        => $data['city'],
                    'country'     => $data['country'],
                    'currency'    => $data['currency'],
                    'vat'         => $data['vat'],
                    'siren'       => $data['siren'],
                    'ice'         => $data['ice'],
                ]);

                $created++;
            }
        });

        // ✉️ Email sending skipped for now — re-enable when ready:
        // foreach ($createdUsers as $item) {
        //     try {
        //         Mail::to($item['user']->email)->send(new WelcomeMail($item['user'], $item['password']));
        //         Log::info('Welcome email sent', ['email' => $item['user']->email]);
        //     } catch (\Exception $e) {
        //         Log::error('Failed to send welcome email', ['email' => $item['user']->email, 'error' => $e->getMessage()]);
        //     }
        // }

        return response()->json([
            'message' => 'File imported successfully',
            'created' => $created,
            'skipped' => $skipped,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // UPLOAD INVOICES
    // ─────────────────────────────────────────────────────────────

    public function uploadInvoices(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt'
        ]);

        $file    = fopen($request->file('file'), 'r');
        $header  = fgetcsv($file);
        $skipped = 0;
        $created = 0;

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($file)) !== false) {

                // MAP raw Zoho/simple row → normalized fields
                $data = $this->mapInvoiceRow(array_combine($header, $row));

                /*
                |--------------------------------------------------------------------------
                | Validate Required Fields
                |--------------------------------------------------------------------------
                */
                $validation = validator($data, [
                    'invoice_date' => 'required|date',
                    'due_date'     => 'required|date',
                    'status'       => 'required|in:draft,sent,unpaid,partially_paid,paid,overdue',
                    'total_amount' => 'required|numeric',
                    'balance_due'  => 'required|numeric',
                ]);

                if ($validation->fails()) {
                    $skipped++;
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Find Client Using USER NAME or COMPANY NAME
                |--------------------------------------------------------------------------
                */
                $client     = null;
                $clientName = $data['client_name'] ?? null;

                if (!empty($clientName)) {
                    $clientName = strtolower(trim($clientName));

                    $client = Client::whereHas('user', function ($q) use ($clientName) {
                        $q->whereRaw('LOWER(name) = ?', [$clientName]);
                    })->orWhereRaw('LOWER(company) = ?', [$clientName])->first();
                }

                /*
                |--------------------------------------------------------------------------
                | ✅ Find Service via Item Name
                |--------------------------------------------------------------------------
                */
                $service  = null;
                $itemName = $data['item_name'] ?? null;

                if (!empty($itemName)) {
                    $service = Service::whereRaw('LOWER(name) = ?', [strtolower($itemName)])->first();
                }

                /*
                |--------------------------------------------------------------------------
                | Generate Checksum and Skip Duplicates
                |--------------------------------------------------------------------------
                */
                $normalizedData = [
                    'client_name'  => strtolower(trim($data['client_name'] ?? '')),
                    'invoice_date' => $data['invoice_date'],
                    'due_date'     => $data['due_date'],
                    'total_amount' => round(floatval($data['total_amount']), 2),
                ];

                $checksum = md5(json_encode($normalizedData));

                if (Invoice::where('checksum', $checksum)->exists()) {
                    $skipped++;
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Create Invoice
                |--------------------------------------------------------------------------
                */
                $invoice = Invoice::create([
                    'client_id'    => $client?->id,
                    'invoice_date' => $data['invoice_date'],
                    'due_date'     => $data['due_date'],
                    'status'       => $data['status'],
                    'total_amount' => $data['total_amount'],
                    'balance_due'  => $data['balance_due'],
                    'notes'        => $data['notes'],
                    'checksum'     => $checksum,
                ]);

/*
|--------------------------------------------------------------------------
| ✅ Attach Service to Invoice if found
|--------------------------------------------------------------------------
*/
if ($service && $invoice) {
    $quantity       = floatval($data['quantity']   ?? 1);
    $unitPrice      = floatval($data['unit_price'] ?? $service->base_price);
    $tax            = floatval($data['tax']        ?? $service->tax_rate ?? 0);
    $individualTotal = round($quantity * $unitPrice, 2);

    DB::table('invoice_services')->insert([
        'invoice_id'       => $invoice->id,
        'service_id'       => $service->id,
        'quantity'         => $quantity,
        'tax'              => $tax,
        'individual_total' => $individualTotal,
        'created_at'       => now(),
        'updated_at'       => now(),
    ]);
}
                $created++;

                /*
                |--------------------------------------------------------------------------
                | Optional Admin Signature
                |--------------------------------------------------------------------------
                */
                // $this->autoSignAdminSignature($invoice);
            }

            fclose($file);
            DB::commit();

            return response()->json([
                'message' => 'Invoices imported successfully',
                'created' => $created,
                'skipped' => $skipped,
            ]);

        } catch (\Throwable $e) {

            DB::rollBack();
            fclose($file);

            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // UPLOAD SERVICES (unchanged)
    // ─────────────────────────────────────────────────────────────

    public function uploadServices(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt',
        ]);

        $file = $request->file('file');

        $rows   = array_map('str_getcsv', file($file->getRealPath()));
        $header = array_map('trim', array_shift($rows));

        $inserted = 0;
        $skipped  = 0;

        foreach ($rows as $index => $row) {
            $rowData = array_combine($header, $row);

            if (Service::where('name', $rowData['name'])->exists()) {
                $skipped++;
                continue;
            }

            $checksum = md5(json_encode([
                $rowData['name']        ?? '',
                $rowData['description'] ?? '',
                $rowData['base_price']  ?? 0,
            ]));

            if (Service::where('checksum', $checksum)->exists()) {
                $skipped++;
                continue;
            }

            Service::create([
                'name'        => $rowData['name']        ?? 'Unnamed Service',
                'description' => $rowData['description'] ?? null,
                'base_price'  => $rowData['base_price']  ?? 0,
                'checksum'    => $checksum,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            $inserted++;
        }

        return response()->json([
            'message'            => 'Services processed',
            'inserted'           => $inserted,
            'skipped_duplicates' => $skipped,
            'total_rows'         => count($rows),
        ]);
    }
}