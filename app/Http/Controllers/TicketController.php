<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Models\File;
use Illuminate\Support\Facades\Storage;
class TicketController extends Controller
{
    public function index()
    {

        $user = Auth::user();

        $tickets = Ticket::with(['user', 'assignedTo', 'file'])
            ->when($user->role === 'client', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->get();

        return response()->json($tickets);
    }

    public function show($id)
    {
        $user = Auth::user();
        $ticket = Ticket::with(['user', 'assignedTo', 'file'])->findOrFail($id);

        // Check if user is client and doesn't own this ticket
        if ($user->role === 'client' && $ticket->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($ticket);
    }

public function store(Request $request)
{
    $user = Auth::user();

    $validated = $request->validate([
        'user_id' => 'required|exists:users,id',
        'title' => 'required|string|max:255',
        'description' => 'required|string',
        'category' => 'required|string|max:255',
        'subcategory' => 'nullable|string|max:255',
        'status' => 'required|in:open,in_progress,closed',
        'priority' => 'required|in:low,medium,high,urgent',
        'assigned_to' => 'nullable|exists:users,id',
        'attachment' => 'nullable|file|max:10240',
    ]);

    if ($user->role === 'client' && $validated['user_id'] != $user->id) {
        return response()->json(['message' => 'Clients can only create tickets for themselves'], 403);
    }

    $attachmentPath = null;
    if ($request->hasFile('attachment')) {
        $file = $request->file('attachment');
        $attachmentPath = $file->store('tickets/attachments', 'public');
    }

    unset($validated['attachment']);
    $ticket = Ticket::create($validated);

    if ($attachmentPath) {
        File::create([
            "user_id"=> $user->id,
    'fileable_id' => $ticket->id,
    'fileable_type' => Ticket::class,
    'path' => $attachmentPath,
    'type' => 'ticket_attachment',
        ]);
    }

    $ticket->load(['user', 'assignedTo', 'file']);
    return response()->json($ticket, 201);
}

// 3. Update update() method
public function update(Request $request, $id)
{
    $user = Auth::user();
    $ticket = Ticket::findOrFail($id);

    if ($user->role === 'client' && $ticket->user_id !== $user->id) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $validated = $request->validate([
        'user_id' => 'sometimes|nullable|exists:users,id',
        'title' => 'sometimes|nullable|string|max:255',
        'description' => 'sometimes|nullable|string',
        'category' => 'sometimes|nullable|string|max:255',
        'subcategory' => 'sometimes|nullable|string|max:255',
        'status' => 'sometimes|in:open,in_progress,resolved',
        'priority' => 'sometimes|nullable|in:low,medium,high,urgent',
        'assigned_to' => 'sometimes|nullable|exists:users,id',
        'attachment' => 'sometimes|nullable|file|max:10240', // Changed
    ]);

    if ($user->role === 'client') {
        unset($validated['user_id']);
        unset($validated['assigned_to']);
    }

    // NEW: Handle new attachment
    if ($request->hasFile('attachment')) {
        // Delete old files
        $oldFiles = $ticket->file()->get();
        foreach ($oldFiles as $oldFile) {
            Storage::disk('public')->delete($oldFile->path);
            $oldFile->delete();
        }

        // Store new file
        $file = $request->file('attachment');
        $attachmentPath = $file->store('tickets/attachments', 'public');

        File::create([
    "user_id"=> $user->id,
    'fileable_id' => $ticket->id,
    'fileable_type' => Ticket::class,
    'path' => $attachmentPath,
    'type' => 'ticket_attachment',
]);

        unset($validated['attachment']);
    } else {
        unset($validated['attachment']);
    }

    $ticket->update($validated);
    $ticket->load(['user', 'assignedTo', 'file']);
    return response()->json($ticket);
}

// 4. Update destroy() method
public function destroy($id)
{
    $user = Auth::user();
    $ticket = Ticket::findOrFail($id);

    if ($user->role === 'client' && $ticket->user_id !== $user->id) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    // NEW: Delete associated files from storage
    foreach ($ticket->file as $file) {
        Storage::disk('public')->delete($file->path);
        $file->delete();
    }

    $ticket->delete();
    return response()->json(['message' => 'Ticket deleted successfully']);
}

public function downloadAttachment($ticketId, $fileId)
{
    $user = Auth::user();
    $ticket = Ticket::findOrFail($ticketId);

    if ($user->role === 'client' && $ticket->user_id !== $user->id) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $file = File::where('id', $fileId)
        ->where('fileable_id', $ticketId)
        ->where('fileable_type', Ticket::class)
        ->firstOrFail();

    if (!Storage::disk('public')->exists($file->path)) {
        return response()->json(['message' => 'File not found'], 404);
    }

    // Get the MIME type from the file
    $mimeType = Storage::disk('public')->mimeType($file->path);

    return Storage::disk('public')->download(
        $file->path,
        basename($file->path),
        ['Content-Type' => $mimeType]
    );
}
}
