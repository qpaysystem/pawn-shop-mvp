<form method="get" class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-auto">
                <label class="form-label">Дата поступления с</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom ?? '' }}">
            </div>
            <div class="col-auto">
                <label class="form-label">по</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo ?? '' }}">
            </div>
            @if($stores->count() > 1)
                <div class="col-auto">
                    <label class="form-label">Точка</label>
                    <select name="store_id" class="form-select form-select-sm" style="width:auto; max-width:220px">
                        <option value="">Все точки</option>
                        @foreach($stores as $s)
                            <option value="{{ $s->id }}" {{ ($storeId ?? null) == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-auto">
                <label class="form-label">Операция</label>
                <select name="stock_kind" class="form-select form-select-sm" style="width:auto; max-width:180px">
                    @foreach($stockKindOptions as $value => $label)
                        <option value="{{ $value }}" {{ ($stockKind ?? 'all') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label">Вид товара</label>
                <select name="item_kind" class="form-select form-select-sm" style="width:auto; max-width:200px">
                    @foreach($itemKindOptions as $value => $label)
                        <option value="{{ $value }}" {{ ($itemKind ?? 'all') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @if(($categories ?? collect())->isNotEmpty())
                <div class="col-auto">
                    <label class="form-label">Категория</label>
                    <select name="category_id" class="form-select form-select-sm" style="width:auto; max-width:200px">
                        <option value="">Все категории</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ ($categoryId ?? null) == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-auto">
                <label class="form-label">Статус</label>
                <select name="stock_status" class="form-select form-select-sm" style="width:auto; max-width:200px">
                    @foreach($stockStatusOptions as $value => $label)
                        <option value="{{ $value }}" {{ ($stockStatus ?? 'in_stock') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm">Показать</button>
                <a href="{{ route('management.reports.lombard.inventory') }}" class="btn btn-outline-secondary btn-sm">Сбросить</a>
            </div>
        </div>
        <p class="text-muted small mb-0 mt-2">По умолчанию — позиции <strong>в остатке</strong> (активные залоги и нереализованная скупка). Даты фильтруют день поступления; если даты пустые — без ограничения по периоду.</p>
    </div>
</form>
