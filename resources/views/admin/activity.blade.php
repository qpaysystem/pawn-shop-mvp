@extends('layouts.admin')
@section('title', 'Активность')
@section('content')
<h1 class="h4 mb-4">Журнал активности</h1>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Дата</th><th>Пользователь</th><th>Действие</th><th>Объект</th></tr></thead>
            <tbody>
                @forelse($logs as $log)
                <tr>
                    <td>{{ $log->created_at->format('d.m.Y H:i') }}</td>
                    <td>{{ $log->user?->name ?? '—' }}</td>
                    <td>{{ $log->action }}</td>
                    <td>{{ $log->model_type ? class_basename($log->model_type) . ' #' . $log->model_id : '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="4" class="text-muted">Нет записей</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $logs->links() }}</div>
@endsection
