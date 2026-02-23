{{-- Вкладка "Бухгалтерские проводки" для любого документа. Двойная запись: блоки ДТ (дебет) и КТ (кредит). Переменные: $documentType, $documentId, $ledgerEntries, $templates --}}
@php
    $debitEntries = $ledgerEntries->filter(fn ($e) => (float) $e->debit > 0);
    $creditEntries = $ledgerEntries->filter(fn ($e) => (float) $e->credit > 0);
@endphp
<div class="card mb-4">
    <div class="card-header">Проводки по документу (двойная запись, отражение в ОСВ)</div>
    <div class="card-body">
        @if($ledgerEntries->isEmpty())
            <p class="text-muted mb-3">По этому документу проводок пока нет.</p>
        @else
            <div class="row mb-4">
                <div class="col-md-6">
                    <h6 class="text-success border-bottom border-success pb-2 mb-2">Дебет (ДТ) — операции по дебету</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Дата</th>
                                    <th>Счёт</th>
                                    <th class="text-end">Сумма</th>
                                    <th class="small">Комментарий</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($debitEntries as $e)
                                <tr>
                                    <td>{{ $e->entry_date ? \Carbon\Carbon::parse($e->entry_date)->format('d.m.Y') : '—' }}</td>
                                    <td>{{ $e->account ? $e->account->code . ' ' . $e->account->name : '—' }}</td>
                                    <td class="text-end">{{ number_format($e->debit, 2, ',', ' ') }}</td>
                                    <td class="small">{{ Str::limit($e->comment, 40) }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="4" class="text-muted small">—</td></tr>
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
                                    <th>Счёт</th>
                                    <th class="text-end">Сумма</th>
                                    <th class="small">Комментарий</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($creditEntries as $e)
                                <tr>
                                    <td>{{ $e->entry_date ? \Carbon\Carbon::parse($e->entry_date)->format('d.m.Y') : '—' }}</td>
                                    <td>{{ $e->account ? $e->account->code . ' ' . $e->account->name : '—' }}</td>
                                    <td class="text-end">{{ number_format($e->credit, 2, ',', ' ') }}</td>
                                    <td class="small">{{ Str::limit($e->comment, 40) }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="4" class="text-muted small">—</td></tr>
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
