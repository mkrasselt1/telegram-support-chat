<?php
// =============================================================================
// Telegram Support Chat — Main API Endpoint
// =============================================================================
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/telegram.php';

// --- Initialise data directories ---
foreach ([DATA_DIR, SESSION_DIR, UPLOAD_DIR, UPDATES_DIR] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
}

// --- CORS ---
handleCors();

// --- Rate limiting ---
if (!checkRateLimit()) {
    jsonError('Too many requests. Please slow down.', 429);
}

// --- Route request ---
$action = $_GET['action'] ?? ($_POST['action'] ?? (json_decode(file_get_contents('php://input'), true)['action'] ?? ''));

switch ($action) {
    case 'init':   actionInit();   break;
    case 'send':   actionSend();   break;
    case 'poll':   actionPoll();   break;
    case 'upload': actionUpload(); break;
    case 'file':   actionFile();   break;
    default:       jsonError('Unknown action', 400);
}

// =============================================================================
// ACTIONS
// =============================================================================

function actionInit(): void
{
    $body      = jsonBody();
    $sessionId = sanitizeSessionId($body['session_id'] ?? '');
    $userInfo  = sanitizeUserInfo($body['user'] ?? []);
    $pageUrl   = filter_var($body['page_url'] ?? '', FILTER_SANITIZE_URL);
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $bot = makeTelegramBot();

    if ($sessionId && sessionExists($sessionId)) {
        // Returning visitor — load existing session
        $session = loadSession($sessionId);
        touchSession($sessionId);
    } else {
        // New visitor
        $sessionId = generateUuid();
        $session   = createSession($sessionId, $userInfo, $pageUrl, $userAgent, $bot);
    }

    jsonOk([
        'session_id'       => $sessionId,
        'welcome_message'  => WELCOME_MESSAGE,
        'company_name'     => COMPANY_NAME,
        'company_avatar'   => COMPANY_AVATAR,
        'availability'     => checkAvailability(),
        'offline_message'  => AVAILABILITY_SCHEDULE['offline_message'],
        'history'          => array_slice($session['history'] ?? [], -50),  // last 50 msgs
    ]);
}

function actionSend(): void
{
    $body      = jsonBody();
    $sessionId = sanitizeSessionId($body['session_id'] ?? '');
    $type      = $body['type'] ?? 'text';

    if (!$sessionId || !sessionExists($sessionId)) {
        jsonError('Invalid session', 400);
    }

    $session = loadSession($sessionId);
    $bot     = makeTelegramBot();
    $threadId = (int) $session['thread_id'];

    $message = [
        'id'        => generateUuid(),
        'from'      => 'user',
        'type'      => $type,
        'timestamp' => time(),
    ];

    switch ($type) {
        case 'text':
            $text = trim($body['text'] ?? '');
            if ($text === '') jsonError('Empty message', 400);
            $text = mb_substr($text, 0, 4096);
            $message['content'] = $text;

            $userName = $session['user_info']['name'] ?? 'User';
            $formatted = "<b>{$userName}:</b>\n" . htmlspecialchars($text, ENT_QUOTES | ENT_HTML5);
            $bot->sendMessage($formatted, $threadId);
            break;

        case 'location':
            $lat = (float) ($body['lat'] ?? 0);
            $lng = (float) ($body['lng'] ?? 0);
            if ($lat === 0.0 && $lng === 0.0) jsonError('Invalid location', 400);
            $message['content'] = "📍 Location";
            $message['lat']     = $lat;
            $message['lng']     = $lng;
            $bot->sendLocation($lat, $lng, $threadId);
            break;

        default:
            jsonError('Use /upload for file messages', 400);
    }

    appendMessageToSession($sessionId, $message);

    jsonOk(['message' => $message]);
}

function actionPoll(): void
{
    $sessionId = sanitizeSessionId($_GET['session_id'] ?? '');
    $sinceId   = $_GET['since_id'] ?? '';

    if (!$sessionId || !sessionExists($sessionId)) {
        jsonError('Invalid session', 400);
    }

    touchSession($sessionId);

    // Fetch Telegram updates via getUpdates — only when no webhook is configured.
    // In webhook mode Telegram pushes updates to webhook.php directly, so polling
    // here would be redundant and could cause offset conflicts.
    if (TELEGRAM_WEBHOOK_URL === '') {
        fetchAndDistributeUpdates(makeTelegramBot());
    }

    // Read agent messages from persistent history since last known message ID.
    // Using history (not a drainable inbox) means:
    //   - multiple browser tabs of the same user all get their messages
    //   - a user who returns after days still sees every reply in order
    $session  = loadSession($sessionId);
    $history  = $session['history'] ?? [];
    $new      = [];
    $found    = ($sinceId === '');   // if no sinceId, return everything from agent

    foreach ($history as $msg) {
        if (!$found) {
            if (($msg['id'] ?? '') === $sinceId) {
                $found = true;
            }
            continue;
        }
        // Only surface messages sent by the agent (user messages the client already knows)
        if (($msg['from'] ?? '') === 'agent') {
            $new[] = $msg;
        }
    }

    jsonOk([
        'messages'     => $new,
        'availability' => checkAvailability(),
    ]);
}

function actionUpload(): void
{
    $sessionId = sanitizeSessionId($_POST['session_id'] ?? '');

    if (!$sessionId || !sessionExists($sessionId)) {
        jsonError('Invalid session', 400);
    }

    if (empty($_FILES['file'])) {
        jsonError('No file uploaded', 400);
    }

    $file    = $_FILES['file'];
    $tmpPath = $file['tmp_name'];
    $origName = basename($file['name']);

    // Validate size
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        jsonError('File too large (max ' . MAX_UPLOAD_BYTES / 1024 / 1024 . ' MB)', 400);
    }

    // Validate MIME via finfo (do NOT trust $_FILES['type'])
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($tmpPath);

    if (!in_array($mimeType, ALLOWED_MIME_TYPES, true)) {
        jsonError('File type not allowed', 400);
    }

    // Save to per-session upload dir
    $sessionUploadDir = UPLOAD_DIR . '/' . $sessionId;
    if (!is_dir($sessionUploadDir)) {
        mkdir($sessionUploadDir, 0750, true);
    }

    $safeName  = preg_replace('/[^a-zA-Z0-9._-]/', '_', $origName);
    $destName  = time() . '_' . $safeName;
    $destPath  = $sessionUploadDir . '/' . $destName;

    if (!move_uploaded_file($tmpPath, $destPath)) {
        jsonError('Failed to save file', 500);
    }

    $bot      = makeTelegramBot();
    $session  = loadSession($sessionId);
    $threadId = (int) $session['thread_id'];
    $userName = $session['user_info']['name'] ?? 'User';
    $caption  = "<b>{$userName}</b> sent a file";

    // Send to Telegram based on type
    if (str_starts_with($mimeType, 'image/')) {
        $bot->sendPhoto($destPath, $threadId, htmlspecialchars($origName, ENT_QUOTES | ENT_HTML5));
        $msgType = 'image';
    } elseif (str_starts_with($mimeType, 'video/')) {
        $bot->sendVideo($destPath, $threadId, $caption);
        $msgType = 'video';
    } elseif (str_starts_with($mimeType, 'audio/') && str_contains($mimeType, 'ogg')) {
        $bot->sendVoice($destPath, $threadId);
        $msgType = 'voice';
    } elseif (str_starts_with($mimeType, 'audio/')) {
        $bot->sendAudio($destPath, $threadId, $caption);
        $msgType = 'audio';
    } else {
        $bot->sendDocument($destPath, $threadId, $caption);
        $msgType = 'file';
    }

    $fileUrl = '?action=file&session_id=' . urlencode($sessionId) . '&name=' . urlencode($destName);

    $message = [
        'id'        => generateUuid(),
        'from'      => 'user',
        'type'      => $msgType,
        'content'   => $origName,
        'file_url'  => $fileUrl,
        'mime_type' => $mimeType,
        'timestamp' => time(),
    ];

    appendMessageToSession($sessionId, $message);

    jsonOk(['message' => $message]);
}

function actionFile(): void
{
    $sessionId = sanitizeSessionId($_GET['session_id'] ?? '');
    $name      = basename($_GET['name'] ?? '');

    if (!$sessionId || !$name) {
        http_response_code(400); exit;
    }

    $filePath = UPLOAD_DIR . '/' . $sessionId . '/' . $name;
    $realPath = realpath($filePath);

    // Path traversal check
    $uploadBase = realpath(UPLOAD_DIR);
    if (!$realPath || !str_starts_with($realPath, $uploadBase)) {
        http_response_code(404); exit;
    }

    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($realPath);

    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . filesize($realPath));
    header('Content-Disposition: inline; filename="' . addslashes(basename($name)) . '"');
    header('Cache-Control: private, max-age=86400');
    readfile($realPath);
    exit;
}

// =============================================================================
// SESSION MANAGEMENT
// =============================================================================

function sessionExists(string $sessionId): bool
{
    return is_file(SESSION_DIR . '/' . $sessionId . '.json');
}

function createSession(string $sessionId, array $userInfo, string $pageUrl, string $userAgent, TelegramBot $bot): array
{
    // Use exclusive file create to prevent race conditions
    $sessionFile = SESSION_DIR . '/' . $sessionId . '.json';
    $fp = @fopen($sessionFile, 'x');
    if (!$fp) {
        // Race: another request created it; load instead
        return loadSession($sessionId);
    }

    flock($fp, LOCK_EX);

    $threadName = 'Session ' . strtoupper(substr($sessionId, 0, 6));
    if (!empty($userInfo['name'])) {
        $threadName .= ' | ' . mb_substr($userInfo['name'], 0, 50);
    }

    // Create Telegram forum topic
    $threadId = null;
    try {
        $topic    = $bot->createForumTopic($threadName);
        $threadId = $topic['message_thread_id'];

        // Send info card to the thread
        $infoCard = buildInfoCard($sessionId, $userInfo, $pageUrl, $userAgent);
        $bot->sendMessage($infoCard, $threadId);

        // Announce to general group (if configured)
        if (defined('TELEGRAM_ANNOUNCE_THREAD_ID') && TELEGRAM_ANNOUNCE_THREAD_ID !== null) {
            $announce = "🆕 New support session started\n"
                . "Session: <code>{$sessionId}</code>\n"
                . "User: " . (empty($userInfo['name']) ? 'Anonymous' : htmlspecialchars($userInfo['name'], ENT_QUOTES | ENT_HTML5));
            $bot->sendMessage($announce, (int) TELEGRAM_ANNOUNCE_THREAD_ID);
        }
    } catch (TelegramException $e) {
        // Fallback: no forum topics (e.g. not a supergroup with Topics)
        // Messages will still go to the group without a thread
        error_log('Support Chat: createForumTopic failed — ' . $e->getMessage());
    }

    $session = [
        'session_id'   => $sessionId,
        'thread_id'    => $threadId,
        'created_at'   => time(),
        'last_activity'=> time(),
        'user_info'    => $userInfo,
        'page_url'     => $pageUrl,
        'user_agent'   => $userAgent,
        'history'      => [],
    ];

    $json = json_encode($session, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    ftruncate($fp, 0);
    fwrite($fp, $json);
    flock($fp, LOCK_UN);
    fclose($fp);

    // Update thread index
    if ($threadId !== null) {
        updateThreadIndex($threadId, $sessionId);
    }

    return $session;
}

function loadSession(string $sessionId): array
{
    $path = SESSION_DIR . '/' . $sessionId . '.json';
    $fp   = fopen($path, 'r');
    flock($fp, LOCK_SH);
    $data = json_decode(stream_get_contents($fp), true) ?? [];
    flock($fp, LOCK_UN);
    fclose($fp);
    return $data;
}

function touchSession(string $sessionId): void
{
    $path = SESSION_DIR . '/' . $sessionId . '.json';
    $fp   = fopen($path, 'r+');
    flock($fp, LOCK_EX);
    $session = json_decode(stream_get_contents($fp), true) ?? [];
    $session['last_activity'] = time();
    rewind($fp);
    ftruncate($fp, 0);
    fwrite($fp, json_encode($session, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    flock($fp, LOCK_UN);
    fclose($fp);
}

function appendMessageToSession(string $sessionId, array $message): void
{
    $path = SESSION_DIR . '/' . $sessionId . '.json';
    $fp   = fopen($path, 'r+');
    flock($fp, LOCK_EX);
    $session = json_decode(stream_get_contents($fp), true) ?? [];
    $session['history'][] = $message;
    // Keep last 500 messages in history
    if (count($session['history']) > 500) {
        $session['history'] = array_slice($session['history'], -500);
    }
    rewind($fp);
    ftruncate($fp, 0);
    fwrite($fp, json_encode($session, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    flock($fp, LOCK_UN);
    fclose($fp);
}

// -------------------------------------------------------------------------
// Inbox: messages from Telegram agent → widget
// -------------------------------------------------------------------------

function inboxPath(string $sessionId): string
{
    return SESSION_DIR . '/' . $sessionId . '_inbox.json';
}

/**
 * Called when a Telegram agent reply arrives.
 * Writes the message into the session history (the single source of truth)
 * and — if the user has an email address and hasn't been active recently —
 * sends an offline notification email.
 */
function appendToInbox(string $sessionId, array $message): void
{
    if (!sessionExists($sessionId)) return;

    // Persist into history (this IS the source of truth for poll responses)
    appendMessageToSession($sessionId, $message);

    // Offline e-mail notification: only if user has been away for > OFFLINE_NOTIFY_AFTER seconds
    maybeNotifyByEmail($sessionId, $message);
}

function maybeNotifyByEmail(string $sessionId, array $message): void
{
    if (!defined('OFFLINE_NOTIFY_EMAIL') || !OFFLINE_NOTIFY_EMAIL) return;
    if (!defined('OFFLINE_NOTIFY_AFTER'))                          return;

    $session  = loadSession($sessionId);
    $lastSeen = (int) ($session['last_activity'] ?? 0);
    $email    = $session['user_info']['email'] ?? '';

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) return;
    if (time() - $lastSeen < OFFLINE_NOTIFY_AFTER)           return;

    // Throttle: one email per session per hour
    $flagFile = SESSION_DIR . '/' . $sessionId . '_notified.txt';
    if (file_exists($flagFile) && time() - filemtime($flagFile) < 3600) return;
    touch($flagFile);

    $name        = $session['user_info']['name'] ?? 'there';
    $companyName = COMPANY_NAME;
    $preview     = mb_substr($message['content'] ?? '(new message)', 0, 200);
    $pageUrl     = $session['page_url'] ?? '';

    $subject = "=?UTF-8?B?" . base64_encode("{$companyName}: you have a new reply") . "?=";
    $body    = "Hi {$name},\n\n"
        . "Our support team replied to your chat message:\n\n"
        . "\"{$preview}\"\n\n"
        . "To continue the conversation, visit:\n{$pageUrl}\n\n"
        . "— {$companyName}";
    $headers = "From: " . OFFLINE_NOTIFY_EMAIL . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n";

    @mail($email, $subject, $body, $headers);
}

// -------------------------------------------------------------------------
// Thread index: thread_id → session_id lookup
// -------------------------------------------------------------------------

function threadIndexPath(): string
{
    return UPDATES_DIR . '/thread_index.json';
}

function updateThreadIndex(int $threadId, string $sessionId): void
{
    $path = threadIndexPath();
    $fp   = fopen($path, 'c+');
    flock($fp, LOCK_EX);
    $index = json_decode(stream_get_contents($fp), true) ?? [];
    $index[(string)$threadId] = $sessionId;
    rewind($fp);
    ftruncate($fp, 0);
    fwrite($fp, json_encode($index));
    flock($fp, LOCK_UN);
    fclose($fp);
}

function loadThreadIndex(): array
{
    $path = threadIndexPath();
    if (!file_exists($path)) return [];
    $fp   = fopen($path, 'r');
    flock($fp, LOCK_SH);
    $data = json_decode(stream_get_contents($fp), true) ?? [];
    flock($fp, LOCK_UN);
    fclose($fp);
    return $data;
}

// -------------------------------------------------------------------------
// Telegram update fetching & distribution
// -------------------------------------------------------------------------

function fetchAndDistributeUpdates(TelegramBot $bot): void
{
    $lockPath = UPDATES_DIR . '/fetch.lock';
    $fp       = fopen($lockPath, 'c');

    // Non-blocking: skip if another process is already fetching
    if (!flock($fp, LOCK_EX | LOCK_NB)) {
        fclose($fp);
        return;
    }

    try {
        $offsetPath = UPDATES_DIR . '/last_update_id.json';
        $offsetData = file_exists($offsetPath)
            ? (json_decode(file_get_contents($offsetPath), true) ?? [])
            : [];
        $offset = (int)($offsetData['last_update_id'] ?? 0);
        if ($offset > 0) $offset++;

        $updates = $bot->getUpdates($offset, 100);

        if (empty($updates)) return;

        $threadIndex = loadThreadIndex();

        foreach ($updates as $update) {
            $msg = $update['message'] ?? $update['edited_message'] ?? null;
            if (!$msg) continue;

            // Skip messages from bots (including ourselves)
            if ($msg['from']['is_bot'] ?? false) continue;
            if (BOT_USER_ID !== 0 && (int)($msg['from']['id'] ?? 0) === BOT_USER_ID) continue;

            $threadId = $msg['message_thread_id'] ?? null;
            if ($threadId === null) continue;  // ignore messages not in a thread

            $sessionId = $threadIndex[(string)$threadId] ?? null;
            if (!$sessionId) continue;

            $agentMsg = formatAgentMessage($msg);
            appendToInbox($sessionId, $agentMsg);
        }

        $lastUpdateId = end($updates)['update_id'];
        file_put_contents($offsetPath, json_encode(['last_update_id' => $lastUpdateId]));

    } catch (TelegramException $e) {
        error_log('Support Chat getUpdates error: ' . $e->getMessage());
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

function formatAgentMessage(array $msg): array
{
    $agentName = trim(($msg['from']['first_name'] ?? '') . ' ' . ($msg['from']['last_name'] ?? ''));
    if (!$agentName) $agentName = COMPANY_NAME;

    $base = [
        'id'         => generateUuid(),
        'from'       => 'agent',
        'agent_name' => $agentName,
        'timestamp'  => $msg['date'],
    ];

    if (!empty($msg['text'])) {
        return array_merge($base, ['type' => 'text', 'content' => $msg['text']]);
    }
    if (!empty($msg['photo'])) {
        $photo   = end($msg['photo']);
        $fileId  = $photo['file_id'];
        $caption = $msg['caption'] ?? '';
        return array_merge($base, ['type' => 'image', 'content' => $caption, 'telegram_file_id' => $fileId]);
    }
    if (!empty($msg['voice'])) {
        return array_merge($base, ['type' => 'voice', 'content' => '🎤 Voice message', 'telegram_file_id' => $msg['voice']['file_id']]);
    }
    if (!empty($msg['audio'])) {
        $title = $msg['audio']['title'] ?? 'Audio';
        return array_merge($base, ['type' => 'audio', 'content' => $title, 'telegram_file_id' => $msg['audio']['file_id']]);
    }
    if (!empty($msg['document'])) {
        $name = $msg['document']['file_name'] ?? 'Document';
        return array_merge($base, ['type' => 'file', 'content' => $name, 'telegram_file_id' => $msg['document']['file_id']]);
    }
    if (!empty($msg['location'])) {
        $lat = $msg['location']['latitude'];
        $lng = $msg['location']['longitude'];
        return array_merge($base, [
            'type'    => 'location',
            'content' => "📍 Location",
            'lat'     => $lat,
            'lng'     => $lng,
        ]);
    }
    if (!empty($msg['sticker'])) {
        $emoji = $msg['sticker']['emoji'] ?? '🎭';
        return array_merge($base, ['type' => 'text', 'content' => $emoji]);
    }

    return array_merge($base, ['type' => 'text', 'content' => '(unsupported message type)']);
}

// =============================================================================
// AVAILABILITY
// =============================================================================

function checkAvailability(): bool
{
    $schedule = AVAILABILITY_SCHEDULE;
    $tz       = new DateTimeZone($schedule['timezone']);
    $now      = new DateTime('now', $tz);
    $dayKey   = strtolower($now->format('D'));  // 'mon', 'tue', etc.
    $hours    = $schedule['hours'][$dayKey] ?? null;

    if ($hours === null) return false;

    [$open, $close] = $hours;
    $current = $now->format('H:i');
    return $current >= $open && $current < $close;
}

// =============================================================================
// RATE LIMITING
// =============================================================================

function checkRateLimit(): bool
{
    $ip      = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $path    = DATA_DIR . '/ratelimit_' . $ip . '.json';
    $now     = time();
    $window  = 60;  // 1 minute

    $fp = fopen($path, 'c+');
    flock($fp, LOCK_EX);
    $data = json_decode(stream_get_contents($fp), true) ?? ['count' => 0, 'reset_at' => $now + $window];

    if ($now > $data['reset_at']) {
        $data = ['count' => 0, 'reset_at' => $now + $window];
    }

    $data['count']++;
    $allowed = $data['count'] <= RATE_LIMIT_PER_MINUTE;

    rewind($fp);
    ftruncate($fp, 0);
    fwrite($fp, json_encode($data));
    flock($fp, LOCK_UN);
    fclose($fp);

    return $allowed;
}

// =============================================================================
// HELPERS
// =============================================================================

function buildInfoCard(string $sessionId, array $userInfo, string $pageUrl, string $userAgent): string
{
    $lines = ["🆕 <b>New Support Session</b>", ""];
    $lines[] = "🔑 Session: <code>{$sessionId}</code>";
    if (!empty($userInfo['name']))  $lines[] = "👤 Name: " . htmlspecialchars($userInfo['name'], ENT_QUOTES | ENT_HTML5);
    if (!empty($userInfo['email'])) $lines[] = "📧 Email: " . htmlspecialchars($userInfo['email'], ENT_QUOTES | ENT_HTML5);
    if (!empty($userInfo['id']))    $lines[] = "🪪 User ID: " . htmlspecialchars((string)$userInfo['id'], ENT_QUOTES | ENT_HTML5);
    if ($pageUrl)                   $lines[] = "🌐 URL: " . htmlspecialchars($pageUrl, ENT_QUOTES | ENT_HTML5);
    if ($userAgent)                 $lines[] = "💻 UA: " . htmlspecialchars(mb_substr($userAgent, 0, 200), ENT_QUOTES | ENT_HTML5);
    $lines[] = "🕐 Time: " . date('Y-m-d H:i:s T');
    return implode("\n", $lines);
}

function sanitizeSessionId(string $id): string
{
    // UUID v4 format only
    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id)) {
        return strtolower($id);
    }
    return '';
}

function sanitizeUserInfo(array $info): array
{
    return [
        'name'  => mb_substr(strip_tags($info['name']  ?? ''), 0, 100),
        'email' => filter_var($info['email'] ?? '', FILTER_SANITIZE_EMAIL),
        'id'    => mb_substr(strip_tags((string)($info['id'] ?? '')), 0, 100),
    ];
}

function generateUuid(): string
{
    $data    = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function makeTelegramBot(): TelegramBot
{
    return new TelegramBot(TELEGRAM_BOT_TOKEN, TELEGRAM_CHAT_ID);
}

function jsonBody(): array
{
    $raw = file_get_contents('php://input');
    return $raw ? (json_decode($raw, true) ?? $_POST) : $_POST;
}

function jsonOk(array $data): never
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function jsonError(string $message, int $code = 400): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

function handleCors(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    $allowed = ALLOWED_ORIGINS;
    if (empty($allowed) || in_array($origin, $allowed, true)) {
        if ($origin) header("Access-Control-Allow-Origin: {$origin}");
    }

    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
    header('Access-Control-Allow-Credentials: false');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}
