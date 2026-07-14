<?php

namespace App\Http\Middleware;

use App\Support\AgentTeamsToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Доступ к internal API для портала agent-teams (Bearer или X-Agent-Teams-Token). */
class VerifyAgentTeamsToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = AgentTeamsToken::resolve();
        if ($expected === '') {
            return response()->json([
                'message' => 'Секрет не задан: Настройки → Портал ИИ-агентов или AGENT_TEAMS_API_TOKEN в .env.',
            ], 503);
        }

        $cand = trim((string) $request->header('X-Agent-Teams-Token', ''));
        if ($cand === '' && $request->bearerToken()) {
            $cand = trim((string) $request->bearerToken());
        }
        if ($cand === '' || ! hash_equals($expected, $cand)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
