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
    public function updatePreferences(Request $request)
{
    $validated = $request->validate([
        'ui.language' => 'sometimes|string|in:en,fr,ar,es',
        'ui.dark_mode' => 'sometimes|boolean',

        'mail' => 'sometimes|array',
        'browser' => 'sometimes|array',
        'mail.*' => 'boolean',
        'browser.*' => 'boolean',
    ]);

    $user = $request->user();

    $user->update([
        'preferences' => array_replace_recursive(
            $user->preferences ?? [],
            $validated
        ),
    ]);

    return response()->json([
        'message' => 'Preferences updated successfully for user '.$user->id,
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
