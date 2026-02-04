<?php

namespace App\Http\Controllers;

use App\Models\Pack;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PackController extends Controller
{
    /**
     * Display a listing of packs.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Pack::with(['plans.prices', 'plans.customFields']);

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        // Only active packs
        if ($request->get('active_only', false)) {
            $query->active();
        }

        $packs = $query->paginate($request->get('per_page', 15));

        return response()->json($packs);
    }

    /**
     * Store a newly created pack.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $pack = Pack::create($request->all());
            return response()->json([
                'message' => 'Pack created successfully',
                'pack' => $pack
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create pack: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified pack.
     */
    public function show(Pack $pack): JsonResponse
    {
        $pack->load(['plans.prices', 'plans.customFields', 'plans.subscriptions']);

        return response()->json(['pack' => $pack]);
    }

    /**
     * Update the specified pack.
     */
    public function update(Request $request, Pack $pack): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $pack->update($request->all());

            return response()->json([
                'message' => 'Pack updated successfully',
                'pack' => $pack->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update pack: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified pack.
     */
    public function destroy(Pack $pack): JsonResponse
    {
        try {
            $pack->delete();

            return response()->json(['message' => 'Pack deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete pack: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get all active packs with their plans.
     */
    public function activePacks(): JsonResponse
    {
        $packs = Pack::active()
            ->with(['activePlans.prices', 'activePlans.customFields'])
            ->get();

        return response()->json(['packs' => $packs]);
    }
}