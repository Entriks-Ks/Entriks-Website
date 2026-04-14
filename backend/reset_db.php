<?php
// backend/reset_db.php - DANGER: This deletes all data
require_once __DIR__ . '/database.php';

header('Content-Type: text/plain');

if (!isset($db)) {
    die("Database connection failed.\n");
}

$confirm = $_GET['confirm'] ?? '';
if ($confirm !== 'yes') {
    die("WARNING: This will DELETE ALL DATA!\nAdd ?confirm=yes to proceed.\n");
}

// List of collections to drop
$collections = [
    'admins', 'blog', 'categories', 'comment_votes', 'comments',
    'content_blocks', 'fs.chunks', 'fs.files', 'invites',
    'main_views', 'notifications', 'page_structure', 'password_resets',
    'settings', 'visitor_log'
];

foreach ($collections as $collectionName) {
    try {
        $db->dropCollection($collectionName);
        echo "Dropped: $collectionName\n";
    } catch (Exception $e) {
        echo "Skipped (may not exist): $collectionName\n";
    }
}

echo "\nDatabase reset complete!\n";
echo "Now run:\n";
echo "1. insert_admin.php - to create admin user\n";
echo "2. seed_content.php - to seed default content\n";
?>
