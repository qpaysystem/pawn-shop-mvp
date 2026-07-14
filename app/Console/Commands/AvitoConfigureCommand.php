<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\Avito\AvitoApiService;
use App\Services\Avito\AvitoBranchConfig;
use Illuminate\Console\Command;

/** Сохранить ключи Avito и привязать user_id филиала из /core/v1/accounts/self. */
class AvitoConfigureCommand extends Command
{
    protected $signature = 'avito:configure
        {--client-id= : Client ID из кабинета Avito API}
        {--client-secret= : Client Secret}
        {--branch=kolhidskaya : slug филиала из config/avito.php}
        {--user-id= : Явный user_id (если не брать из API)}';

    protected $description = 'Настроить Avito API: client_id/secret и user_id филиала';

    public function handle(AvitoApiService $api): int
    {
        $clientId = trim((string) ($this->option('client-id') ?: env('AVITO_CLIENT_ID', '')));
        $clientSecret = trim((string) ($this->option('client-secret') ?: env('AVITO_CLIENT_SECRET', '')));
        $branchSlug = trim((string) $this->option('branch'));

        if ($clientId === '' || $clientSecret === '') {
            $this->error('Укажите --client-id и --client-secret или переменные AVITO_CLIENT_ID / AVITO_CLIENT_SECRET в .env');

            return self::FAILURE;
        }

        $branch = AvitoBranchConfig::branch($branchSlug);
        if ($branch === null) {
            $this->error("Неизвестный филиал «{$branchSlug}». См. config/avito.php branch_defaults.");

            return self::FAILURE;
        }

        Setting::set('avito_client_id', $clientId);
        Setting::set('avito_client_secret', $clientSecret);
        $api->clearTokenCache();

        $userId = trim((string) $this->option('user-id'));
        $accountName = '';

        if ($userId === '') {
            $self = $api->getSelfAccount();
            if (! ($self['ok'] ?? false)) {
                $this->error('Не удалось получить аккаунт: '.($self['error'] ?? 'unknown'));

                return self::FAILURE;
            }
            $userId = (string) ($self['id'] ?? '');
            $accountName = (string) ($self['name'] ?? '');
        }

        if ($userId === '') {
            $this->error('user_id пустой. Передайте --user-id вручную.');

            return self::FAILURE;
        }

        $branches = [];
        foreach (AvitoBranchConfig::branches() as $slug => $meta) {
            $branches[$slug] = [
                'user_id' => $slug === $branchSlug ? $userId : ($meta['user_id'] ?? ''),
            ];
        }
        Setting::set('avito_branches', json_encode($branches, JSON_UNESCAPED_UNICODE));

        $this->info('Avito настроен.');
        $this->line("  client_id: {$clientId}");
        $this->line("  филиал: {$branch['label']} ({$branchSlug})");
        $this->line("  user_id: {$userId}".($accountName !== '' ? " — {$accountName}" : ''));

        if (count(array_filter($branches, fn (array $b): bool => trim((string) ($b['user_id'] ?? '')) !== '')) === 1) {
            $this->warn('Ключи привязаны к одному аккаунту Avito. Для остальных филиалов нужны отдельные приложения API.');
        }

        return self::SUCCESS;
    }
}
