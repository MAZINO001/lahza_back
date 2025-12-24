<?php

namespace App\Http\Controllers;

use App\Models\TeamAdditionalData;
use Illuminate\Http\Request;

class TeamAdditionalDataController extends Controller
{
      public function store(Request $request)
    {
        $validated = $request->validate([
            'team_user_id' => 'required|exists:team_users,id|unique:team_additional_data,team_user_id',

            'bank_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:255',
            'iban' => 'nullable|string|max:255',

            'contract_type' => 'nullable|in:CDI,CDD,Freelance,Intern',
            'contract_start_date' => 'nullable|date',
            'contract_end_date' => 'nullable|date|after_or_equal:contract_start_date',
            'contract_file' => 'nullable|string',

            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:50',

            'job_title' => 'nullable|string|max:255',
            'salary' => 'nullable|numeric|min:0',

            'certifications' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $detail = TeamAdditionalData::create($validated);

        return response()->json($detail, 201);
    }

    public function show($teamUserId)
    {
        return TeamAdditionalData::where('team_user_id', $teamUserId)->firstOrFail();
    }

    public function update(Request $request, $teamUserId)
    {
        $detail = TeamAdditionalData::where('team_user_id', $teamUserId)->firstOrFail();

        $validated = $request->validate([
            'bank_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:255',
            'iban' => 'nullable|string|max:255',

            'contract_type' => 'nullable|in:CDI,CDD,Freelance,Intern',
            'contract_start_date' => 'nullable|date',
            'contract_end_date' => 'nullable|date|after_or_equal:contract_start_date',
            'contract_file' => 'nullable|string',

            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:50',

            'job_title' => 'nullable|string|max:255',
            'salary' => 'nullable|numeric|min:0',

            'certifications' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $detail->update($validated);

        return response()->json($detail);
    }

    public function destroy($teamUserId)
    {
        TeamAdditionalData::where('team_user_id', $teamUserId)->firstOrFail()->delete();

        return response()->json(['message' => 'Detail deleted']);
    }
}
