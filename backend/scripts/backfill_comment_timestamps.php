<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../database.php';

// Backfill created_at for comments missing this field
try {
    $result = $db->comments->updateMany(
        ['created_at' => ['$exists' => false]],
        ['$set' => ['created_at' => new MongoDB\BSON\UTCDateTime()]]
    );

    echo 'Matched: ' . $result->getMatchedCount() . PHP_EOL;
    echo 'Modified: ' . $result->getModifiedCount() . PHP_EOL;
    exit(0);
} catch (Exception $e) {
    echo 'Error while backfilling created_at: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
