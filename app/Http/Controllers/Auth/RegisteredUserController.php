<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Intern;
use App\Models\Other;
use App\Models\TeamUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class RegisteredUserController extends Controller
{
    public function store(Request $request): Response
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'user_type' => ['required', Rule::in(['client', 'team', 'intern', 'other'])],
        ];

        // Role-specific validations
        switch ($request->user_type) {
            case 'client':
                $rules = array_merge($rules, [
                    'company' => 'nullable|string|max:255',
                    'address' => 'nullable|string|max:255',
                    'phone' => 'nullable|string|max:20',
                    'city' => 'nullable|string|max:255',
                    'country' => 'nullable|string|max:255',
                    'client_type' => ['required', Rule::in(['individual', 'company'])],
                    'siren' => 'nullable|string|max:255',
                    'vat' => 'nullable|string|max:255',
                    'ice' => 'nullable|string|max:255',
                ]);
                break;

            case 'team':
                $rules = array_merge($rules, [
                    'poste' => 'required|string|max:255',
                    'department' => 'required|string|max:255',
                ]);
                break;

            case 'intern':
                $rules = array_merge($rules, [
                    'department' => 'required|string|max:255',
                    'linkedin' => 'nullable|string|max:255',
                    'github' => 'nullable|string|max:255',
                    // 'cv' => 'nullable|file|mimes:pdf,doc,docx|max:2048',
                    'portfolio' => 'nullable|string|max:255',
                    'start_date' => 'required|date',
                    'end_date' => 'required|date|after_or_equal:start_date',
                ]);
                break;

            case 'other':
                $rules = array_merge($rules, [
                    'description' => 'required|string|max:1000',
                    'tags' => 'nullable|array',
                ]);
                break;
        }

        $request->validate($rules);

        $role = match ($request->user_type) {
            'client' => 'client',
            // 'team', 'intern', 'other' => 'member',
            'team', 'intern', 'other' => 'admin',
        };

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $role,
            'user_type' => $request->user_type,
            'status' => 'active',
        ]);

        switch ($request->user_type) {
            case 'client':
                $latestClient = Client::latest('id')->first();
                $nextNumber = $latestClient ? $latestClient->id + 1 : 1;
                $clientNumber = 'Client-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

                Client::create([
                    'user_id' => $user->id,
                    'company' => $request->company,
                    'address' => $request->address,
                    'phone' => $request->phone,
                    'city' => $request->city,
                    'country' => $request->country,
                    'currency' => $request->currency,
                    'client_type' => $request->client_type,
                    'siren' => $request->siren,
                    'vat' => $request->vat,
                    'ice' => $request->ice,
                    'client_number' => $clientNumber,
                ]);

                DB::table('user_permissions')->insert([
                    ['user_id' => $user->id, 'permission_id' => 2],
                ]);
                break;

            case 'team':
                TeamUser::create([
                    'user_id' => $user->id,
                    'department' => $request->department,
                    'poste' => $request->poste,
                ]);
                DB::table('user_permissions')->insert([
                    ['user_id' => $user->id, 'permission_id' => 5],
                    ['user_id' => $user->id, 'permission_id' => 2],
                ]);
                break;

            case 'intern':
                $data = is_string($request->data) ? json_decode($request->data, true) : $request->all();

                // Create intern first
                $intern = Intern::create([
                    'user_id' => $user->id,
                    'department' => $data['department'] ?? $request->department,
                    'linkedin' => $data['linkedin'] ?? $request->linkedin,
                    'github' => $data['github'] ?? $request->github,
                    'portfolio' => $data['portfolio'] ?? $request->portfolio,
                    'start_date' => $data['start_date'] ?? $request->start_date,
                    'end_date' => $data['end_date'] ?? $request->end_date,
                ]);

                // Handle CV upload and attach to intern
                if ($request->hasFile('cv')) {
                    $file = $request->file('cv');
                    $path = $file->store('cvs', 'public');

                    $intern->files()->create([
                        'user_id' => $user->id,
                        'path' => $path,
                        'type' => 'cv',
                    ]);
                }

                DB::table('user_permissions')->insert([
                    ['user_id' => $user->id, 'permission_id' => 2],
                    ['user_id' => $user->id, 'permission_id' => 5],
                ]);
                break;


            // case 'intern':
            //     $cvPath = null;

            //     if ($request->hasFile('cv')) {
            //         $cvPath = $request->file('cv')->store('cvs', 'public'); // saves to storage/app/public/cvs
            //     }
            //     Intern::create([
            //         'user_id' => $user->id,
            //         'department' => $request->department,
            //         'linkedin' => $request->linkedin,
            //         'github' => $request->github,
            //         // 'cv' => $request->cv,
            //         // 'cv' => $cvPath,
            //         'portfolio' => $request->portfolio,
            //         'start_date' => $request->start_date,
            //         'end_date' => $request->end_date,
            //     ]);
            //     DB::table('user_permissions')->insert([
            //         ['user_id' => $user->id, 'permission_id' => 2],
            //         ['user_id' => $user->id, 'permission_id' => 5],
            //     ]);
            //     break;

            case 'other':
                Other::create([
                    'user_id' => $user->id,
                    'description' => $request->description,
                    'tags' => $request->tags ?? [],
                ]);
                DB::table('user_permissions')->insert([
                    ['user_id' => $user->id, 'permission_id' => 2],
                    ['user_id' => $user->id, 'permission_id' => 5],
                ]);
                break;
        }

        // Auth::login($user);

        return response()->noContent();
    }
}
