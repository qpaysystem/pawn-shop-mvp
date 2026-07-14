@php
    $showDates = $showDates ?? true;
@endphp
<form method="get" class="row g-3 mb-4">
    @if($showDates)
        <div class="col-auto">
            <label class="form-label">Дата с</label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom ?? '' }}">
        </div>
        <div class="col-auto">
            <label class="form-label">Дата по</label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo ?? '' }}">
        </div>
    @endif
    @if($stores->count() > 1)
        <div class="col-auto">
            <label class="form-label">Точка</label>
            <select name="store_id" class="form-select form-select-sm" style="width:auto; max-width:240px">
                <option value="">Все точки</option>
                @foreach($stores as $s)
                    <option value="{{ $s->id }}" {{ ($storeId ?? null) == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <div class="col-auto align-self-end">
        <button type="submit" class="btn btn-primary btn-sm">Показать</button>
    </div>
</form>
