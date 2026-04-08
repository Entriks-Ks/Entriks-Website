<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/database.php';

$since = (string)(time() * 1000 - 60000);

$ch = curl_init("http://localhost/xampp/ENTRIKS/backend/get_notifications.php?since=$since");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);

$data = json_decode($response, true);

if ($data && $data['success']) {
    echo "Successfully retrieved " . count($data['notifications']) . " notifications.\n";
    foreach ($data['notifications'] as $n) {
        echo "- Type: " . $n['type'] . ", Item: " . $n['item_title'] . "\n";
    }
} else {
    echo "Failed to retrieve notifications. Response: " . $response . "\n";
}
