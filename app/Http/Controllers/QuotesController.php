<?php

namespace App\Http\Controllers;

use App\Models\Quotes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Quotes_service;
use App\Models\Service;

class QuotesController extends Controller
{
    // GET /api/Quotess
    public function index()
    {
        $quotes = Quotes::with(['quoteServices', 'client.user:id,name'])->get();
        $allServices = Service::all();

        return response()->json([
            'quotes' => $quotes,
            'services' => $allServices
        ]);
    }

    // POST /api/Quotess
    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'quotation_date' => 'required|date',
            'status' => 'required|in:draft,sent,confirmed,signed,rejected',
            'notes' => 'nullable|string',
            'total_amount' => 'required|numeric',
            'services' => 'array',
            'services.*.service_id' => 'required|exists:services,id',
            'services.*.quantity' => 'required|integer|min:1',
            'services.*.tax' => 'nullable|numeric',
            'services.*.individual_total' => 'nullable|numeric',
        ]);


        return DB::transaction(function () use ($validated) {



            // Generate the next quote number
            $latestQuote = Quotes::latest('id')->first();
            $nextNumber = $latestQuote ? $latestQuote->id + 1 : 1;
            $quoteNumber = 'Quote-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
            // Create the quote
            $quote = Quotes::create([
                'client_id' => $validated['client_id'],
                'quotation_date' => $validated['quotation_date'],
                'status' => $validated['status'],
                'notes' => $validated['notes'],
                'quote_number' => $quoteNumber,
                'total_amount' => $validated['total_amount'],
            ]);

            // Insert pivot records
            if (!empty($validated['services'])) {
                foreach ($validated['services'] as $service) {
                    Quotes_service::create([
                        'quote_id' => $quote->id,
                        'service_id' => $service['service_id'],
                        'quantity' => $service['quantity'],
                        'tax' => $service['tax'] ?? 0,
                        'individual_total' => $service['individual_total'] ?? 0,
                    ]);
                }
            }

            return response()->json($quote->load('quoteServices'), 201);
        });
    }

    // GET /api/quotes/{id}
    public function show($id)
    {
        $quote = Quotes::with('quoteServices')->findOrFail($id);
        return response()->json($quote);
    }

    // PUT /api/quotes/{id}
    public function update(Request $request, $id)
    {
        $quote = Quotes::findOrFail($id);

        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'quotation_date' => 'required|date',
            'status' => 'required|in:draft,sended,confirmed,signed,rejected',
            'notes' => 'nullable|string',
            'total_amount' => 'required|numeric',
            'services' => 'array',
            'services.*.service_id' => 'required|exists:services,id',
            'services.*.quantity' => 'required|integer|min:1',
            'services.*.tax' => 'nullable|numeric',
            'services.*.individual_total' => 'nullable|numeric',
        ]);


        $quote->update($validated);

        // Update pivot table if services provided
        if (!empty($validated['services'])) {
            // Delete old pivot entries
            $quote->quoteServices()->delete();

            // Insert new pivot entries
            foreach ($validated['services'] as $service) {
                Quotes_service::create([
                    'quote_id' => $quote->id,
                    'service_id' => $service['service_id'],
                    'quantity' => $service['quantity'],
                    'tax' => $service['tax'] ?? 0,
                    'individual_total' => $service['individual_total'] ?? 0,
                ]);
            }
        }

        return response()->json($quote->load('quoteServices'));
    }

    // DELETE /api/quotes/{id}
    public function destroy($id)
    {
        $quote = Quotes::findOrFail($id);
        $quote->delete(); // Pivot entries cascade if foreign key is set
        return response()->json(null, 204);
    }
}
