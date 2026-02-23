@extends('layouts.app')

@section('title', $employee->full_name)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">{{ $employee->full_name }}</h1>
    <a href="{{ route('employees.edit', $employee) }}" class="btn btn-outline-primary">Изменить</a>
</div>
<div class="card">
    <div class="card-body">
        <p><strong>Должность:</strong> {{ $employee->position ?? '—' }}</p>
        <p><strong>Магазин:</strong> {{ $employee->store?->name ?? '—' }}</p>
        <p class="mb-0"><strong>Статус:</strong> {{ $employee->is_active ? 'Активен' : 'Неактивен' }}</p>
    </div>
</div>
<a href="{{ route('employees.index') }}" class="btn btn-secondary mt-3">К списку</a>
@endsection
