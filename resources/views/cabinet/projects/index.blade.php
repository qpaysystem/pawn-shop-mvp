@extends('cabinet.layout')
@section('title', 'Проекты')
@section('content')
<h1 class="h4 mb-4">Проекты</h1>
<p class="text-muted small mb-3">Выберите проект, чтобы увидеть сводку по расходам, транзакции и данные по клиентам.</p>

<div class="row g-3">
    @forelse($projects as $project)
    <div class="col-md-6 col-lg-4">
        <a href="{{ route('cabinet.projects.show', $project) }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title text-dark mb-1">{{ $project->name }}</h5>
                    @if($project->description)
                        <p class="text-muted small mb-2">{{ Str::limit($project->description, 80) }}</p>
                    @endif
                    <span class="badge bg-secondary">Операций: {{ $project->balance_transactions_count }}</span>
                </div>
            </div>
        </a>
    </div>
    @empty
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center text-muted py-4">Нет проектов с операциями.</div>
        </div>
    </div>
    @endforelse
</div>
@endsection
