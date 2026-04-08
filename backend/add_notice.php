<?php
require_once __DIR__ . '/session_config.php';
require "database.php";

header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION["admin"])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    $title = trim($input['title'] ?? '');
    $content = trim($input['content'] ?? '');
    
    // Validate input
    if (empty($title)) {
        echo json_encode(['success' => false, 'error' => 'Title is required']);
        exit;
    }
    
    if (empty($content)) {
        echo json_encode(['success' => false, 'error' => 'Content is required']);
        exit;
    }
    
    // Get admin info
    $adminEmail = $_SESSION['admin']['email'] ?? 'Admin';
    $adminName = explode('@', $adminEmail)[0];
    
    // Create notice document
    $notice = [
        'title' => $title,
        'content' => $content,
        'author' => ucfirst($adminName),
        'created_at' => new MongoDB\BSON\UTCDateTime()
    ];
    
    // Insert into database
    $result = $db->notices->insertOne($notice);
    
    if ($result->getInsertedCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Notice added successfully',
            'notice_id' => (string)$result->getInsertedId()
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to add notice'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
