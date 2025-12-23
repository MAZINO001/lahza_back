<?php

namespace App\Http\Controllers;

use App\Models\CompanyInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
class CompanyInfoController extends Controller
{
    public function index()
    {
        $companyinfo = CompanyInfo::all();
        return response()->json($companyinfo);
    }
     public function store(Request $request)
    {
        $data = $request->validate([
            'company_name' => 'required|string|max:255',
            'tagline' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            
            'logo_path' => 'nullable|image|mimes:jpg,png,jpeg,svg,webp|max:2048',
            'logo_dark_path' => 'nullable|image|mimes:jpg,png,jpeg,svg,webp|max:2048',
            'signature_path' => 'nullable|image|mimes:jpg,png,jpeg,svg,webp|max:2048',
            'stamp_path' => 'nullable|image|mimes:jpg,png,jpeg,svg,webp|max:2048',

            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'phone2' => 'nullable|string|max:50',
            'website' => 'nullable|string|max:255',

            'address_line1' => 'nullable|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',

            'ma_ice' => 'nullable|string|max:255',
            'ma_if' => 'nullable|string|max:255',
            'ma_cnss' => 'nullable|string|max:255',
            'ma_rc' => 'nullable|string|max:255',
            'ma_vat' => 'nullable|string|max:255',
            
            'fr_siret' => 'nullable|string|max:255',
            'fr_vat' => 'nullable|string|max:255',

            'bank_name' => 'required|string|max:255',
            'rib' => 'required|string|max:24',
            'account_name' => 'nullable|string|max:255',
        ]);

        // Define fixed file paths
        $paths = [
            'logo_path' => public_path('logo.png'),
            'logo_dark_path' => public_path('logo-dark.png'),
            'signature_path' => public_path('images/admin_signature.png'),
            'stamp_path' => public_path('images/stamp.png'),
        ];

        // Handle uploads and overwrite
        foreach ($paths as $field => $path) {
            if ($request->hasFile($field)) {
                $request->file($field)->move(dirname($path), basename($path));
            }
        }

        // Remove these fields from $data, since your DB column doesn't need them
        unset($data['logo_path'], $data['logo_dark_path'], $data['signature_path'], $data['stamp_path']);


        $companyinfo = CompanyInfo::create($data);

        return response()->json(['message' => 'Company detail created successfully', 'data' => $companyinfo]);
    }

    // Update an existing company detail
    public function update(Request $request, CompanyInfo $companyinfo)
    {
        $data = $request->validate([
            'company_name' => 'sometimes|required|string|max:255',
            'tagline' => 'nullable|string|max:255',
            'description' => 'nullable|string',

            'logo_path' => 'nullable|image|mimes:jpg,png,jpeg,svg,webp|max:2048',
            'logo_dark_path' => 'nullable|image|mimes:jpg,png,jpeg,svg,webp|max:2048',
            'signature_path' => 'nullable|image|mimes:jpg,png,jpeg,svg,webp|max:2048',
            'stamp_path' => 'nullable|image|mimes:jpg,png,jpeg,svg,webp|max:2048',

            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'phone2' => 'nullable|string|max:50',
            'website' => 'nullable|string|max:255',

            'address_line1' => 'nullable|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',

            'ma_ice' => 'nullable|string|max:255',
            'ma_if' => 'nullable|string|max:255',
            'ma_cnss' => 'nullable|string|max:255',
            'ma_rc' => 'nullable|string|max:255',
            'ma_vat' => 'nullable|string|max:255',

            'fr_siret' => 'nullable|string|max:255',
            'fr_vat' => 'nullable|string|max:255',

            'bank_name' => 'sometimes|required|string|max:255',
            'rib' => 'nullable|string|max:24',
            'account_name' => 'nullable|string|max:255',
        ]);

    
        $paths = [
        'logo_path' => public_path('logo.png'),
        'logo_dark_path' => public_path('logo-dark.png'),
        'signature_path' => public_path('images/admin_signature.png'),
        'stamp_path' => public_path('images/stamp.png'),
        ];

        foreach ($paths as $field => $path) {
            if ($request->hasFile($field)) {
                $request->file($field)->move(dirname($path), basename($path));
            }
        }

        // Remove fields from $data
        unset($data['logo_path'], $data['logo_dark_path'], $data['signature_path'], $data['stamp_path']);

        $companyinfo->update($data);


        return response()->json(['message' => 'Company detail updated successfully', 'data' => $companyinfo]);
        }
}
