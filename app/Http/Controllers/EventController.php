<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Event;
use App\Models\Team;
use App\Models\TeamUser;
use App\Models\Client;
use App\Models\Intern;
use App\Models\Other;

class EventController extends Controller
{
    // Show all events
    public function index()
    {
        $events = Event::with('guests.guestable')->get();
        return response()->json($events);
    }

    // Show a single event
    public function show($id)
    {
        $event = Event::with('guests.guestable')->findOrFail($id);
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
        'color' => 'nullable|string',
        'all_day' => 'nullable|boolean',
        'guests' => 'nullable|array',
        'guests.*' => 'required|integer',
    ]);

    $guests = $data['guests'] ?? null;
    unset($data['guests']);

    $event = Event::create($data);

    if (!empty($guests)) {
        $event->guests()->sync($guests);
    }

    return response()->json($event->load('guests'), 201);
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
        'color' => 'nullable|string',
        'all_day' => 'nullable|boolean',
        'guests' => 'nullable|array',
        'guests.*' => 'required|integer',
    ]);

    $guests = $data['guests'] ?? null;
    unset($data['guests']);

    $event->update($data);

    if (!is_null($guests)) {
        $event->guests()->sync($guests);
    }

    return response()->json($event->load('guests'));
}


    // Delete an event
    public function destroy($id)
    {
        $event = Event::findOrFail($id);
        $event->delete();
        return response()->json(['message' => 'Event deleted successfully']);
    }
}
