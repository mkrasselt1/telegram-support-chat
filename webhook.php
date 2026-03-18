<?php
// =============================================================================
// Telegram Support Chat — Webhook Receiver
//
// Register this URL with Telegram once:
//   php register_webhook.php
// or via curl:
//   curl "https://api.telegram.org/bot<TOKEN>/setWebhook" \
//        -d "url=https://yoursite.com/webhook.php" \
//        -d "secret_token=<TELEGRAM_WEBHOOK_SECRET>"
//
// Telegram will POST every update as JSON to this file instantly.
// =============================================================================
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/telegram.php';

// --- Validate secret token header (prevents spoofed requests) ---
if (TELEGRAM_WEBHOOK_SECRET !== '') {
    $received = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
    if (!hash_equals(TELEGRAM_WEBHOOK_SECRET, $received)) {
        http_response_code(403);
        exit;
    }
}

// --- Only accept POST ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// --- Parse update ---
$raw    = file_get_contents('php://input');
$update = json_decode($raw, true);

if (!is_array($update) || !isset($update['update_id'])) {
    http_response_code(400);
    exit;
}

// --- Ensure data directories exist ---
foreach ([DATA_DIR, SESSION_DIR, UPLOAD_DIR, UPDATES_DIR] as $dir) {
    if (!is_dir($dir)) mkdir($dir, 0750, true);
}

// --- Process the update ---
processUpdate($update);

// Respond 200 after processing — Telegram retries on non-2xx
http_response_code(200);
header('Content-Type: text/plain');
echo 'ok';

// =============================================================================
// Processing
// =============================================================================

function processUpdate(array $update): void
{
    $isEdit = isset($update['edited_message']) && !isset($update['message']);
    $msg    = $update['message'] ?? $update['edited_message'] ?? null;
    if (!$msg) return;

    // Ignore bots
    if ($msg['from']['is_bot'] ?? false) return;
    if (BOT_USER_ID !== 0 && (int)($msg['from']['id'] ?? 0) === BOT_USER_ID) return;

    $threadId  = $msg['message_thread_id'] ?? null;
    $agentName = trim(($msg['from']['first_name'] ?? '') . ' ' . ($msg['from']['last_name'] ?? '')) ?: COMPANY_NAME;
    $text      = trim($msg['text'] ?? '');
    $command   = strtolower(preg_replace('/@\S+$/', '', $text));
    $bot       = new TelegramBot(TELEGRAM_BOT_TOKEN, TELEGRAM_CHAT_ID);

    // /online and /offline are group-wide — work even in the General topic
    // where message_thread_id is absent.
    if ($command === '/online' || $command === '/offline') {
        setAgentManualStatus($agentName, ltrim($command, '/'));
        if ($threadId !== null) {
            $reply = $command === '/online'
                ? "✅ Status set to <b>online</b> (valid 12 h)."
                : "🔴 Status set to <b>offline</b> (valid 12 h).";
            try { $bot->sendMessage($reply, (int)$threadId); } catch (Exception) {}
        }
        return;
    }

    if ($threadId === null) return;  // all other commands require a thread

    $sessionId = lookupSession((int) $threadId);
    if (!$sessionId) return;  // update is for a thread we don't manage

    if (in_array($command, ['/close', '/resolved', '/done', '/closed'], true)) {
        closeSession($sessionId, 'agent', $bot);
        return;
    }

    // Edited message — update existing entry in session history
    if ($isEdit) {
        $telegramMsgId = (int)($msg['message_id'] ?? 0);
        if ($telegramMsgId && !empty($msg['text'])) {
            updateAgentMessageInSession($sessionId, $telegramMsgId, [
                'content'   => $msg['text'],
                'edited_at' => time(),
            ]);
        }
        return;
    }

    $agentMsg = formatAgentMessage($msg);
    $agentMsg = resolveAgentFile($agentMsg, $sessionId, $bot);
    appendToInbox($sessionId, $agentMsg);
    recordAgentActivity($agentName);
}

function lookupSession(int $threadId): ?string
{
    $path = UPDATES_DIR . '/thread_index.json';
    if (!file_exists($path)) return null;

    $fp   = fopen($path, 'r');
    flock($fp, LOCK_SH);
    $data = json_decode(stream_get_contents($fp), true) ?? [];
    flock($fp, LOCK_UN);
    fclose($fp);

    return $data[(string) $threadId] ?? null;
}

function formatAgentMessage(array $msg): array
{
    $agentName = trim(($msg['from']['first_name'] ?? '') . ' ' . ($msg['from']['last_name'] ?? ''));
    if (!$agentName) $agentName = COMPANY_NAME;

    $base = [
        'id'                  => generateUuid(),
        'from'                => 'agent',
        'agent_name'          => $agentName,
        'timestamp'           => $msg['date'],
        'telegram_message_id' => $msg['message_id'] ?? null,
    ];

    if (!empty($msg['text'])) {
        return $base + ['type' => 'text', 'content' => $msg['text']];
    }
    if (!empty($msg['photo'])) {
        $photo = end($msg['photo']);
        return $base + ['type' => 'image', 'content' => $msg['caption'] ?? '', 'telegram_file_id' => $photo['file_id']];
    }
    if (!empty($msg['voice'])) {
        return $base + ['type' => 'voice', 'content' => '🎤 Voice message', 'telegram_file_id' => $msg['voice']['file_id']];
    }
    if (!empty($msg['audio'])) {
        return $base + ['type' => 'audio', 'content' => $msg['audio']['title'] ?? 'Audio', 'telegram_file_id' => $msg['audio']['file_id']];
    }
    if (!empty($msg['document'])) {
        return $base + ['type' => 'file', 'content' => $msg['document']['file_name'] ?? 'Document', 'telegram_file_id' => $msg['document']['file_id']];
    }
    if (!empty($msg['location'])) {
        return $base + [
            'type'    => 'location',
            'content' => '📍 Location',
            'lat'     => $msg['location']['latitude'],
            'lng'     => $msg['location']['longitude'],
        ];
    }
    if (!empty($msg['sticker'])) {
        return $base + ['type' => 'text', 'content' => $msg['sticker']['emoji'] ?? '🎭'];
    }

    return $base + ['type' => 'text', 'content' => '(unsupported message type)'];
}

// --- Shared helpers (duplicated from chat.php to keep webhook.php self-contained) ---

function closeSession(string $sessionId, string $initiator, $bot): void
{
    $path = SESSION_DIR . '/' . $sessionId . '.json';
    if (!file_exists($path)) return;

    $fp = fopen($path, 'r+');
    flock($fp, LOCK_EX);
    $session = json_decode(stream_get_contents($fp), true) ?? [];

    if (($session['status'] ?? 'open') === 'closed') {
        flock($fp, LOCK_UN); fclose($fp); return;
    }

    $session['status']    = 'closed';
    $session['closed_at'] = time();
    $session['closed_by'] = $initiator;
    $closedMessages = [
        'en' => ['user' => 'You marked this chat as resolved.', 'agent' => 'Support marked this chat as resolved. ✅'],
        'de' => ['user' => 'Du hast dieses Gespräch als gelöst markiert.', 'agent' => 'Der Support hat dieses Gespräch als gelöst markiert. ✅'],
    ];
    $lang    = defined('LANGUAGE') ? LANGUAGE : 'en';
    $msgLang = $closedMessages[$lang] ?? $closedMessages['en'];

    $session['history'][] = [
        'id'        => generateUuid(),
        'from'      => 'system',
        'type'      => 'closed',
        'content'   => $msgLang[$initiator] ?? $msgLang['agent'],
        'closed_by' => $initiator,
        'timestamp' => time(),
    ];

    rewind($fp); ftruncate($fp, 0);
    fwrite($fp, json_encode($session, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    flock($fp, LOCK_UN); fclose($fp);

    $threadId = $session['thread_id'] ?? null;
    if ($threadId !== null) {
        try {
            $bot->sendMessage('✅ Chat closed by support agent.', (int) $threadId);
            $bot->closeForumTopic((int) $threadId);
        } catch (Exception $e) {
            error_log('closeSession: ' . $e->getMessage());
        }
    }
}

function resolveAgentFile(array $msg, string $sessionId, TelegramBot $bot): array
{
    $fileId = $msg['telegram_file_id'] ?? null;
    if (!$fileId) return $msg;

    $destDir  = UPLOAD_DIR . '/' . $sessionId;
    $fileName = $bot->downloadFile($fileId, $destDir);

    if ($fileName) {
        $msg['file_url'] = '?action=file&session_id=' . urlencode($sessionId)
            . '&name=' . urlencode($fileName);
    }

    unset($msg['telegram_file_id']);
    return $msg;
}

function appendToInbox(string $sessionId, array $message): void
{
    if (!file_exists(SESSION_DIR . '/' . $sessionId . '.json')) return;
    appendMessageToSession($sessionId, $message);
    maybeNotifyByEmail($sessionId, $message);
}

function appendMessageToSession(string $sessionId, array $message): void
{
    $path = SESSION_DIR . '/' . $sessionId . '.json';
    $fp   = fopen($path, 'r+');
    flock($fp, LOCK_EX);
    $session = json_decode(stream_get_contents($fp), true) ?? [];
    $session['history'][] = $message;
    if (count($session['history']) > 500) {
        $session['history'] = array_slice($session['history'], -500);
    }
    rewind($fp);
    ftruncate($fp, 0);
    fwrite($fp, json_encode($session, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    flock($fp, LOCK_UN);
    fclose($fp);
}

function maybeNotifyByEmail(string $sessionId, array $message): void
{
    if (OFFLINE_NOTIFY_EMAIL === '') return;

    $path    = SESSION_DIR . '/' . $sessionId . '.json';
    $fp      = fopen($path, 'r');
    flock($fp, LOCK_SH);
    $session = json_decode(stream_get_contents($fp), true) ?? [];
    flock($fp, LOCK_UN);
    fclose($fp);

    $lastSeen = (int) ($session['last_activity'] ?? 0);
    $email    = $session['user_info']['email'] ?? '';

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) return;
    if (time() - $lastSeen < OFFLINE_NOTIFY_AFTER)           return;

    $flagFile = SESSION_DIR . '/' . $sessionId . '_notified.txt';
    if (file_exists($flagFile) && time() - filemtime($flagFile) < 3600) return;
    touch($flagFile);

    $name    = $session['user_info']['name'] ?? 'there';
    $preview = mb_substr($message['content'] ?? '(new message)', 0, 200);
    $pageUrl = $session['page_url'] ?? '';

    $subject = '=?UTF-8?B?' . base64_encode(COMPANY_NAME . ': you have a new reply') . '?=';
    $body    = "Hi {$name},\n\n"
        . "Our support team replied to your chat:\n\n"
        . "\"{$preview}\"\n\n"
        . "Continue the conversation:\n{$pageUrl}\n\n"
        . '— ' . COMPANY_NAME;
    $headers = 'From: ' . OFFLINE_NOTIFY_EMAIL . "\r\nContent-Type: text/plain; charset=UTF-8\r\n";

    @mail($email, $subject, $body, $headers);
}

function updateAgentMessageInSession(string $sessionId, int $telegramMsgId, array $fields): void
{
    $path = SESSION_DIR . '/' . $sessionId . '.json';
    if (!file_exists($path)) return;
    $fp = fopen($path, 'r+');
    flock($fp, LOCK_EX);
    $session = json_decode(stream_get_contents($fp), true) ?? [];
    foreach ($session['history'] as &$msg) {
        if (($msg['telegram_message_id'] ?? null) === $telegramMsgId) {
            foreach ($fields as $k => $v) $msg[$k] = $v;
            break;
        }
    }
    unset($msg);
    rewind($fp); ftruncate($fp, 0);
    fwrite($fp, json_encode($session, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    flock($fp, LOCK_UN); fclose($fp);
}

function recordAgentActivity(string $agentName): void
{
    $path   = UPDATES_DIR . '/agent_status.json';
    $fp     = fopen($path, 'c+');
    flock($fp, LOCK_EX);
    $status = json_decode(stream_get_contents($fp), true) ?? [];
    $status['last_activity_at']  = time();
    $status['last_active_agent'] = $agentName;
    rewind($fp); ftruncate($fp, 0);
    fwrite($fp, json_encode($status));
    flock($fp, LOCK_UN); fclose($fp);
}

function setAgentManualStatus(string $agentName, string $value): void
{
    $path   = UPDATES_DIR . '/agent_status.json';
    $fp     = fopen($path, 'c+');
    flock($fp, LOCK_EX);
    $status = json_decode(stream_get_contents($fp), true) ?? [];
    $status['manual']    = $value;
    $status['manual_by'] = $agentName;
    $status['manual_at'] = time();
    rewind($fp); ftruncate($fp, 0);
    fwrite($fp, json_encode($status));
    flock($fp, LOCK_UN); fclose($fp);
}

function generateUuid(): string
{
    $data    = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
