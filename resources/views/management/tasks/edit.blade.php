@extends('layouts.app')

@section('title', 'Изменить задачу')

@section('content')
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-2">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Дашборд</a></li>
            <li class="breadcrumb-item"><a href="{{ route('section.management') }}">Управление</a></li>
            <li class="breadcrumb-item"><a href="{{ route('management.tasks.index') }}">Задачи</a></li>
            <li class="breadcrumb-item"><a href="{{ route('management.tasks.show', $task) }}">{{ $task->title }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">Изменить</li>
        </ol>
    </nav>
    <h1 class="h3 mb-0">Изменить задачу</h1>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="post" action="{{ route('management.tasks.update', $task) }}">
            @csrf
            @method('PUT')
            @include('management.tasks._form')
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Сохранить</button>
                <a href="{{ route('management.tasks.show', $task) }}" class="btn btn-outline-secondary">Отмена</a>
            </div>
        </form>
    </div>
</div>
@endsection
