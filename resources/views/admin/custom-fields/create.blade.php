@extends('layouts.admin')
@section('title', 'Новое поле')
@section('content')
<h1 class="h4 mb-4">Добавить поле</h1>
<form method="post" action="{{ route('admin.custom-fields.store') }}">
    @csrf
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Системное имя (латиница, цифры, _) *</label>
            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" pattern="[a-z0-9_]+" placeholder="например: city" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Отображаемое название *</label>
            <input type="text" name="label" class="form-control @error('label') is-invalid @enderror" value="{{ old('label') }}" required>
            @error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Тип *</label>
            <select name="type" class="form-select" id="fieldType">
                @foreach($types as $k => $v)<option value="{{ $k }}" {{ old('type') === $k ? 'selected' : '' }}>{{ $v }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Порядок</label>
            <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', 0) }}" min="0">
        </div>
        <div class="col-12">
            <div class="form-check">
                <input type="checkbox" name="required" value="1" class="form-check-input" id="required" {{ old('required') ? 'checked' : '' }}>
                <label class="form-check-label" for="required">Обязательное поле</label>
            </div>
        </div>
        <div class="col-12" id="optionsWrap" style="display:{{ old('type') === 'select' ? 'block' : 'none' }};">
            <label class="form-label">Варианты для выпадающего списка (каждый с новой строки)</label>
            <textarea name="options" class="form-control" rows="4" placeholder="Вариант 1\nВариант 2">{{ old('options') }}</textarea>
        </div>
    </div>
    <div class="mt-4">
        <button type="submit" class="btn btn-primary">Создать</button>
        <a href="{{ route('admin.custom-fields.index') }}" class="btn btn-secondary">Отмена</a>
    </div>
</form>
<script>
document.getElementById('fieldType').addEventListener('change', function() {
    document.getElementById('optionsWrap').style.display = this.value === 'select' ? 'block' : 'none';
});
</script>
@endsection
