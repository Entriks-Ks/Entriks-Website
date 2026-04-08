<?php
require_once 'session_config.php';

require_once 'config.php';
$currentLang = $defaultLanguage ?? 'de';
setcookie('preferred_lang', $currentLang, time() + (365 * 24 * 60 * 60), '/');  // 1 year

require 'database.php';

// Clear Remember Me Token from DB
if (isset($_SESSION['admin']) && $db) {
    $db->admins->updateOne(
        ['_id' => new MongoDB\BSON\ObjectId($_SESSION['admin']['id'])],
        ['$unset' => ['remember_token' => '', 'token_expires' => '']]
    );
}

// Clear Remember Me Cookie
if (isset($_COOKIE['remember_me'])) {
    setcookie('remember_me', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
}

session_destroy();
header('Location: login.php');
exit;
