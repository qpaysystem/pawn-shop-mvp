@extends('cabinet.layout')
@section('title', 'Новая квартира')
@section('content')
<div class="mb-3">
    <a href="{{ route('cabinet.projects.show', $project) }}" class="btn btn-sm btn-outline-secondary">← К проекту «{{ $project->name }}»</a>
</div>
<h1 class="h4 mb-4">Создать карточку квартиры</h1>

<form method="post" action="{{ route('cabinet.projects.apartments.store', $project) }}">
    @csrf
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Номер квартиры *</label>
            <input type="text" name="apartment_number" class="form-control @error('apartment_number') is-invalid @enderror" value="{{ old('apartment_number') }}" required>
            @error('apartment_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Подъезд</label>
            <input type="text" name="entrance" class="form-control @error('entrance') is-invalid @enderror" value="{{ old('entrance') }}" placeholder="1" maxlength="20">
            @error('entrance')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Этаж</label>
            <input type="number" name="floor" class="form-control @error('floor') is-invalid @enderror" value="{{ old('floor') }}" min="0" max="999">
            @error('floor')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Жилая площадь, м²</label>
            <input type="number" name="living_area" step="0.01" min="0" class="form-control @error('living_area') is-invalid @enderror" value="{{ old('living_area') }}">
            @error('living_area')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Количество комнат</label>
            <input type="number" name="rooms_count" class="form-control @error('rooms_count') is-invalid @enderror" value="{{ old('rooms_count') }}" min="1" max="20">
            @error('rooms_count')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Номер договора ДДУ</label>
            <input type="text" name="ddu_contract_number" class="form-control @error('ddu_contract_number') is-invalid @enderror" value="{{ old('ddu_contract_number') }}" placeholder="Договор долевого строительства" maxlength="100">
            @error('ddu_contract_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Стоимость, {{ \App\Models\Setting::get('currency', 'RUB') }}</label>
            <input type="number" name="price" step="0.01" min="0" class="form-control @error('price') is-invalid @enderror" value="{{ old('price') }}" placeholder="0">
            @error('price')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Ответственный за квартиру</label>
            <select name="client_id" class="form-select @error('client_id') is-invalid @enderror">
                <option value="">— не выбран —</option>
                @foreach($clients as $c)
                    <option value="{{ $c->id }}" @selected(old('client_id') == $c->id)>{{ $c->full_name }}</option>
                @endforeach
            </select>
            @error('client_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <label class="form-label">Статус квартиры *</label>
            <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                @foreach(\App\Models\Apartment::statusLabels() as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', 'available') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <label class="form-label">Данные собственника</label>
            <textarea name="owner_data" class="form-control @error('owner_data') is-invalid @enderror" rows="2" placeholder="ФИО, контакт">{{ old('owner_data') }}</textarea>
            @error('owner_data')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <p class="text-muted small mt-2">Картинку планировки можно добавить в карточке квартиры после создания.</p>
    <div class="mt-3">
        <button type="submit" class="btn btn-primary">Создать</button>
        <a href="{{ route('cabinet.projects.show', $project) }}" class="btn btn-secondary">Отмена</a>
    </div>
</form>
@endsection
