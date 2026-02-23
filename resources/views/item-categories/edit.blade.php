@extends('layouts.app')

@section('title', 'Редактировать категорию')

@section('content')
<h1 class="h4 mb-4">Редактировать категорию</h1>
<form method="post" action="{{ route('item-categories.update', $itemCategory) }}">
    @csrf @method('PUT')
    <div class="mb-3"><label class="form-label">Название *</label><input type="text" name="name" class="form-control" value="{{ old('name', $itemCategory->name) }}" required></div>
    <div class="mb-3"><label class="form-label">Родительская категория</label><select name="parent_id" class="form-select"><option value="">—</option>@foreach($parents as $p)<option value="{{ $p->id }}" {{ old('parent_id', $itemCategory->parent_id) == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>@endforeach</select></div>
    <div class="mb-3">
        <label class="form-label">Доп. подсказка для AI-оценки</label>
        <textarea name="evaluation_config[ai_prompt_suffix]" class="form-control" rows="2" placeholder="Например: учитывать износ, актуальные цены на ювелирку…">{{ old('evaluation_config.ai_prompt_suffix', $itemCategory->evaluation_config['ai_prompt_suffix'] ?? '') }}</textarea>
        <small class="text-muted">Добавляется к промпту ИИ при оценке товаров этой категории.</small>
    </div>
    <button type="submit" class="btn btn-primary">Сохранить</button>
    <a href="{{ route('item-categories.index') }}" class="btn btn-secondary">Отмена</a>
</form>
@endsection
