<?php
// =============================================================================
// Telegram Support Chat — Configuration
// Copy your deployment-specific values into config.local.php (gitignored).
// =============================================================================

// Load local overrides first — anything defined there wins.
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

// --- Telegram ---
defined('TELEGRAM_BOT_TOKEN')           || define('TELEGRAM_BOT_TOKEN',           'YOUR_BOT_TOKEN_HERE');
defined('TELEGRAM_CHAT_ID')             || define('TELEGRAM_CHAT_ID',             0);
defined('TELEGRAM_ANNOUNCE_THREAD_ID')  || define('TELEGRAM_ANNOUNCE_THREAD_ID',  null);
defined('BOT_USER_ID')                  || define('BOT_USER_ID',                  0);

// --- Storage Paths (must be writable by web server) ---
defined('DATA_DIR')    || define('DATA_DIR',    __DIR__ . '/data');
defined('SESSION_DIR') || define('SESSION_DIR', DATA_DIR . '/sessions');
defined('UPLOAD_DIR')  || define('UPLOAD_DIR',  DATA_DIR . '/uploads');
defined('UPDATES_DIR') || define('UPDATES_DIR', DATA_DIR . '/updates');

// --- File Upload Limits ---
defined('MAX_UPLOAD_BYTES')   || define('MAX_UPLOAD_BYTES',  10 * 1024 * 1024);
defined('ALLOWED_MIME_TYPES') || define('ALLOWED_MIME_TYPES', [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'application/pdf',
    'audio/webm', 'audio/ogg', 'audio/mpeg', 'audio/mp4', 'audio/wav',
    'video/mp4', 'video/webm',
]);

// --- Session ---
defined('SESSION_TTL_SECONDS') || define('SESSION_TTL_SECONDS', 30 * 24 * 3600);

// --- Offline e-mail notification ---
defined('OFFLINE_NOTIFY_EMAIL') || define('OFFLINE_NOTIFY_EMAIL', '');
defined('OFFLINE_NOTIFY_AFTER') || define('OFFLINE_NOTIFY_AFTER', 5 * 60);
defined('AGENT_ACTIVE_WINDOW')  || define('AGENT_ACTIVE_WINDOW',  15 * 60);

// --- Rate Limiting ---
defined('RATE_LIMIT_PER_MINUTE') || define('RATE_LIMIT_PER_MINUTE', 60);

// --- CORS ---
defined('ALLOWED_ORIGINS') || define('ALLOWED_ORIGINS', []);

// --- Availability Schedule ---
defined('AVAILABILITY_SCHEDULE') || define('AVAILABILITY_SCHEDULE', [
    'timezone' => 'Europe/Berlin',
    'hours'    => [
        'mon' => ['09:00', '18:00'],
        'tue' => ['09:00', '18:00'],
        'wed' => ['09:00', '18:00'],
        'thu' => ['09:00', '18:00'],
        'fri' => ['09:00', '17:00'],
        'sat' => ['09:00', '17:00'],
        'sun' => ['09:00', '17:00'],
    ],
    'offline_message' => "We're currently offline. Leave a message and we'll get back to you as soon as possible!",
]);

// --- Welcome Message ---
defined('WELCOME_MESSAGE') || define('WELCOME_MESSAGE', "Hello 👋 How can we help you today?");

// --- Company / Widget Branding ---
defined('COMPANY_NAME')   || define('COMPANY_NAME',   'Support');
defined('COMPANY_AVATAR') || define('COMPANY_AVATAR', '');

// --- Language ---
defined('LANGUAGE') || define('LANGUAGE', 'en');

// --- Webhook ---
defined('TELEGRAM_WEBHOOK_URL')    || define('TELEGRAM_WEBHOOK_URL',    '');
defined('TELEGRAM_WEBHOOK_SECRET') || define('TELEGRAM_WEBHOOK_SECRET', '');
