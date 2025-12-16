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
        return Client::with('user:id,name,email')->get();
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


}
