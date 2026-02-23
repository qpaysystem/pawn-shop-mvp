@extends('layouts.admin')
@section('title', $client->full_name)
@section('content')
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h4 mb-1">{{ $client->full_name }}</h1>
        <span class="badge bg-{{ $client->status === 'active' ? 'success' : 'secondary' }}">{{ $client->status === 'active' ? 'Активный' : 'Неактивный' }}</span>
    </div>
    <div>
        <a href="{{ route('admin.clients.edit', $client) }}" class="btn btn-outline-primary">Изменить</a>
        <form method="post" action="{{ route('admin.clients.destroy', $client) }}" class="d-inline" onsubmit="return confirm('Удалить клиента?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger">Удалить</button>
        </form>
    </div>
</div>
<div class="row">
    <div class="col-md-4">
        @if($client->photo_path)
            <img src="{{ asset('storage/'.$client->photo_path) }}" alt="" class="img-fluid rounded mb-3" style="max-height: 300px;">
            <form method="post" action="{{ route('admin.clients.photo.delete', $client) }}" class="d-inline" onsubmit="return confirm('Удалить фото?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger">Удалить фото</button>
            </form>
        @else
            <div class="bg-light rounded d-flex align-items-center justify-content-center mb-3" style="height: 200px;"><span class="text-muted">Нет фото</span></div>
        @endif
        <form method="post" action="{{ route('admin.clients.photo', $client) }}" enctype="multipart/form-data" class="mt-2">
            @csrf
            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="form-control form-control-sm">
            <button type="submit" class="btn btn-sm btn-primary mt-1">Загрузить</button>
        </form>
    </div>
    <div class="col-md-8">
        <table class="table table-bordered">
            <tr><th style="width:180px">Email</th><td>{{ $client->email }}</td></tr>
            <tr><th>Телефон</th><td>{{ $client->phone }}</td></tr>
            @if($client->telegram_id || $client->telegram_username)
            <tr><th>Telegram</th><td>
                @if($client->telegram_username)
                    <a href="https://t.me/{{ $client->telegram_username }}" target="_blank">@{{ $client->telegram_username }}</a>
                    @if($client->telegram_id)<span class="text-muted">(ID: {{ $client->telegram_id }})</span>@endif
                @else
                    ID: {{ $client->telegram_id }}
                @endif
            </td></tr>
            @endif
            <tr><th>Дата рождения</th><td>{{ $client->birth_date?->format('d.m.Y') }}</td></tr>
            <tr><th>Дата регистрации</th><td>{{ $client->registered_at?->format('d.m.Y') }}</td></tr>
            <tr><th>Баланс</th><td><strong>{{ number_format($client->balance, 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}</strong></td></tr>
        </table>
        @foreach($client->customValues as $cv)
            @if($cv->customField && $cv->value !== null && $cv->value !== '')
            <p class="mb-1"><strong>{{ $cv->customField->label }}:</strong> {{ $cv->value }}</p>
            @endif
        @endforeach

        <h5 class="mt-4">Операции с балансом</h5>
        <form method="post" action="{{ route('admin.clients.balance', $client) }}" id="balance-form" class="row g-2 mb-3 align-items-end">
            @csrf
            <div class="col-auto">
                <label class="form-label small mb-0">Тип операции</label>
                <select name="operation_type" id="operation_type" class="form-select @error('operation_type') is-invalid @enderror" required>
                    @foreach(\App\Models\BalanceTransaction::operationTypeLabels() as $value => $label)
                        <option value="{{ $value }}" @selected(old('operation_type', '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('operation_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-auto" id="project_wrap" style="display: {{ old('operation_type') === \App\Models\BalanceTransaction::OPERATION_PROJECT_EXPENSE ? 'block' : 'none' }};">
                <label class="form-label small mb-0">Проект</label>
                <select name="project_id" id="project_id" class="form-select">
                    <option value="">— Выберите проект —</option>
                    @foreach($projects ?? [] as $proj)
                        <option value="{{ $proj->id }}" @selected(old('project_id') == $proj->id)>{{ $proj->name }}</option>
                    @endforeach
                </select>
                @error('project_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-auto" id="expense_item_wrap" style="display: {{ old('operation_type') === \App\Models\BalanceTransaction::OPERATION_PROJECT_EXPENSE ? 'block' : 'none' }};">
                <label class="form-label small mb-0">Статья расхода</label>
                <select name="project_expense_item_id" id="project_expense_item_id" class="form-select">
                    <option value="">— Сначала выберите проект —</option>
                </select>
                @error('project_expense_item_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <script type="application/json" id="projects-expense-items-data">@json($projects ? $projects->mapWithKeys(function ($p) { return [$p->id => $p->expenseItems->map(function ($e) { return ['id' => $e->id, 'name' => $e->name]; })->values()->all()]; })->all() : [])</script>
            <div class="col-auto" id="loan_days_wrap" style="display: {{ old('operation_type') === \App\Models\BalanceTransaction::OPERATION_LOAN ? 'block' : 'none' }};">
                <label class="form-label small mb-0">Количество дней займа</label>
                <input type="number" name="loan_days" id="loan_days" class="form-control @error('loan_days') is-invalid @enderror" value="{{ old('loan_days') }}" min="1" max="3650" placeholder="дней">
                @error('loan_days')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-auto" id="product_pledge_wrap" style="display: {{ old('operation_type') === \App\Models\BalanceTransaction::OPERATION_LOAN ? 'block' : 'none' }};">
                <label class="form-label small mb-0">Залог (товар)</label>
                <select name="product_id" class="form-select">
                    <option value="">— не выбран</option>
                    @foreach($products as $prod)
                        <option value="{{ $prod->id }}" @selected(old('product_id') == $prod->id)>{{ $prod->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto" id="loan_repayment_date_wrap" style="display: {{ old('operation_type') === \App\Models\BalanceTransaction::OPERATION_LOAN ? 'block' : 'none' }};">
                <label class="form-label small mb-0">Дата возврата</label>
                <div class="form-control-plaintext py-2" id="loan_repayment_date_display">—</div>
            </div>
            <div class="col-auto">
                <label class="form-label small mb-0">Сумма</label>
                <input type="number" name="amount" step="0.01" min="0.01" class="form-control @error('amount') is-invalid @enderror" placeholder="Сумма" value="{{ old('amount') }}" required>
                @error('amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-auto">
                <label class="form-label small mb-0">Комментарий</label>
                <input type="text" name="comment" class="form-control" placeholder="Комментарий" value="{{ old('comment') }}">
            </div>
            <div class="col-auto"><button type="submit" class="btn btn-primary">Выполнить</button></div>
        </form>
        <script>
            (function() {
                var op = document.getElementById('operation_type');
                var wrap = document.getElementById('loan_days_wrap');
                var wrapPledge = document.getElementById('product_pledge_wrap');
                var wrapDate = document.getElementById('loan_repayment_date_wrap');
                var inputDays = document.getElementById('loan_days');
                var displayDate = document.getElementById('loan_repayment_date_display');
                function formatDate(d) {
                    var day = ('0' + d.getDate()).slice(-2);
                    var month = ('0' + (d.getMonth() + 1)).slice(-2);
                    var year = d.getFullYear();
                    return day + '.' + month + '.' + year;
                }
                function updateRepaymentDate() {
                    var days = parseInt(inputDays.value, 10);
                    if (!isNaN(days) && days >= 1) {
                        var d = new Date();
                        d.setDate(d.getDate() + days);
                        displayDate.textContent = formatDate(d);
                    } else {
                        displayDate.textContent = '—';
                    }
                }
                var projectWrap = document.getElementById('project_wrap');
                var expenseItemWrap = document.getElementById('expense_item_wrap');
                var projectSelect = document.getElementById('project_id');
                var expenseItemSelect = document.getElementById('project_expense_item_id');
                var projectsDataEl = document.getElementById('projects-expense-items-data');
                var projectsData = projectsDataEl ? JSON.parse(projectsDataEl.textContent || '{}') : {};
                var oldExpenseItemId = {{ json_encode(old('project_expense_item_id')) }};
                function filterExpenseItems() {
                    if (!expenseItemSelect) return;
                    var projectId = projectSelect && projectSelect.value;
                    var saved = oldExpenseItemId || expenseItemSelect.value;
                    expenseItemSelect.innerHTML = '<option value="">— Выберите статью —</option>';
                    if (projectId && projectsData[projectId]) {
                        projectsData[projectId].forEach(function(item) {
                            var opt = document.createElement('option');
                            opt.value = item.id;
                            opt.textContent = item.name;
                            if (String(item.id) === String(saved)) opt.selected = true;
                            expenseItemSelect.appendChild(opt);
                        });
                    }
                    oldExpenseItemId = null;
                }
                function toggle() {
                    var isLoan = op.value === '{{ \App\Models\BalanceTransaction::OPERATION_LOAN }}';
                    var isProjectExpense = op.value === '{{ \App\Models\BalanceTransaction::OPERATION_PROJECT_EXPENSE }}';
                    wrap.style.display = isLoan ? 'block' : 'none';
                    if (wrapPledge) wrapPledge.style.display = isLoan ? 'block' : 'none';
                    wrapDate.style.display = isLoan ? 'block' : 'none';
                    if (projectWrap) projectWrap.style.display = isProjectExpense ? 'block' : 'none';
                    if (expenseItemWrap) expenseItemWrap.style.display = isProjectExpense ? 'block' : 'none';
                    projectSelect.required = isProjectExpense;
                    if (expenseItemSelect) expenseItemSelect.required = isProjectExpense;
                    inputDays.required = isLoan;
                    if (isProjectExpense) filterExpenseItems();
                    updateRepaymentDate();
                }
                if (projectSelect) projectSelect.addEventListener('change', function() {
                    filterExpenseItems();
                });
                op.addEventListener('change', toggle);
                inputDays.addEventListener('input', updateRepaymentDate);
                inputDays.addEventListener('change', updateRepaymentDate);
                toggle();
                if (projectSelect && projectSelect.value) filterExpenseItems();
            })();
        </script>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead><tr><th>Дата</th><th>Тип операции</th><th>Залог / Проект</th><th>Дней</th><th>Дата возврата</th><th>Сумма</th><th>Баланс после</th><th>Комментарий</th></tr></thead>
                <tbody>
                    @forelse($client->balanceTransactions as $t)
                    <tr>
                        <td>{{ $t->created_at->format('d.m.Y H:i') }}</td>
                        <td>{{ $t->operation_type_label }}</td>
                        <td>
                            @if($t->operation_type === \App\Models\BalanceTransaction::OPERATION_PROJECT_EXPENSE)
                                @if($t->project)<a href="{{ route('admin.projects.show', $t->project) }}">{{ $t->project->name }}</a>@if($t->projectExpenseItem) — {{ $t->projectExpenseItem->name }}@endif @else — @endif
                            @else
                                @if($t->product)<a href="{{ route('admin.products.edit', $t->product) }}">{{ $t->product->name }}</a>@else — @endif
                            @endif
                        </td>
                        <td>{{ $t->loan_days ?? '—' }}</td>
                        <td>{{ $t->loan_due_at?->format('d.m.Y') ?? '—' }}</td>
                        <td>{{ $t->type === 'deposit' ? '+' : '−' }}{{ number_format($t->amount, 2) }}</td>
                        <td>{{ number_format($t->balance_after, 2) }}</td>
                        <td>{{ $t->comment }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-muted">Нет операций</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
<a href="{{ route('admin.clients.index') }}" class="btn btn-secondary mt-3">← К списку</a>
@endsection
