<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\File;
use App\Models\Quotes;
use Illuminate\Support\Facades\Auth;

class SignatureController extends Controller
{
    public function upload(Request $request, Quotes $quote)
    {
        $request->validate([
            'signature' => 'required|image|max:2048',
            'type' => 'required|in:admin_signature,client_signature',
        ]);

        $path = $request->file('signature')->store('signatures');

        $file = $quote->files()->updateOrCreate(
            ['type' => $request->type],
            [
                'path' => $path,
                'user_id' => Auth::id(),
            ]
        );

        return response()->json([
            'message' => 'Signature uploaded successfully',
            'url' => $file->url,
        ]);
    }
}
