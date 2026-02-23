<div class="stage-modal-content">
    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#stage-tab-main" type="button">Основное</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#stage-tab-photos" type="button">Фотоотчеты ({{ $stage->photos->count() }})</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#stage-tab-works" type="button">Виды работ ({{ $stage->works->count() }})</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#stage-tab-comments" type="button">Обсуждения ({{ $stage->comments->count() }})</button>
        </li>
    </ul>
    <div class="tab-content">
        <div class="tab-pane fade show active" id="stage-tab-main">
            <dl class="row mb-0">
                <dt class="col-sm-4 text-muted">Ответственный</dt>
                <dd class="col-sm-8">{{ $stage->client ? $stage->client->full_name : '—' }}</dd>
                <dt class="col-sm-4 text-muted">План: начало / окончание</dt>
                <dd class="col-sm-8">{{ $stage->planned_start_date ? $stage->planned_start_date->format('d.m.Y') : '—' }} / {{ $stage->planned_end_date ? $stage->planned_end_date->format('d.m.Y') : '—' }}</dd>
                <dt class="col-sm-4 text-muted">Факт: начало / окончание</dt>
                <dd class="col-sm-8">{{ $stage->actual_start_date ? $stage->actual_start_date->format('d.m.Y') : '—' }} / {{ $stage->actual_end_date ? $stage->actual_end_date->format('d.m.Y') : '—' }}</dd>
                <dt class="col-sm-4 text-muted">Статус</dt>
                <dd class="col-sm-8"><span class="badge bg-{{ $stage->status === 'completed' ? 'success' : ($stage->status === 'in_progress' ? 'primary' : 'secondary') }}" id="stage-modal-status-badge">{{ $stage->status_label }}</span></dd>
                @if($stage->contractor)
                <dt class="col-sm-4 text-muted">Подрядчик</dt>
                <dd class="col-sm-8">{{ e($stage->contractor) }}</dd>
                @endif
                @if($stage->budget !== null)
                <dt class="col-sm-4 text-muted">Бюджет</dt>
                <dd class="col-sm-8">{{ number_format($stage->budget, 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}</dd>
                @endif
            </dl>
            <div class="mt-3">
                <label class="form-label small text-muted">Сменить статус</label>
                <select class="form-select form-select-sm" id="stage-modal-status-select" style="max-width: 200px;">
                    @foreach(\App\Models\ConstructionStage::statusLabels() as $key => $label)
                        <option value="{{ $key }}" @selected($stage->status === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="stage-modal-status-btn">Применить</button>
            </div>
        </div>
        <div class="tab-pane fade" id="stage-tab-photos">
            <form id="stage-photo-upload-form" class="mb-3">
                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="form-control form-control-sm mb-2" id="stage-photo-input" required>
                <button type="submit" class="btn btn-sm btn-primary">Загрузить фото</button>
            </form>
            <div class="row g-2" id="stage-photos-gallery">
                @foreach($stage->photos as $photo)
                <div class="col-6 col-md-4">
                    <a href="{{ $photo->url }}" target="_blank" rel="noopener" class="d-block rounded overflow-hidden border">
                        <img src="{{ $photo->url }}" alt="{{ $photo->caption }}" class="img-fluid w-100" style="height: 120px; object-fit: cover;">
                    </a>
                </div>
                @endforeach
            </div>
            @if($stage->photos->isEmpty())
            <p class="text-muted small mb-0" id="stage-photos-empty">Нет загруженных фото.</p>
            @endif
        </div>
        <div class="tab-pane fade" id="stage-tab-works">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 24px;"></th>
                            <th>Дата начала</th>
                            <th>Материалы</th>
                            <th class="text-end">Сумма мат.</th>
                            <th>Работы</th>
                            <th class="text-end">Сумма работ</th>
                            <th>Подрядчик</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($stage->works as $work)
                        <tr class="cabinet-work-row" data-work-id="{{ $work->id }}" style="cursor: pointer;">
                            <td><i class="bi bi-chevron-right cabinet-work-toggle"></i></td>
                            <td>{{ $work->work_start_date ? $work->work_start_date->format('d.m.Y') : '—' }}</td>
                            <td>{{ Str::limit($work->materials_name, 25) ?: '—' }}</td>
                            <td class="text-end">{{ $work->materials_cost !== null ? number_format($work->materials_cost, 2) : '—' }}</td>
                            <td>{{ Str::limit($work->works_name, 25) ?: '—' }}</td>
                            <td class="text-end">{{ $work->works_cost !== null ? number_format($work->works_cost, 2) : '—' }}</td>
                            <td>{{ Str::limit($work->contractor, 15) ?: '—' }}</td>
                        </tr>
                        <tr class="cabinet-work-detail d-none" id="cabinet-work-detail-{{ $work->id }}">
                            <td colspan="7" class="bg-light small py-2 px-3">
                                <div class="row">
                                    <div class="col-md-6"><strong>Материалы:</strong> {{ $work->materials_name ?: '—' }} — {{ $work->materials_cost !== null ? number_format($work->materials_cost, 2) . ' ' . \App\Models\Setting::get('currency', 'RUB') : '—' }}</div>
                                    <div class="col-md-6"><strong>Работы:</strong> {{ $work->works_name ?: '—' }} — {{ $work->works_cost !== null ? number_format($work->works_cost, 2) . ' ' . \App\Models\Setting::get('currency', 'RUB') : '—' }}</div>
                                    @if($work->contractor)<div class="col-12 mt-1"><strong>Подрядчик:</strong> {{ e($work->contractor) }}</div>@endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="text-muted text-center py-3">Нет видов работ.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="small text-muted mt-2 mb-0">Нажмите на строку, чтобы раскрыть детали по материалам и работам.</p>
        </div>
        <div class="tab-pane fade" id="stage-tab-comments">
            <form id="stage-comment-form" class="mb-3">
                @csrf
                <textarea name="body" class="form-control form-control-sm mb-2" rows="2" placeholder="Ваш комментарий или замечание..." required maxlength="5000"></textarea>
                <button type="submit" class="btn btn-sm btn-primary">Отправить</button>
            </form>
            <div class="stage-comments-list" id="stage-comments-list">
                @forelse($stage->comments as $comment)
                <div class="border-bottom pb-2 mb-2 small">
                    <strong>{{ $comment->client ? $comment->client->full_name : 'Гость' }}</strong>
                    <span class="text-muted ms-1">{{ $comment->created_at->format('d.m.Y H:i') }}</span>
                    <div class="mt-1">{{ nl2br(e($comment->body)) }}</div>
                </div>
                @empty
                <p class="text-muted small mb-0" id="stage-comments-empty">Пока нет обсуждений.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
