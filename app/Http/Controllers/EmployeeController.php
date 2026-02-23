<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Store;
use Illuminate\Http\Request;

/** Сотрудники (для ФОТ). */
class EmployeeController extends Controller
{
    public function index()
    {
        $employees = Employee::with('store')->orderBy('last_name')->orderBy('first_name')->get();
        return view('employees.index', compact('employees'));
    }

    public function show(Employee $employee)
    {
        $employee->load('store');
        return view('employees.show', compact('employee'));
    }

    public function create()
    {
        $stores = Store::where('is_active', true)->orderBy('name')->get();
        return view('employees.create', compact('stores'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'last_name' => 'required|string|max:100',
            'first_name' => 'required|string|max:100',
            'patronymic' => 'nullable|string|max:100',
            'position' => 'nullable|string|max:255',
            'store_id' => 'nullable|exists:stores,id',
            'is_active' => 'boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active', true);
        Employee::create($data);
        return redirect()->route('employees.index')->with('success', 'Сотрудник добавлен.');
    }

    public function edit(Employee $employee)
    {
        $stores = Store::where('is_active', true)->orderBy('name')->get();
        return view('employees.edit', compact('employee', 'stores'));
    }

    public function update(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'last_name' => 'required|string|max:100',
            'first_name' => 'required|string|max:100',
            'patronymic' => 'nullable|string|max:100',
            'position' => 'nullable|string|max:255',
            'store_id' => 'nullable|exists:stores,id',
            'is_active' => 'boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $employee->update($data);
        return redirect()->route('employees.index')->with('success', 'Сотрудник обновлён.');
    }

    public function destroy(Employee $employee)
    {
        $employee->delete();
        return redirect()->route('employees.index')->with('success', 'Сотрудник удалён.');
    }
}
