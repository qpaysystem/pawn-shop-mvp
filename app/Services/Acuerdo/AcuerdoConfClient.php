<?php

namespace App\Services\Acuerdo;

use App\Support\AcuerdoCredentials;
use Carbon\Carbon;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Клиент conf.nnfm.pro — список и скачивание записей собраний. */
class AcuerdoConfClient
{
    private CookieJar $cookies;

    private string $base;

    public function __construct()
    {
        $this->cookies = new CookieJar();
        $this->base = rtrim((string) config('services.acuerdo.conf_base_url', 'https://conf.nnfm.pro'), '/');
    }

    public function login(): bool
    {
        $username = AcuerdoCredentials::username();
        $password = AcuerdoCredentials::password();
        if ($username === '' || $password === '') {
            Log::warning('acuerdo-conf: credentials not configured');

            return false;
        }

        $response = $this->http()->get($this->base.'/');
        if (str_contains($response->body(), 'name="username"')) {
            if (! preg_match('/action="([^"]+)"/', $response->body(), $matches)) {
                return false;
            }
            $action = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
            $this->http()->asForm()->post($action, [
                'username' => $username,
                'password' => $password,
                'credentialId' => '',
            ]);
        }

        $room = $this->http()->get($this->base.'/room');

        return $room->successful() && str_contains($room->body(), 'oauth2/logout');
    }

    /** @return list<array{title: string, file_ref: string, meeting_at: Carbon}> */
    public function listRecordings(string $room): array
    {
        $url = $this->base.'/record?room='.rawurlencode($room);
        $response = $this->http()->get($url);
        $response->throw();

        return $this->parseRecordingsHtml($response->body());
    }

    public function downloadVideo(string $fileRef, string $destPath): void
    {
        $url = $this->base.'/video/'.ltrim($fileRef, '/');
        $response = $this->http()->withOptions(['sink' => $destPath])->get($url);
        $response->throw();
    }

    /** @return list<array{title: string, file_ref: string, meeting_at: Carbon}> */
    public function parseRecordingsHtml(string $html): array
    {
        $tz = config('app.timezone', 'Asia/Novosibirsk');
        $out = [];
        $pattern = '/href="[^"]*\/view\?name=([^"]+)&(?:amp;)?file=([^"]+\.mp4)"/iu';
        if (! preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
            return [];
        }

        foreach ($matches as $m) {
            $title = trim(html_entity_decode(rawurldecode($m[1]), ENT_QUOTES | ENT_HTML5));
            $fileRef = trim(html_entity_decode(rawurldecode($m[2]), ENT_QUOTES | ENT_HTML5));
            if ($title === '' || $fileRef === '') {
                continue;
            }
            $meetingAt = $this->parseRecordingTitleDatetime($title, $tz) ?? now($tz);
            $out[] = [
                'title' => $title,
                'file_ref' => $fileRef,
                'meeting_at' => $meetingAt,
            ];
        }

        usort($out, fn (array $a, array $b) => $b['meeting_at']->getTimestamp() <=> $a['meeting_at']->getTimestamp());

        return $out;
    }

    private function parseRecordingTitleDatetime(string $title, string $tz): ?Carbon
    {
        if (! preg_match('/(\d{1,2})\s+([а-яa-z]+)\s+(\d{4})\s+в\s+(\d{1,2}):(\d{2})/iu', $title, $m)) {
            return null;
        }

        $months = [
            'января' => 1, 'февраля' => 2, 'марта' => 3, 'апреля' => 4,
            'мая' => 5, 'июня' => 6, 'июля' => 7, 'августа' => 8,
            'сентября' => 9, 'октября' => 10, 'ноября' => 11, 'декабря' => 12,
        ];
        $month = $months[mb_strtolower($m[2])] ?? null;
        if ($month === null) {
            return null;
        }

        try {
            return Carbon::create((int) $m[3], $month, (int) $m[1], (int) $m[4], (int) $m[5], 0, $tz);
        } catch (\Throwable) {
            return null;
        }
    }

    private function http()
    {
        return Http::withOptions([
            'cookies' => $this->cookies,
            'allow_redirects' => true,
        ])
            ->timeout(600)
            ->connectTimeout(30)
            ->withHeaders(['User-Agent' => 'lombard-portal/1.0']);
    }
}
