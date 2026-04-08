<?php
require 'database.php';

if (!isset($db)) {
    die('Database not connected');
}

$pagesToReset = ['home', 'home_en'];
$count = 0;

foreach ($pagesToReset as $pageId) {
    try {
        $result = $db->content_blocks->deleteMany(['page_id' => $pageId]);
        $count += $result->getDeletedCount();
        echo "Reset page '$pageId': Deleted " . $result->getDeletedCount() . " blocks.\n";
    } catch (Exception $e) {
        echo "Error resetting page $pageId: " . $e->getMessage() . "\n";
    }
}

echo "Total blocks removed: $count";
?>