<?php
require_once dirname(__DIR__) . '/session_config.php';
require '../database.php';

if (!isset($_SESSION['admin'])) {
    http_response_code(403);
    exit;
}

try {
    // Get all unique tags from the blog collection
    $tags = $db->blog->distinct('tags');

    // Sort alphabetically
    if ($tags) {
        sort($tags);
    } else {
        $tags = [];
    }

    header('Content-Type: application/json');
    echo json_encode($tags);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
