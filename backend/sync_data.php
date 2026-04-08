<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/database.php';

header('Content-Type: application/json');

// Simple request checks (no auth):
// - only allow POST
// - if Referer or Origin is present, require host to match server host
// - require a non-empty User-Agent

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log('ENTRIKS Tracking Error: Method not allowed - ' . $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$host = $_SERVER['HTTP_HOST'] ?? '';
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

if (empty($ua) || strlen(trim($ua)) < 6) {
    error_log('ENTRIKS Tracking Error: Invalid User-Agent - ' . $ua);
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid User-Agent']);
    exit;
}

// If Referer or Origin present, verify host matches
foreach (['Referer' => $referer, 'Origin' => $origin] as $type => $val) {
    if (!empty($val)) {
        $p = parse_url($val);
        $rhost = $p['host'] ?? '';

        // Strip port from HTTP_HOST for comparison if parse_url didn't return one
        $currentHost = $host;
        if (strpos($currentHost, ':') !== false) {
            $currentHost = explode(':', $currentHost)[0];
        }

        if ($rhost !== '' && $currentHost !== '' && $rhost !== $currentHost) {
            error_log("ENTRIKS Tracking Error: Invalid $type - received: $rhost, expected: $currentHost (Full header: $val)");
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid origin', 'debug' => ['type' => $type, 'received' => $rhost, 'expected' => $currentHost]]);
            exit;
        }
    }
}

if (!$db) {
    // Gracefully fail for views if DB is down, but don't error out hard
    echo json_encode(['success' => false, 'error' => 'Database unavailable']);
    exit;
}

try {
    // 1. Frequency Control (Cooldown)
    $cookieName = 'last_main_view_tracked';
    if (isset($_COOKIE[$cookieName])) {
        // Already tracked within the last 30 mins, exit silently or log for debug
        echo json_encode(['success' => true, 'status' => 'cooldown', 'message' => 'Visit already tracked recently']);
        exit;
    }

    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie($cookieName, '1', time() + 1800, '/', '', $isSecure, true);

    $today = date('Y-m-d');
    $hour = date('Y-m-d H');

    // Get title from POST if provided
    $pageTitle = $_POST['title'] ?? 'Main Page';

    // 2. Unique Visitor Tracking (visitor_log)
    $ip = '0.0.0.0';
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP']))
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    else
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    $visitorHash = hash('sha256', $ip . $ua . $today);

    // 2. Metadata Extraction (Browser, OS, Language)
    $browser = 'Unknown';
    $os = 'Unknown';
    $language = 'Unknown';

    // Basic User-Agent Parsing
    if (preg_match('/(Edge|Edg)\/(\d+)/i', $ua))
        $browser = 'Edge';
    elseif (preg_match('/Chrome\/(\d+)/i', $ua))
        $browser = 'Chrome';
    elseif (preg_match('/Safari\/(\d+)/i', $ua))
        $browser = 'Safari';
    elseif (preg_match('/Firefox\/(\d+)/i', $ua))
        $browser = 'Firefox';
    elseif (preg_match('/MSIE (\d+)|Trident/i', $ua))
        $browser = 'IE';

    if (preg_match('/Windows/i', $ua))
        $os = 'Windows';
    elseif (preg_match('/Macintosh|Mac OS X/i', $ua))
        $os = 'MacOS';
    elseif (preg_match('/Android/i', $ua))
        $os = 'Android';
    elseif (preg_match('/iPhone|iPad|iPod/i', $ua))
        $os = 'iOS';
    elseif (preg_match('/Linux/i', $ua))
        $os = 'Linux';

    // Language Extraction
    if (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $language = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
    }

    // Fetch country
    $country = 'Unknown';
    $countryCode = '';

    // Check if IP is local/private
    $isLocal = ($ip === '::1' || $ip === '127.0.0.1' || strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0 || strpos($ip, '172.') === 0);

    if ($isLocal) {
        $country = 'Local';
        $countryCode = 'LOCAL';
    } else {
        $ctx = stream_context_create(['http' => ['timeout' => 1.5]]);
        $geo = @file_get_contents("http://ip-api.com/json/$ip?fields=status,country,countryCode", false, $ctx);
        if ($geo) {
            $geoData = json_decode($geo, true);
            if ($geoData['status'] === 'success') {
                $country = $geoData['country'];
                $countryCode = $geoData['countryCode'];
            }
        }
    }

    // 3. Log every hit for real-time analytics
    $db->visitor_log->insertOne([
        'hash' => $visitorHash,
        'date' => $today,
        'timestamp' => new MongoDB\BSON\UTCDateTime(),
        'ua' => $ua,
        'ip' => $ip,
        'country' => $country,
        'country_code' => $countryCode,
        'browser' => $browser,
        'os' => $os,
        'language' => $language,
        'referrer' => $referer,
        'created_at' => new MongoDB\BSON\UTCDateTime()
    ]);

    // 3. Increment daily count
    $db->main_views->updateOne(
        ['date' => $today],
        [
            '$inc' => ['count' => 1],
            '$setOnInsert' => ['created_at' => new MongoDB\BSON\UTCDateTime()]
        ],
        ['upsert' => true]
    );

    // 4. Increment hourly count
    $result = $db->main_views->updateOne(
        ['date' => $hour],
        [
            '$inc' => ['count' => 1],
            '$setOnInsert' => ['created_at' => new MongoDB\BSON\UTCDateTime(), 'is_hour' => true]
        ],
        ['upsert' => true]
    );

    // 5. Add notification record
    $notifTimestamp = new MongoDB\BSON\UTCDateTime();
    $db->notifications->insertOne([
        'type' => 'main_view',
        'item_title' => $pageTitle,
        'timestamp' => $notifTimestamp,
        'created_at' => $notifTimestamp
    ]);

    echo json_encode(['success' => true, 'modified' => $result->getModifiedCount(), 'location' => $country]);
} catch (Exception $e) {
    error_log('ENTRIKS Tracking Exception: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
