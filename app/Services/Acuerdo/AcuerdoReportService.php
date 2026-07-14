<?php

namespace App\Services\Acuerdo;

use App\Support\AcuerdoCredentials;
use Illuminate\Support\Facades\Log;

/** Загрузка HTML-отчётов info.acuerdo.pro (без кэша — каждый запрос страницы). */
class AcuerdoReportService
{
    public const REPORT_CURRENT_ASSET = 'current_asset';

    public const REPORT_CURRENT_FINANCES = 'current_finances';

    public function __construct(
        private AcuerdoInfoClient $client = new AcuerdoInfoClient,
    ) {}

    /**
     * @return array{
     *   ok: bool,
     *   error?: string,
     *   report_path?: string,
     *   report_title?: string,
     *   html?: string,
     *   fetched_at?: string
     * }
     */
    public function fetchReport(string $reportKey): array
    {
        if (! AcuerdoCredentials::isConfigured()) {
            return [
                'ok' => false,
                'error' => 'Не заданы учётные данные Acuerdo. Укажите ACUERDO_USERNAME и ACUERDO_PASSWORD в .env или в Настройки → Acuerdo.',
            ];
        }

        try {
            if (! $this->client->login()) {
                return ['ok' => false, 'error' => 'Не удалось войти на info.acuerdo.pro. Проверьте логин и пароль.'];
            }

            $indexHtml = $this->client->fetchIndexHtml();
            $links = $this->client->parseIndexReportLinks($indexHtml);
            $path = $this->resolveReportPath($reportKey, $links);
            if ($path === null) {
                return ['ok' => false, 'error' => 'Отчёт не найден в списке info.acuerdo.pro.'];
            }

            $html = $this->client->fetchReportHtml($path);
            $title = $this->extractReportTitle($html, $links, $path);

            return [
                'ok' => true,
                'report_path' => $path,
                'report_title' => $title,
                'html' => $this->sanitizeReportHtml($html),
                'fetched_at' => now()->format('d.m.Y H:i:s'),
            ];
        } catch (\Throwable $e) {
            Log::warning('acuerdo report fetch failed', [
                'report' => $reportKey,
                'message' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'error' => 'Ошибка загрузки с info.acuerdo.pro: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @param  list<array{path: string, title: string}>  $links
     */
    private function resolveReportPath(string $reportKey, array $links): ?string
    {
        $needles = match ($reportKey) {
            self::REPORT_CURRENT_ASSET => [
                ['текущ', 'актив'],
                ['актив'],
            ],
            self::REPORT_CURRENT_FINANCES => [
                ['текущ', 'финанс'],
                ['финанс'],
            ],
            default => [],
        };

        foreach ($needles as $parts) {
            foreach ($links as $link) {
                $title = mb_strtolower($link['title']);
                $ok = true;
                foreach ($parts as $part) {
                    if (! str_contains($title, $part)) {
                        $ok = false;
                        break;
                    }
                }
                if ($ok) {
                    return $link['path'];
                }
            }
        }

        return match ($reportKey) {
            self::REPORT_CURRENT_ASSET => $this->pathExists('2.html', $links) ? '2.html' : null,
            self::REPORT_CURRENT_FINANCES => $this->pathExists('3.html', $links) ? '3.html' : null,
            default => null,
        };
    }

    /** @param  list<array{path: string, title: string}>  $links */
    private function pathExists(string $path, array $links): bool
    {
        foreach ($links as $link) {
            if ($link['path'] === $path) {
                return true;
            }
        }

        return true;
    }

    /**
     * @param  list<array{path: string, title: string}>  $links
     */
    private function extractReportTitle(string $html, array $links, string $path): string
    {
        foreach ($links as $link) {
            if ($link['path'] === $path && $link['title'] !== '') {
                return $link['title'];
            }
        }

        if (preg_match('/<td[^>]*>([^<]{3,200})<\/td>/iu', $html, $m)) {
            return trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5));
        }

        return $path;
    }

    private function sanitizeReportHtml(string $html): string
    {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        if (preg_match('/<body[^>]*>(.*)<\/body>/is', $html, $m)) {
            return trim($m[1]);
        }

        return trim($html);
    }
}
