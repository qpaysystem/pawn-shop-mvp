<?php

namespace App\Support;

use App\Models\Setting;

/** Секрет для API agent-teams-portal: БД (настройки) → .env AGENT_TEAMS_API_TOKEN. */
final class AgentTeamsToken
{
    public static function resolve(): string
    {
        $fromDb = trim((string) Setting::get('agent_teams_api_token', ''));
        if ($fromDb !== '') {
            return $fromDb;
        }

        return trim((string) config('services.agent_teams.api_token', ''));
    }

    public static function isConfigured(): bool
    {
        return self::resolve() !== '';
    }
}
