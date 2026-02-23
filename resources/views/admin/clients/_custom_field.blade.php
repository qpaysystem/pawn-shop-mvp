@php $name = 'custom_'.$field->name; $id = 'custom_'.$field->id; @endphp
<label class="form-label">{{ $field->label }} @if($field->required)<span class="text-danger">*</span>@endif</label>
@if($field->type === 'text')
    <input type="text" name="{{ $name }}" id="{{ $id }}" class="form-control" value="{{ $value ?? '' }}" @if($field->required) required @endif>
@elseif($field->type === 'number')
    <input type="number" name="{{ $name }}" id="{{ $id }}" class="form-control" value="{{ $value ?? '' }}" @if($field->required) required @endif>
@elseif($field->type === 'date')
    <input type="date" name="{{ $name }}" id="{{ $id }}" class="form-control" value="{{ $value ?? '' }}" @if($field->required) required @endif>
@elseif($field->type === 'select')
    <select name="{{ $name }}" id="{{ $id }}" class="form-select" @if($field->required) required @endif>
        <option value="">— Выберите —</option>
        @foreach($field->options ?? [] as $opt)
            <option value="{{ $opt }}" {{ ($value ?? '') == $opt ? 'selected' : '' }}>{{ $opt }}</option>
        @endforeach
    </select>
@elseif($field->type === 'checkbox')
    <div class="form-check">
        <input type="checkbox" name="{{ $name }}" id="{{ $id }}" class="form-check-input" value="1" {{ ($value ?? '') == '1' ? 'checked' : '' }}>
        <label class="form-check-label" for="{{ $id }}">Да</label>
    </div>
@elseif($field->type === 'textarea')
    <textarea name="{{ $name }}" id="{{ $id }}" class="form-control" rows="3" @if($field->required) required @endif>{{ $value ?? '' }}</textarea>
@else
    <input type="file" name="{{ $name }}" id="{{ $id }}" class="form-control" @if($field->required) required @endif>
@endif
