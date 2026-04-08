<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/database.php';

echo "DEBUG_START\n";
$cursor = $db->main_views->find([], ['sort' => ['date' => -1], 'limit' => 5]);
foreach ($cursor as $doc) {
    $date = $doc['date'] ?? 'NULL';
    $isHour = isset($doc['is_hour']) ? ($doc['is_hour'] ? 'TRUE' : 'FALSE') : 'MISSING';
    $count = $doc['count'] ?? 0;
    echo "Date: [$date] | Count: $count | IsHour: $isHour\n";
}
echo "DEBUG_END\n";
?>