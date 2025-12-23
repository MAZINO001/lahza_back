<?php

namespace App\Http\Controllers;

use App\Models\Certification;
use Illuminate\Http\Request;

class CertificationController extends Controller
{
    public function index(Request $request)
    {
        return Certification::all();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'owner_type'   => 'required|string',
            'owner_id'     => 'required|integer',
            'title'        => 'required|string',
            'description'  => 'nullable|string',
            'source_type'  => 'required|in:file,url',
            'file_path'    => 'nullable|string',
            'url'          => 'nullable|url',
            'preview_image'=> 'nullable|string',
            'issued_by'    => 'nullable|string',
            'issued_at'    => 'nullable|date',
            'expires_at'   => 'nullable|date',
            'status'       => 'nullable|in:active,expired,pending,revoked',
        ]);

        return Certification::create($data);
    }

    public function show(Certification $certification)
    {
        return $certification;
    }

    public function update(Request $request, Certification $certification)
    {
        $certification->update($request->all());
        return $certification;
    }

    public function destroy(Certification $certification)
    {
        $certification->delete();
        return response()->noContent();
    }
}
