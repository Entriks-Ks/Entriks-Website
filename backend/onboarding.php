<?php
require_once 'session_config.php';
require_once 'database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {
        die('Password must be at least 8 characters long.');
    }

    if ($password !== $confirm_password) {
        die('Passwords do not match.');
    }

    $admin = $db->admins->findOne([
        'invitation_token' => $token,
        'status' => 'pending'
    ]);

    if (!$admin) {
        die('Invalid or expired invitation.');
    }

    // Hash password and activate account
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $result = $db->admins->updateOne(
        ['_id' => $admin['_id']],
        [
            '$set' => [
                'password' => $hash,
                'status' => 'active',
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ],
            '$unset' => [
                'invitation_token' => '',
                'invitation_expires' => ''
            ]
        ]
    );

    if ($result->getModifiedCount()) {
        header('Location: login.php?setup=success');
        exit;
    } else {
        die('Failed to activate account. Please try again.');
    }
}
