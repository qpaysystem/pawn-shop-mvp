@extends('layouts.app')

@section('title', 'Новый клиент')

@section('content')
<h1 class="h4 mb-4">Новый клиент</h1>
<form method="post" action="{{ route('clients.store') }}" id="client-form">@csrf
    <div class="mb-4">
        <label class="form-label">Статус клиента</label>
        <div class="d-flex gap-3">
            <label class="form-check">
                <input type="radio" name="client_type" value="individual" class="form-check-input" {{ old('client_type', 'individual') === 'individual' ? 'checked' : '' }}>
                <span class="form-check-label">Физическое лицо</span>
            </label>
            <label class="form-check">
                <input type="radio" name="client_type" value="legal" class="form-check-input" {{ old('client_type') === 'legal' ? 'checked' : '' }}>
                <span class="form-check-label">Юридическое лицо</span>
            </label>
        </div>
    </div>

    <div id="block-individual" class="mb-3">
        <div class="row mb-3">
            <div class="col-md-4"><label class="form-label">Фамилия *</label><input type="text" name="last_name" class="form-control" value="{{ old('last_name') }}"></div>
            <div class="col-md-4"><label class="form-label">Имя *</label><input type="text" name="first_name" class="form-control" value="{{ old('first_name') }}"></div>
            <div class="col-md-4"><label class="form-label">Отчество</label><input type="text" name="patronymic" class="form-control" value="{{ old('patronymic') }}"></div>
        </div>
    </div>

    <div id="block-legal" class="mb-3" style="display: none;">
        <div class="card card-body mb-3">
            <h6 class="text-secondary mb-3">Реквизиты юридического лица</h6>
            <div class="mb-3">
                <label class="form-label">Наименование организации *</label>
                <input type="text" name="legal_name" class="form-control" value="{{ old('legal_name') }}" placeholder="ООО «Ромашка»">
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">ИНН</label>
                    <input type="text" name="inn" class="form-control" value="{{ old('inn') }}" maxlength="12" placeholder="10 или 12 цифр">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">КПП</label>
                    <input type="text" name="kpp" class="form-control" value="{{ old('kpp') }}" maxlength="9" placeholder="9 цифр">
                </div>
            </div>
            <div class="mb-0">
                <label class="form-label">Юридический адрес</label>
                <textarea name="legal_address" class="form-control" rows="2" placeholder="Индекс, город, улица, дом">{{ old('legal_address') }}</textarea>
            </div>
        </div>
        <p class="small text-muted">Контактное лицо (ФИО) можно указать в полях ниже или в заметках.</p>
    </div>

    <div class="mb-3"><label class="form-label">Телефон *</label><input type="text" name="phone" class="form-control" value="{{ old('phone') }}" required></div>
    <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ old('email') }}"></div>
    <div id="block-passport" class="mb-3">
        <label class="form-label">Паспортные данные</label>
        <textarea name="passport_data" class="form-control" rows="2">{{ old('passport_data') }}</textarea>
    </div>
    <div class="mb-3"><label class="form-label">Заметки</label><textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea></div>
    <div class="mb-3 form-check"><input type="checkbox" name="blacklist_flag" class="form-check-input" value="1" {{ old('blacklist_flag') ? 'checked' : '' }}><label class="form-check-label">Чёрный список</label></div>
    <button type="submit" class="btn btn-primary">Создать</button>
    <a href="{{ route('clients.index') }}" class="btn btn-secondary">Отмена</a>
</form>
<script>
(function() {
    var form = document.getElementById('client-form');
    var blockIndividual = document.getElementById('block-individual');
    var blockLegal = document.getElementById('block-legal');
    var blockPassport = document.getElementById('block-passport');
    function toggle() {
        var isLegal = form.querySelector('input[name="client_type"]:checked')?.value === 'legal';
        blockIndividual.style.display = isLegal ? 'none' : 'block';
        blockLegal.style.display = isLegal ? 'block' : 'none';
        blockPassport.style.display = isLegal ? 'none' : 'block';
        blockIndividual.querySelectorAll('input').forEach(function(inp) {
            inp.required = !isLegal && (inp.name === 'last_name' || inp.name === 'first_name');
        });
        blockLegal.querySelector('input[name="legal_name"]').required = isLegal;
    }
    form.querySelectorAll('input[name="client_type"]').forEach(function(r) {
        r.addEventListener('change', toggle);
    });
    toggle();
})();
</script>
@endsection
