<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Выписки по расчётным счетам. */
class BankStatementController extends Controller
{
    public function index(BankAccount $bankAccount)
    {
        $statements = $bankAccount->bankStatements()->with('createdByUser')->orderBy('date_from', 'desc')->paginate(20);
        return view('bank-statements.index', compact('bankAccount', 'statements'));
    }

    public function create(BankAccount $bankAccount)
    {
        return view('bank-statements.create', compact('bankAccount'));
    }

    public function store(Request $request, BankAccount $bankAccount)
    {
        $data = $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'opening_balance' => 'nullable|numeric',
            'closing_balance' => 'nullable|numeric',
            'notes' => 'nullable|string|max:1000',
            'file' => 'nullable|file|mimes:pdf,csv,txt,xlsx,xls|max:10240',
        ]);
        $filePath = null;
        $fileName = null;
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = $file->getClientOriginalName();
            $filePath = $file->store('bank-statements', 'public');
        }
        $statement = BankStatement::create([
            'bank_account_id' => $bankAccount->id,
            'date_from' => $data['date_from'],
            'date_to' => $data['date_to'],
            'opening_balance' => $data['opening_balance'] ?? null,
            'closing_balance' => $data['closing_balance'] ?? null,
            'notes' => $data['notes'] ?? null,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'created_by' => auth()->id(),
        ]);
        return redirect()->route('bank-accounts.statements.show', [$bankAccount, $statement])->with('success', 'Выписка создана.');
    }

    public function show(BankAccount $bankAccount, BankStatement $statement)
    {
        if ($statement->bank_account_id !== $bankAccount->id) {
            abort(404);
        }
        $statement->load(['lines', 'createdByUser']);
        return view('bank-statements.show', compact('bankAccount', 'statement'));
    }

    public function addLine(Request $request, BankAccount $bankAccount, BankStatement $statement)
    {
        if ($statement->bank_account_id !== $bankAccount->id) {
            abort(404);
        }
        $data = $request->validate([
            'line_date' => 'required|date',
            'amount' => 'required|numeric',
            'description' => 'nullable|string|max:500',
            'counterparty' => 'nullable|string|max:255',
            'document_number' => 'nullable|string|max:100',
        ]);
        BankStatementLine::create([
            'bank_statement_id' => $statement->id,
            'line_date' => $data['line_date'],
            'amount' => $data['amount'],
            'description' => $data['description'] ?? null,
            'counterparty' => $data['counterparty'] ?? null,
            'document_number' => $data['document_number'] ?? null,
        ]);
        return redirect()->route('bank-accounts.statements.show', [$bankAccount, $statement])->with('success', 'Строка добавлена.');
    }

    public function downloadFile(BankAccount $bankAccount, BankStatement $statement)
    {
        if ($statement->bank_account_id !== $bankAccount->id || ! $statement->file_path) {
            abort(404);
        }
        $path = Storage::disk('public')->path($statement->file_path);
        if (! is_file($path)) {
            abort(404);
        }
        return response()->download($path, $statement->file_name ?? 'statement.pdf');
    }
}
