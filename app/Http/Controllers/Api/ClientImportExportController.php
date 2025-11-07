<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Client;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Maatwebsite\Excel\Facades\Excel;
use League\Csv\Reader;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log as FacadesLog;

class ClientImportExportController extends Controller
{
    // Export CSV or JSON
    public function export(Request $request)
    {
        try {
            $format = $request->query('format', 'csv');

            $clients = Client::select(
                'user_id',
                // 'name',
                'company',
                'address',
                'phone',
                'city',
                'country',
                'currency',
                'client_type',
                'siren',
                'vat',
                'ice'
            )->get();

            if ($format === 'json') {
                return response()->json($clients);
            }

            $response = new StreamedResponse(function () use ($clients) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, [
                    'user_id',
                    // 'name',
                    'company',
                    'address',
                    'phone',
                    'city',
                    'country',
                    'currency',
                    'client_type',
                    'siren',
                    'vat',
                    'ice'
                ]);

                foreach ($clients as $c) {
                    fputcsv($handle, [
                        $c->user_id,
                        // $c->name,
                        $c->company,
                        $c->address,
                        $c->phone,
                        $c->city,
                        $c->country,
                        $c->currency,
                        $c->client_type,
                        $c->siren,
                        $c->vat,
                        $c->ice
                    ]);
                }

                fclose($handle);
            }, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="clients.csv"',
            ]);

            return $response;
        } catch (\Exception $e) {
            FacadesLog::error('Export failed: ' . $e->getMessage());
            return response()->json(['message' => 'Export failed: ' . $e->getMessage()], 500);
        }
    }



    // Import CSV or JSON file
    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240', // max 10MB, adjust as needed
            'has_header' => 'sometimes|in:0,1',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());

        DB::beginTransaction();
        try {
            if (in_array($ext, ['json'])) {
                $json = json_decode(file_get_contents($file->getRealPath()), true);
                if (!is_array($json)) {
                    throw new \Exception('Invalid JSON');
                }
                // normalize and upsert
                $this->processRows($json);
            } else { // assume CSV
                $hasHeader = $request->input('has_header', 1);
                $handle = fopen($file->getRealPath(), 'r');
                if ($handle === false) {
                    throw new \Exception('Cannot open uploaded file');
                }

                $rows = [];
                if ($hasHeader) {
                    $header = fgetcsv($handle);
                    if ($header === false) throw new \Exception('Empty CSV or invalid header');
                    // normalize header names to lowercase
                    $header = array_map(function ($h) {
                        return strtolower(trim($h));
                    }, $header);
                    while (($data = fgetcsv($handle)) !== false) {
                        if (count($data) === 1 && trim($data[0]) === '') continue;
                        $rows[] = array_combine($header, $data);
                    }
                } else {
                    // no header: assume specific column order: id,name,email,phone,company,country
                    while (($data = fgetcsv($handle)) !== false) {
                        if (count($data) === 1 && trim($data[0]) === '') continue;
                        $rows[] = [
                            'user_id' => $data[0] ?? null,
                            // 'name' => $data[1] ?? null,
                            'company' => $data[1] ?? null,
                            'address' => $data[2] ?? null,
                            'phone' => $data[3] ?? null,
                            'city' => $data[4] ?? null,
                            'country' => $data[5] ?? null,
                            'currency' => $data[6] ?? null,
                            'client_type' => $data[7] ?? null,
                            'siren' => $data[8] ?? null,
                            'vat' => $data[9] ?? null,
                            'ice' => $data[10] ?? null,
                        ];
                    }
                }
                fclose($handle);

                $this->processRows($rows);
            }

            DB::commit();
            // return response()->json(['message' => 'Import successful']);
            return response()->json($rows);
        } catch (\Exception $e) {
            DB::rollBack();
            FacadesLog::error('Clients import failed: ' . $e->getMessage());
            return response()->json(['message' => 'Import failed: ' . $e->getMessage()], 500);
        }
    }

    // Process rows array: validate & upsert
    protected function processRows(array $rows)
    {
        // Basic row-level validation & upsert
        $chunks = array_chunk($rows, 500);
        foreach ($chunks as $chunk) {
            $insertData = [];
            foreach ($chunk as $r) {
                // normalize keys (lowercase)
                $row = [];
                foreach ($r as $k => $v) {
                    $row[strtolower(trim($k))] = is_string($v) ? trim($v) : $v;
                }
                // required fields: at least name or email — adjust to your rules
                // if (empty($row['email']) && empty($row['name'])) continue;
                if (empty($row['email']) && empty($row['name'])) continue;

                $insertData[] = [
                    'user_id' => $row['user_id'] ?? null,
                    // 'name' => $row['name'] ?? null,
                    'company' => $row['company'] ?? null,
                    'address' => $row['address'] ?? null,
                    'phone' => $row['phone'] ?? null,
                    'city' => $row['city'] ?? null,
                    'country' => $row['country'] ?? null,
                    'currency' => $row['currency'] ?? null,
                    'client_type' => $row['client_type'] ?? null,
                    'siren' => $row['siren'] ?? null,
                    'vat' => $row['vat'] ?? null,
                    'ice' => $row['ice'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Upsert by email (if email exists update, else insert). Adjust unique key as needed.
            // Note: if id is present and you want to preserve it, remove null id rows.
            foreach ($insertData as $data) {
                $unique = [];
                if (!empty($data['email'])) {
                    $unique['email'] = $data['email'];
                } elseif (!empty($data['id'])) {
                    $unique['id'] = $data['id'];
                } else {
                    // fallback: insert as new
                    Client::create(Arr::except($data, ['id']));
                    continue;
                }

                Client::updateOrCreate($unique, Arr::except($data, ['id'])); // don't overwrite id
            }
        }
    }
}
