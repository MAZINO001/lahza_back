<?php

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

        $instance = $this->getModelInstance($model, $id);

        if (!$instance) {
            return response()->json(['message' => 'Resource not found'], 404);
        }

        $user = $request->user();
        // $type = $user && $user->role === 'admin' ? 'admin_signature' : 'client_signature';
        $type = 'client_signature';

        $path = $request->file('signature')->store('signatures', 'public');

        $file = $instance->files()->updateOrCreate(
            ['type' => $type],
            [
                'path' => $path,
                'user_id' => Auth::id(),
            ]
        );

        // Update status to 'signed' if client signature is uploaded
        if ($type === 'client_signature') {
            if ($instance instanceof Quotes) {
                $instance->update(['status' => 'signed']);
            } elseif ($instance instanceof Invoice) {
                // Invoice status doesn't have 'signed', so we don't update it
                // But we can add a note or handle it differently if needed
            }
        }

        // Get the updated instance with relationships
        $instance->refresh();
        $instance->load('files');

        // Build response message based on signature status
        $message = 'Signature uploaded successfully';
        if ($instance instanceof Quotes) {
            $isFullySigned = $instance->is_fully_signed;
            if ($isFullySigned) {
                $message = 'Signature uploaded successfully. Document is now fully signed by both parties.';
            } else {
                $message = 'Signature uploaded successfully. ' . 
                    ($type === 'admin_signature' 
                        ? 'Waiting for client signature.' 
                        : 'Waiting for admin signature.');
            }
        }

        return response()->json([
            'message' => $message,
            'url' => $file->url,
            'type' => $type,
            'has_client_signature' => $instance->clientSignature() !== null,
            'has_admin_signature' => $instance->adminSignature() !== null,
            'status' => $instance instanceof Quotes ? $instance->status : null,
            'is_fully_signed' => $instance instanceof Quotes ? $instance->is_fully_signed : null,
        ]);
    }

    public function destroy(Request $request, $model, $id)
    {
        logger($model);
        $instance = $this->getModelInstance($model, $id);

        if (!$instance) {
            return response()->json(['message' => 'Resource not found'], 404);
        }

        $user = $request->user();
        if($instance instanceof Quotes){
         if($instance->invoice->status === 'paid'|| $instance->invoice->status === 'partially_paid'){
            return response()->json(['message' => 'Invoice is paid'], 400);
         }   
        }

        if ($user && $user->role === 'admin') {
            $type = 'client_signature';
        } else {
            $type = 'client_signature';
        }

        $file = $instance->files()->where('type', $type)->first();

        if (!$file) {
            return response()->json(['message' => 'No signature found for this type'], 404);
        }

        try {
            if (Storage::disk('public')->exists($file->path)) {
                Storage::disk('public')->delete($file->path);
            }
        } catch (\Exception $e) {
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
