<?php
require_once __DIR__ . '/session_config.php';
require "database.php";

header('Content-Type: application/json');

if (!isset($_SESSION['admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['action'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$email = $_SESSION['admin']['email'];
$action = $input['action']; // 'welcome_seen' or 'complete_page'
$value = $input['value'] ?? true;

if (!$db) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

try {
    if ($action === 'welcome_seen') {
        $db->admins->updateOne(
            ['email' => $email],
            ['$set' => ['onboarding.welcome_seen' => true]]
        );
    } elseif ($action === 'complete_page') {
        $db->admins->updateOne(
            ['email' => $email],
            ['$addToSet' => ['onboarding.viewed_pages' => $value]]
        );
    } elseif ($action === 'finish_all') {
         $db->admins->updateOne(
            ['email' => $email],
            ['$set' => ['onboarding.finished' => true]]
        );
    } elseif ($action === 'reset_tour') {
        $db->admins->updateOne(
            ['email' => $email],
            ['$unset' => ['onboarding' => '']]
        );
    }
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
