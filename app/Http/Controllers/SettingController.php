<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\Avito\AvitoBranchConfig;
use App\Support\AcuerdoCredentials;
use App\Support\AgentTeamsToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function index(): View
    {
        $settings = [
            'currency' => Setting::get('currency', 'RUB'),
            'max_upload_mb' => Setting::get('max_upload_mb', 5),
            'mail_notifications' => Setting::get('mail_notifications', '0'),
            'telegram_bot_token' => Setting::get('telegram_bot_token', ''),
            'telegram_bot_username' => Setting::get('telegram_bot_username', 'NskCapital_bot'),
            'telegram_chat_id' => Setting::get('telegram_chat_id', ''),
            'telegram_inbox_chat_id' => Setting::get('telegram_inbox_chat_id', ''),
            'telegram_webhook_secret_set' => trim((string) Setting::get('telegram_webhook_secret', '')) !== '',
            'telegram_private_inbox_enabled' => Setting::get('telegram_private_inbox_enabled', '1'),
            'agent_teams_api_token_configured' => AgentTeamsToken::isConfigured(),
            'agent_teams_api_token_in_db' => trim((string) Setting::get('agent_teams_api_token', '')) !== '',
            'acuerdo_username_configured' => AcuerdoCredentials::isConfigured(),
            'acuerdo_username_in_db' => trim((string) Setting::get('acuerdo_username', '')) !== '',
            'acuerdo_password_in_db' => trim((string) Setting::get('acuerdo_password', '')) !== '',
            'avito_client_id' => Setting::get('avito_client_id', ''),
            'avito_client_secret_set' => trim((string) Setting::get('avito_client_secret', '')) !== '',
            'avito_branches' => AvitoBranchConfig::branches(),
        ];

        return view('admin.settings.index', compact('settings'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'currency' => 'required|string|max:10',
            'max_upload_mb' => 'required|integer|min:1|max:50',
            'mail_notifications' => 'in:0,1',
            'telegram_bot_token' => 'nullable|string|max:255',
            'telegram_bot_username' => 'nullable|string|max:100',
            'telegram_chat_id' => 'nullable|string|max:50',
            'telegram_inbox_chat_id' => 'nullable|string|max:50',
            'telegram_webhook_secret' => 'nullable|string|max:255',
            'telegram_webhook_secret_clear' => 'in:0,1',
            'telegram_private_inbox_enabled' => 'in:0,1',
            'agent_teams_api_token' => 'nullable|string|max:500',
            'clear_agent_teams_api_token' => 'in:0,1',
            'acuerdo_username' => 'nullable|string|max:255',
            'acuerdo_password' => 'nullable|string|max:255',
            'clear_acuerdo_credentials' => 'in:0,1',
            'avito_client_id' => 'nullable|string|max:255',
            'avito_client_secret' => 'nullable|string|max:255',
            'avito_client_secret_clear' => 'in:0,1',
        ]);

        Setting::set('currency', $request->input('currency'));
        Setting::set('max_upload_mb', (string) $request->input('max_upload_mb'));
        Setting::set('mail_notifications', $request->boolean('mail_notifications') ? '1' : '0');
        Setting::set('telegram_bot_token', $request->input('telegram_bot_token', ''));
        Setting::set('telegram_bot_username', $request->input('telegram_bot_username', ''));
        Setting::set('telegram_chat_id', $request->input('telegram_chat_id', ''));
        Setting::set('telegram_inbox_chat_id', $request->input('telegram_inbox_chat_id', ''));
        Setting::set('telegram_private_inbox_enabled', $request->boolean('telegram_private_inbox_enabled') ? '1' : '0');
        if ($request->boolean('telegram_webhook_secret_clear')) {
            Setting::set('telegram_webhook_secret', '');
        } elseif ($request->filled('telegram_webhook_secret')) {
            Setting::set('telegram_webhook_secret', trim((string) $request->input('telegram_webhook_secret')));
        }

        if ($request->boolean('clear_agent_teams_api_token')) {
            Setting::set('agent_teams_api_token', '');
        } elseif ($request->filled('agent_teams_api_token')) {
            Setting::set('agent_teams_api_token', trim((string) $request->input('agent_teams_api_token')));
        }

        if ($request->boolean('clear_acuerdo_credentials')) {
            Setting::set('acuerdo_username', '');
            Setting::set('acuerdo_password', '');
        } else {
            if ($request->filled('acuerdo_username')) {
                Setting::set('acuerdo_username', trim((string) $request->input('acuerdo_username')));
            }
            if ($request->filled('acuerdo_password')) {
                Setting::set('acuerdo_password', trim((string) $request->input('acuerdo_password')));
            }
        }

        Setting::set('avito_client_id', trim((string) $request->input('avito_client_id', '')));
        if ($request->boolean('avito_client_secret_clear')) {
            Setting::set('avito_client_secret', '');
        } elseif ($request->filled('avito_client_secret')) {
            Setting::set('avito_client_secret', trim((string) $request->input('avito_client_secret')));
        }

        $branchPayload = [];
        $defaults = (array) config('avito.branch_defaults', []);
        foreach (array_keys($defaults) as $slug) {
            $meta = is_array($defaults[$slug] ?? null) ? $defaults[$slug] : [];
            $branchPayload[$slug] = [
                'label' => (string) ($meta['label'] ?? $slug),
                'user_id' => trim((string) $request->input('avito_user_id_'.$slug, '')),
            ];
        }
        Setting::set('avito_branches', json_encode($branchPayload, JSON_UNESCAPED_UNICODE));

        return redirect()->route('settings.system.index')->with('success', 'Настройки сохранены.');
    }
}
