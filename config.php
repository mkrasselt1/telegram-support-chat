<?php
// =============================================================================
// Telegram Support Chat — Configuration
// =============================================================================

// --- Telegram ---
const TELEGRAM_BOT_TOKEN        = 'YOUR_BOT_TOKEN_HERE';
const TELEGRAM_CHAT_ID          = -1001234567890;   // Supergroup ID with Topics enabled
const TELEGRAM_ANNOUNCE_THREAD_ID = null;            // Optional: thread ID for "new session" announcements

// --- Storage Paths (must be writable by web server) ---
// These use __DIR__ which is a runtime value, so define() is required here.
define('DATA_DIR',    __DIR__ . '/data');
define('SESSION_DIR', DATA_DIR . '/sessions');
define('UPLOAD_DIR',  DATA_DIR . '/uploads');
define('UPDATES_DIR', DATA_DIR . '/updates');

// --- File Upload Limits ---
const MAX_UPLOAD_BYTES  = 10 * 1024 * 1024;  // 10 MB
const ALLOWED_MIME_TYPES = [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'application/pdf',
    'audio/webm', 'audio/ogg', 'audio/mpeg', 'audio/mp4', 'audio/wav',
    'video/mp4', 'video/webm',
];

// --- Session ---
const SESSION_TTL_SECONDS = 30 * 24 * 3600; // 30 days — sessions older than this get cleaned up

// --- Offline e-mail notification ---
// If the user has an email in their user_info and has been away longer than
// OFFLINE_NOTIFY_AFTER seconds when an agent reply arrives, an e-mail is sent.
// Set OFFLINE_NOTIFY_EMAIL to your sender address, or '' to disable.
const OFFLINE_NOTIFY_EMAIL = '';        // e.g. 'support@yoursite.com'
const OFFLINE_NOTIFY_AFTER = 5 * 60;   // 5 minutes away = considered offline

// --- Rate Limiting ---
const RATE_LIMIT_PER_MINUTE = 60;

// --- CORS: list of allowed origins (empty = allow all, not recommended in production) ---
const ALLOWED_ORIGINS = [
    // 'https://yoursite.com',
    // 'http://localhost',
];

// --- Availability Schedule ---
const AVAILABILITY_SCHEDULE = [
    'timezone' => 'Europe/Berlin',
    'hours'    => [
        'mon' => ['09:00', '18:00'],
        'tue' => ['09:00', '18:00'],
        'wed' => ['09:00', '18:00'],
        'thu' => ['09:00', '18:00'],
        'fri' => ['09:00', '17:00'],
        'sat' => null,   // closed
        'sun' => null,   // closed
    ],
    'offline_message' => "We're currently offline. Leave a message and we'll get back to you as soon as possible!",
];

// --- Welcome Message (shown when a new chat is opened) ---
const WELCOME_MESSAGE = "Hello! 👋 How can we help you today?";

// --- Company / Widget Branding ---
const COMPANY_NAME   = 'Support Team';
const COMPANY_AVATAR = '';  // URL to avatar image, or '' for initials

// --- Telegram bot self-filtering: fill in your bot's numeric user ID ---
// (run getMe to find it: https://api.telegram.org/bot<TOKEN>/getMe)
const BOT_USER_ID = 0;

// --- Webhook ---
// Set TELEGRAM_WEBHOOK_URL to your public webhook.php URL to enable webhook mode.
// When set, the poll action skips getUpdates (Telegram pushes updates instead).
// Leave empty to fall back to getUpdates polling (works without a public HTTPS URL).
//
// Setup: php register_webhook.php set https://yoursite.com/webhook.php
const TELEGRAM_WEBHOOK_URL    = '';                  // e.g. 'https://yoursite.com/webhook.php'
const TELEGRAM_WEBHOOK_SECRET = '';                  // random string, e.g. bin2hex(random_bytes(16))
