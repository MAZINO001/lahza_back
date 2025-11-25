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
        $validate = $request->validate([
            'service_id' => ['required|exists:services,id'],
            'title' => ['required|string'],
            'description' => ['required', 'string'],
            'discount_type' => ['required', Rule::in(['percent', 'fixed'])],
            'discount_value' => ['required|numeric'],
            'start_date' => ['required|date'],
            'end_date' => ['required|date'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
        return  DB::transaction(function () use ($validate) {
            $offer = Offer::create([
                'service_id' => $validate["service_id"],
                'title' => $validate["title"],
                'description' => $validate["description"],
                'discount_type' => $validate["discount_type"],
                'discount_value' => $validate["discount_value"],
                'start_date' => $validate["start_date"],
                'end_date' => $validate["end_date"],
                'status' => $validate["status"],
            ]);
            return response()->json($offer, 201);
        });
    }
    public function update(Request $request, $id)
    {
        $offer = Offer::findOrFail($id);
        $validate = $request->validate([
            'service_id' => ['required', 'exists:services,id'],
            'title' => ['required', 'string'],
            'description' => ['required', 'string'],
            'discount_type' => ['required', Rule::in(['percent', 'fixed'])],
            'discount_value' => ['required', 'numeric'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $offer->update($validate);

        return response()->json($offer);
    }


    public function destroy($id)
    {
        $offer = Offer::findOrFail($id);
        $offer->delete();
        return response()->json(null, 204);
    }
}
