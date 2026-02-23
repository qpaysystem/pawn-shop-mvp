@extends('layouts.admin')
@section('title', 'Поля клиентов')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Поля карточки клиента</h1>
    <a href="{{ route('admin.custom-fields.create') }}" class="btn btn-primary">Добавить поле</a>
</div>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>Системное имя</th><th>Название</th><th>Тип</th><th>Обязательное</th><th>Активно</th><th></th></tr></thead>
            <tbody>
                @foreach($fields as $f)
                <tr>
                    <td>{{ $f->sort_order }}</td>
                    <td><code>{{ $f->name }}</code></td>
                    <td>{{ $f->label }}</td>
                    <td>{{ $f->type }}</td>
                    <td>{{ $f->required ? 'Да' : 'Нет' }}</td>
                    <td>{{ $f->is_active ? 'Да' : 'Нет' }}</td>
                    <td>
                        <a href="{{ route('admin.custom-fields.edit', $f) }}" class="btn btn-sm btn-outline-primary">Изменить</a>
                        <form method="post" action="{{ route('admin.custom-fields.destroy', $f) }}" class="d-inline" onsubmit="return confirm('Удалить поле?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@if($fields->isEmpty())
<p class="text-muted mt-3">Нет пользовательских полей. Добавьте поля для расширения карточки клиента.</p>
@endif
@endsection
