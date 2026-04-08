<?php
// change_password.php
require_once 'session_config.php';
require_once 'database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$current = $data['current_password'] ?? '';
$new = $data['new_password'] ?? '';

if (!$current || !$new) {
    echo json_encode(['success' => false, 'error' => 'All fields required.']);
    exit;
}

if (!isset($_SESSION['admin']['id'])) {
    echo json_encode(['success' => false, 'error' => 'User not found.']);
    exit;
}
$user = $db->admins->findOne(['_id' => new MongoDB\BSON\ObjectId($_SESSION['admin']['id'])]);
if (!$user || !isset($user['password']) || $user['password'] === null || !password_verify($current, $user['password'])) {
    echo json_encode(['success' => false, 'error' => 'Current password is incorrect.']);
    exit;
}
if (strlen($new) < 6) {
    echo json_encode(['success' => false, 'error' => 'New password must be at least 6 characters.']);
    exit;
}
// Check if new password is same as current
if (password_verify($new, $user['password'])) {
    echo json_encode(['success' => false, 'error' => 'New password must be different from the current password.']);
    exit;
}

// Save current password to history before updating
$db->admins->updateOne([
    '_id' => new MongoDB\BSON\ObjectId($_SESSION['admin']['id'])
], [
    '$push' => ['password_history' => $user['password']],
    '$set' => [
        'password' => password_hash($new, PASSWORD_DEFAULT),
        'password_updated_at' => new MongoDB\BSON\UTCDateTime()
    ]
]);
echo json_encode(['success' => true]);
