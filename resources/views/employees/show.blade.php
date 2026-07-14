@extends('layouts.app')

@section('title', $employee->full_name)

@section('content')
@php
    $activeTab = request('tab', 'main');
    $portalUser = $employee->user;
@endphp

<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-2">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Дашборд</a></li>
            <li class="breadcrumb-item"><a href="{{ route('section.management') }}">Управление</a></li>
            <li class="breadcrumb-item"><a href="{{ route('management.personnel.index') }}">Персонал</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $employee->full_name }}</li>
        </ol>
    </nav>
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
            @if($employee->photo_url)
                <img src="{{ $employee->photo_url }}" alt="" class="rounded" style="width:72px;height:72px;object-fit:cover;">
            @else
                <span class="d-inline-flex align-items-center justify-content-center rounded bg-light text-muted" style="width:72px;height:72px;font-size:1.5rem;">
                    <i class="bi bi-person"></i>
                </span>
            @endif
            <div>
                <h1 class="h3 mb-1">{{ $employee->full_name }}</h1>
                <p class="text-muted mb-0">{{ $employee->position ?: 'Должность не указана' }} · {{ $employee->store?->name ?: 'Магазин не указан' }}</p>
            </div>
        </div>
        <div>
            <a href="{{ route('management.personnel.edit', $employee) }}" class="btn btn-outline-primary">Редактировать</a>
            <a href="{{ route('management.personnel.index') }}" class="btn btn-outline-secondary">К журналу</a>
        </div>
    </div>
</div>

<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item"><a class="nav-link {{ $activeTab === 'main' ? 'active' : '' }}" href="{{ route('management.personnel.show', [$employee, 'tab' => 'main']) }}">Основные данные</a></li>
    <li class="nav-item"><a class="nav-link {{ $activeTab === 'portal' ? 'active' : '' }}" href="{{ route('management.personnel.show', [$employee, 'tab' => 'portal']) }}">Пользователь портала</a></li>
    <li class="nav-item"><a class="nav-link {{ $activeTab === 'skills' ? 'active' : '' }}" href="{{ route('management.personnel.show', [$employee, 'tab' => 'skills']) }}">Компетенции</a></li>
</ul>

@if($activeTab === 'main')
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <p><strong>Телефон:</strong> {{ $employee->phone ?: '—' }}</p>
        @if($employee->telegram)<p><strong>Telegram:</strong> {{ $employee->telegram }}</p>@endif
        <p><strong>Паспортные данные:</strong><br>{!! nl2br(e($employee->passport_data ?: '—')) !!}</p>
        <p><strong>Прописка:</strong><br>{!! nl2br(e($employee->registration_address ?: '—')) !!}</p>
        <p class="mb-0"><strong>Статус:</strong> {{ $employee->is_active ? 'Активен' : 'Неактивен' }}</p>
    </div>
</div>
@elseif($activeTab === 'portal')
<div class="card border-0 shadow-sm">
    <div class="card-body">
        @if($portalUser)
            <p><strong>Имя пользователя:</strong> {{ $portalUser->name }}</p>
            <p><strong>Email (логин):</strong> {{ $portalUser->email }}</p>
            <p><strong>Telegram:</strong> {{ $portalUser->telegram ?: '—' }}</p>
            <p><strong>Роль:</strong> {{ $portalUser->role }}</p>
            <p class="mb-0"><strong>Дата регистрации:</strong> {{ $portalUser->created_at?->format('d.m.Y H:i') ?: '—' }}</p>
        @else
            <p class="text-muted mb-0">Учётная запись портала не привязана. <a href="{{ route('management.personnel.edit', [$employee, 'tab' => 'portal']) }}">Привязать</a></p>
        @endif
    </div>
</div>
@else
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <p><strong>Характер:</strong><br>{!! nl2br(e($employee->character_description ?: '—')) !!}</p>
        <p class="mb-0"><strong>Профессиональные данные:</strong><br>{!! nl2br(e($employee->professional_data ?: '—')) !!}</p>
    </div>
</div>
@endif
@endsection
