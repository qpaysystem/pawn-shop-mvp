<?php

namespace Tests\Feature;

use App\Models\TelegramMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentTeamsTelegramApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.agent_teams.api_token' => 'test-agent-token']);
    }

    public function test_health_requires_token(): void
    {
        $this->getJson('/api/internal/agent-teams/telegram/health')
            ->assertStatus(401);
    }

    public function test_health_returns_ok(): void
    {
        $this->withHeaders($this->agentTeamsHeaders())
            ->getJson('/api/internal/agent-teams/telegram/health')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('service', 'lombard-telegram');
    }

    public function test_messages_returns_inbox_rows(): void
    {
        TelegramMessage::query()->create([
            'chat_id' => '-100123456',
            'message_id' => 42,
            'chat_type' => 'supergroup',
            'from_user_id' => 1001,
            'from_first_name' => 'Иван',
            'text' => 'Сколько стоит залог часов?',
            'message_date' => now(),
        ]);

        $response = $this->withHeaders($this->agentTeamsHeaders())
            ->getJson('/api/internal/agent-teams/telegram/messages?since_id=0&limit=10');

        $response->assertOk()
            ->assertJsonPath('max_id', 1)
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.external_id', 'lombard-tg:-100123456:42')
            ->assertJsonPath('messages.0.text', 'Сколько стоит залог часов?');
    }

    public function test_reply_validates_payload(): void
    {
        $this->withHeaders($this->agentTeamsHeaders())
            ->postJson('/api/internal/agent-teams/telegram/reply', [])
            ->assertStatus(422);
    }

    /**
     * @return array<string, string>
     */
    private function agentTeamsHeaders(): array
    {
        return [
            'Authorization' => 'Bearer test-agent-token',
            'X-Agent-Teams-Token' => 'test-agent-token',
            'Accept' => 'application/json',
        ];
    }
}
