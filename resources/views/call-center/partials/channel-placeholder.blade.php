@php
    $channel = $channel ?? 'other';
@endphp

<div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
    <a href="{{ route('call-center.create') }}" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Зафиксировать обращение</a>
    <a href="{{ route('call-center.analytics') }}" class="btn btn-outline-primary"><i class="bi bi-bar-chart"></i> Аналитика</a>
</div>

<div class="alert alert-secondary">
    <strong>{{ $title }}</strong><br>
    <span class="text-muted">{{ $hint }}</span>
</div>

@if($contacts && $contacts->isNotEmpty())
<table class="table table-hover">
    <thead>
        <tr>
            <th>Дата</th>
            <th>Абонент</th>
            <th>Контакт</th>
            <th>Исход</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @foreach($contacts as $c)
        <tr>
            <td>{{ $c->contact_date ? \Carbon\Carbon::parse($c->contact_date)->format('d.m.Y H:i') : '—' }}</td>
            <td>
                @if($c->client_id)
                    <a href="{{ route('clients.show', $c->client) }}">{{ $c->client->full_name }}</a>
                @else
                    {{ $c->contact_name ?: '—' }}
                @endif
            </td>
            <td>{{ $c->contact_phone ?: '—' }}</td>
            <td>{{ $c->outcome_label }}</td>
            <td><a href="{{ route('call-center.show', $c) }}" class="btn btn-sm btn-outline-secondary">Подробнее</a></td>
        </tr>
        @endforeach
    </tbody>
</table>
{{ $contacts->links() }}
@else
    <p class="text-muted">Нет обращений в этом канале.</p>
@endif
