<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\User;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

use App\Models\ActivityLog;
 use Illuminate\Support\Facades\DB;

class ClientController extends Controller
{
public function index()
{
    // Order by latest created
    $clients = Client::with('user:id,name,email')
                     ->orderBy('created_at', 'desc') // latest first
                     ->get();

    $clients = $clients->map(function ($client) {

        $payments = Payment::where('client_id', $client->id)
            ->where('status', 'paid')
            ->get();

        $totalPaid = $payments->sum('amount');
        $totalPaymentTotal = $payments->sum('total');
        $balanceDue = $totalPaymentTotal - $totalPaid;

        return [
            'client' => $client,
            'totalPaid' => $totalPaid,
            'balanceDue' => $balanceDue,
        ];
    });

    return response()->json($clients);
}


public function show($id)
{
    $client = Client::with('user')->findOrFail($id);

    $payments = \App\Models\Payment::where('client_id', $client->id)
        ->where('status', 'paid')
        ->get();

    $totalPaid = $payments->sum('amount');
    $totalPaymentTotal = $payments->sum('total');
    $balanceDue = $totalPaymentTotal - $totalPaid;

    return response()->json([
        'client' => $client,
        'totalPaid' => $totalPaid,
        'balanceDue' => $balanceDue
    ]);
}



public function update(Request $request, $id)
{
    $client = Client::with('user')->findOrFail($id);

    $data = $request->validate([
        // USER FIELDS
        'name'     => 'sometimes|string|max:255',
        'email'    => 'sometimes|email|unique:users,email,' . $client->user_id,
        'password' => 'sometimes|min:8',

        // CLIENT FIELDS
        'client_type' => 'sometimes|string',
        'company'     => 'sometimes|string|max:255',
        'phone'       => 'sometimes|string|max:20',
        'address'     => 'sometimes|string',
        'city'        => 'sometimes|string',
        'country'     => 'sometimes|string',
        'currency'    => 'sometimes|string|max:10',
        'vat'         => 'nullable|string',
        'siren'       => 'nullable|string',
        'ice'         => 'nullable|string',
    ]);

    DB::transaction(function () use ($client, $data) {

        /** ---------- Update USER ---------- */
        if ($client->user) {
            $userData = collect($data)->only([
                'name', 'email', 'password'
            ])->toArray();

            if (isset($userData['password'])) {
                $userData['password'] = bcrypt($userData['password']);
            }

            if (!empty($userData)) {
                $client->user->update($userData);
            }
        }

        /** ---------- Update CLIENT ---------- */
        $clientData = collect($data)->except([
            'name', 'email', 'password'
        ])->toArray();

        if (!empty($clientData)) {
            $client->update($clientData);
        }
    });

    return response()->json([
        'message' => 'Client updated successfully',
        'client'  => $client->load('user'),
    ]);
}
   public function destroy($id)
{
    $client = Client::with('user')->findOrFail($id);

    DB::transaction(function () use ($client) {
        if ($client->user) {
            $client->user->delete();
        }
        $client->delete();
    });

    return response()->json([
        'message' => 'Client and user deleted successfully'
    ]);
}


    public function me(Request $request)
    {
        return $request->Client();
    }

public function getClientHistory($id)
{
    $client = Client::with('user')->findOrFail($id);
    $logs = ActivityLog::where('record_id', $client->id)->where('action','clients_details')->get();

    return response()->json($logs);
}
}
