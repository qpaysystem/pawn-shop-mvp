@extends('layouts.app')

@section('title', 'Системные настройки')

@section('content')
<h1 class="h4 mb-4">Системные настройки</h1>
<p class="text-muted small mb-4">
    <a href="{{ route('section.settings') }}"><i class="bi bi-arrow-left"></i> Назад в раздел «Настройки»</a>
</p>

<form method="post" action="{{ route('settings.system.store') }}">
    @csrf
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Общие</h5>
            <div class="mb-3">
                <label class="form-label">Валюта для баланса</label>
                <input type="text" name="currency" class="form-control" value="{{ $settings['currency'] }}" maxlength="10" placeholder="RUB">
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
            <h5 class="card-title">Портал ИИ-агентов (agent-teams)</h5>
            <p class="text-muted small mb-3">
                Секрет для API портала (<code>agent-teams.home</code>): MTS и Telegram inbox.
                В портале: <strong>Настройки → Мессенджеры</strong>.
                API: <code>/api/internal/agent-teams/mts/calls</code>,
                <code>/api/internal/agent-teams/telegram/messages</code>
            </p>
            @if($settings['agent_teams_api_token_configured'] && !($settings['agent_teams_api_token_in_db'] ?? false))
                <p class="small text-warning mb-2">
                    Сейчас используется секрет из <code>.env</code> (<code>AGENT_TEAMS_API_TOKEN</code>).
                    Сохраните поле ниже, чтобы хранить значение в БД.
                </p>
            @endif
            <div class="mb-3">
                <label class="form-label">Секрет синхронизации</label>
                <input
                    type="password"
                    name="agent_teams_api_token"
                    class="form-control font-monospace"
                    autocomplete="new-password"
                    placeholder="{{ ($settings['agent_teams_api_token_in_db'] ?? false) ? '•••••••• (сохранён — введите новый, чтобы заменить)' : 'длинная случайная строка' }}"
                >
            </div>
            <div class="form-check mb-0">
                <input
                    type="checkbox"
                    name="clear_agent_teams_api_token"
                    value="1"
                    class="form-check-input"
                    id="clear_agent_teams_api_token"
                    {{ ($settings['agent_teams_api_token_in_db'] ?? false) ? '' : 'disabled' }}
                >
                <label class="form-check-label" for="clear_agent_teams_api_token">Удалить секрет из БД (останется только .env, если задан)</label>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Acuerdo (info.acuerdo.pro)</h5>
            <p class="text-muted small mb-3">
                Доступ к отчётам «Текущий актив» и «Текущие финансы» в разделе
                <strong>Управление → Отчёты</strong>. Те же учётные данные, что в agent-teams-portal.
            </p>
            @if($settings['acuerdo_username_configured'] && !($settings['acuerdo_username_in_db'] ?? false))
                <p class="small text-warning mb-2">
                    Сейчас используются учётные данные из <code>.env</code>
                    (<code>ACUERDO_USERNAME</code>, <code>ACUERDO_PASSWORD</code>).
                </p>
            @endif
            <div class="mb-3">
                <label class="form-label">Логин</label>
                <input
                    type="text"
                    name="acuerdo_username"
                    class="form-control"
                    autocomplete="off"
                    placeholder="{{ ($settings['acuerdo_username_in_db'] ?? false) ? 'сохранён — введите новый, чтобы заменить' : 'логин info.acuerdo.pro' }}"
                >
            </div>
            <div class="mb-3">
                <label class="form-label">Пароль</label>
                <input
                    type="password"
                    name="acuerdo_password"
                    class="form-control"
                    autocomplete="new-password"
                    placeholder="{{ ($settings['acuerdo_password_in_db'] ?? false) ? '•••••••• (сохранён — введите новый, чтобы заменить)' : 'пароль info.acuerdo.pro' }}"
                >
            </div>
            <div class="form-check mb-0">
                <input
                    type="checkbox"
                    name="clear_acuerdo_credentials"
                    value="1"
                    class="form-check-input"
                    id="clear_acuerdo_credentials"
                    {{ (($settings['acuerdo_username_in_db'] ?? false) || ($settings['acuerdo_password_in_db'] ?? false)) ? '' : 'disabled' }}
                >
                <label class="form-check-label" for="clear_acuerdo_credentials">Удалить логин и пароль из БД (останется только .env, если задан)</label>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Telegram inbox (ломбард → портал ИИ)</h5>
            <p class="text-muted small">
                Входящие сообщения сохраняются в ломбарде и передаются в agent-teams-portal.
                Webhook: <code class="user-select-all">{{ url('/telegram/webhook') }}</code>
                · API: <code>/api/internal/agent-teams/telegram/messages</code>
            </p>
            <div class="mb-3">
                <label class="form-label">Токен бота</label>
                <input type="text" name="telegram_bot_token" class="form-control" value="{{ $settings['telegram_bot_token'] ?? '' }}" placeholder="123456789:ABC…">
            </div>
            <div class="mb-3">
                <label class="form-label">Username бота (без @)</label>
                <input type="text" name="telegram_bot_username" class="form-control" value="{{ $settings['telegram_bot_username'] ?: 'NskCapital_bot' }}" placeholder="NskCapital_bot">
            </div>
            <div class="mb-3">
                <label class="form-label">Chat ID группы (уведомления / общий)</label>
                <input type="text" name="telegram_chat_id" class="form-control" value="{{ $settings['telegram_chat_id'] ?? '' }}" placeholder="-1001234567890">
            </div>
            <div class="mb-3">
                <label class="form-label">Chat ID inbox (опционально, отдельная группа)</label>
                <input type="text" name="telegram_inbox_chat_id" class="form-control" value="{{ $settings['telegram_inbox_chat_id'] ?? '' }}" placeholder="-100…">
            </div>
            <div class="mb-3">
                <label class="form-label">Секрет webhook</label>
                <input type="password" name="telegram_webhook_secret" class="form-control" autocomplete="new-password" placeholder="{{ ($settings['telegram_webhook_secret_set'] ?? false) ? 'сохранён — оставьте пустым' : 'случайная строка' }}">
                @if($settings['telegram_webhook_secret_set'] ?? false)
                    <div class="form-check mt-2">
                        <input type="checkbox" name="telegram_webhook_secret_clear" value="1" class="form-check-input" id="telegram_webhook_secret_clear">
                        <label class="form-check-label" for="telegram_webhook_secret_clear">Сбросить секрет</label>
                    </div>
                @endif
            </div>
            <div class="form-check">
                <input type="checkbox" name="telegram_private_inbox_enabled" value="1" class="form-check-input" id="telegram_private_inbox_enabled" {{ ($settings['telegram_private_inbox_enabled'] ?? '1') == '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="telegram_private_inbox_enabled">Принимать личные сообщения клиентов боту</label>
            </div>
            <p class="text-muted small mt-2 mb-0">Без webhook: <code>php artisan telegram:poll --delete-webhook</code></p>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Avito Messenger (колл-центр)</h5>
            <p class="text-muted small">
                Ключи из <a href="https://developers.avito.ru/" target="_blank" rel="noopener">кабинета разработчика Avito</a>.
                Для каждого филиала — numeric <strong>user_id</strong> (Profile ID аккаунта Avito).
                Нужна подписка «API мессенджера» для чтения и отправки сообщений.
            </p>
            <div class="mb-3">
                <label class="form-label">Client ID</label>
                <input type="text" name="avito_client_id" class="form-control" value="{{ $settings['avito_client_id'] ?? '' }}">
            </div>
            <div class="mb-3">
                <label class="form-label">Client Secret</label>
                <input type="password" name="avito_client_secret" class="form-control" autocomplete="new-password"
                       placeholder="{{ ($settings['avito_client_secret_set'] ?? false) ? 'сохранён — оставьте пустым' : '' }}">
                @if($settings['avito_client_secret_set'] ?? false)
                    <div class="form-check mt-2">
                        <input type="checkbox" name="avito_client_secret_clear" value="1" class="form-check-input" id="avito_client_secret_clear">
                        <label class="form-check-label" for="avito_client_secret_clear">Сбросить secret</label>
                    </div>
                @endif
            </div>
            <div class="row g-2">
                @foreach($settings['avito_branches'] ?? [] as $slug => $branch)
                    <div class="col-md-6">
                        <label class="form-label small">{{ $branch['label'] }} — user_id</label>
                        <input type="text" name="avito_user_id_{{ $slug }}" class="form-control form-control-sm"
                               value="{{ $branch['user_id'] ?? '' }}" placeholder="12345678">
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">Сохранить настройки</button>
</form>
@endsection
