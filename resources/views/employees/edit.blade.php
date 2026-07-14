@extends('layouts.app')

@section('title', 'Редактирование — ' . $employee->full_name)

@section('content')
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-2">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Дашборд</a></li>
            <li class="breadcrumb-item"><a href="{{ route('section.management') }}">Управление</a></li>
            <li class="breadcrumb-item"><a href="{{ route('management.personnel.index') }}">Персонал</a></li>
            <li class="breadcrumb-item"><a href="{{ route('management.personnel.show', $employee) }}">{{ $employee->full_name }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">Редактирование</li>
        </ol>
    </nav>
    <h1 class="h3 mb-0">Редактирование: {{ $employee->full_name }}</h1>
</div>

<form method="post" action="{{ route('management.personnel.update', $employee) }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    @include('employees._form')
    <button type="submit" class="btn btn-primary">Сохранить</button>
    <a href="{{ route('management.personnel.show', $employee) }}" class="btn btn-outline-secondary">Отмена</a>
</form>

<form method="post" action="{{ route('management.personnel.destroy', $employee) }}" class="mt-4" onsubmit="return confirm('Удалить карточку сотрудника?')">
    @csrf
    @method('DELETE')
    <button type="submit" class="btn btn-outline-danger btn-sm">Удалить карточку</button>
</form>
@endsection
