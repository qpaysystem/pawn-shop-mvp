@extends('layouts.admin')
@section('title', 'Настройки')
@section('content')
<h1 class="h4 mb-4">Системные настройки</h1>
<form method="post" action="{{ route('admin.settings.store') }}">
    @csrf
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Общие</h5>
            <div class="mb-3">
                <label class="form-label">Валюта для баланса</label>
                <input type="text" name="currency" class="form-control" value="{{ $settings['currency'] }}" maxlength="10" placeholder="RUB, USD, EUR">
            </div>
            <div class="mb-3">
                <label class="form-label">Максимальный размер загружаемого файла (МБ)</label>
                <input type="number" name="max_upload_mb" class="form-control" value="{{ $settings['max_upload_mb'] }}" min="1" max="50">
            </div>
            <div class="form-check mb-3">
                <input type="checkbox" name="mail_notifications" value="1" class="form-check-input" id="mail_notifications" {{ ($settings['mail_notifications'] ?? '0') == '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="mail_notifications">Включить почтовые уведомления</label>
            </div>
        </div>
    </div>
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Telegram-бот</h5>
            <p class="text-muted small">Уведомления о проведении транзакций. Создайте бота через <a href="https://t.me/BotFather" target="_blank">@BotFather</a>, получите токен и chat_id.</p>
            <div class="mb-3">
                <label class="form-label">Токен бота</label>
                <input type="text" name="telegram_bot_token" class="form-control" value="{{ $settings['telegram_bot_token'] ?? '' }}" placeholder="123456789:ABCdefGHIjklMNOpqrsTUVwxyz">
            </div>
            <div class="mb-3">
                <label class="form-label">Username бота (без @)</label>
                <input type="text" name="telegram_bot_username" class="form-control" value="{{ $settings['telegram_bot_username'] ?: 'NskCapital_bot' }}" placeholder="NskCapital_bot">
                <small class="text-muted">Нужен для входа клиентов в личный кабинет</small>
            </div>
            <div class="mb-3">
                <label class="form-label">Chat ID</label>
                <input type="text" name="telegram_chat_id" class="form-control" value="{{ $settings['telegram_chat_id'] ?? '' }}" placeholder="123456789 или -1001234567890">
            </div>
            <div class="form-check mb-2">
                <input type="checkbox" name="telegram_notify_transactions" value="1" class="form-check-input" id="telegram_notify" {{ ($settings['telegram_notify_transactions'] ?? '0') == '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="telegram_notify">Отправлять уведомления о транзакциях</label>
            </div>
            <div class="form-check mb-2">
                <input type="checkbox" name="telegram_notify_tasks" value="1" class="form-check-input" id="telegram_notify_tasks" {{ ($settings['telegram_notify_tasks'] ?? '0') == '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="telegram_notify_tasks">Уведомления об изменениях в задачах (создание, изменение, удаление)</label>
            </div>
            <div class="form-check">
                <input type="checkbox" name="telegram_notify_stages" value="1" class="form-check-input" id="telegram_notify_stages" {{ ($settings['telegram_notify_stages'] ?? '0') == '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="telegram_notify_stages">Уведомления об изменениях в этапах строительства (создание, изменение, удаление)</label>
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-primary">Сохранить настройки</button>
</form>
@endsection
