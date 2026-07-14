<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Журнал сотрудников (раздел «Управление → Персонал»). */
class EmployeeController extends Controller
{
    public function index()
    {
        $this->ensurePersonnelAccess();

        $employees = Employee::with(['store', 'user'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('employees.index', compact('employees'));
    }

    public function show(Employee $employee)
    {
        $this->ensurePersonnelAccess();
        $employee->load(['store', 'user']);

        return view('employees.show', compact('employee'));
    }

    public function create()
    {
        $this->ensurePersonnelAccess();

        return view('employees.create', [
            'stores' => $this->storesList(),
            'portalUsers' => $this->availablePortalUsers(),
            'employee' => new Employee(['is_active' => true]),
        ]);
    }

    public function store(Request $request)
    {
        $this->ensurePersonnelAccess();

        $data = $this->validatedEmployee($request);
        $portalTelegram = $request->input('portal_telegram');
        $data = $this->employeePayload($data);
        $data['is_active'] = $request->boolean('is_active', true);
        $data = $this->applyPhotoUpload($request, $data);

        $employee = Employee::create($data);
        $this->syncPortalUserTelegram($employee, $portalTelegram);

        return redirect()
            ->route('management.personnel.show', $employee)
            ->with('success', 'Карточка сотрудника создана.');
    }

    public function edit(Employee $employee)
    {
        $this->ensurePersonnelAccess();
        $employee->load('user');

        return view('employees.edit', [
            'employee' => $employee,
            'stores' => $this->storesList(),
            'portalUsers' => $this->availablePortalUsers($employee->id),
        ]);
    }

    public function update(Request $request, Employee $employee)
    {
        $this->ensurePersonnelAccess();

        $data = $this->validatedEmployee($request, $employee);
        $portalTelegram = $request->input('portal_telegram');
        $data = $this->employeePayload($data);
        $data['is_active'] = $request->boolean('is_active');
        $data = $this->applyPhotoUpload($request, $data, $employee);

        if ($request->boolean('remove_photo') && $employee->photo_path) {
            Storage::disk('public')->delete($employee->photo_path);
            $data['photo_path'] = null;
        }

        $employee->update($data);
        $this->syncPortalUserTelegram($employee, $portalTelegram);

        return redirect()
            ->route('management.personnel.show', $employee)
            ->with('success', 'Карточка сотрудника сохранена.');
    }

    public function destroy(Employee $employee)
    {
        $this->ensurePersonnelAccess();

        if ($employee->photo_path) {
            Storage::disk('public')->delete($employee->photo_path);
        }
        $employee->delete();

        return redirect()
            ->route('management.personnel.index')
            ->with('success', 'Сотрудник удалён.');
    }

    private function ensurePersonnelAccess(): void
    {
        if (! auth()->user()->hasFullStoreAccess()) {
            abort(403);
        }
    }

    /** @return \Illuminate\Support\Collection<int, Store> */
    private function storesList()
    {
        return Store::where('is_active', true)->orderBy('name')->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function availablePortalUsers(?int $exceptEmployeeId = null)
    {
        $linkedIds = Employee::query()
            ->when($exceptEmployeeId, fn ($q) => $q->where('id', '!=', $exceptEmployeeId))
            ->whereNotNull('user_id')
            ->pluck('user_id');

        return User::query()
            ->whereNotIn('id', $linkedIds)
            ->orderBy('name')
            ->get();
    }

    /** @return array<string, mixed> */
    private function validatedEmployee(Request $request, ?Employee $employee = null): array
    {
        $employeeId = $employee?->id;

        return $request->validate([
            'last_name' => 'required|string|max:100',
            'first_name' => 'required|string|max:100',
            'patronymic' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'passport_data' => 'nullable|string|max:2000',
            'registration_address' => 'nullable|string|max:1000',
            'position' => 'nullable|string|max:255',
            'store_id' => 'nullable|exists:stores,id',
            'user_id' => [
                'nullable',
                'exists:users,id',
                Rule::unique('employees', 'user_id')->ignore($employeeId),
            ],
            'telegram' => 'nullable|string|max:100',
            'character_description' => 'nullable|string|max:5000',
            'professional_data' => 'nullable|string|max:5000',
            'photo' => 'nullable|image|max:5120',
            'remove_photo' => 'nullable|boolean',
            'portal_telegram' => 'nullable|string|max:100',
        ]);
    }

    /** @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function employeePayload(array $data): array
    {
        unset($data['photo'], $data['remove_photo'], $data['portal_telegram']);

        return $data;
    }

    /** @param  array<string, mixed>  $data */
    private function applyPhotoUpload(Request $request, array $data, ?Employee $employee = null): array
    {
        if (! $request->hasFile('photo')) {
            return $data;
        }

        if ($employee?->photo_path) {
            Storage::disk('public')->delete($employee->photo_path);
        }

        $data['photo_path'] = $request->file('photo')->store('employees', 'public');

        return $data;
    }

    private function syncPortalUserTelegram(Employee $employee, ?string $telegram): void
    {
        if (! $employee->user_id) {
            return;
        }

        $user = User::find($employee->user_id);
        if ($user) {
            $user->update(['telegram' => $telegram]);
        }
    }
}
