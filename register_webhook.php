<?php
// =============================================================================
// Run once from CLI or browser to register / inspect / remove the webhook.
// Usage:
//   php register_webhook.php set    https://yoursite.com/webhook.php
//   php register_webhook.php info
//   php register_webhook.php delete
// =============================================================================
require_once __DIR__ . '/config.php';

$action  = $argv[1] ?? ($_GET['action'] ?? 'info');
$url     = $argv[2] ?? ($_GET['url']    ?? '');

$token   = TELEGRAM_BOT_TOKEN;
$secret  = TELEGRAM_WEBHOOK_SECRET;

function tgCall(string $token, string $method, array $params = []): array
{
    $ch = curl_init("https://api.telegram.org/bot{$token}/{$method}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => !empty($params),
        CURLOPT_POSTFIELDS     => $params ? http_build_query($params) : null,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $result = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $result ?? [];
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
        echo $result['ok'] ? "✅ Webhook set: {$url}\n" : "❌ Error: " . ($result['description'] ?? 'unknown') . "\n";
        break;

    case 'delete':
        $result = tgCall($token, 'deleteWebhook', ['drop_pending_updates' => 'false']);
        echo $result['ok'] ? "✅ Webhook deleted — now using getUpdates polling.\n" : "❌ Error: " . ($result['description'] ?? 'unknown') . "\n";
        break;

    case 'info':
    default:
        $result = tgCall($token, 'getWebhookInfo');
        $info   = $result['result'] ?? [];
        echo "Webhook URL    : " . ($info['url'] ?: '(none — using getUpdates)') . "\n";
        echo "Pending updates: " . ($info['pending_update_count'] ?? 0) . "\n";
        echo "Last error     : " . ($info['last_error_message'] ?? '—') . "\n";
        echo "Has custom cert: " . (($info['has_custom_certificate'] ?? false) ? 'yes' : 'no') . "\n";
        break;
}
