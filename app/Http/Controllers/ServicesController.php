<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class ServicesController extends Controller
{
    public function index()
    {
        return Service::all();
    }

    public function store(Request $request)
    {
        $this->authorize('create');
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:65535',
            'base_price'  => 'required|numeric|min:0',
            'tax_rate'    => 'required|numeric|min:0|max:100',
            'status'      => 'required|in:active,inactive',
        ]);

        $service = Service::create($validated);

        return response()->json($service, 201);
    }


    public function show($id)
    {
        $this->authorize('view',Service::findOrFail($id));
        return Service::findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $service = Service::findOrFail($id);
        
        $this->authorize('update',$service);
        
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:65535',
            'base_price'  => 'required|numeric|min:0',
            'tax_rate'    => 'required|numeric|min:0|max:100',
            'status'      => 'required|in:active,inactive',
        ]);

        $service->update($validated);
        return response()->json($service);
    }

    public function destroy($id)
    {
        $service = Service::findOrFail($id);
        $this->authorize('delete',$service);
        $service->delete();
        return response()->json(null, 204);
    }

public function getQuotes(Service $service)
{
    $user = Auth::user();

    $quotes = $service->quotes()
        ->when($user->role === 'client', function($query) use ($user) {
            $query->where('client_id', $user->client->first()->id ?? 0);
        })
        ->get();

    return response()->json($quotes);
}

public function getInvoices(Service $service)
{
    $user = Auth::user();

    $invoices = $service->invoices()
        ->when($user->role === 'client', function($query) use ($user) {
            $query->where('client_id', $user->client->first()->id ?? 0);
        })
        ->get();

    return response()->json($invoices);
}

}
