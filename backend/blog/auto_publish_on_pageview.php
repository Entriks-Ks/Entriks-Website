<?php
// Lightweight page-view-triggered publisher: runs process_scheduled_posts.php at most once every 60 seconds.

$lockFile = __DIR__ . '/.last_scheduled_run';
$statusFile = __DIR__ . '/.last_scheduled_status.json';
$now = time();
$interval = 60; // seconds between runs

// If lock file doesn't exist or is older than interval, run the scheduled processor
$last = 0;
if (file_exists($lockFile)) {
    $last = (int) @file_get_contents($lockFile);
}

$status = [
    'last_checked' => $now,
    'ran' => false,
    'published_count' => 0,
    'output' => '',
    'error' => null,
    'next_allowed' => $last + $interval
];

if (($now - $last) >= $interval) {
    // update lock immediately to avoid concurrent runs
    @file_put_contents($lockFile, (string)$now, LOCK_EX);
    // run processor and capture output
    try {
        ob_start();
        // include processor which now sets $publishedCount when included
        require_once __DIR__ . '/process_scheduled_posts.php';
        $out = ob_get_clean();
        $published = isset($publishedCount) ? (int)$publishedCount : 0;
        $status['ran'] = true;
        $status['published_count'] = $published;
        $status['output'] = is_string($out) ? $out : '';
        $status['next_allowed'] = $now + $interval;
    } catch (Throwable $e) {
        $status['ran'] = false;
        $status['error'] = $e->getMessage();
        // restore last timestamp on failure
        @file_put_contents($lockFile, (string)$last, LOCK_EX);
    }
    // write status file
    @file_put_contents($statusFile, json_encode($status), LOCK_EX);
} else {
    // If we didn't run, read existing status if available to report
    if (file_exists($statusFile)) {
        $existing = @file_get_contents($statusFile);
        $decoded = json_decode($existing, true);
        if (is_array($decoded)) {
            $status = array_merge($status, $decoded);
        }
    }
    // update next_allowed based on last
    $status['next_allowed'] = $last + $interval;
}
// ensure status file exists
@file_put_contents($statusFile, json_encode($status), LOCK_EX);