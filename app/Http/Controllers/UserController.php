<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Models\ActivityLog;
use App\Models\TeamUser;
use App\Models\Intern;
use App\Models\TeamAdditionalData;
use Illuminate\Support\Facades\Storage;
class UserController extends Controller
{
    public function index()
    {
        return User::all();
    }

    public function show($id)
    {
        return User::findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'password' => 'sometimes|string|min:6|confirmed',
        ]);

        if (isset($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        }

        $user->update($data);
        return response()->json($user);
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        return response()->json(['message' => 'User deleted']);
    }

    public function me(Request $request)
    {
        return $request->user();
    }
    public function updatePreferences(Request $request)
    {
        $validated = $request->validate([
            'language' => 'sometimes|string|in:en,fr,ar,es',
            'dark_mode' => 'sometimes|boolean',
            'email_notifications' => 'sometimes|boolean',
            'browser_notifications' => 'sometimes|boolean',
        ]);

        $user = $request->user();
        $preferences = $user->preferences ?? [];
        
        // Merge new preferences with existing ones
        $preferences = array_merge($preferences, $validated);
        
        $user->update(['preferences' => $preferences]);

        return response()->json([
            'message' => 'Preferences updated successfully',
            'preferences' => $user->preferences,
        ]);
    }

    public function convertTeamUser($internId)
    {
        $intern = Intern::findOrFail($internId);

        
        $team = TeamUser::create([
            'user_id' => $intern->user_id,
            'department' => $intern->department,
            'poste' => $intern->post?? null,

        ]);
        TeamAdditionalData::create([
            'team_user_id' => $team->id,
            'contract_start_date' => $intern->end_date??null,
            'portfolio' => $intern->portfolio??null,
            'cv' => $intern->cv,
            'notes' => 'Converted from intern to team user',
            'github' => $intern->github??null,
            'linkedin' => $intern->linkedin??null,
        ]);
    Storage::move('csv/'.$intern->cv->getClientOriginalName(), 'team_additional_data/cv/'.$intern->cv->getClientOriginalName());

        $intern->delete();

        return response()->json(['message' => 'Team user converted successfully']);
    }
}
