<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\DocumentLedgerTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Настройка шаблонов проводок по типам документов (отражение в ОСВ). */
class DocumentLedgerTemplateController extends Controller
{
    public static function documentTypeLabels(): array
    {
        return [
            'pawn_contract' => 'Договор залога',
            'commission_contract' => 'Договор комиссии',
            'purchase_contract' => 'Договор скупки',
            'cash_document' => 'Кассовый документ',
            'payroll_accrual' => 'Начисление ФОТ',
            'expense' => 'Расход',
        ];
    }

    public function index(Request $request): View
    {
        $typeFilter = $request->get('document_type');
        $query = DocumentLedgerTemplate::orderBy('document_type')->orderBy('sort_order')->orderBy('id');
        if ($typeFilter) {
            $query->where('document_type', $typeFilter);
        }
        $templates = $query->get();
        $grouped = $templates->groupBy('document_type');
        $typeLabels = self::documentTypeLabels();
        $accounts = Account::where('is_active', true)->orderBy('sort_order')->orderBy('code')->get();

        return view('document-ledger-templates.index', compact(
            'grouped', 'typeLabels', 'typeFilter', 'accounts'
        ));
    }

    public function create(Request $request): View
    {
        $documentType = $request->get('document_type', 'pawn_contract');
        $typeLabels = self::documentTypeLabels();
        $accounts = Account::where('is_active', true)->orderBy('sort_order')->orderBy('code')->get();
        return view('document-ledger-templates.create', compact('documentType', 'typeLabels', 'accounts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'document_type' => 'required|string|max:64',
            'name' => 'nullable|string|max:255',
            'debit_account_code' => 'required|string|max:20',
            'credit_account_code' => 'required|string|max:20',
            'amount_field' => 'nullable|string|max:64',
            'comment_template' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
        ]);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        DocumentLedgerTemplate::create($data);
        return redirect()->route('document-ledger-templates.index', ['document_type' => $data['document_type']])
            ->with('success', 'Шаблон проводки добавлен.');
    }

    public function destroy(DocumentLedgerTemplate $documentLedgerTemplate): RedirectResponse
    {
        $documentType = $documentLedgerTemplate->document_type;
        $documentLedgerTemplate->delete();
        return redirect()->route('document-ledger-templates.index', ['document_type' => $documentType])
            ->with('success', 'Шаблон удалён.');
    }
}
