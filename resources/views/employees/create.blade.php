@extends('layouts.app')

@section('title', 'Новая карточка сотрудника')

@section('content')
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-2">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Дашборд</a></li>
            <li class="breadcrumb-item"><a href="{{ route('section.management') }}">Управление</a></li>
            <li class="breadcrumb-item"><a href="{{ route('management.personnel.index') }}">Персонал</a></li>
            <li class="breadcrumb-item active" aria-current="page">Новая карточка</li>
        </ol>
    </nav>
    <h1 class="h3 mb-0">Новая карточка сотрудника</h1>
</div>

<form method="post" action="{{ route('management.personnel.store') }}" enctype="multipart/form-data">
    @csrf
    @include('employees._form')
    <button type="submit" class="btn btn-primary">Создать карточку</button>
    <a href="{{ route('management.personnel.index') }}" class="btn btn-outline-secondary">Отмена</a>
</form>
@endsection
