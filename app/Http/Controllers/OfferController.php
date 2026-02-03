<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OfferController extends Controller
{
    public function index()
    {
        $offers = Offer::get();

        return response()->json($offers);
    }

    public function show($id)
    {
        $offer = Offer::findOrFail($id);
        return response()->json($offer);
    }

public function store(Request $request)
{
    $this->authorize('create', Offer::class);
    $validated = $request->validate([
        'service_id' => 'required|exists:services,id',
        'title' => 'required|string|max:255',
        'description' => 'required|string',
        'discount_type' => ['required', Rule::in(['percent', 'fixed'])],
        'discount_value' => 'required|numeric|min:0',
        'start_date' => 'required|date',
        'end_date' => 'required|date|after_or_equal:start_date',
        'status' => ['required', Rule::in(['active', 'inactive'])],
        'placement' => 'nullable|array',
        'placement.*' => 'string|in:header,calendar,quotes,invoices,projects',
    ]);

    $offer = Offer::create($validated);

    return response()->json($offer, 201);
}

public function update(Request $request, $id)
{
    $offer = Offer::findOrFail($id);
    $this->authorize('update', $offer);

    $validated = $request->validate([
        'service_id' => 'required|exists:services,id',
        'title' => 'required|string|max:255',
        'description' => 'required|string',
        'discount_type' => ['required', Rule::in(['percent', 'fixed'])],
        'discount_value' => 'required|numeric|min:0',
        'start_date' => 'required|date',
        'end_date' => 'required|date|after_or_equal:start_date',
        'status' => ['required', Rule::in(['active', 'inactive'])],
        'placement' => 'nullable|array',
        'placement.*' => 'string|in:header,calendar',
    ]);

    $offer->update($validated);

    return response()->json($offer);
}

    // public function store(Request $request)
    // {
    //     $this->authorize('create', Offer::class);
    //     $validated = $request->validate([
    //         'service_id' => 'required|exists:services,id',
    //         'title' => 'required|string|max:255',
    //         'description' => 'required|string',
    //         'discount_type' => ['required', Rule::in(['percent', 'fixed'])],
    //         'discount_value' => 'required|numeric|min:0',
    //         'start_date' => 'required|date',
    //         'end_date' => 'required|date|after_or_equal:start_date',
    //         'status' => ['required', Rule::in(['active', 'inactive'])],
    //         'placement' => 'array',
    //     ]);

    //     $offer = Offer::create($validated);

    //     return response()->json($offer, 201);
    // }

    // public function update(Request $request, $id)
    // {
    //     $this->authorize('update');
    //     $offer = Offer::findOrFail($id);

    //     $validated = $request->validate([
    //         'service_id' => 'required|exists:services,id',
    //         'title' => 'required|string|max:255',
    //         'description' => 'required|string',
    //         'discount_type' => ['required', Rule::in(['percent', 'fixed'])],
    //         'discount_value' => 'required|numeric|min:0',
    //         'start_date' => 'required|date',
    //         'end_date' => 'required|date|after_or_equal:start_date',
    //         'status' => ['required', Rule::in(['active', 'inactive'])],
    //     ]);

    //     $offer->update($validated);

    //     return response()->json($offer);
    // }


    public function destroy($id)
    {
        $offer = Offer::findOrFail($id);
        $offer->delete();
        return response()->json(null, 204);
    }
}
