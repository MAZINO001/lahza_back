<?php

namespace App\Http\Controllers;

use App\Models\Objective;
use Illuminate\Http\Request;
use App\Models\Event;
use Illuminate\Support\Facades\DB;
class ObjectiveController extends Controller
{
    public function index()
    {
        return Objective::with('owner')->get();
    }

    public function show(Objective $objective)
    {
        return $objective->load('owner');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'nullable|in:pending,in_progress,completed,cancelled',
            'progress' => 'nullable|integer|min:0|max:100',
            'owner_id' => 'nullable|exists:users,id',
        ]);

        $objective = Objective::create($data);

        return response()->json($objective, 201);
    }

    public function update(Request $request, Objective $objective)
    {
        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',
            'status' => 'nullable|in:pending,in_progress,completed,cancelled',
            'progress' => 'nullable|integer|min:0|max:100',
            'owner_id' => 'nullable|exists:users,id',
        ]);

        $objective->update($data);

        return response()->json($objective);
    }

    public function destroy(Objective $objective)
    {
        $objective->delete();
        return response()->noContent();
    }
    public function converObjecTtoEvent(Objective $objective)
    {
        $event=null;
        DB::transaction(function () use ($objective , &$event) {
        $event = Event::create([
        'title'       => $objective->title,
        'start_date'  => $objective->start_date,
        'end_date'    => $objective->end_date,
        'status'      => 'pending',
        'type'        => 'objective',
        'description' => 'Converted from Objective ID: ' . $objective->id . '+ description :' . $objective->description,

    ]);
Objective::find($objective->id)->delete();}
);
    return response()->json([
        'message' => 'Objective successfully converted to event.',
        'event' => $event
    ], 201);
    }
}
