<?php

// namespace App\Http\Controllers;

// use Illuminate\Http\Request;
// use App\Models\File;
// use App\Models\Invoice;
// use Illuminate\Support\Facades\Auth;
// use Illuminate\Support\Facades\Storage;

// class SignatureController extends Controller
// {
//     public function upload(Request $request, Invoice $invoice)
//     {
//         $request->validate([
//             'signature' => 'required|image|max:2048',
//         ]);

//         $user = $request->user();
//         $type = $user && $user->role === 'admin' ? 'admin_signature' : 'client_signature';

//         $path = $request->file('signature')->store('signatures', 'public');

//         $file = $invoice->files()->updateOrCreate(
//             ['type' => $type],
//             [
//                 'path' => $path,
//                 'user_id' => Auth::id(),
//             ]
//         );

//         return response()->json([
//             'message' => 'Signature uploaded successfully',
//             'url' => $file->url,
//         ]);
//     }

//     public function destroy(Request $request, Invoice $invoice)
//     {
//         $user = $request->user();
//         $type = $user && $user->role === 'admin' ? 'admin_signature' : 'client_signature';

//         $file = $invoice->files()->where('type', $type)->first();

//         if (!$file) {
//             return response()->json(['message' => 'No signature found for this type'], 404);
//         }

//         try {
//             if (Storage::disk('public')->exists($file->path)) {
//                 Storage::disk('public')->delete($file->path);
//             }
//         } catch (\Exception $e) {
//             // optional: log the error
//         }

//         $file->delete();

//         return response()->json(['message' => 'Signature removed successfully']);
//     }
// }



namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\File;
use App\Models\Invoice;
use App\Models\Quotes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class SignatureController extends Controller
{
    public function upload(Request $request, $model, $id)
    {
        $request->validate([
            'signature' => 'required|image|max:2048',
        ]);

        // Get the appropriate model instance
        $instance = $this->getModelInstance($model, $id);

        if (!$instance) {
            return response()->json(['message' => 'Resource not found'], 404);
        }

        $user = $request->user();
        $type = $user && $user->role === 'admin' ? 'admin_signature' : 'client_signature';

        $path = $request->file('signature')->store('signatures', 'public');

        $file = $instance->files()->updateOrCreate(
            ['type' => $type],
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

    public function destroy(Request $request, $model, $id)
    {
        // Get the appropriate model instance
        $instance = $this->getModelInstance($model, $id);

        if (!$instance) {
            return response()->json(['message' => 'Resource not found'], 404);
        }

        $user = $request->user();
        $type = $user && $user->role === 'admin' ? 'admin_signature' : 'client_signature';

        $file = $instance->files()->where('type', $type)->first();

        if (!$file) {
            return response()->json(['message' => 'No signature found for this type'], 404);
        }

        try {
            if (Storage::disk('public')->exists($file->path)) {
                Storage::disk('public')->delete($file->path);
            }
        } catch (\Exception $e) {
            // optional: log the error
        }

        $file->delete();

        return response()->json(['message' => 'Signature removed successfully']);
    }

    private function getModelInstance($model, $id)
    {
        switch ($model) {
            case 'invoices':
                return Invoice::find($id);
            case 'quotes':
                return Quotes::find($id);
            default:
                return null;
        }
    }
}
