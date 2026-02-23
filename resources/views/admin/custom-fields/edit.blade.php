@extends('layouts.admin')
@section('title', 'Редактировать поле')
@section('content')
<h1 class="h4 mb-4">Редактировать поле: {{ $field->label }}</h1>
<form method="post" action="{{ route('admin.custom-fields.update', $field) }}">
    @csrf
    @method('PUT')
    <div class="row g-3">
        <div class="col-12"><p class="text-muted">Системное имя: <code>{{ $field->name }}</code> (не изменяется)</p></div>
        <div class="col-md-6">
            <label class="form-label">Отображаемое название *</label>
            <input type="text" name="label" class="form-control @error('label') is-invalid @enderror" value="{{ old('label', $field->label) }}" required>
            @error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Тип *</label>
            <select name="type" class="form-select" id="fieldType">
                @foreach($types as $k => $v)<option value="{{ $k }}" {{ old('type', $field->type) === $k ? 'selected' : '' }}>{{ $v }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Порядок</label>
            <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $field->sort_order) }}" min="0">
        </div>
        <div class="col-12">
            <div class="form-check">
                <input type="checkbox" name="required" value="1" class="form-check-input" id="required" {{ old('required', $field->required) ? 'checked' : '' }}>
                <label class="form-check-label" for="required">Обязательное поле</label>
            </div>
        </div>
        <div class="col-12">
            <div class="form-check">
                <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" {{ old('is_active', $field->is_active) ? 'checked' : '' }}>
                <label class="form-check-label" for="is_active">Активно (отображать в формах и на сайте)</label>
            </div>
        </div>
        <div class="col-12" id="optionsWrap" style="display:{{ $field->type === 'select' ? 'block' : 'none' }};">
            <label class="form-label">Варианты для выпадающего списка (каждый с новой строки)</label>
            <textarea name="options" class="form-control" rows="4">{{ is_array($field->options) ? implode("\n", $field->options) : old('options', '') }}</textarea>
        </div>
    </div>
    <div class="mt-4">
        <button type="submit" class="btn btn-primary">Сохранить</button>
        <a href="{{ route('admin.custom-fields.index') }}" class="btn btn-secondary">Отмена</a>
    </div>
</form>
<script>
document.getElementById('fieldType').addEventListener('change', function() {
    document.getElementById('optionsWrap').style.display = this.value === 'select' ? 'block' : 'none';
});
</script>
@endsection
