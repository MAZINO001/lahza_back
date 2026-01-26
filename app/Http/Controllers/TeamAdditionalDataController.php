<?php

namespace App\Http\Controllers;

use App\Models\TeamAdditionalData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Services\FileUploadService;

class TeamAdditionalDataController extends Controller
{
    protected $fileUploadService;
    public function __construct (FileUploadService $fileUploadService){
        $this->fileUploadService = $fileUploadService;
    }

public function store(Request $request, FileUploadService $fileUploadService)
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

    $detail = TeamAdditionalData::create(
        collect($validated)->except(['cv', 'contract_file'])->toArray()
    );

    if ($request->hasFile('cv')) {
        $fileUploadService->upload(
            $request->file('cv'),
            $detail,
            'cv',
            'team_additional_data/cv'
        );
    }

    if ($request->hasFile('contract_file')) {
        $fileUploadService->upload(
            $request->file('contract_file'),
            $detail,
            'contract',
            'team_additional_data/contracts'
        );
    }

    return response()->json($detail->load('files'), 201);
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

public function update(Request $request, $teamUserId, FileUploadService $fileUploadService)
{
    $teamAdditionalData = TeamAdditionalData::where('team_user_id', $teamUserId)
        ->firstOrFail();

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

    // 1️⃣ Update only non-file fields
    $teamAdditionalData->update(
        collect($validated)->except(['cv', 'contract_file'])->toArray()
    );

    // 2️⃣ Define file fields and storage paths
    $fileFields = [
        'cv' => 'team_additional_data/cv',
        'contract_file' => 'team_additional_data/contracts',
    ];

foreach ($fileFields as $field => $path) {

if (array_key_exists($field, $request->all()) && 
    ($request->input($field) === null || $request->input($field) === '')) {
    $teamAdditionalData->files()
        ->where('type', $field)
        ->get()
        ->each(fn ($file) => $fileUploadService->deleteFile($file));
}

    if ($request->hasFile($field)) {
        $teamAdditionalData->files()
            ->where('type', $field)
            ->get()
            ->each(fn ($file) => $fileUploadService->deleteFile($file));

        $fileUploadService->upload(
            $request->file($field),
            $teamAdditionalData,
            $field,
            $path
        );
    }
}


    // 4️⃣ Return fresh model with files
    return response()->json($teamAdditionalData->refresh()->load('files'));
}


  public function destroy($teamUserId, FileUploadService $fileUploadService)
{
    $teamAdditionalData = TeamAdditionalData::where('team_user_id', $teamUserId)
        ->firstOrFail();

    $teamAdditionalData->files
        ->each(fn ($file) => $fileUploadService->deleteFile($file));

    $teamAdditionalData->delete();

    return response()->json(['message' => 'Detail deleted']);
}

}
