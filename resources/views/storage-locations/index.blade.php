@extends('layouts.app')

@section('title', 'Места хранения')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Места хранения</h1>
    <a href="{{ route('storage-locations.create') }}" class="btn btn-primary"><i class="bi bi-plus"></i> Добавить</a>
</div>
<form method="get" class="mb-3 row g-2">
    <div class="col-auto"><select name="store_id" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">Все магазины</option>@foreach($stores as $s)<option value="{{ $s->id }}" {{ request('store_id') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>@endforeach</select></div>
</form>
<table class="table table-hover">
    <thead><tr><th>Название</th><th>Магазин</th><th></th></tr></thead>
    <tbody>
        @foreach($locations as $loc)
        <tr>
            <td>{{ $loc->name }}</td>
            <td>{{ $loc->store->name }}</td>
            <td>
                <a href="{{ route('storage-locations.edit', $loc) }}" class="btn btn-sm btn-outline-secondary">Изменить</a>
                <form action="{{ route('storage-locations.destroy', $loc) }}" method="post" class="d-inline" onsubmit="return confirm('Удалить?')">@csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button></form>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
{{ $locations->links() }}
@endsection
