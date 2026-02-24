{{-- Вкладка "Бухгалтерские проводки" для любого документа. Журнал операций: Содержание | Дебет | Кредит. Переменные: $documentType, $documentId, $ledgerEntries, $templates --}}
@php
    $accounts = \App\Models\Account::where('is_active', true)->orderBy('sort_order')->orderBy('code')->get();
    $debitEntries = $ledgerEntries->filter(fn ($e) => (float) $e->debit > 0);
    $creditEntries = $ledgerEntries->filter(fn ($e) => (float) $e->credit > 0);
    // Журнал операций: группируем проводки по (дата, комментарий) и собираем строки "Содержание | Дебет | Кредит"
    $journalRows = [];
    foreach ($ledgerEntries->groupBy(fn ($e) => ($e->entry_date ? \Carbon\Carbon::parse($e->entry_date)->format('Y-m-d') : '') . '|' . (string) $e->comment) as $groupKey => $group) {
        $debitRow = $group->first(fn ($e) => (float) $e->debit > 0);
        $creditRow = $group->first(fn ($e) => (float) $e->credit > 0);
        if ($debitRow && $creditRow) {
            $journalRows[] = (object) [
                'content' => $debitRow->comment ?: '—',
                'debit_code' => $debitRow->account ? $debitRow->account->code : '—',
                'credit_code' => $creditRow->account ? $creditRow->account->code : '—',
                'amount' => (float) $debitRow->debit,
                'entry_date' => $debitRow->entry_date,
            ];
        }
    }
    $journalRows = collect($journalRows)->sortBy('entry_date')->values()->all();
@endphp
<div class="card mb-4">
    <div class="card-header">Проводки по документу (двойная запись, отражение в ОСВ)</div>
    <div class="card-body">
        @if($ledgerEntries->isEmpty())
            <p class="text-muted mb-3">По этому документу проводок пока нет.</p>
        @else
            <h6 class="mb-2">Журнал операций</h6>
            <div class="table-responsive mb-4">
                <table class="table table-sm table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Дата</th>
                            <th>Содержание</th>
                            <th class="text-center">Дебет</th>
                            <th class="text-center">Кредит</th>
                            <th class="text-end">Сумма</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($journalRows as $jr)
                        <tr>
                            <td>{{ $jr->entry_date ? \Carbon\Carbon::parse($jr->entry_date)->format('d.m.Y') : '—' }}</td>
                            <td>{{ $jr->content }}</td>
                            <td class="text-center"><strong>{{ $jr->debit_code }}</strong></td>
                            <td class="text-center"><strong>{{ $jr->credit_code }}</strong></td>
                            <td class="text-end">{{ number_format($jr->amount, 2, ',', ' ') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <h6 class="mb-2">Детализация по дебету и кредиту</h6>
            <p class="small text-muted mb-2">Счёт можно изменить — выберите другой из плана счетов и нажмите «Изменить». Изменения сразу отражаются в ОСВ.</p>
            <div class="row mb-4">
                <div class="col-md-6">
                    <h6 class="text-success border-bottom border-success pb-2 mb-2">Дебет (ДТ) — операции по дебету</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Дата</th>
                                    <th>Счёт (активный выбор)</th>
                                    <th class="text-end">Сумма</th>
                                    <th class="small">Комментарий</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($debitEntries as $e)
                                <tr>
                                    <td>{{ $e->entry_date ? \Carbon\Carbon::parse($e->entry_date)->format('d.m.Y') : '—' }}</td>
                                    <td>
                                        <form method="post" action="{{ route('document-ledger-entries.update', $e) }}" class="d-inline-flex align-items-center gap-1 flex-wrap">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="redirect_to" value="{{ url()->current() }}#tab-ledger">
                                            <select name="account_code" class="form-select form-select-sm" style="min-width:160px" required>
                                                @foreach($accounts as $a)
                                                    <option value="{{ $a->code }}" {{ $e->account_id == $a->id ? 'selected' : '' }}>{{ $a->code }} {{ $a->name }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="btn btn-outline-secondary btn-sm">Изменить</button>
                                        </form>
                                    </td>
                                    <td class="text-end">{{ number_format($e->debit, 2, ',', ' ') }}</td>
                                    <td class="small">{{ Str::limit($e->comment, 40) }}</td>
                                    <td></td>
                                </tr>
                                @empty
                                <tr><td colspan="5" class="text-muted small">—</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="col-md-6">
                    <h6 class="text-danger border-bottom border-danger pb-2 mb-2">Кредит (КТ) — операции по кредиту</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Дата</th>
                                    <th>Счёт (активный выбор)</th>
                                    <th class="text-end">Сумма</th>
                                    <th class="small">Комментарий</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($creditEntries as $e)
                                <tr>
                                    <td>{{ $e->entry_date ? \Carbon\Carbon::parse($e->entry_date)->format('d.m.Y') : '—' }}</td>
                                    <td>
                                        <form method="post" action="{{ route('document-ledger-entries.update', $e) }}" class="d-inline-flex align-items-center gap-1 flex-wrap">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="redirect_to" value="{{ url()->current() }}#tab-ledger">
                                            <select name="account_code" class="form-select form-select-sm" style="min-width:160px" required>
                                                @foreach($accounts as $a)
                                                    <option value="{{ $a->code }}" {{ $e->account_id == $a->id ? 'selected' : '' }}>{{ $a->code }} {{ $a->name }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="btn btn-outline-secondary btn-sm">Изменить</button>
                                        </form>
                                    </td>
                                    <td class="text-end">{{ number_format($e->credit, 2, ',', ' ') }}</td>
                                    <td class="small">{{ Str::limit($e->comment, 40) }}</td>
                                    <td></td>
                                </tr>
                                @empty
                                <tr><td colspan="5" class="text-muted small">—</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <p class="small text-muted mb-0">Итого по дебету и кредиту совпадает (принцип двойной записи).</p>
        @endif

        <h6 class="mb-2">Настройка отражения в ОСВ</h6>
        <p class="small text-muted mb-3">Шаблоны проводок для типа документа «{{ $documentType }}». По ним можно сформировать проводки для этого документа (если ещё не сформированы).</p>
        <p class="mb-3">
            <a href="{{ route('document-ledger-templates.index', ['document_type' => $documentType]) }}" class="btn btn-sm btn-outline-primary">Настроить шаблоны проводок для этого типа</a>
        </p>
        <h6 class="mb-2 mt-4">Добавить проводку вручную</h6>
        <p class="small text-muted mb-2">Укажите счета дебета и кредита и сумму — проводка будет отражена в ОСВ по этому документу.</p>
        @if($errors->any())
            <div class="alert alert-danger small py-2 mb-2">{{ $errors->first() }}</div>
        @endif
        <form method="post" action="{{ route('document-ledger-entries.store') }}" class="row g-2 align-items-end mb-4">
            @csrf
            <input type="hidden" name="document_type" value="{{ $documentType }}">
            <input type="hidden" name="document_id" value="{{ $documentId }}">
            <input type="hidden" name="redirect_to" value="{{ url()->current() }}#tab-ledger">
            <div class="col-auto">
                <label class="form-label small mb-0">Дебет (счёт)</label>
                <select name="debit_account_code" class="form-select form-select-sm" style="min-width:180px" required>
                    <option value="">— выберите</option>
                    @foreach($accounts as $a)
                        <option value="{{ $a->code }}" {{ old('debit_account_code') == $a->code ? 'selected' : '' }}>{{ $a->code }} {{ $a->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small mb-0">Кредит (счёт)</label>
                <select name="credit_account_code" class="form-select form-select-sm" style="min-width:180px" required>
                    <option value="">— выберите</option>
                    @foreach($accounts as $a)
                        <option value="{{ $a->code }}" {{ old('credit_account_code') == $a->code ? 'selected' : '' }}>{{ $a->code }} {{ $a->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small mb-0">Сумма</label>
                <input type="number" name="amount" class="form-control form-control-sm" value="{{ old('amount') }}" step="0.01" min="0.01" required style="width:120px">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-0">Дата проводки</label>
                <input type="date" name="entry_date" class="form-control form-control-sm" value="{{ old('entry_date', date('Y-m-d')) }}" style="width:140px">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-0">Комментарий</label>
                <input type="text" name="comment" class="form-control form-control-sm" value="{{ old('comment') }}" placeholder="Необязательно" style="min-width:160px">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm">Добавить проводку</button>
            </div>
        </form>
        @if($templates->isEmpty())
            <p class="text-muted small">Шаблонов для этого типа документа нет. Добавьте их в разделе «План счетов» или в настройках.</p>
        @else
            <table class="table table-sm">
                <thead class="table-light">
                    <tr>
                        <th>Название</th>
                        <th>Дебет (счёт)</th>
                        <th>Кредит (счёт)</th>
                        <th>Сумма (поле)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($templates as $t)
                    <tr>
                        <td>{{ $t->name ?? '—' }}</td>
                        <td><code>{{ $t->debit_account_code }}</code> {{ \App\Models\Account::findByCode($t->debit_account_code)?->name }}</td>
                        <td><code>{{ $t->credit_account_code }}</code> {{ \App\Models\Account::findByCode($t->credit_account_code)?->name }}</td>
                        <td><code>{{ $t->amount_field ?? '—' }}</code></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
