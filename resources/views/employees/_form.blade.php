@php
    $activeTab = old('active_tab', request('tab', 'main'));
    $portalUser = $employee->user ?? null;
@endphp

<ul class="nav nav-tabs mb-3" id="employeeFormTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link {{ $activeTab === 'main' ? 'active' : '' }}" id="emp-tab-main" data-bs-toggle="tab" data-bs-target="#emp-pane-main" type="button" role="tab">Основные данные</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link {{ $activeTab === 'portal' ? 'active' : '' }}" id="emp-tab-portal" data-bs-toggle="tab" data-bs-target="#emp-pane-portal" type="button" role="tab">Пользователь портала</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link {{ $activeTab === 'skills' ? 'active' : '' }}" id="emp-tab-skills" data-bs-toggle="tab" data-bs-target="#emp-pane-skills" type="button" role="tab">Компетенции</button>
    </li>
</ul>

<input type="hidden" name="active_tab" id="active_tab" value="{{ $activeTab }}">

<div class="tab-content mb-3">
    <div class="tab-pane fade {{ $activeTab === 'main' ? 'show active' : '' }}" id="emp-pane-main" role="tabpanel">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Фамилия *</label>
                        <input type="text" name="last_name" class="form-control @error('last_name') is-invalid @enderror" value="{{ old('last_name', $employee->last_name) }}" required>
                        @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Имя *</label>
                        <input type="text" name="first_name" class="form-control @error('first_name') is-invalid @enderror" value="{{ old('first_name', $employee->first_name) }}" required>
                        @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Отчество</label>
                        <input type="text" name="patronymic" class="form-control" value="{{ old('patronymic', $employee->patronymic) }}">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Телефон</label>
                        <input type="text" name="phone" class="form-control" value="{{ old('phone', $employee->phone) }}" placeholder="+7…">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Telegram (личный)</label>
                        <input type="text" name="telegram" class="form-control" value="{{ old('telegram', $employee->telegram) }}" placeholder="@username">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Паспортные данные</label>
                    <textarea name="passport_data" class="form-control" rows="3" placeholder="Серия, номер, кем и когда выдан…">{{ old('passport_data', $employee->passport_data) }}</textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Прописка / адрес регистрации</label>
                    <textarea name="registration_address" class="form-control" rows="2">{{ old('registration_address', $employee->registration_address) }}</textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Фото</label>
                    @if($employee->photo_url)
                        <div class="mb-2">
                            <img src="{{ $employee->photo_url }}" alt="" class="rounded" style="max-height:120px;">
                        </div>
                        <div class="form-check mb-2">
                            <input type="checkbox" name="remove_photo" value="1" class="form-check-input" id="remove_photo">
                            <label class="form-check-label" for="remove_photo">Удалить текущее фото</label>
                        </div>
                    @endif
                    <input type="file" name="photo" class="form-control" accept="image/jpeg,image/png,image/webp">
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Должность</label>
                        <input type="text" name="position" class="form-control" value="{{ old('position', $employee->position) }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Магазин</label>
                        <select name="store_id" class="form-select">
                            <option value="">— не указан</option>
                            @foreach($stores as $s)
                                <option value="{{ $s->id }}" @selected(old('store_id', $employee->store_id) == $s->id)>{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-check">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" @checked(old('is_active', $employee->is_active ?? true))>
                    <label class="form-check-label" for="is_active">Активен (участвует в начислениях ФОТ)</label>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade {{ $activeTab === 'portal' ? 'show active' : '' }}" id="emp-pane-portal" role="tabpanel">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="text-muted small">Привяжите учётную запись портала к карточке сотрудника.</p>
                <div class="mb-3">
                    <label class="form-label">Пользователь портала</label>
                    <select name="user_id" class="form-select">
                        <option value="">— не привязан</option>
                        @foreach($portalUsers as $u)
                            <option value="{{ $u->id }}" @selected(old('user_id', $employee->user_id) == $u->id)>
                                {{ $u->name }} ({{ $u->email }})
                            </option>
                        @endforeach
                    </select>
                    @if($portalUser && !$portalUsers->contains('id', $portalUser->id))
                        <div class="form-text">Текущий: {{ $portalUser->name }} ({{ $portalUser->email }})</div>
                    @endif
                </div>
                <div class="mb-0">
                    <label class="form-label">Telegram в профиле портала</label>
                    <input type="text" name="portal_telegram" class="form-control" value="{{ old('portal_telegram', $portalUser?->telegram) }}" placeholder="@username">
                    <div class="form-text">Сохраняется в учётной записи пользователя при привязке.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade {{ $activeTab === 'skills' ? 'show active' : '' }}" id="emp-pane-skills" role="tabpanel">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Описание характера</label>
                    <textarea name="character_description" class="form-control" rows="4" placeholder="Коммуникабельность, ответственность, особенности…">{{ old('character_description', $employee->character_description) }}</textarea>
                </div>
                <div class="mb-0">
                    <label class="form-label">Профессиональные данные</label>
                    <textarea name="professional_data" class="form-control" rows="5" placeholder="Навыки, опыт, сертификаты, специализация…">{{ old('professional_data', $employee->professional_data) }}</textarea>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var hidden = document.getElementById('active_tab');
    if (!hidden) return;
    document.querySelectorAll('#employeeFormTabs [data-bs-toggle="tab"]').forEach(function (btn) {
        btn.addEventListener('shown.bs.tab', function () {
            var id = btn.getAttribute('data-bs-target');
            if (id === '#emp-pane-portal') hidden.value = 'portal';
            else if (id === '#emp-pane-skills') hidden.value = 'skills';
            else hidden.value = 'main';
        });
    });
})();
</script>
@endpush
