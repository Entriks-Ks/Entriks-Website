<?php

/**
 * Session Management Helper Functions
 * Handles creation, tracking, and termination of user sessions
 * Similar to Gmail's device management system
 */

/**
 * Create a new session record in the database
 */
function createSession($db, $userEmail, $userAgent, $ipAddress) {
    try {
        // Generate unique session ID
        $sessionId = bin2hex(random_bytes(32));
        
        // Parse user agent for device and browser info
        $deviceInfo = parseUserAgent($userAgent);
        
        // Get location from IP (using free API)
        $location = getLocationFromIP($ipAddress);
        
        // Create session document
        $sessionData = [
            'session_id' => $sessionId,
            'user_email' => $userEmail,
            'device' => $deviceInfo['device'],
            'browser' => $deviceInfo['browser'],
            'ip_address' => $ipAddress,
            'location' => $location,
            'user_agent' => $userAgent,
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'last_active' => new MongoDB\BSON\UTCDateTime(),
            'is_active' => true
        ];
        
        // Insert into database
        $db->sessions->insertOne($sessionData);
        
        return $sessionId;
    } catch (Exception $e) {
        error_log("Error creating session: " . $e->getMessage());
        return null;
    }
}

/**
 * Update session activity timestamp
 */
function updateSessionActivity($db, $sessionId) {
    try {
        if (!$sessionId) return false;
        
        $db->sessions->updateOne(
            ['session_id' => $sessionId, 'is_active' => true],
            ['$set' => ['last_active' => new MongoDB\BSON\UTCDateTime()]]
        );
        return true;
    } catch (Exception $e) {
        error_log("Error updating session activity: " . $e->getMessage());
        return false;
    }
}

/**
 * Terminate a session (logout)
 */
function terminateSession($db, $sessionId) {
    try {
        if (!$sessionId) return false;
        
        $db->sessions->updateOne(
            ['session_id' => $sessionId],
            [
                '$set' => [
                    'is_active' => false,
                    'terminated_at' => new MongoDB\BSON\UTCDateTime()
                ]
            ]
        );
        return true;
    } catch (Exception $e) {
        error_log("Error terminating session: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all active sessions for a user
 */
function getActiveSessions($db, $userEmail) {
    try {
        $sessions = $db->sessions->find(
            ['user_email' => $userEmail, 'is_active' => true],
            ['sort' => ['last_active' => -1]]
        );
        
        return iterator_to_array($sessions);
    } catch (Exception $e) {
        error_log("Error getting active sessions: " . $e->getMessage());
        return [];
    }
}

/**
 * Clean up expired sessions (inactive for more than X days)
 */
function cleanupExpiredSessions($db, $expiryDays = 30) {
    try {
        $expiryDate = new MongoDB\BSON\UTCDateTime(
            (time() - ($expiryDays * 24 * 60 * 60)) * 1000
        );
        
        $result = $db->sessions->deleteMany([
            'last_active' => ['$lt' => $expiryDate],
            'is_active' => false
        ]);
        
        return $result->getDeletedCount();
    } catch (Exception $e) {
        error_log("Error cleaning up sessions: " . $e->getMessage());
        return 0;
    }
}

/**
 * Parse user agent string to extract device and browser info
 * Similar to how Gmail displays device information
 */
function parseUserAgent($ua) {
    $device = 'Unknown Device';
    $browser = 'Unknown Browser';
    
    // Detect device/OS
    if (preg_match('/Windows NT 10/i', $ua)) {
        $device = 'Windows';
    } elseif (preg_match('/Windows NT 11/i', $ua)) {
        $device = 'Windows';
    } elseif (preg_match('/Windows NT/i', $ua)) {
        $device = 'Windows';
    } elseif (preg_match('/Macintosh.*Mac OS X (\d+[._]\d+)/i', $ua, $match)) {
        $device = 'Mac';
    } elseif (preg_match('/iPhone/i', $ua)) {
        $device = 'iPhone';
    } elseif (preg_match('/iPad/i', $ua)) {
        $device = 'iPad';
    } elseif (preg_match('/Android/i', $ua)) {
        if (preg_match('/Mobile/i', $ua)) {
            $device = 'Android';
        } else {
            $device = 'Android Tablet';
        }
    } elseif (preg_match('/Linux/i', $ua)) {
        $device = 'Linux';
    }
    
    // Detect browser and version
    if (preg_match('/Edg\/(\d+)/i', $ua, $match)) {
        $browser = 'Edge ' . $match[1];
    } elseif (preg_match('/Chrome\/(\d+)/i', $ua, $match) && !preg_match('/Edg/i', $ua)) {
        $browser = 'Chrome ' . $match[1];
    } elseif (preg_match('/Firefox\/(\d+)/i', $ua, $match)) {
        $browser = 'Firefox ' . $match[1];
    } elseif (preg_match('/Safari\/(\d+)/i', $ua, $match) && !preg_match('/Chrome/i', $ua) && !preg_match('/Edg/i', $ua)) {
        if (preg_match('/Version\/(\d+)/i', $ua, $versionMatch)) {
            $browser = 'Safari ' . $versionMatch[1];
        } else {
            $browser = 'Safari';
        }
    } elseif (preg_match('/OPR\/(\d+)/i', $ua, $match)) {
        $browser = 'Opera ' . $match[1];
    }
    
    return [
        'device' => $device,
        'browser' => $browser
    ];
}

/**
 * Get location from IP address using free ip-api.com service
 * Similar to Gmail's location detection
 */
function getLocationFromIP($ip) {
    // Default location for local/unknown IPs
    $defaultLocation = [
        'city' => 'Unknown',
        'country' => 'Unknown Location',
        'country_code' => 'XX'
    ];
    
    // Skip local IPs
    if (in_array($ip, ['127.0.0.1', '::1', 'localhost']) || strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0) {
        return [
            'city' => '',
            'country' => 'Local Network',
            'country_code' => 'LN'
        ];
    }
    
    try {
        // Use ip-api.com free service (45 requests/min, no API key needed)
        $url = "http://ip-api.com/json/{$ip}?fields=status,country,countryCode,city";
        
        // Create context with timeout
        $context = stream_context_create([
            'http' => [
                'timeout' => 3,
                'ignore_errors' => true
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            return $defaultLocation;
        }
        
        $data = json_decode($response, true);
        
        if (!$data || $data['status'] !== 'success') {
            return $defaultLocation;
        }
        
        return [
            'city' => $data['city'] ?? '',
            'country' => $data['country'] ?? 'Unknown',
            'country_code' => $data['countryCode'] ?? 'XX'
        ];
    } catch (Exception $e) {
        error_log("Error getting location from IP: " . $e->getMessage());
        return $defaultLocation;
    }
}

/**
 * Format timestamp to relative time (e.g., "2 hours ago", "Just now")
 * Similar to Gmail's time display - supports bilingual via $lang
 */
function formatRelativeTime($timestamp) {
    global $lang;
    if ($timestamp instanceof MongoDB\BSON\UTCDateTime) {
        $timestamp = $timestamp->toDateTime()->getTimestamp();
    }
    
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return $lang['time_just_now'] ?? 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return sprintf($lang['time_minutes_ago'] ?? '%s minutes ago', $mins);
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return sprintf($lang['time_hours_ago'] ?? '%s hours ago', $hours);
    } elseif ($diff < 2592000) {
        $days = floor($diff / 86400);
        return sprintf($lang['time_days_ago'] ?? '%s days ago', $days);
    } else {
        return date('M j, Y', $timestamp);
    }
}
