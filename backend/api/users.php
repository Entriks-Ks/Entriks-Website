<?php
require_once '../session_config.php';
require_once '../database.php';
require_once '../vendor/autoload.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

header('Content-Type: application/json');

// Check Authorization
if (!isset($_SESSION['admin']) || ($_SESSION['admin']['role'] ?? 'admin') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // List all users
            $cursor = $db->admins->find([], [
                'projection' => [
                    'password' => 0  // Exclude password
                ],
                'sort' => ['created_at' => -1]
            ]);

            $users = [];
            foreach ($cursor as $doc) {
                // Ensure role exists
                $doc['role'] = $doc['role'] ?? 'admin';
                $doc['id'] = (string) $doc['_id'];
                unset($doc['_id']);  // Remove raw ObjectID

                // Format dates
                if (isset($doc['created_at']) && $doc['created_at'] instanceof MongoDB\BSON\UTCDateTime) {
                    $doc['created_at'] = $doc['created_at']->toDateTime()->format('Y-m-d H:i:s');
                }

                $users[] = $doc;
            }

            echo json_encode(['success' => true, 'users' => $users]);
            break;

        case 'POST':
            // Create new user or Invite
            $data = json_decode(file_get_contents('php://input'), true);

            // Handle Invitation
            if (isset($data['action']) && $data['action'] === 'invite') {
                $email = filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL);
                $role = $data['role'] ?? 'editor';

                if (!$email) {
                    throw new Exception('Valid email is required');
                }

                // Check if user already exists
                $exists = $db->admins->findOne(['email' => $email]);
                if ($exists) {
                    throw new Exception('User already registered with this email');
                }

                // Generate Token
                $token = bin2hex(random_bytes(32));
                $expiry = new UTCDateTime((time() + 48 * 3600) * 1000);  // 48 hours

                // Store in 'invites' collection
                $db->invites->updateOne(
                    ['email' => $email],
                    ['$set' => [
                        'email' => $email,
                        'role' => $role,
                        'token' => $token,
                        'expires_at' => $expiry,
                        'created_at' => new UTCDateTime()
                    ]],
                    ['upsert' => true]
                );

                // Fetch SMTP Settings
                $settings = $db->settings->findOne(['type' => 'global_config']);
                if (!$settings || empty($settings['smtp_host'])) {
                    throw new Exception('SMTP settings not configured in Account Settings');
                }

                $mail = new PHPMailer(true);
                try {
                    // Server settings
                    $mail->isSMTP();
                    $mail->Host = $settings['smtp_host'];
                    $mail->SMTPAuth = true;
                    $mail->Username = $settings['smtp_username'];
                    $mail->Password = $settings['smtp_password'];
                    $mail->SMTPSecure = $settings['smtp_encryption'] === 'none' ? null : $settings['smtp_encryption'];
                    $mail->Port = $settings['smtp_port'];

                    // Sender
                    $fromEmail = $settings['smtp_from_email'] ?? 'noreply@example.com';
                    $fromName = $settings['smtp_from_name'] ?? 'Admin';
                    $mail->setFrom($fromEmail, $fromName);

                    // Recipient
                    $mail->addAddress($email);

                    // Content
                    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'];
                    $path = dirname(dirname($_SERVER['PHP_SELF']));  // Go up to /backend
                    // Fix path slashes for some servers
                    $path = rtrim($path, '/\\');

                    $link = "$protocol://$host$path/accept-invite.php?token=$token";

                    $mail->isHTML(true);
                    $mail->Subject = 'You have been invited to ' . ($settings['site_name'] ?? 'ENTRIKS');
                    $mail->Body = '
                        <h2>Hello!</h2>
                        <p>You have been invited to join the dashboard as an <b>' . ucfirst($role) . "</b>.</p>
                        <p>Click the link below to accept the invitation and set your password:</p>
                        <p><a href='$link'>$link</a></p>
                        <p>This link will expire in 48 hours.</p>
                    ";

                    $mail->send();
                    echo json_encode(['success' => true, 'message' => 'Invitation sent']);
                } catch (Exception $e) {
                    throw new Exception('Message could not be sent. Mailer Error: ' . $mail->ErrorInfo);
                }
                break;
            }

            // Normal Create User (Direct)

            $email = filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL);
            $password = $data['password'] ?? '';
            $role = $data['role'] ?? 'editor';
            $displayName = $data['display_name'] ?? '';

            if (!$email || !$password) {
                throw new Exception('Email and Password are required');
            }

            // Check existing
            $exists = $db->admins->findOne(['email' => $email]);
            if ($exists) {
                throw new Exception('User already exists with this email');
            }

            $result = $db->admins->insertOne([
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $role,  // 'admin' or 'editor'
                'display_name' => $displayName,
                'created_at' => new MongoDB\BSON\UTCDateTime()
            ]);

            echo json_encode(['success' => true, 'id' => (string) $result->getInsertedId()]);
            break;

        case 'DELETE':
            // Delete user
            $id = $_GET['id'] ?? '';

            if (!$id) {
                throw new Exception('User ID is required');
            }

            // Prevent deleting self
            if ($id === $_SESSION['admin']['id']) {
                throw new Exception('Cannot delete your own account');
            }

            $result = $db->admins->deleteOne(['_id' => new MongoDB\BSON\ObjectId($id)]);

            if ($result->getDeletedCount() === 0) {
                throw new Exception('User not found');
            }

            echo json_encode(['success' => true]);
            break;

        default:
            throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
