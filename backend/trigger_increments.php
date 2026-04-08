<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/database.php';

// Mock standard headers for testing
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) CLI-Test';
$_SERVER['HTTP_HOST'] = 'localhost';

// Trigger main view
echo "--- Triggering main view ---\n";
include 'sync_data.php';
echo "\n";

// Trigger blog view
$post = $db->blog->findOne(['status' => 'published']);
if ($post) {
    $id = (string) $post['_id'];
    $_POST['id'] = $id;
    echo "--- Triggering blog view for post $id ---\n";
    include 'content_sync.php';
    echo "\n";
}
