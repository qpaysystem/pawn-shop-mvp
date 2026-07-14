<?php

/** @var \App\Models\ManagementTask $task */
/** @var \Illuminate\Support\Collection $employees */
/** @var array<string, string> $statuses */
?>
<div class="row g-3">
    <div class="col-12">
        <label class="form-label" for="title">Название задачи <span class="text-danger">*</span></label>
        <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror"
               value="{{ old('title', $task->title) }}" required maxlength="255">
        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label" for="employee_id">Сотрудник <span class="text-danger">*</span></label>
        <select name="employee_id" id="employee_id" class="form-select @error('employee_id') is-invalid @enderror" required>
            <option value="">— выберите —</option>
            @foreach($employees as $employee)
                <option value="{{ $employee->id }}" @selected((int) old('employee_id', $task->employee_id) === $employee->id)>
                    {{ $employee->full_name }}@if($employee->position) ({{ $employee->position }})@endif
                </option>
            @endforeach
        </select>
        @error('employee_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label" for="status">Статус</label>
        <select name="status" id="status" class="form-select @error('status') is-invalid @enderror">
            @foreach($statuses as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $task->status ?: 'new') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label class="form-label" for="description">Описание</label>
        <textarea name="description" id="description" rows="4"
                  class="form-control @error('description') is-invalid @enderror"
                  maxlength="5000">{{ old('description', $task->description) }}</textarea>
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label" for="starts_at">Дата начала <span class="text-danger">*</span></label>
        <input type="date" name="starts_at" id="starts_at" class="form-control @error('starts_at') is-invalid @enderror"
               value="{{ old('starts_at', optional($task->starts_at)->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required>
        @error('starts_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label" for="due_at">План окончания <span class="text-danger">*</span></label>
        <input type="date" name="due_at" id="due_at" class="form-control @error('due_at') is-invalid @enderror"
               value="{{ old('due_at', optional($task->due_at)->format('Y-m-d')) }}" required>
        @error('due_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
