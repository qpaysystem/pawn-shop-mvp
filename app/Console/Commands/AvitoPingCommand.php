<?php

namespace App\Console\Commands;

use App\Services\Avito\AvitoApiService;
use App\Services\Avito\AvitoBranchConfig;
use App\Services\CallCenterAvitoService;
use Illuminate\Console\Command;

/** Проверка подключения к Avito Messenger API. */
class AvitoPingCommand extends Command
{
    protected $signature = 'avito:ping {--branch= : slug филиала (по умолчанию — первый настроенный)}';

    protected $description = 'Проверить OAuth, аккаунт и список чатов Avito';

    public function handle(AvitoApiService $api, CallCenterAvitoService $callCenter): int
    {
        if (! AvitoBranchConfig::isConfigured()) {
            $this->error('Avito не настроен. Запустите: php artisan avito:configure --client-id=... --client-secret=...');

            return self::FAILURE;
        }

        $self = $api->getSelfAccount();
        if ($self['ok'] ?? false) {
            $this->info('Аккаунт: '.($self['name'] ?? '?').' (id '.($self['id'] ?? '?').')');
        } else {
            $this->warn('accounts/self: '.($self['error'] ?? 'ошибка'));
        }

        $branchSlug = trim((string) $this->option('branch'));
        if ($branchSlug === '') {
            $branchSlug = $callCenter->defaultBranchSlug();
        }

        $branch = AvitoBranchConfig::branch($branchSlug);
        if ($branch === null || empty($branch['user_id'])) {
            $this->error("Филиал «{$branchSlug}» не настроен (нет user_id).");

            return self::FAILURE;
        }

        $this->line("Филиал: {$branch['label']} → user_id {$branch['user_id']}");

        $chats = $api->listChats((string) $branch['user_id'], 5, 0);
        if (! ($chats['ok'] ?? false)) {
            $this->error('Чаты: '.($chats['error'] ?? 'ошибка'));

            return self::FAILURE;
        }

        $items = $chats['chats'] ?? [];
        $this->info('Чаты: '.count($items).' (первые 5)');

        foreach ($items as $chat) {
            $ctx = is_array($chat['context']['value'] ?? null) ? $chat['context']['value'] : [];
            $title = (string) ($ctx['title'] ?? 'без объявления');
            $this->line('  • '.mb_substr($title, 0, 60));
        }

        return self::SUCCESS;
    }
}
