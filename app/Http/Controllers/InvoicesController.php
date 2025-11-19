<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceService;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InvoicesController extends Controller
{
    // public function index()
    // {
    //     $invoices = Invoice::with(['invoiceServices', 'client.user:id,name'])->get();
    //     $allServices = Service::all();

    //     return response()->json([
    //         "invoices"  => $invoices,
    //         "services"  => $allServices
    //     ]);
    // }
    
    public function index(Request $request)
    {
        $query = Invoice::with(['invoiceServices', 'client.user:id,name']);

        // filter by quote_id if it's in the query
        if ($request->has('quote_id')) {
            $query->where('quote_id', $request->quote_id);
        }

        $invoices = $query->get();
        $allServices = Service::all();

        return response()->json([
            "invoices" => $invoices,
            "services" => $allServices
        ]);
    }
    public function store(Request $request)
    {
        $validate = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'quote_id' => 'nullable|exists:quotes,id',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date',
            'status' => ['required', Rule::in(['draft', 'sent', 'unpaid', 'partially_paid', 'paid', 'overdue'])],
            'notes' => 'nullable|string',
            'total_amount' => 'required|numeric',
            'balance_due' => 'required|numeric',
            'services' => 'array',
            'services.*.service_id' => 'required|exists:services,id',
            'services.*.quantity' => 'required|integer|min:1',
            'services.*.tax' => 'nullable|numeric',
            'services.*.individual_total' => 'nullable|numeric',
        ]);

        return  DB::transaction(function () use ($validate) {
            // Generate the next quote number
            $latestInvoice = Invoice::latest('id')->first();
            $nextNumber = $latestInvoice ? $latestInvoice->id + 1 : 1;
            $invoiceNumber = 'INV-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

            $invoice = Invoice::create([
                'client_id' => $validate["client_id"],
                'quote_id' => $validate["quote_id"] ?? null,
                'invoice_number' => $invoiceNumber,
                'invoice_date' => $validate["invoice_date"],
                'due_date' => $validate["due_date"],
                'status' => $validate["status"],
                'notes' => $validate["notes"],
                'total_amount' => $validate["total_amount"],
                'balance_due' => $validate["balance_due"],
            ]);
            if (!empty($validate["services"])) {
                foreach ($validate["services"] as $service) {
                    InvoiceService::create([
                        'invoice_id' => $invoice->id,
                        'service_id' => $service['service_id'],
                        'quantity' => $service['quantity'],
                        'tax' => $service['tax'] ?? 0,
                        'individual_total' => $service['individual_total'] ?? 0,
                    ]);
                }
            }
            return response()->json($invoice->load("invoiceServices"), 201);
        });
    }

    public function show($id)
    {
        $invoice = Invoice::with(['invoiceServices', 'client.user:id,name'])->findOrFail($id);
        return response()->json($invoice);
    }

    public function update(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);
        $validate = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'quote_id' => 'nullable|exists:quotes,id',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date',
            'status' => ['required', Rule::in(['draft', 'sent', 'unpaid', 'partially_paid', 'paid', 'overdue'])],
            'notes' => 'nullable|string',
            'total_amount' => 'required|numeric',
            'balance_due' => 'required|numeric',
            'services' => 'array',
            'services.*.service_id' => 'required|exists:services,id',
            'services.*.quantity' => 'required|integer|min:1',
            'services.*.tax' => 'nullable|numeric',
            'services.*.individual_total' => 'nullable|numeric',
        ]);

        return DB::transaction(function () use ($invoice, $validate) {
            $invoice->update([
                'client_id' => $validate['client_id'],
                'quote_id' => $validate["quote_id"] ?? null,
                'invoice_date' => $validate['invoice_date'],
                'due_date' => $validate['due_date'],
                'status' => $validate['status'],
                'notes' => $validate['notes'] ?? null,
                'total_amount' => $validate['total_amount'],
                'balance_due' => $validate['balance_due'],
            ]);

            if (!empty($validate['services'])) {
                $invoice->invoiceServices()->delete();
                foreach ($validate['services'] as $service) {
                    InvoiceService::create([
                        'invoice_id' => $invoice->id,
                        'service_id' => $service['service_id'],
                        'quantity' => $service['quantity'],
                        'tax' => $service['tax'] ?? 0,
                        'individual_total' => $service['individual_total'] ?? 0,
                    ]);
                }
            }

            return response()->json($invoice->load('invoiceServices'));
        });
    }


    public function destroy($id)
    {
        $invoice = Invoice::findOrFail($id);
        $invoice->delete();
        return response()->json(null, 204);
    }
}
