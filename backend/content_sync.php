<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/database.php';

// Allow from any origin (or specify your domain) - if needed, but usually strictly same origin for this
// header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

$id = $_GET['id'] ?? $_POST['id'] ?? null;
if (!$id) {
    echo json_encode(['success' => false, 'error' => 'No ID']);
    exit;
}

if (!$db) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

try {
    $objectId = new MongoDB\BSON\ObjectId($id);

    $cookieName = 'last_blog_view_tracked';
    if (isset($_COOKIE[$cookieName])) {
        echo json_encode(['success' => true, 'status' => 'cooldown']);
        exit;
    }

    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie($cookieName, '1', time() + 1800, '/', '', $isSecure, true);

    $today = date('Y-m-d');
    $hour = date('Y-m-d H');

    $result = $db->blog->updateOne(
        ['_id' => $objectId],
        [
            '$inc' => [
                'views' => 1,
                "views_history.$today" => 1,
                "views_history.$hour" => 1
            ]
        ]
    );

    // Track unique visitor data for analytics
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip = '0.0.0.0';
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP']))
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    else
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    $referer = $_SERVER['HTTP_REFERER'] ?? '';
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

    // Track every hit for real-time analytics
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
        'post_id' => $objectId,
        'created_at' => new MongoDB\BSON\UTCDateTime()
    ]);

    // Get post title for notification
    $post = $db->blog->findOne(['_id' => $objectId], ['projection' => ['title_de' => 1, 'title_en' => 1]]);
    $title = $post['title_de'] ?? $post['title_en'] ?? 'a blog post';

    // Notification Logic (Debounced 60s)
    $debounceTime = new MongoDB\BSON\UTCDateTime((time() - 60) * 1000);
    $recentNotif = $db->notifications->findOne([
        'type' => 'blog_view',
        'item_title' => $title,
        'item_id' => $objectId,
        'timestamp' => ['$gte' => $debounceTime]
    ]);

    if (!$recentNotif) {
        $notifTimestamp = new MongoDB\BSON\UTCDateTime();
        $db->notifications->insertOne([
            'type' => 'blog_view',
            'item_id' => $objectId,
            'item_title' => $title,
            'timestamp' => $notifTimestamp,
            'created_at' => $notifTimestamp  // Fixed: Use UTCDateTime matches get_notifications.php
        ]);
    }

    echo json_encode(['success' => true, 'modified' => $result->getModifiedCount()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
