@extends('cabinet.layout')
@section('title', 'Квартира № ' . $apartment->apartment_number)
@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="mb-3">
    <a href="{{ route('cabinet.projects.show', $project) }}" class="btn btn-sm btn-outline-secondary">← К проекту «{{ $project->name }}»</a>
</div>

<div class="row">
    <div class="col-lg-4 mb-4">
        {{-- Картинка планировки --}}
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="card-title">Планировка</h6>
                @if($apartment->layout_photo_url)
                    <img src="{{ $apartment->layout_photo_url }}" class="img-fluid rounded" alt="Планировка">
                    <form method="post" action="{{ route('cabinet.projects.apartments.layout-photo', [$project, $apartment]) }}" enctype="multipart/form-data" class="mt-2">
                        @csrf
                        <input type="file" name="layout_photo" accept="image/jpeg,image/png,image/webp" class="form-control form-control-sm">
                        <button type="submit" class="btn btn-sm btn-outline-primary mt-1">Заменить</button>
                    </form>
                @else
                    <form method="post" action="{{ route('cabinet.projects.apartments.layout-photo', [$project, $apartment]) }}" enctype="multipart/form-data">
                        @csrf
                        <input type="file" name="layout_photo" accept="image/jpeg,image/png,image/webp" class="form-control" required>
                        <button type="submit" class="btn btn-primary btn-sm mt-2">Загрузить планировку</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h5 class="card-title mb-0">Квартира № {{ $apartment->apartment_number }}</h5>
                    <span class="badge bg-{{ $apartment->status === 'sold' ? 'secondary' : ($apartment->status === 'in_pledge' ? 'warning text-dark' : 'success') }} fs-6">{{ $apartment->status_label }}</span>
                </div>
                <table class="table table-borderless mb-0">
                    <tr>
                        <th style="width: 180px;" class="text-muted">Номер квартиры</th>
                        <td>{{ $apartment->apartment_number }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Подъезд</th>
                        <td>{{ $apartment->entrance ? e($apartment->entrance) : '—' }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Этаж</th>
                        <td>{{ $apartment->floor !== null ? $apartment->floor : '—' }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Жилая площадь</th>
                        <td>{{ $apartment->living_area !== null ? $apartment->living_area . ' м²' : '—' }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Количество комнат</th>
                        <td>{{ $apartment->rooms_count ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Номер ДДУ</th>
                        <td>{{ $apartment->ddu_contract_number ? e($apartment->ddu_contract_number) : '—' }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Стоимость</th>
                        <td>{{ $apartment->price !== null ? number_format($apartment->price, 2) . ' ' . \App\Models\Setting::get('currency', 'RUB') : '—' }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Стоимость м²</th>
                        <td>{{ $apartment->price_per_sqm !== null ? number_format($apartment->price_per_sqm, 2) . ' ' . \App\Models\Setting::get('currency', 'RUB') : '—' }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Ответственный</th>
                        <td>{{ $apartment->client ? $apartment->client->full_name : '—' }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Статус</th>
                        <td>{{ $apartment->status_label }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Данные собственника</th>
                        <td>{{ $apartment->owner_data ? nl2br(e($apartment->owner_data)) : '—' }}</td>
                    </tr>
                </table>
                <hr>
                <a href="{{ route('cabinet.projects.apartments.edit', [$project, $apartment]) }}" class="btn btn-outline-primary btn-sm">Изменить</a>
                <form method="post" action="{{ route('cabinet.projects.apartments.destroy', [$project, $apartment]) }}" class="d-inline" onsubmit="return confirm('Удалить карточку квартиры?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm">Удалить</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
