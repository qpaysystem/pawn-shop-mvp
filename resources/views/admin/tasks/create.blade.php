@extends('layouts.admin')
@section('title', 'Новая задача')
@section('content')
<h1 class="h4 mb-4">Новая задача</h1>
<form method="post" action="{{ route('admin.tasks.store') }}">
    @csrf
    <div class="mb-3">
        <label class="form-label">Название</label>
        <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title') }}" required>
        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="mb-3">
        <label class="form-label">Описание</label>
        <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="3">{{ old('description') }}</textarea>
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="mb-3">
        <label class="form-label">Проект</label>
        <select name="project_id" class="form-select">
            <option value="">— не выбран</option>
            @foreach($projects as $p)
                <option value="{{ $p->id }}" @selected(old('project_id') == $p->id)>{{ $p->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="mb-3">
        <label class="form-label">Бюджет на исполнение, {{ \App\Models\Setting::get('currency', 'RUB') }}</label>
        <input type="number" name="budget" class="form-control @error('budget') is-invalid @enderror" value="{{ old('budget') }}" step="0.01" min="0" placeholder="Не указан">
        @error('budget')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="mb-3">
        <label class="form-label">Дата окончания задачи</label>
        <input type="date" name="due_date" class="form-control @error('due_date') is-invalid @enderror" value="{{ old('due_date') }}" placeholder="Не указана">
        @error('due_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <small class="text-muted">Отображается в календаре клиента на iPhone</small>
    </div>
    <div class="mb-3">
        <label class="form-label">Ответственный (клиент)</label>
        <select name="client_id" class="form-select">
            <option value="">— не назначен</option>
            @foreach($clients as $c)
                <option value="{{ $c->id }}" @selected(old('client_id') == $c->id)>{{ $c->full_name }}</option>
            @endforeach
        </select>
    </div>
    <div class="mb-3">
        <label class="form-label">Статус</label>
        <select name="status" class="form-select">
            @foreach(\App\Models\Task::statusLabels() as $value => $label)
                <option value="{{ $value }}" @selected(old('status', 'in_development') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="mb-3 form-check">
        <input type="checkbox" name="show_on_board" value="1" class="form-check-input" id="show_on_board" @checked(old('show_on_board', true))>
        <label class="form-check-label" for="show_on_board">Показывать на канбан-доске (фронтенд)</label>
    </div>
    <button type="submit" class="btn btn-primary">Создать</button>
    <a href="{{ route('admin.tasks.index') }}" class="btn btn-secondary">Отмена</a>
</form>
@endsection
