<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\ProjectAssignment;
use App\Models\User;
use App\Models\Project;
class ProjectAssignmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
public function getProjectTeamMembers(Project $project)
{
    $teamUsers = $project->assignments()
        ->with('teamUser.user') // load the nested relation
        ->get();

    return response()->json($teamUsers, 200);
}

public function DeleteProjectTeamMember(Project $project, User $user)
{
    $teamUser = $user->teamUser;

    if (!$teamUser) {
        return response()->json(['message' => 'Team user not found'], 404);
    }

    $deleted = $project->assignments()
        ->where('team_id', $teamUser->id)
        ->delete();

    if ($deleted) {
        return response()->json(['message' => 'Team member removed from project'], 200);
    }

    return response()->json(['message' => 'Team member assignment not found'], 404);
}
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'team_id'=>'required|exists:team_users,id',
            'project_id'=>'required|exists:projects,id',
        ]);

        $exists = ProjectAssignment::where('project_id', $request->project_id)
                    ->where('team_id', $request->team_id)
                    ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Project already assigned to this team user'
            ], 400);
        }
        ProjectAssignment::create([
            'project_id'=>$request->project_id,
            'team_id'=>$request->team_id,
            'assigned_by'=>'1'
            // 'assigned_by'=>Auth::user()->id
        ]);
        return response()->json([
            'message'=>'project is assigned to this team user successfully'
        ],201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        $assingment =  ProjectAssignment::where('project_id', $request->project_id)
            ->where('team_id', $request->team_id);
        $assingment->delete();

    }
}
