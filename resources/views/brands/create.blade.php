@extends('layouts.app')

@section('title', 'Новый бренд')

@section('content')
<h1 class="h4 mb-4">Новый бренд</h1>
<form method="post" action="{{ route('brands.store') }}">@csrf
    <div class="mb-3"><label class="form-label">Название *</label><input type="text" name="name" class="form-control" value="{{ old('name') }}" required></div>
    <button type="submit" class="btn btn-primary">Создать</button>
    <a href="{{ route('brands.index') }}" class="btn btn-secondary">Отмена</a>
</form>
@endsection
