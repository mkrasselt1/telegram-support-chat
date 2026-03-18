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
    case 'close':  actionClose();  break;
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

    $avail = checkAvailability();
    jsonOk([
        'session_id'       => $sessionId,
        'welcome_message'  => WELCOME_MESSAGE,
        'company_name'     => COMPANY_NAME,
        'company_avatar'   => COMPANY_AVATAR,
        'availability'     => $avail['online'],
        'availability_label' => $avail['label'],
        'offline_message'  => AVAILABILITY_SCHEDULE['offline_message'],
        'history'          => array_slice($session['history'] ?? [], -50),
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
    $sinceTs   = (int)($_GET['since_ts'] ?? 0);

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
        // Surface agent messages and system events (e.g. session closed)
        $from = $msg['from'] ?? '';
        if ($from === 'agent' || $from === 'system') {
            $new[] = $msg;
        }
    }

    $avail = checkAvailability();
    // Collect edits: agent messages updated since client's last poll timestamp
    $edits = [];
    foreach ($session['history'] as $msg) {
        if (isset($msg['edited_at']) && $msg['edited_at'] >= $sinceTs && ($msg['from'] ?? '') === 'agent') {
            $edits[] = [
                'id'        => $msg['id'],
                'type'      => $msg['type'] ?? 'text',
                'content'   => $msg['content'] ?? '',
                'edited_at' => $msg['edited_at'],
            ];
        }
    }

    $avail = checkAvailability();
    jsonOk([
        'messages'           => $new,
        'edits'              => $edits,
        'availability'       => $avail['online'],
        'availability_label' => $avail['label'],
        'closed'             => ($session['status'] ?? 'open') === 'closed',
    ]);
}

function actionClose(): void
{
    $body      = jsonBody();
    $sessionId = sanitizeSessionId($body['session_id'] ?? '');
    $initiator = in_array($body['initiator'] ?? '', ['user', 'agent'], true)
        ? $body['initiator']
        : 'user';

    if (!$sessionId || !sessionExists($sessionId)) {
        jsonError('Invalid session', 400);
    }

    closeSession($sessionId, $initiator, makeTelegramBot());
    jsonOk([]);
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
    } elseif (str_starts_with($mimeType, 'audio/')) {
        // Telegram sendVoice requires OGG/Opus for native waveform playback.
        // If the browser sent webm (Chrome), convert to ogg/opus via ffmpeg.
        [$voicePath, $converted] = convertToOggOpus($destPath, $mimeType);
        $bot->sendVoice($voicePath, $threadId);
        if ($converted) @unlink($voicePath);
        $msgType = 'voice';
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

function closeSession(string $sessionId, string $initiator, TelegramBot $bot): void
{
    $path = SESSION_DIR . '/' . $sessionId . '.json';
    $fp   = fopen($path, 'r+');
    flock($fp, LOCK_EX);
    $session = json_decode(stream_get_contents($fp), true) ?? [];

    if (($session['status'] ?? 'open') === 'closed') {
        // Already closed — nothing to do
        flock($fp, LOCK_UN);
        fclose($fp);
        return;
    }

    $session['status']    = 'closed';
    $session['closed_at'] = time();
    $session['closed_by'] = $initiator;

    // Append system message so the widget picks it up on the next poll
    $closedMessages = [
        'en' => [
            'user'  => 'You marked this chat as resolved.',
            'agent' => 'Support marked this chat as resolved. ✅',
        ],
        'de' => [
            'user'  => 'Du hast dieses Gespräch als gelöst markiert.',
            'agent' => 'Der Support hat dieses Gespräch als gelöst markiert. ✅',
        ],
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

    rewind($fp);
    ftruncate($fp, 0);
    fwrite($fp, json_encode($session, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    flock($fp, LOCK_UN);
    fclose($fp);

    // Notify the Telegram thread and close the topic
    $threadId = $session['thread_id'] ?? null;
    if ($threadId !== null) {
        try {
            $who = $initiator === 'user' ? 'user' : 'support agent';
            $bot->sendMessage("✅ Chat closed by {$who}.", (int) $threadId);
            $bot->closeForumTopic((int) $threadId);
        } catch (TelegramException $e) {
            error_log('Support Chat closeSession: ' . $e->getMessage());
        }
    }
}

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
            $isEdit = isset($update['edited_message']) && !isset($update['message']);
            $msg    = $update['message'] ?? $update['edited_message'] ?? null;
            if (!$msg) continue;

            // Skip messages from bots (including ourselves)
            if ($msg['from']['is_bot'] ?? false) continue;
            if (BOT_USER_ID !== 0 && (int)($msg['from']['id'] ?? 0) === BOT_USER_ID) continue;

            $threadId = $msg['message_thread_id'] ?? null;

            // Extract command early — /online and /offline work in any thread,
            // including the General topic where message_thread_id is absent.
            $agentName = trim(($msg['from']['first_name'] ?? '') . ' ' . ($msg['from']['last_name'] ?? '')) ?: COMPANY_NAME;
            $text      = trim($msg['text'] ?? '');
            $command   = strtolower(preg_replace('/@\S+$/', '', $text));

            if ($command === '/online' || $command === '/offline') {
                setAgentManualStatus($agentName, ltrim($command, '/'));
                if ($threadId !== null) {
                    $reply = $command === '/online'
                        ? "✅ Status set to <b>online</b> (valid 12 h)."
                        : "🔴 Status set to <b>offline</b> (valid 12 h).";
                    try { $bot->sendMessage($reply, (int)$threadId); } catch (TelegramException $e) {}
                }
                continue;
            }

            $sessionId = $threadIndex[(string)$threadId] ?? null;
            if (!$sessionId) continue;

            // Edited message — update in-place instead of appending
            if ($isEdit) {
                $telegramMsgId = (int)($msg['message_id'] ?? 0);
                if ($telegramMsgId && !empty($msg['text'])) {
                    updateAgentMessageInSession($sessionId, $telegramMsgId, [
                        'content'   => $msg['text'],
                        'edited_at' => time(),
                    ]);
                }
                continue;
            }

            if (in_array($command, ['/close', '/resolved', '/done', '/closed'], true)) {
                closeSession($sessionId, 'agent', $bot);
                continue;
            }

            $agentMsg = formatAgentMessage($msg);
            $agentMsg = resolveAgentFile($agentMsg, $sessionId, $bot);
            appendToInbox($sessionId, $agentMsg);
            recordAgentActivity($agentName);
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

/**
 * If an agent message carries a telegram_file_id (image, voice, audio, document),
 * download the file locally and replace the opaque ID with a servable file_url.
 */
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

function agentStatusPath(): string
{
    return UPDATES_DIR . '/agent_status.json';
}

function loadAgentStatus(): array
{
    $path = agentStatusPath();
    if (!file_exists($path)) return [];
    $fp   = fopen($path, 'r');
    flock($fp, LOCK_SH);
    $data = json_decode(stream_get_contents($fp), true) ?? [];
    flock($fp, LOCK_UN);
    fclose($fp);
    return $data;
}

function saveAgentStatus(array $data): void
{
    $path = agentStatusPath();
    $fp   = fopen($path, 'c+');
    flock($fp, LOCK_EX);
    rewind($fp);
    ftruncate($fp, 0);
    fwrite($fp, json_encode($data));
    flock($fp, LOCK_UN);
    fclose($fp);
}

/** Record that an agent sent a message — used as passive online signal. */
function recordAgentActivity(string $agentName): void
{
    $status = loadAgentStatus();
    $status['last_activity_at'] = time();
    $status['last_active_agent'] = $agentName;
    saveAgentStatus($status);
}

/** Handle /online or /offline command from an agent. */
function setAgentManualStatus(string $agentName, string $value): void
{
    $status = loadAgentStatus();
    $status['manual']      = $value;       // 'online' | 'offline'
    $status['manual_by']   = $agentName;
    $status['manual_at']   = time();
    saveAgentStatus($status);
}

/**
 * Returns availability + a short label for the widget status line.
 * Priority:
 *   1. Explicit /offline set within 12 h            → offline
 *   2. Explicit /online  set within 12 h            → online
 *   3. Agent sent a message within AGENT_ACTIVE_WINDOW seconds → online
 *   4. Schedule                                      → online/offline
 */
function checkAvailability(): array
{
    $lang    = defined('LANGUAGE') ? LANGUAGE : 'en';
    $labels  = [
        'en' => [
            'online_manual'   => '%s is online',
            'offline_manual'  => "We'll be back soon",
            'active'          => '%s is active',
            'available'       => 'Available now',
            'offline_sched'   => 'Currently offline',
        ],
        'de' => [
            'online_manual'   => '%s ist online',
            'offline_manual'  => 'Wir sind gleich zurück',
            'active'          => '%s ist aktiv',
            'available'       => 'Jetzt verfügbar',
            'offline_sched'   => 'Zurzeit offline',
        ],
    ];
    $l = $labels[$lang] ?? $labels['en'];

    $status    = loadAgentStatus();
    $now       = time();
    $manualAge = isset($status['manual_at']) ? $now - (int)$status['manual_at'] : PHP_INT_MAX;

    // 1 & 2 — explicit manual override (valid for 12 hours)
    if ($manualAge < 12 * 3600 && isset($status['manual'])) {
        $online = $status['manual'] === 'online';
        $by     = $status['manual_by'] ?? COMPANY_NAME;
        return [
            'online' => $online,
            'label'  => $online ? sprintf($l['online_manual'], $by) : $l['offline_manual'],
            'source' => 'manual',
        ];
    }

    // 3 — recent agent activity
    $activityAge = isset($status['last_activity_at']) ? $now - (int)$status['last_activity_at'] : PHP_INT_MAX;
    if ($activityAge < AGENT_ACTIVE_WINDOW) {
        $by = $status['last_active_agent'] ?? COMPANY_NAME;
        return [
            'online' => true,
            'label'  => sprintf($l['active'], $by),
            'source' => 'activity',
        ];
    }

    // 4 — schedule fallback
    $schedule = AVAILABILITY_SCHEDULE;
    $tz       = new DateTimeZone($schedule['timezone']);
    $dt       = new DateTime('now', $tz);
    $dayKey   = strtolower($dt->format('D'));
    $hours    = $schedule['hours'][$dayKey] ?? null;
    $current  = $dt->format('H:i');
    $online   = $hours !== null && $current >= $hours[0] && $current < $hours[1];

    return [
        'online' => $online,
        'label'  => $online ? $l['available'] : ($schedule['offline_message'] ?? $l['offline_sched']),
        'source' => 'schedule',
    ];
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

/**
 * Ensures an audio file is in OGG/Opus format for Telegram's native voice player.
 * Returns [path, wasConverted]. If wasConverted=true, caller should unlink the temp file.
 *
 * - Already ogg  → returned as-is, no conversion needed.
 * - webm/other   → converted via ffmpeg if available; falls back to original on failure.
 */
function convertToOggOpus(string $srcPath, string $mimeType): array
{
    // Already the right format
    if (str_contains($mimeType, 'ogg')) {
        return [$srcPath, false];
    }

    // Check ffmpeg availability
    exec('ffmpeg -version 2>/dev/null', $out, $code);
    if ($code !== 0) {
        // ffmpeg not available — Telegram will still accept the file but
        // may show it as an audio attachment rather than a voice message
        return [$srcPath, false];
    }

    $oggPath = $srcPath . '_converted.ogg';
    $cmd = sprintf(
        'ffmpeg -y -i %s -c:a libopus -b:a 64k -vn %s 2>/dev/null',
        escapeshellarg($srcPath),
        escapeshellarg($oggPath)
    );
    exec($cmd, $output, $returnCode);

    if ($returnCode === 0 && file_exists($oggPath) && filesize($oggPath) > 0) {
        return [$oggPath, true];
    }

    // Conversion failed — use original
    @unlink($oggPath);
    return [$srcPath, false];
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
