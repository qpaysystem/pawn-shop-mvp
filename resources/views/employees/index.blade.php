@extends('layouts.app')

@section('title', 'Журнал сотрудников')

@section('content')
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-2">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Дашборд</a></li>
            <li class="breadcrumb-item"><a href="{{ route('section.management') }}">Управление</a></li>
            <li class="breadcrumb-item active" aria-current="page">Персонал</li>
        </ol>
    </nav>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h1 class="h3 mb-0"><i class="bi bi-people me-2"></i>Журнал сотрудников</h1>
        <div>
            <a href="{{ route('payroll-accruals.index') }}" class="btn btn-outline-primary me-2">Начисления ФОТ</a>
            <a href="{{ route('management.personnel.create') }}" class="btn btn-primary"><i class="bi bi-plus"></i> Новая карточка</a>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width:56px"></th>
                        <th>ФИО</th>
                        <th>Телефон</th>
                        <th>Должность</th>
                        <th>Магазин</th>
                        <th>Портал</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($employees as $emp)
                    <tr>
                        <td>
                            @if($emp->photo_url)
                                <img src="{{ $emp->photo_url }}" alt="" class="rounded" style="width:40px;height:40px;object-fit:cover;">
                            @else
                                <span class="d-inline-flex align-items-center justify-content-center rounded bg-light text-muted" style="width:40px;height:40px;">
                                    <i class="bi bi-person"></i>
                                </span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('management.personnel.show', $emp) }}" class="fw-semibold text-decoration-none">{{ $emp->full_name }}</a>
                            @if(!$emp->is_active)
                                <span class="badge text-bg-secondary ms-1">неактивен</span>
                            @endif
                        </td>
                        <td>{{ $emp->phone ?: '—' }}</td>
                        <td>{{ $emp->position ?: '—' }}</td>
                        <td>{{ $emp->store?->name ?: '—' }}</td>
                        <td>
                            @if($emp->user)
                                <span class="badge text-bg-success">есть</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('management.personnel.show', $emp) }}" class="btn btn-sm btn-outline-primary">Карточка</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-muted p-4 text-center">
                            Сотрудников пока нет. <a href="{{ route('management.personnel.create') }}">Создайте первую карточку</a>.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
