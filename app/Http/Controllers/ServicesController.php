<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;

class ServicesController extends Controller
{
    public function index()
    {
        return Service::all();
    }

    public function store(Request $request)
    {
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
        return Service::findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $service = Service::findOrFail($id);

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
        $service->delete();
        return response()->json(null, 204);
    }

    public function getInvoices(Service $service)
{
    return $service->invoices;
}
    public function getQuotes(Service $service)
{
    return $service->quotes;
}
}
