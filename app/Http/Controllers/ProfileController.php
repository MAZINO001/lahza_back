<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $isClient = $user->user_type === 'client';

        // Base response for all users
        $profile = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'profile_image' => $user->profile_image,
            'profile_image_url' => $user->profile_image ? asset('storage/' . $user->profile_image) : null,
            'role' => $user->role,
            'user_type' => $user->user_type,
            'is_client' => $isClient,
        ];

        // If client, add client-specific data
        if ($isClient && $user->client) {
            $profile['phone'] = $user->client->phone;
            $profile['address'] = $user->client->address;
            $profile['country'] = $user->client->country;
        }

        return response()->json($profile);
    }

    public function uploadProfile(Request $request)
    {
        $user = $request->user();
        $isClient = $user->user_type === 'client';

        // Build validation rules
        $rules = [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            'profile_image' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];

        // Add client-specific validation only for clients
        if ($isClient) {
            $rules['phone'] = 'sometimes|string|max:20';
            $rules['address'] = 'sometimes|string|max:255';
            $rules['country'] = 'sometimes|string|max:100';
        }

        $request->validate($rules);

        // Update User record (name, email, profile_image)
        $userUpdateData = [];

        if ($request->has('name')) {
            $userUpdateData['name'] = $request->input('name');
        }
        if ($request->has('email')) {
            $userUpdateData['email'] = $request->input('email');
        }

        // Handle profile image
        if ($request->hasFile('profile_image')) {
            if ($user->profile_image && file_exists(storage_path('app/public/' . $user->profile_image))) {
                unlink(storage_path('app/public/' . $user->profile_image));
            }

            $path = $request->file('profile_image')->store('profile-images', 'public');
            $userUpdateData['profile_image'] = $path;
        }

        // Update User
        if (!empty($userUpdateData)) {
            $user->update($userUpdateData);
        }

        // Update Client record if user is a client
        if ($isClient && $user->client) {
            $clientUpdateData = [];

            if ($request->has('phone')) {
                $clientUpdateData['phone'] = $request->input('phone');
            }
            if ($request->has('address')) {
                $clientUpdateData['address'] = $request->input('address');
            }
            if ($request->has('country')) {
                $clientUpdateData['country'] = $request->input('country');
            }

            if (!empty($clientUpdateData)) {
                $user->client->update($clientUpdateData);
            }
        }

        // Refresh user to get updated data
        $user->refresh();

        // Build response
        $responseData = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'profile_image' => $user->profile_image,
            'profile_image_url' => $user->profile_image ? asset('storage/' . $user->profile_image) : null,
            'role' => $user->role,
            'user_type' => $user->user_type,
            'is_client' => $isClient,
        ];

        // Add client data if client
        if ($isClient && $user->client) {
            $user->client->refresh();
            $responseData['phone'] = $user->client->phone;
            $responseData['address'] = $user->client->address;
            $responseData['country'] = $user->client->country;
        }

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $responseData,
        ], 200);
    }
}
