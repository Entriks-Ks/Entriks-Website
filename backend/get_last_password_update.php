<?php
// get_last_password_update.php
require_once 'session_config.php';
require_once 'database.php';
require_once 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin'])) {
    echo json_encode(['last_update' => 'Unknown']);
    exit;
}

try {
    $user = $db->admins->findOne(['_id' => new MongoDB\BSON\ObjectId($_SESSION['admin']['id'])]);
} catch (Exception $e) {
    echo json_encode(['last_update' => 'Unknown']);
    exit;
}
if (!$user || !isset($user['password_updated_at']) || $user['password_updated_at'] === null) {
    echo json_encode(['last_update' => $lang['password_never_changed'] ?? 'Never changed']);
    exit;
}

$updatedAt = $user['password_updated_at'];
if ($updatedAt instanceof MongoDB\BSON\UTCDateTime) {
    $timestamp = $updatedAt->toDateTime()->getTimestamp();
} else {
    $timestamp = (int)$updatedAt;
}

$diff = time() - $timestamp;
if ($diff < 60) {
    $str = $lang['time_just_now'] ?? 'just now';
} elseif ($diff < 3600) {
    $str = sprintf($lang['time_minutes_ago'] ?? '%s minutes ago', floor($diff/60));
} elseif ($diff < 86400) {
    $str = sprintf($lang['time_hours_ago'] ?? '%s hours ago', floor($diff/3600));
} elseif ($diff < 2592000) {
    $str = sprintf($lang['time_days_ago'] ?? '%s days ago', floor($diff/86400));
} elseif ($diff < 31536000) {
    $str = sprintf($lang['time_months_ago'] ?? '%s months ago', floor($diff/2592000));
} else {
    $str = sprintf($lang['time_years_ago'] ?? '%s years ago', floor($diff/31536000));
}
echo json_encode(['last_update' => $str]);
