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
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use App\Mail\ClientRegisteredMail;

class RegisteredUserController extends Controller
{
    public function store(Request $request): \Illuminate\Http\JsonResponse
{
    $rules = [
        'name' => 'required|string|max:255',
        'email' => 'required|string|email|max:255|unique:users',
        'password' => ['required', 'confirmed', Rules\Password::defaults()],
        'user_type' => ['required', Rule::in(['client', 'team', 'intern', 'other'])],
    ];

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
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'cv' => 'required|file|mimes:pdf,doc,docx,txt|max:2048',

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

    try {
        $result = DB::transaction(function () use ($request) {

            $role = match ($request->user_type) {
                'client' => 'client',
                'team', 'intern', 'other' => 'admin',
            };

            // Create user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $role,
                'user_type' => $request->user_type,
                'status' => 'active',
            ]);

            $clientId = null;

            switch ($request->user_type) {

                case 'client':
                    $latestClient = Client::latest('id')->first();
                    $nextNumber = $latestClient ? $latestClient->id + 1 : 1;

                    $client = Client::create([
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
                        'client_number' => 'Client-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT),
                    ]);

                    DB::table('user_permissions')->insert([
                        ['user_id' => $user->id, 'permission_id' => 2],
                    ]);

                    $this->sendClientRegistrationEmail($user, $client);
                    $clientId = $client;
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

    if ($request->hasFile('cv')) {
        $file = $request->file('cv');
        $cvName = $file->getClientOriginalName();
    }
                    $intern = Intern::create([
                        'user_id' => $user->id,
                        'department' => $request->department,
                        'linkedin' => $request->linkedin,
                        'github' => $request->github,
                        'portfolio' => $request->portfolio,
                        'start_date' => $request->start_date,
                        'end_date' => $request->end_date,
                        'cv' => $cvName,
                    ]);

                    if ($request->hasFile('cv')) {
                        $path = $request->file('cv')->store('cvs', 'public');

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

            return [
                'user' => $user,
                'client_id' => $clientId,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => $request->user_type . ' registered successfully',
            'client_id' => $result['client_id'],
        ]);

    } catch (\Throwable $e) {
        Log::error('Registration failed', [
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Registration failed. Please try again.',
        ], 500);
    }
}


    /**
     * Send client registration email
     */
    private function sendClientRegistrationEmail($user, $client)
    {
        try {
            Log::info('Sending client registration email...', [
                'user_id' => $user->id,
                'client_id' => $client->id,
                'email' => $user->email
            ]);

            Mail::to($user->email)->send(new ClientRegisteredMail($user, $client));

            Log::info('Client registration email sent successfully.', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send client registration email', [
                'user_id' => $user->id,
                'client_id' => $client->id,
                'error' => $e->getMessage()
            ]);
            // Don't throw exception - registration should succeed even if email fails
        }
    }
}
