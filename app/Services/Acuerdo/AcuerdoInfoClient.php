<?php

namespace App\Services\Acuerdo;

use App\Support\AcuerdoCredentials;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Клиент info.acuerdo.pro (Keycloak, как в agent-teams-portal). */
class AcuerdoInfoClient
{
    private CookieJar $cookies;

    private string $base;

    public function __construct()
    {
        $this->cookies = new CookieJar();
        $this->base = rtrim((string) config('services.acuerdo.info_base_url', 'https://info.acuerdo.pro'), '/');
    }

    public function login(): bool
    {
        $username = AcuerdoCredentials::username();
        $password = AcuerdoCredentials::password();
        if ($username === '' || $password === '') {
            Log::warning('acuerdo: credentials not configured');

            return false;
        }

        $response = $this->http()->get($this->base.'/');
        if (! str_contains((string) $response->effectiveUri(), 'auth.acuerdo.pro') && ! str_contains($response->body(), 'name="username"')) {
            return true;
        }

        if (! preg_match('/action="([^"]+)"/', $response->body(), $matches)) {
            return false;
        }

        $action = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
        if (str_starts_with($action, '/')) {
            $action = 'https://auth.acuerdo.pro'.$action;
        }

        $this->http()->asForm()->post($action, [
            'username' => $username,
            'password' => $password,
            'credentialId' => '',
        ]);

        $check = $this->http()->get($this->base.'/');

        return ! str_contains((string) $check->effectiveUri(), 'auth.acuerdo.pro')
            && ! str_contains($check->body(), 'name="username"');
    }

    public function fetchIndexHtml(): string
    {
        $response = $this->http()->get($this->base.'/');
        $response->throw();

        return $response->body();
    }

    public function fetchReportHtml(string $path): string
    {
        $path = ltrim($path, '/');
        $response = $this->http()->get($this->base.'/'.$path);
        $response->throw();

        return $response->body();
    }

    /** @return list<array{path: string, title: string}> */
    public function parseIndexReportLinks(string $indexHtml): array
    {
        $out = [];
        $seen = [];
        if (preg_match_all('/<a href="([^"]+\.html)">([^<]+)<\/a>/iu', $indexHtml, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $path = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5));
                $title = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5));
                if ($path === '' || isset($seen[$path])) {
                    continue;
                }
                $seen[$path] = true;
                $out[] = ['path' => $path, 'title' => $title];
            }
        }

        return $out;
    }

    private function http()
    {
        return Http::withOptions([
            'cookies' => $this->cookies,
            'allow_redirects' => true,
        ])
            ->timeout(120)
            ->connectTimeout(30)
            ->withHeaders(['User-Agent' => 'lombard-portal/1.0']);
    }
}
