<?php

namespace App\Console\Commands;

use App\Services\MtsVpbxService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class MtsVpbxDebugResponse extends Command
{
    protected $signature = 'mts:debug-response
                            {--days=7}
                            {--domain= : Домен AC20 (если не задан MTS_AC20_DOMAIN в .env)}
                            {--list-trunks : Только список транков GET /trunks/all (нужен Domain)}';

    protected $description = 'Проверить ответ API телефонии MTS (VPBX или Автосекретарь 2.0 / AC20)';

    public function handle(): int
    {
        $token = config('services.mts_vpbx.password', '');
        if ($token === '') {
            $this->error('MTS_VPBX_PASSWORD (токен / JWT) не задан в .env');

            return self::FAILURE;
        }

        $opts = ['curl' => [CURLOPT_SSL_CIPHER_LIST => 'DEFAULT@SECLEVEL=1']];
        $usesAc20 = app(MtsVpbxService::class)->usesAc20Api();

        if ($usesAc20) {
            return $this->debugAc20($opts);
        }

        return $this->debugVpbx($opts);
    }

    private function resolveAc20Domain(): string
    {
        $fromOpt = trim((string) $this->option('domain'));

        return $fromOpt !== '' ? $fromOpt : trim((string) config('services.mts_vpbx.ac20_domain', ''));
    }

    private function debugVpbx(array $opts): int
    {
        $url = rtrim(config('services.mts_vpbx.url', 'https://vpbx.mts.ru'), '/');
        $token = config('services.mts_vpbx.password', '');
        $days = (int) $this->option('days');
        $dateFromMs = now()->subDays($days)->getTimestamp() * 1000;
        $dateToMs = now()->getTimestamp() * 1000;
        $fullUrl = $url.'/api/v1/callHistory/enterprise';
        $params = [
            'dateFrom' => $dateFromMs,
            'dateTo' => $dateToMs,
            'page' => 0,
            'size' => 5,
        ];

        $this->line('Режим: VPBX (классический).');
        $this->line('Запрос: GET '.$fullUrl.' с заголовком X-AUTH-TOKEN');
        $this->line('Параметры: dateFrom='.$dateFromMs.', dateTo='.$dateToMs.', page=0, size=5');
        $this->line('');

        $response = Http::timeout(30)
            ->withOptions($opts)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-AUTH-TOKEN' => $token,
            ])
            ->get($fullUrl, $params);

        $this->line('Status: '.$response->status());

        if ($response->successful()) {
            $this->info('Успех. Ответ API (история вызовов):');
            $this->line($response->body());

            return self::SUCCESS;
        }

        $this->line('Body: '.substr($response->body(), 0, 500).(strlen($response->body()) > 500 ? '...' : ''));
        $this->line('');
        $this->line('Подсказка: ЛК vpbx.mts.ru → активация API, токен в MTS_VPBX_PASSWORD, заголовок X-AUTH-TOKEN.');

        return self::FAILURE;
    }

    private function debugAc20(array $opts): int
    {
        $base = rtrim(config('services.mts_vpbx.ac20_base_url', 'https://aa.mts.ru/api/ac20'), '/');
        $token = config('services.mts_vpbx.password', '');
        $domain = $this->resolveAc20Domain();
        $trunkId = preg_replace('/\D/', '', (string) config('services.mts_vpbx.ac20_trunk_id', ''));

        $this->line('Режим: Автосекретарь 2.0 (AC20). Документация: https://aa.mts.ru/api/ac20/index.html');
        $this->line('');

        if ($this->option('list-trunks')) {
            if ($domain === '') {
                $this->error('Укажите домен: MTS_AC20_DOMAIN в .env или опция --domain=ваш-домен.ru');
                $this->line('Это тот же параметр «Domain», что в Swagger (FQDN организации в AC20).');

                return self::FAILURE;
            }

            return $this->printAc20Trunks($domain) ? self::SUCCESS : self::FAILURE;
        }

        if ($domain === '') {
            $this->error('Не задан домен AC20 (параметр API Domain).');
            $this->line('1) Укажите в .env: MTS_AC20_DOMAIN=ваш-домен.ru');
            $this->line('2) Или разово: php artisan mts:debug-response --domain=ваш-домен.ru');
            $this->line('3) Чтобы увидеть TrunkId: php artisan mts:debug-response --domain=ваш-домен.ru --list-trunks');

            return self::FAILURE;
        }

        if (strlen($trunkId) !== 10) {
            $this->warn('MTS_AC20_TRUNK_ID в .env не задан или не 10 цифр. Список транков для домена «'.$domain.'»:');
            $this->line('');
            if (! $this->printAc20Trunks($domain)) {
                return self::FAILURE;
            }
            $this->line('');
            $this->error('Скопируйте нужный trunkId в .env: MTS_AC20_TRUNK_ID=0123456789');
            $this->line('Затем снова: php artisan mts:debug-response --days=1');

            return self::FAILURE;
        }

        $days = (int) $this->option('days');
        $begin = now()->subDays($days)->format('Y-m-d\TH:i:s');
        $end = now()->format('Y-m-d\TH:i:s');
        $fullUrl = $base.'/trunks/statistics';
        $params = [
            'Domain' => $domain,
            'TrunkId' => $trunkId,
            'Begin' => $begin,
            'End' => $end,
            'Limit' => 5,
            'Offset' => 0,
        ];

        $this->line('Запрос: GET '.$fullUrl);
        $this->line('Заголовок: Authorization: Bearer <JWT>');
        $this->line('Параметры: '.json_encode($params, JSON_UNESCAPED_UNICODE));
        $this->line('');

        $response = Http::timeout(30)
            ->withOptions($opts)
            ->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$token,
            ])
            ->get($fullUrl, $params);

        $this->line('Status: '.$response->status());

        if ($response->successful()) {
            $this->info('Успех. Ответ API (статистика вызовов):');
            $this->line($response->body());
            $decoded = $response->json();
            $svc = app(MtsVpbxService::class);
            $sum = $svc->summarizeAc20StatisticsPayload($decoded);
            $this->line('');
            if (! $sum['unwrap_ok']) {
                $this->warn('JSON не распознан как список звонков (см. unwrapAc20StatisticsRows в MtsVpbxService).');
            } else {
                $this->line('Записей в ответе: '.$sum['rows'].', распознано для CRM: '.$sum['parsed'].'.');
                if (($sum['rows'] ?? 0) > 0 && $sum['parsed'] === 0) {
                    $this->warn('Поля в JSON не совпадают с ожидаемыми (callId, startTime и т.д.) — доработайте normalizeAc20StatisticsRow.');
                }
            }

            return self::SUCCESS;
        }

        $this->line('Body: '.substr($response->body(), 0, 800).(strlen($response->body()) > 800 ? '...' : ''));
        $this->line('');
        $this->line('Проверьте JWT, Domain и TrunkId. Список транков: mts:debug-response --domain='.$domain.' --list-trunks');

        return self::FAILURE;
    }

    private function printAc20Trunks(string $domain): bool
    {
        $service = app(MtsVpbxService::class);
        $rows = $service->fetchAc20Trunks($domain);
        if ($rows === null) {
            $this->error('Запрос GET /trunks/all не удался (401 / неверный Domain / сеть). Проверьте JWT и домен.');

            return false;
        }
        if ($rows === []) {
            $this->warn('Транков нет (пустой массив). Уточните Domain в ЛК aa.mts.ru.');

            return true;
        }

        $table = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $tid = $row['trunkId'] ?? $row['TrunkId'] ?? '';
            $table[] = [
                (string) $tid,
                (string) ($row['name'] ?? $row['Name'] ?? ''),
                (string) ($row['cli'] ?? $row['Cli'] ?? ''),
            ];
        }
        $this->table(['TrunkId (в MTS_AC20_TRUNK_ID)', 'Название', 'АОН'], $table);

        return true;
    }
}
