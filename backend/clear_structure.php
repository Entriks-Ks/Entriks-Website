<?php
require_once __DIR__ . '/session_config.php';
require_once 'database.php';

if (!isset($_SESSION['admin']) || ($_SESSION['admin']['role'] ?? 'admin') !== 'admin') {
    die('Unauthorized');
}

try {
    $collection = $db->page_structure;
    $result = $collection->deleteOne(['page_id' => 'index']);

    echo 'Structure cleared. Deleted count: ' . $result->getDeletedCount();
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>
