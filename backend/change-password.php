<?php
require_once __DIR__ . '/session_config.php';
require_once 'database.php';

header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$currentPassword = $_POST['currentPassword'] ?? '';
$newPassword = $_POST['newPassword'] ?? '';

if (empty($currentPassword) || empty($newPassword)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit();
}

if (strlen($newPassword) < 6) {
    echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters']);
    exit();
}

try {
    // Get current admin data
    $adminEmail = $_SESSION['admin']['email'];
    $admin = $db->admins->findOne(['email' => $adminEmail]);
    
    if (!$admin) {
        echo json_encode(['success' => false, 'message' => 'Admin not found']);
        exit();
    }
    
    // Verify current password
    if (!password_verify($currentPassword, $admin->password)) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        exit();
    }

    // Check if new password is same as current
    if (password_verify($newPassword, $admin->password)) {
        echo json_encode(['success' => false, 'message' => 'New password must be different from the current password.']);
        exit();
    }
    
    // Hash new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password and save old one to history
    $result = $db->admins->updateOne(
        ['email' => $adminEmail],
        [
            '$push' => ['password_history' => $admin->password],
            '$set' => [
                'password' => $hashedPassword,
                'password_updated_at' => new MongoDB\BSON\UTCDateTime()
            ]
        ]
    );
    
    if ($result->getModifiedCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No changes made']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
