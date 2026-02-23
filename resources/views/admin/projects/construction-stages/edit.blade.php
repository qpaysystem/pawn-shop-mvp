@extends('layouts.admin')
@section('title', 'Изменить этап строительства')
@section('content')
<div class="mb-3">
    <a href="{{ route('admin.projects.show', $project) }}#admin-stages" class="btn btn-sm btn-outline-secondary">← К проекту</a>
</div>
<h1 class="h4 mb-4">Изменить этап: {{ $constructionStage->name }}</h1>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<form method="post" action="{{ route('admin.projects.construction-stages.update', [$project, $constructionStage]) }}">
    @csrf
    @method('PUT')
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Название этапа *</label>
            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $constructionStage->name) }}" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Ответственный</label>
            <select name="client_id" class="form-select @error('client_id') is-invalid @enderror">
                <option value="">— не выбран —</option>
                @foreach($clients as $c)
                    <option value="{{ $c->id }}" @selected(old('client_id', $constructionStage->client_id) == $c->id)>{{ $c->full_name }}</option>
                @endforeach
            </select>
            @error('client_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Бюджет</label>
            <input type="number" name="budget" step="0.01" min="0" class="form-control @error('budget') is-invalid @enderror" value="{{ old('budget', $constructionStage->budget) }}">
            @error('budget')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Подрядчик</label>
            <input type="text" name="contractor" class="form-control @error('contractor') is-invalid @enderror" value="{{ old('contractor', $constructionStage->contractor) }}">
            @error('contractor')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Статус *</label>
            <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                @foreach(\App\Models\ConstructionStage::statusLabels() as $key => $label)
                    <option value="{{ $key }}" @selected(old('status', $constructionStage->status) === $key)>{{ $label }}</option>
                @endforeach
            </select>
            @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
            <label class="form-label">План: начало</label>
            <input type="date" name="planned_start_date" class="form-control @error('planned_start_date') is-invalid @enderror" value="{{ old('planned_start_date', $constructionStage->planned_start_date?->format('Y-m-d')) }}">
            @error('planned_start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
            <label class="form-label">План: окончание</label>
            <input type="date" name="planned_end_date" class="form-control @error('planned_end_date') is-invalid @enderror" value="{{ old('planned_end_date', $constructionStage->planned_end_date?->format('Y-m-d')) }}">
            @error('planned_end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
            <label class="form-label">Факт: начало</label>
            <input type="date" name="actual_start_date" class="form-control @error('actual_start_date') is-invalid @enderror" value="{{ old('actual_start_date', $constructionStage->actual_start_date?->format('Y-m-d')) }}">
            @error('actual_start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
            <label class="form-label">Факт: окончание</label>
            <input type="date" name="actual_end_date" class="form-control @error('actual_end_date') is-invalid @enderror" value="{{ old('actual_end_date', $constructionStage->actual_end_date?->format('Y-m-d')) }}">
            @error('actual_end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary">Сохранить</button>
            <a href="{{ route('admin.projects.show', $project) }}#admin-stages" class="btn btn-outline-secondary">Отмена</a>
        </div>
    </div>
</form>

<hr class="my-4">
<h5 class="mb-3">Виды работ</h5>

@if(session('success_work'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success_work') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="card mb-4">
    <div class="card-body">
        <form method="post" action="{{ route('admin.projects.construction-stages.works.store', [$project, $constructionStage]) }}" class="row g-3 align-items-end mb-4">
            @csrf
            <div class="col-md-2">
                <label class="form-label small mb-0">Дата начала работ</label>
                <input type="date" name="work_start_date" class="form-control form-control-sm" value="{{ old('work_start_date') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0">Материалы (наименование)</label>
                <input type="text" name="materials_name" class="form-control form-control-sm" value="{{ old('materials_name') }}" placeholder="—">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0">Стоимость материалов</label>
                <input type="number" name="materials_cost" step="0.01" min="0" class="form-control form-control-sm" value="{{ old('materials_cost') }}" placeholder="0">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0">Работы (наименование)</label>
                <input type="text" name="works_name" class="form-control form-control-sm" value="{{ old('works_name') }}" placeholder="—">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0">Стоимость работ</label>
                <input type="number" name="works_cost" step="0.01" min="0" class="form-control form-control-sm" value="{{ old('works_cost') }}" placeholder="0">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0">Подрядчик</label>
                <input type="text" name="contractor" class="form-control form-control-sm" value="{{ old('contractor') }}" placeholder="—">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-sm btn-primary">Добавить вид работ</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover mb-0 table-sm">
                <thead class="table-light">
                    <tr>
                        <th style="width: 32px;"></th>
                        <th>Дата начала</th>
                        <th>Материалы</th>
                        <th class="text-end">Сумма мат.</th>
                        <th>Работы</th>
                        <th class="text-end">Сумма работ</th>
                        <th>Подрядчик</th>
                        <th style="width: 80px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($constructionStage->works as $work)
                    <tr class="stage-work-row" data-work-id="{{ $work->id }}" style="cursor: pointer;">
                        <td><i class="bi bi-chevron-right stage-work-toggle"></i></td>
                        <td>{{ $work->work_start_date ? $work->work_start_date->format('d.m.Y') : '—' }}</td>
                        <td>{{ Str::limit($work->materials_name, 30) ?: '—' }}</td>
                        <td class="text-end">{{ $work->materials_cost !== null ? number_format($work->materials_cost, 2) : '—' }}</td>
                        <td>{{ Str::limit($work->works_name, 30) ?: '—' }}</td>
                        <td class="text-end">{{ $work->works_cost !== null ? number_format($work->works_cost, 2) : '—' }}</td>
                        <td>{{ Str::limit($work->contractor, 20) ?: '—' }}</td>
                        <td>
                            <form method="post" action="{{ route('admin.projects.construction-stages.works.destroy', [$project, $constructionStage, $work]) }}" class="d-inline" onsubmit="return confirm('Удалить вид работ?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="event.stopPropagation();">Удалить</button>
                            </form>
                        </td>
                    </tr>
                    <tr class="stage-work-detail d-none" id="work-detail-{{ $work->id }}">
                        <td colspan="8" class="bg-light small py-3">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Материалы:</strong>
                                    <div>{{ $work->materials_name ?: '—' }}</div>
                                    <div>Стоимость: {{ $work->materials_cost !== null ? number_format($work->materials_cost, 2) . ' ' . \App\Models\Setting::get('currency', 'RUB') : '—' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <strong>Работы:</strong>
                                    <div>{{ $work->works_name ?: '—' }}</div>
                                    <div>Стоимость: {{ $work->works_cost !== null ? number_format($work->works_cost, 2) . ' ' . \App\Models\Setting::get('currency', 'RUB') : '—' }}</div>
                                </div>
                                @if($work->contractor)
                                <div class="col-12 mt-2"><strong>Подрядчик:</strong> {{ $work->contractor }}</div>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-muted text-center py-3">Нет видов работ. Добавьте запись выше.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.stage-work-row').forEach(function(row) {
    row.addEventListener('click', function(e) {
        if (e.target.closest('form')) return;
        var id = row.dataset.workId;
        var detail = document.getElementById('work-detail-' + id);
        var icon = row.querySelector('.stage-work-toggle');
        if (detail && detail.classList.contains('d-none')) {
            detail.classList.remove('d-none');
            if (icon) icon.classList.replace('bi-chevron-right', 'bi-chevron-down');
        } else if (detail) {
            detail.classList.add('d-none');
            if (icon) icon.classList.replace('bi-chevron-down', 'bi-chevron-right');
        }
    });
});
</script>
@endsection
