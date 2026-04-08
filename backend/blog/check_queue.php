<?php
/**
 * Setup Instructions for Async Translation
 * 
 * === WINDOWS ===
 * Use Task Scheduler to run: php C:\xampp\htdocs\xampp\ENTRIKS\backend\blog\process_translation_queue.php
 * Schedule every 1-5 minutes
 * 
 * === LINUX/MAC ===
 * Add to crontab:
 * * * * * /usr/bin/php /var/www/html/ENTRIKS/backend/blog/process_translation_queue.php
 * 
 * === MANUAL TEST ===
 * From command line:
 * cd C:\xampp\htdocs\xampp\ENTRIKS
 * php backend/blog/process_translation_queue.php
 * 
 * === MONITOR QUEUE STATUS ===
 * Visit: /backend/blog/check_queue.php (optional dashboard)
 */

require_once "../database.php";

$stats = [
    'pending' => $db->translation_queue->countDocuments(['status' => 'pending']),
    'completed' => $db->translation_queue->countDocuments(['status' => 'completed']),
    'failed' => $db->translation_queue->countDocuments(['status' => 'failed']),
];

echo json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);