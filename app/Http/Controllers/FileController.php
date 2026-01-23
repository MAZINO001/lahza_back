<?php

namespace App\Http\Controllers;

use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FileController extends Controller
{
    public function search(Request $request)
    {
        // 1. Validate inputs
        $validated = $request->validate([
            'type'          => 'nullable|string|max:255',
            'fileable_type' => 'nullable|string|max:255',
            'fileable_id'   => 'nullable|integer',
            'disk'          => 'nullable|string|max:255',
            'with_trashed'  => 'nullable|boolean', // Option to see soft-deleted files
        ]);

        // 2. Start the query
        $query = File::query();

        // 3. Security Logic: Role-based filtering
        $user = Auth::user();

        if ($user->role !== 'admin') {
            // Non-admins can ONLY see files they uploaded
            $query->where('user_id', $user->id);
        } else {
            // Admins can filter by a specific user_id if they want
            if ($request->has('user_id')) {
                $query->where('user_id', $validated['user_id']);
            }
        }

        // 4. Dynamic Filters (Optional fields)
        if ($request->filled('type')) {
            $query->where('type', $validated['type']);
        }

        if ($request->filled('fileable_type')) {
            // Large apps often pass 'Project' but the DB stores 'App\Models\Project'
            // This helper ensures we match the full class name
            $type = $validated['fileable_type'];
            $query->where('fileable_type', str_contains($type, '\\') ? $type : 'App\\Models\\' . ucfirst($type));
        }

        if ($request->filled('fileable_id')) {
            $query->where('fileable_id', $validated['fileable_id']);
        }

        if ($request->filled('disk')) {
            $query->where('disk', $validated['disk']);
        }

        // 5. Handle Soft Deletes
        if ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }

        // 6. Execute with Pagination (Better for big apps)
        $files = $query->latest()->get();

        return response()->json($files);
    }
}