<?php
require __DIR__ . "/database.php";
$count = $db->translation_queue->countDocuments(['status' => 'pending']);
echo "Pending queue items: $count\n";
