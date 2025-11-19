<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
 
class ClientController extends Controller
{
    public function index()
    {
        return Client::with('user')->get();
    }

    public function show($id)
    {
        return Client::with('user')->findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $Client = Client::findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:Clients,email,' . $id,
        ]);

        if (isset($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        }

        $Client->update($data);
        return response()->json($Client);
    }

    public function destroy($id)
    {
        $Client = Client::findOrFail($id);
        $Client->delete();
        return response()->json(['message' => 'Client deleted']);
    }

    public function me(Request $request)
    {
        return $request->Client();
    }

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
}
