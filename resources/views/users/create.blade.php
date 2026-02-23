@extends('layouts.app')

@section('title', 'Новый пользователь')

@section('content')
<h1 class="h4 mb-4">Новый пользователь</h1>
<form method="post" action="{{ route('users.store') }}">@csrf
    <div class="mb-3"><label class="form-label">Имя *</label><input type="text" name="name" class="form-control" value="{{ old('name') }}" required></div>
    <div class="mb-3"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" value="{{ old('email') }}" required></div>
    <div class="mb-3"><label class="form-label">Пароль *</label><input type="password" name="password" class="form-control" required></div>
    <div class="mb-3"><label class="form-label">Подтверждение пароля *</label><input type="password" name="password_confirmation" class="form-control" required></div>
    <div class="mb-3"><label class="form-label">Роль *</label><select name="role" class="form-select" id="user_role">@foreach(['super-admin' => 'Супер-админ', 'manager' => 'Управляющий', 'appraiser' => 'Оценщик', 'cashier' => 'Кассир', 'storekeeper' => 'Кладовщик'] as $v => $l)<option value="{{ $v }}" {{ old('role') === $v ? 'selected' : '' }}>{{ $l }}</option>@endforeach</select></div>
    <div class="mb-3" id="user_store_block"><label class="form-label">Магазин *</label><select name="store_id" class="form-select">@foreach($stores as $s)<option value="{{ $s->id }}" {{ old('store_id') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>@endforeach</select></div>
    <button type="submit" class="btn btn-primary">Создать</button>
    <a href="{{ route('users.index') }}" class="btn btn-secondary">Отмена</a>
</form>
@push('scripts')
<script>
document.getElementById('user_role').addEventListener('change', function() {
    document.getElementById('user_store_block').style.display = this.value === 'super-admin' ? 'none' : 'block';
});
if (document.getElementById('user_role').value === 'super-admin') document.getElementById('user_store_block').style.display = 'none';
</script>
@endpush
@endsection
