<?php
// backend/check_db.php
require_once __DIR__ . '/database.php';

header('Content-Type: text/plain');

if (!isset($db)) {
    die("Database connection failed.\n");
}

$keys = ['testimonial_1_text', 'testimonial_1_name'];
$pages = ['home', 'home_en'];

foreach ($pages as $page) {
    echo "Page: $page\n";
    foreach ($keys as $key) {
        $doc = $db->content_blocks->findOne(['page_id' => $page, 'block_key' => $key]);
        if ($doc) {
            echo "  Key: $key\n";
            echo '  Content: ' . $doc['content'] . "\n";
            if (isset($doc['draft_content'])) {
                echo '  Draft: ' . $doc['draft_content'] . "\n";
            }
        } else {
            echo "  Key: $key NOT FOUND\n";
        }
    }
}
?>
