<?php

namespace App\Http\Controllers;

use App\Models\TeamAdditionalData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TeamAdditionalDataController extends Controller
{
    //   public function store(Request $request)
    // {
    //     $validated = $request->validate([
    //         'team_user_id' => 'required|exists:team_users,id|unique:team_additional_data,team_user_id',

    //         'bank_name' => 'nullable|string|max:255',
    //         'bank_account_number' => 'nullable|string|max:255',
    //         'iban' => 'nullable|string|max:255',

    //         'contract_type' => 'nullable|in:CDI,CDD,Freelance,Intern',
    //         'contract_start_date' => 'nullable|date',
    //         'contract_end_date' => 'nullable|date|after_or_equal:contract_start_date',
    //         'contract_file' => 'nullable|string',

    //         'emergency_contact_name' => 'nullable|string|max:255',
    //         'emergency_contact_phone' => 'nullable|string|max:50',

    //         'job_title' => 'nullable|string|max:255',
    //         'salary' => 'nullable|numeric|min:0',

    //         'certifications' => 'nullable|string',
    //         'notes' => 'nullable|string',
    //         'portfolio' => 'nullable|string',
    //         'github' => 'nullable|string',
    //         'linkedin' => 'nullable|string',
    //         'cv' => 'nullable|string',
    //     ]);
    //     if(file_exists('cv')){
    //         $validated['cv'] = $request->file('cv')->store('team_additional_data/cv');
    //     }

    //     $detail = TeamAdditionalData::create($validated);

    //     return response()->json($detail, 201);
    // }

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
            'contract_file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:50',
            'job_title' => 'nullable|string|max:255',
            'salary' => 'nullable|numeric|min:0',
            'certifications' => 'nullable|string',
            'notes' => 'nullable|string',
            'portfolio' => 'nullable|string',
            'github' => 'nullable|string',
            'linkedin' => 'nullable|string',
            'cv' => 'nullable|file|mimes:pdf,doc,docx|max:5120',
        ]);

        // Ensure directories exist
        Storage::disk('public')->makeDirectory('team_additional_data/cv', 0755, true);
        Storage::disk('public')->makeDirectory('team_additional_data/contracts', 0755, true);

        // Handle CV file upload
        if ($request->hasFile('cv')) {
            $validated['cv'] = $request->file('cv')->store('team_additional_data/cv', 'public');
        }

        // Handle contract file upload
        if ($request->hasFile('contract_file')) {
            $validated['contract_file'] = $request->file('contract_file')->store('team_additional_data/contracts', 'public');
        }

        $detail = TeamAdditionalData::create($validated);

        return response()->json($detail, 201);
    }


    // public function show($teamUserId)
    // {
    //     return TeamAdditionalData::where('team_user_id', $teamUserId)->firstOrFail();
    // }

    public function show($teamUserId)
{
    $data = TeamAdditionalData::where('team_user_id', $teamUserId)->first();

    return $data ?? [];
}

    // public function update(Request $request, $teamUserId)
    // {
    //     $detail = TeamAdditionalData::where('team_user_id', $teamUserId)->firstOrFail();

    //     $validated = $request->validate([
    //         'bank_name' => 'nullable|string|max:255',
    //         'bank_account_number' => 'nullable|string|max:255',
    //         'iban' => 'nullable|string|max:255',

    //         'contract_type' => 'nullable|in:CDI,CDD,Freelance,Intern',
    //         'contract_start_date' => 'nullable|date',
    //         'contract_end_date' => 'nullable|date|after_or_equal:contract_start_date',
    //         'contract_file' => 'nullable|string',

    //         'emergency_contact_name' => 'nullable|string|max:255',
    //         'emergency_contact_phone' => 'nullable|string|max:50',

    //         'job_title' => 'nullable|string|max:255',
    //         'salary' => 'nullable|numeric|min:0',

    //         'certifications' => 'nullable|string',
    //         'notes' => 'nullable|string',
    //     ]);

    //     $detail->update($validated);

    //     return response()->json($detail);
    // }


     public function update(Request $request, $teamUserId)
    {
        $teamAdditionalData = TeamAdditionalData::where('team_user_id', $teamUserId)->firstOrFail();

        $validated = $request->validate([
            'bank_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:255',
            'iban' => 'nullable|string|max:255',
            'contract_type' => 'nullable|in:CDI,CDD,Freelance,Intern',
            'contract_start_date' => 'nullable|date',
            'contract_end_date' => 'nullable|date|after_or_equal:contract_start_date',
            'contract_file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:50',
            'job_title' => 'nullable|string|max:255',
            'salary' => 'nullable|numeric|min:0',
            'certifications' => 'nullable|string',
            'notes' => 'nullable|string',
            'portfolio' => 'nullable|string',
            'github' => 'nullable|string',
            'linkedin' => 'nullable|string',
            'cv' => 'nullable|file|mimes:pdf,doc,docx|max:5120',
        ]);

        // Ensure directories exist
        Storage::disk('public')->makeDirectory('team_additional_data/cv', 0755, true);
        Storage::disk('public')->makeDirectory('team_additional_data/contracts', 0755, true);

        if ($request->hasFile('cv')) {
            if ($teamAdditionalData->cv) {
                Storage::disk('public')->delete($teamAdditionalData->cv);
            }
            $validated['cv'] = $request->file('cv')->store('team_additional_data/cv', 'public');
        }

        if ($request->hasFile('contract_file')) {
            if ($teamAdditionalData->contract_file) {
                Storage::disk('public')->delete($teamAdditionalData->contract_file);
            }
            $validated['contract_file'] = $request->file('contract_file')->store('team_additional_data/contracts', 'public');
        }

        $teamAdditionalData->update($validated);

        return response()->json($teamAdditionalData);
    }

    public function destroy($teamUserId)
    {
        TeamAdditionalData::where('team_user_id', $teamUserId)->firstOrFail()->delete();

        return response()->json(['message' => 'Detail deleted']);
    }
}
