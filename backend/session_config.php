<?php

/**
 * Session Configuration
 * Centralized session management and configuration
 */

// Prevent aggressive caching
header('Cache-Control: no-cache, must-revalidate');  // HTTP 1.1
header('Pragma: no-cache');  // HTTP 1.0
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');  // Date in the past

// Set session save path — use local sessions/ folder if writable, otherwise fall back
// to the system temp dir (needed for read-only filesystems like Render containers)
$sessionPath = __DIR__ . '/sessions';
if (!is_dir($sessionPath)) {
    @mkdir($sessionPath, 0777, true);
}
if (is_dir($sessionPath) && is_writable($sessionPath)) {
    session_save_path($sessionPath);
} else {
    // Fallback: use the system temp directory (always writable in Docker/Render)
    session_save_path(sys_get_temp_dir());
}

// Set session cookie parameters for security
function getCurrentUrl()
{
    return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
}

$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => $cookieParams['lifetime'],
    'path' => $cookieParams['path'],
    'domain' => $cookieParams['domain'],
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',  // Only secure if HTTPS
    'httponly' => true,  // HTTP Only
    'samesite' => 'Lax'  // SameSite attribute (Lax is safer for navigation than Strict)
]);

// Start the session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
