<?php

if (function_exists('set_time_limit')) {
    set_time_limit(30);
}
ini_set('max_execution_time', '30');

require __DIR__ . '/vendor/autoload.php';

$db = null;
try {
    $client = new MongoDB\Client(
        'mongodb+srv://itentriks_db_user:hiWTv6Q4ke2qg6kd@entriks.3isuasp.mongodb.net/?retryWrites=true&w=majority',
        [
            'connectTimeoutMS' => 5000,
            'serverSelectionTimeoutMS' => 5000
        ]
    );
    $db = $client->selectDatabase('entriks_db');
} catch (Exception $e) {
    error_log('MongoDB Connection Error: ' . $e->getMessage());
}

// Refresh permissions from DB for editors on every request
// (Admins edit other users but the session only updates on self-edit — this fixes that)
if ($db && isset($_SESSION['admin']) && ($_SESSION['admin']['role'] ?? 'admin') === 'editor') {
    try {
        $__refreshedAdmin = $db->admins->findOne(
            ['_id' => new MongoDB\BSON\ObjectId($_SESSION['admin']['id'])],
            ['projection' => ['permissions' => 1, 'display_name' => 1, 'email' => 1, 'position' => 1]]
        );
        if ($__refreshedAdmin) {
            $__perms = $__refreshedAdmin['permissions'] ?? [];
            if ($__perms instanceof MongoDB\Model\BSONArray) {
                $__perms = $__perms->getArrayCopy();
            } else {
                $__perms = array_values((array) $__perms);
            }
            $_SESSION['admin']['permissions'] = $__perms;
            $_SESSION['admin']['name'] = $__refreshedAdmin['display_name'] ?? $_SESSION['admin']['name'];
            $_SESSION['admin']['email'] = $__refreshedAdmin['email'] ?? $_SESSION['admin']['email'];
            $_SESSION['admin']['position'] = $__refreshedAdmin['position'] ?? '';
        }
        unset($__refreshedAdmin, $__perms);
    } catch (Exception $__e) {
        // Silently ignore — keep existing session data if refresh fails
    }
}
