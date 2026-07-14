@extends('layouts.app')

@section('title', 'Новая задача')

@section('content')
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-2">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Дашборд</a></li>
            <li class="breadcrumb-item"><a href="{{ route('section.management') }}">Управление</a></li>
            <li class="breadcrumb-item"><a href="{{ route('management.tasks.index') }}">Задачи</a></li>
            <li class="breadcrumb-item active" aria-current="page">Новая</li>
        </ol>
    </nav>
    <h1 class="h3 mb-0">Новая задача</h1>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="post" action="{{ route('management.tasks.store') }}">
            @csrf
            @include('management.tasks._form')
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Создать</button>
                <a href="{{ route('management.tasks.index') }}" class="btn btn-outline-secondary">Отмена</a>
            </div>
        </form>
    </div>
</div>
@endsection
