<?php
// =============================================================================
// Run once from CLI or browser to register / inspect / remove the webhook
// and to register bot commands.
//
// Usage:
//   php register_webhook.php set      https://yoursite.com/webhook.php
//   php register_webhook.php delete
//   php register_webhook.php info
//   php register_webhook.php commands   ← register /close etc. in Telegram
// =============================================================================
require_once __DIR__ . '/config.php';

$action = $argv[1] ?? ($_GET['action'] ?? null);
$url    = $argv[2] ?? ($_GET['url']    ?? '');

// No action given: auto-set if URL is configured, otherwise show info
if ($action === null) {
    $action = (TELEGRAM_WEBHOOK_URL !== '') ? 'set' : 'info';
    if ($action === 'set') {
        $url = TELEGRAM_WEBHOOK_URL;
        if ($secret === '') {
            echo "⚠️  Warning: TELEGRAM_WEBHOOK_SECRET is empty — webhook accepts requests from anyone!\n";
        }
    }
}
$token  = TELEGRAM_BOT_TOKEN;
$secret = TELEGRAM_WEBHOOK_SECRET;

function tgCall(string $token, string $method, array $params = []): array
{
    $ch = curl_init("https://api.telegram.org/bot{$token}/{$method}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => !empty($params),
        CURLOPT_POSTFIELDS     => $params ? http_build_query($params) : null,
        CURLOPT_TIMEOUT        => 15,
    ]);
    // curl_close() is deprecated since PHP 8.4 — let the handle go out of scope instead
    return json_decode(curl_exec($ch), true) ?? [];
}

header('Content-Type: text/plain; charset=utf-8');

switch ($action) {

    case 'set':
        if (!$url) {
            echo "Error: provide a URL.\n";
            echo "Usage: php register_webhook.php set https://yoursite.com/webhook.php\n";
            exit(1);
        }
        $params = [
            'url'             => $url,
            'allowed_updates' => json_encode(['message', 'edited_message']),
            'max_connections' => 40,
        ];
        if ($secret !== '') {
            $params['secret_token'] = $secret;
        }
        $result = tgCall($token, 'setWebhook', $params);
        echo $result['ok']
            ? "✅ Webhook set: {$url}\n"
            : "❌ Error: " . ($result['description'] ?? 'unknown') . "\n";
        break;

    case 'delete':
        $result = tgCall($token, 'deleteWebhook', ['drop_pending_updates' => 'false']);
        echo $result['ok']
            ? "✅ Webhook deleted — now using getUpdates polling.\n"
            : "❌ Error: " . ($result['description'] ?? 'unknown') . "\n";
        break;

    case 'commands':
        // Register agent commands so Telegram shows them as suggestions in the thread.
        // Scope: all_group_chats — visible to admins/agents in the support group.
        $commands = [
            ['command' => 'close',    'description' => 'Mark this chat as resolved ✅'],
            ['command' => 'resolved', 'description' => 'Mark this chat as resolved ✅'],
            ['command' => 'done',     'description' => 'Mark this chat as resolved ✅'],
            ['command' => 'online',   'description' => 'Set support status to online (12 h) 🟢'],
            ['command' => 'offline',  'description' => 'Set support status to offline (12 h) 🔴'],
        ];
        $result = tgCall($token, 'setMyCommands', [
            'commands' => json_encode($commands),
            'scope'    => json_encode(['type' => 'all_group_chats']),
        ]);
        echo $result['ok']
            ? "✅ Commands registered — agents will see /close, /resolved, /done as suggestions.\n"
            : "❌ Error: " . ($result['description'] ?? 'unknown') . "\n";
        break;

    case 'info':
    default:
        $info = tgCall($token, 'getWebhookInfo')['result'] ?? [];
        echo "Webhook URL    : " . ($info['url'] ?: '(none — using getUpdates)') . "\n";
        echo "Pending updates: " . ($info['pending_update_count'] ?? 0) . "\n";
        echo "Last error     : " . ($info['last_error_message'] ?? '—') . "\n";
        echo "Has custom cert: " . (($info['has_custom_certificate'] ?? false) ? 'yes' : 'no') . "\n";

        $cmds = tgCall($token, 'getMyCommands', [
            'scope' => json_encode(['type' => 'all_group_chats']),
        ])['result'] ?? [];
        echo "Registered cmds: " . (empty($cmds) ? '(none)' : implode(', ', array_column($cmds, 'command'))) . "\n";
        break;
}
