<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Employee;
use App\Models\PayrollAccrual;
use App\Models\PayrollAccrualItem;
use App\Services\LedgerService;
use Illuminate\Http\Request;

/** Документы начисления ФОТ. */
class PayrollAccrualController extends Controller
{
    public function index(Request $request)
    {
        $query = PayrollAccrual::with('createdByUser')->orderBy('period_year', 'desc')->orderBy('period_month', 'desc')->orderBy('id', 'desc');
        if ($request->filled('year')) {
            $query->where('period_year', $request->year);
        }
        $accruals = $query->paginate(20)->withQueryString();
        return view('payroll-accruals.index', compact('accruals'));
    }

    public function create()
    {
        $employees = Employee::where('is_active', true)->orderBy('last_name')->orderBy('first_name')->get();
        $periodMonth = (int) request('month', now()->month);
        $periodYear = (int) request('year', now()->year);
        return view('payroll-accruals.create', compact('employees', 'periodMonth', 'periodYear'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'period_month' => 'required|integer|between:1,12',
            'period_year' => 'required|integer|min:2020|max:2100',
            'accrual_date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.employee_id' => 'required|exists:employees,id',
            'items.*.amount' => 'required|numeric|min:0',
        ]);
        $total = 0;
        foreach ($data['items'] as $item) {
            $amt = (float) ($item['amount'] ?? 0);
            if ($amt > 0) {
                $total += $amt;
            }
        }
        $last = PayrollAccrual::orderBy('id', 'desc')->first();
        $accrual = PayrollAccrual::create([
            'number' => 'ФОТ-' . (($last ? $last->id + 1 : 1)),
            'period_month' => $data['period_month'],
            'period_year' => $data['period_year'],
            'accrual_date' => $data['accrual_date'],
            'total_amount' => $total,
            'notes' => $data['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);
        foreach ($data['items'] as $item) {
            $amount = (float) ($item['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }
            PayrollAccrualItem::create([
                'payroll_accrual_id' => $accrual->id,
                'employee_id' => $item['employee_id'],
                'amount' => $amount,
            ]);
        }
        // Проводка: Дт 44 (расходы на продажу) Кт 70 (начисленная ЗП) — попадает в ОСВ по счёту 70
        if ($total > 0) {
            $ledger = app(LedgerService::class);
            $ledger->post(
                '44',
                Account::CODE_PAYROLL,
                $total,
                $accrual->accrual_date,
                null,
                'payroll_accrual',
                $accrual->id,
                'Начисление ФОТ ' . $accrual->number
            );
        }
        return redirect()->route('payroll-accruals.show', $accrual)->with('success', 'Начисление ФОТ создано.');
    }

    public function show(PayrollAccrual $payrollAccrual)
    {
        $payrollAccrual->load(['items.employee', 'createdByUser']);
        return view('payroll-accruals.show', compact('payrollAccrual'));
    }
}
