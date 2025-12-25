<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
class ExpenseController extends Controller
{
    public function index()
    {
        return Expense::with(['project', 'client', 'invoice', 'user'])->get();
    }

    public function show(Expense $expense)
    {
        return $expense->load(['project', 'client', 'invoice', 'user']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'required|numeric',
            'currency' => 'nullable|string|max:10',
            'date' => 'required|date',
            'project_id' => 'nullable|exists:projects,id',
            'client_id' => 'nullable|exists:clients,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'paid_by' => 'nullable|exists:users,id',
            'category' => 'nullable|string|max:255',
            'payment_method' => 'nullable|string|max:255',
            'status' => 'nullable|in:pending,approved,reimbursed,paid',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf',
            'repeatedly' => 'required|in:none,weekly,monthly,yearly',
        ]);

       if ($request->hasFile('attachment')) {
    $data['attachment'] = $request->file('attachment')
                               ->store('expenses/' . date('Y') . '/' . date('m'));
}


        $expense = Expense::create($data);

        return response()->json($expense, 201);
    }

    public function update(Request $request, Expense $expense)
    {
        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'sometimes|required|numeric',
            'currency' => 'nullable|string|max:10',
            'date' => 'sometimes|required|date',
            'project_id' => 'nullable|exists:projects,id',
            'client_id' => 'nullable|exists:clients,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'paid_by' => 'nullable|exists:users,id',
            'category' => 'nullable|string|max:255',
            'payment_method' => 'nullable|string|max:255',
            'status' => 'nullable|in:pending,approved,reimbursed,paid',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf',
            'repeatedly' => 'required|in:none,weekly,monthly,yearly',

        ]);

        if ($request->hasFile('attachment')) {
    // Delete old file if exists
    if ($expense->attachment && Storage::exists($expense->attachment)) {
        Storage::delete($expense->attachment);
    }

    $data['attachment'] = $request->file('attachment')->store('expenses/' . date('Y') . '/' . date('m'));
}


        $expense->update($data);

        return response()->json($expense);
    }

    public function destroy(Expense $expense)
    {
        // Delete attachment if exists
if ($expense->attachment && Storage::exists($expense->attachment)) {
    Storage::delete($expense->attachment);
}

$expense->delete();
return response()->noContent();

    }
}
