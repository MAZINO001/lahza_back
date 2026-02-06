<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
class EventController extends Controller
{
    // Show all events
  public function index()
{
    $user = Auth::user();

    if ($user->role === 'admin') {
        // Admin sees all events
        $events = Event::with('guests')->get();
    } else {
        // Non-admin sees only assigned events via event_guests
        $events = Event::whereHas('guests', function ($query) use ($user) {
            $query->where('users.id', $user->id);
        })->with('guests')->get();
    }

    return response()->json($events);
}


    // Show a single event
public function show($id)
{
    $user = Auth::user();

    $event = Event::with('guests')->findOrFail($id);

    if ($user->role !== 'admin') {
        if (! $event->guests->contains($user->id)) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }
    }

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
        'urgency' => 'nullable|in:low,medium,high,urgent',
        'rrule' => 'nullable|json',
    ]);

    $guests = $data['guests'] ?? null;
    unset($data['guests']);

    $event = Event::create($data);
      if (!empty($guests)) {
        $validGuestIds = User::whereIn('id', $guests)
            ->pluck('id')
            ->toArray();

        if (!empty($validGuestIds)) {
            $event->guests()->attach($validGuestIds);
        }
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
        'urgency' => 'nullable|in:low,medium,high,urgent',
        'rrule' => 'nullable|json',
    ]);

      $guests = $data['guests'] ?? [];
    unset($data['guests']);

    $event->update($data);

    if (!empty($guests)) {
        $validGuestIds = User::whereIn('id', $guests)
            ->pluck('id')
            ->toArray();

        if (!empty($validGuestIds)) {
            $event->guests()->syncWithoutDetaching($validGuestIds);
        }
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
