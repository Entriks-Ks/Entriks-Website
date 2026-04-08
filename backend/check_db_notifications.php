<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/database.php';

$cursor = $db->notifications->find([], ['limit' => 5, 'sort' => ['timestamp' => -1]]);
$count = $db->notifications->countDocuments();

echo "Total notifications: $count\n";
foreach ($cursor as $doc) {
    echo "- [" . $doc['created_at'] . "] Type: " . $doc['type'] . ", Title: " . $doc['item_title'] . "\n";
}