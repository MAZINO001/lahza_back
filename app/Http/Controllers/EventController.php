<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Event;
use App\Models\Team;

class EventController extends Controller
{
    // Show all events
    public function index()
    {
        $events = Event::with('teamUser')->get();
        return response()->json($events);
    }

    // Show a single event
    public function show($id)
    {
        $event = Event::with('teamUser')->findOrFail($id);
        return response()->json($event);
    }

    // Create a new event
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'description' => 'nullable|string',
            'start_hour' => 'nullable|date_format:H:i',
            'end_hour' => 'nullable|date_format:H:i',
            'category' => 'nullable|string',
            'other_notes' => 'nullable|string',
            'status' => 'nullable|in:pending,completed,cancelled',
            'url' => 'nullable|url',
            'type' => 'nullable|string',
            'repeatedly' => 'nullable|in:none,daily,weekly,monthly,yearly',
            'team_ids' => 'nullable|array',         // array of team IDs
            'team_ids.*' => 'exists:team_users,id'
        ]);

        // Create event
        $event = Event::create($data);

        // Attach teams if provided
        if (!empty($data['team_ids'])) {
            $event->teamUser()->attach($data['team_ids']);
        }

        return response()->json($event->load('teamUser'), 201);
    }

    // Update an event
    public function update(Request $request, $id)
    {
        $event = Event::findOrFail($id);

        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date',
            'description' => 'nullable|string',
            'start_hour' => 'nullable|date_format:H:i',
            'end_hour' => 'nullable|date_format:H:i',
            'category' => 'nullable|string',
            'other_notes' => 'nullable|string',
            'status' => 'nullable|in:pending,completed,cancelled',
            'url' => 'nullable|url',
            'type' => 'nullable|string',
            'repeatedly' => 'nullable|in:none,daily,weekly,monthly,yearly',
            'team_ids' => 'nullable|array',
            'team_ids.*' => 'exists:team_users,id'
        ]);

        $event->update($data);

        // Sync teams if provided
        if (isset($data['team_ids'])) {
            $event->teamUser()->sync($data['team_ids']);
        }

        return response()->json($event->load('teamUser'));
    }

    // Delete an event
    public function destroy($id)
    {
        $event = Event::findOrFail($id);
        $event->delete();
        return response()->json(['message' => 'Event deleted successfully']);
    }
}
