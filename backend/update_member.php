<?php
require_once 'session_config.php';
require_once 'database.php';

// Check if user is logged in as admin
if (!isset($_SESSION['admin']) || ($_SESSION['admin']['role'] ?? 'admin') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $displayName = $_POST['display_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $position = $_POST['position'] ?? '';
    $permissions = $_POST['permissions'] ?? [];

    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        exit;
    }

    if (is_string($permissions)) {
        $permissions = $permissions === '' ? [] : explode(',', $permissions);
    }

    try {
        $objectId = new MongoDB\BSON\ObjectId($id);

        // Fetch current member
        $member = $db->admins->findOne(['_id' => $objectId]);
        if (!$member) {
            echo json_encode(['success' => false, 'message' => 'Member not found']);
            exit;
        }

        // Check if email is already taken by another user
        $existingUser = $db->admins->findOne([
            'email' => $email,
            '_id' => ['$ne' => $objectId]
        ]);
        if ($existingUser) {
            echo json_encode(['success' => false, 'message' => 'Another user already exists with this email']);
            exit;
        }

        $updateData = [
            'display_name' => $displayName,
            'position' => $position,
            'permissions' => $permissions,
            'updated_at' => new MongoDB\BSON\UTCDateTime()
        ];

        // Email verification logic
        $emailChanged = ($email !== ($member['email'] ?? ''));
        if ($emailChanged) {
            $updateData['email'] = $email;
            $updateData['email_verified'] = false;
            $token = bin2hex(random_bytes(32));
            $expires = new MongoDB\BSON\UTCDateTime((time() + (24 * 3600)) * 1000);
            $updateData['email_verification_token'] = $token;
            $updateData['email_verification_expires'] = $expires;

            // Send verification email
            require_once 'resend_helper.php';
            require_once 'config.php';
            $product_name = $siteConfig['site_name'] ?? 'ENTRIKS';
            $verifyLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/verify_email.php?token=$token";
            $subject = "Verify your new email address for $product_name";
            $html = "<p>Hi {$displayName},</p><p>You requested to change your email address for <strong>{$product_name}</strong>. Please verify your new email by clicking the link below within 24 hours:</p><p><a href='$verifyLink' style='color:#d225d7;font-weight:bold;'>Verify Email</a></p><p>If you did not request this change, please ignore this email.</p>";
            $emailResult = sendResendEmail($email, $subject, $html);
            // Optionally handle $emailResult for errors
        } else {
            $updateData['email'] = $email;
        }

        $result = $db->admins->updateOne(
            ['_id' => $objectId],
            ['$set' => $updateData]
        );

        if ($result->getModifiedCount() > 0 || $result->getMatchedCount() > 0) {
            // Refresh session data if updating self
            if ($id === ($_SESSION['admin']['id'] ?? '')) {
                $_SESSION['admin']['name'] = $displayName;
                $_SESSION['admin']['email'] = $email;
                $_SESSION['admin']['permissions'] = $permissions;
            }
            $msg = $emailChanged ? 'Member updated. Verification email sent.' : 'Member updated successfully';
            echo json_encode(['success' => true, 'message' => $msg]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No changes made or member not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>
