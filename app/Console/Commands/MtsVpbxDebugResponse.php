<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class MtsVpbxDebugResponse extends Command
{
    protected $signature = 'mts:debug-response {--days=7}';

    protected $description = 'Проверить ответ API MTS VPBX (GET /api/v1/callHistory/enterprise, X-AUTH-TOKEN)';

    public function handle(): int
    {
        $url = rtrim(config('services.mts_vpbx.url', 'https://vpbx.mts.ru'), '/');
        $token = config('services.mts_vpbx.password', '');
        if ($token === '') {
            $this->error('MTS_VPBX_PASSWORD (токен) не задан в .env');
            return self::FAILURE;
        }

        $days = (int) $this->option('days');
        $dateFromMs = now()->subDays($days)->getTimestamp() * 1000;
        $dateToMs = now()->getTimestamp() * 1000;
        $fullUrl = $url . '/api/v1/callHistory/enterprise';
        $params = [
            'dateFrom' => $dateFromMs,
            'dateTo' => $dateToMs,
            'page' => 0,
            'size' => 5,
        ];
        $opts = ['curl' => [CURLOPT_SSL_CIPHER_LIST => 'DEFAULT@SECLEVEL=1']];

        $this->line('Запрос: GET ' . $fullUrl . ' с заголовком X-AUTH-TOKEN');
        $this->line('Параметры: dateFrom=' . $dateFromMs . ', dateTo=' . $dateToMs . ', page=0, size=5');
        $this->line('');

        $response = Http::timeout(30)
            ->withOptions($opts)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-AUTH-TOKEN' => $token,
            ])
            ->get($fullUrl, $params);

        $this->line('Status: ' . $response->status());

        if ($response->successful()) {
            $this->info('Успех. Ответ API (история вызовов):');
            $this->line($response->body());
            return self::SUCCESS;
        }

        $this->line('Body: ' . substr($response->body(), 0, 500) . (strlen($response->body()) > 500 ? '...' : ''));
        $this->line('');
        $this->line('По документации MTS API CRM:');
        $this->line('1. В ЛК vpbx.mts.ru: левое меню → «Активация API» — включите API и скопируйте токен в MTS_VPBX_PASSWORD.');
        $this->line('2. Токен передаётся в заголовке X-AUTH-TOKEN (раздел 4 документации).');
        $this->line('3. История вызовов: GET /api/v1/callHistory/enterprise?dateFrom=&dateTo= (unixtimestamp в мс).');
        return self::FAILURE;
    }
}
