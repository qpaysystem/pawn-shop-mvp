<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\ManagementTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Журнал и канбан задач управления. */
class TaskController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensureAccess();

        $query = ManagementTask::query()
            ->with(['employee', 'creator'])
            ->orderByDesc('created_at');

        if ($request->filled('search')) {
            $q = trim((string) $request->string('search'));
            $query->where(function ($w) use ($q) {
                $w->where('title', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }
        if ($request->filled('status') && array_key_exists($request->string('status')->toString(), ManagementTask::STATUSES)) {
            $query->where('status', $request->string('status')->toString());
        }
        if ($request->filled('starts_from')) {
            $query->whereDate('starts_at', '>=', $request->date('starts_from'));
        }
        if ($request->filled('starts_to')) {
            $query->whereDate('starts_at', '<=', $request->date('starts_to'));
        }
        if ($request->filled('due_from')) {
            $query->whereDate('due_at', '>=', $request->date('due_from'));
        }
        if ($request->filled('due_to')) {
            $query->whereDate('due_at', '<=', $request->date('due_to'));
        }
        if ($request->boolean('overdue')) {
            $query->whereNotNull('due_at')
                ->whereDate('due_at', '<', now()->toDateString())
                ->whereNotIn('status', [ManagementTask::STATUS_DONE, ManagementTask::STATUS_CANCELLED]);
        }

        $tasks = $query->paginate(25)->withQueryString();

        return view('management.tasks.index', [
            'tasks' => $tasks,
            'employees' => $this->employeesForSelect(),
            'statuses' => ManagementTask::STATUSES,
            'filters' => $request->only([
                'search', 'employee_id', 'status',
                'starts_from', 'starts_to', 'due_from', 'due_to', 'overdue',
            ]),
        ]);
    }

    public function board(Request $request): View
    {
        $this->ensureAccess();

        $query = ManagementTask::query()->with(['employee']);

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        $all = $query
            ->orderBy('sort_order')
            ->orderByDesc('due_at')
            ->orderByDesc('id')
            ->get();

        $columns = [];
        foreach (ManagementTask::KANBAN_STATUSES as $status) {
            $columns[$status] = $all->where('status', $status)->values();
        }

        return view('management.tasks.board', [
            'columns' => $columns,
            'statuses' => ManagementTask::STATUSES,
            'employees' => $this->employeesForSelect(),
            'filters' => $request->only(['employee_id']),
        ]);
    }

    public function create(): View
    {
        $this->ensureAccess();

        return view('management.tasks.create', [
            'employees' => $this->employeesForSelect(),
            'statuses' => ManagementTask::STATUSES,
            'task' => new ManagementTask([
                'status' => ManagementTask::STATUS_NEW,
                'starts_at' => now()->toDateString(),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAccess();
        $data = $this->validated($request);
        $data['created_by'] = Auth::id();
        $data['status'] = $data['status'] ?? ManagementTask::STATUS_NEW;

        $task = ManagementTask::create($data);

        return redirect()
            ->route('management.tasks.show', $task)
            ->with('success', 'Задача создана.');
    }

    public function show(ManagementTask $task): View
    {
        $this->ensureAccess();
        $task->load(['employee.store', 'creator']);

        return view('management.tasks.show', [
            'task' => $task,
            'statuses' => ManagementTask::STATUSES,
        ]);
    }

    public function edit(ManagementTask $task): View
    {
        $this->ensureAccess();

        return view('management.tasks.edit', [
            'task' => $task,
            'employees' => $this->employeesForSelect(),
            'statuses' => ManagementTask::STATUSES,
        ]);
    }

    public function update(Request $request, ManagementTask $task): RedirectResponse
    {
        $this->ensureAccess();
        $task->update($this->validated($request));

        return redirect()
            ->route('management.tasks.show', $task)
            ->with('success', 'Задача обновлена.');
    }

    public function destroy(ManagementTask $task): RedirectResponse
    {
        $this->ensureAccess();
        $task->delete();

        return redirect()
            ->route('management.tasks.index')
            ->with('success', 'Задача удалена.');
    }

    public function updateStatus(Request $request, ManagementTask $task): RedirectResponse
    {
        $this->ensureAccess();
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(ManagementTask::STATUSES))],
        ]);
        $task->update(['status' => $data['status']]);

        $redirect = $request->input('redirect', 'board') === 'show'
            ? route('management.tasks.show', $task)
            : route('management.tasks.board', $request->only(['employee_id']));

        return redirect($redirect)->with('success', 'Статус обновлён.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'employee_id' => ['required', 'exists:employees,id'],
            'status' => ['nullable', Rule::in(array_keys(ManagementTask::STATUSES))],
            'starts_at' => ['required', 'date'],
            'due_at' => ['required', 'date', 'after_or_equal:starts_at'],
        ]);
    }

    /** @return \Illuminate\Support\Collection<int, Employee> */
    private function employeesForSelect()
    {
        return Employee::query()
            ->where('is_active', true)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    private function ensureAccess(): void
    {
        if (! Auth::user()?->hasFullStoreAccess()) {
            abort(403);
        }
    }
}
