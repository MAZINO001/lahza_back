<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;

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
}
