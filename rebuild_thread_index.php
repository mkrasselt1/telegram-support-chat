<?php
// =============================================================================
// One-time script: rebuilds data/updates/thread_index.json from session files.
// Run once from CLI after setting up webhook:
//   php rebuild_thread_index.php
// =============================================================================
require_once __DIR__ . '/config.php';

$index   = [];
$rebuilt = 0;
$skipped = 0;

foreach (glob(SESSION_DIR . '/*.json') as $file) {
    $session = json_decode(file_get_contents($file), true);
    $threadId  = $session['thread_id']  ?? null;
    $sessionId = $session['session_id'] ?? null;

    if ($threadId && $sessionId) {
        $index[(string)$threadId] = $sessionId;
        $rebuilt++;
    } else {
        $skipped++;
    }
}

$path = UPDATES_DIR . '/thread_index.json';
file_put_contents($path, json_encode($index, JSON_PRETTY_PRINT));

echo "✅ Rebuilt thread_index.json — {$rebuilt} sessions mapped, {$skipped} skipped (no thread_id).\n";
foreach ($index as $threadId => $sessionId) {
    echo "   thread {$threadId} → session {$sessionId}\n";
}
