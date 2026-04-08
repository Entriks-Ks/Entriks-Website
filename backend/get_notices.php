<?php
error_reporting(0);
ini_set('display_errors', 0);

ob_start();

require_once __DIR__ . '/session_config.php';

ob_end_clean();
ob_start();

try {
    require 'database.php';
} catch (Exception $e) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

ob_end_clean();

header('Content-Type: application/json');

if (!isset($_SESSION['admin'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    if (!isset($db) || !$db) {
        echo json_encode(['success' => false, 'error' => 'Database not initialized']);
        exit;
    }

    $cursor = $db->notifications->find(
        [],
        [
            'sort' => ['created_at' => -1],
            'limit' => 3
        ]
    );

    $notificationsList = [];

    if ($cursor) {
        foreach ($cursor as $notif) {
            if (!isset($notif->created_at)) {
                continue;
            }

            try {
                if ($notif->created_at instanceof MongoDB\BSON\UTCDateTime) {
                    $createdAt = $notif->created_at->toDateTime();
                } elseif (is_string($notif->created_at)) {
                    $createdAt = new DateTime($notif->created_at);
                } elseif (is_numeric($notif->created_at)) {
                    $createdAt = new DateTime('@' . $notif->created_at);
                } else {
                    continue;
                }

                $createdAt->setTimezone(new DateTimeZone('Europe/Berlin'));
            } catch (Exception $e) {
                continue;
            }

            $title = '';
            $content = '';

            $type = isset($notif->type) ? $notif->type : '';

            switch ($type) {
                case 'blog_view':
                    $title = 'New Blog View';
                    $content = isset($notif->item_title) ? $notif->item_title : 'Someone viewed a post';
                    break;
                case 'main_view':
                    $title = 'New Main Page View';
                    $content = 'Someone visited the main page';
                    break;
                case 'new_comment':
                    $title = 'New Comment';
                    $content = 'From: ' . (isset($notif->item_title) ? $notif->item_title : 'a reader');
                    break;
                default:
                    $title = isset($notif->title) ? $notif->title : 'Notification';
                    $content = isset($notif->message) ? $notif->message : (isset($notif->item_title) ? $notif->item_title : '');
            }

            $notificationsList[] = [
                'id' => (string) $notif->_id,
                'title' => $title,
                'content' => $content,
                'type' => $type,
                'author' => isset($notif->author) ? $notif->author : 'Admin',
                'created_at' => $createdAt->format('c'),
                'created_at_formatted' => $createdAt->format('d M, Y'),
                'time_ago' => getTimeAgo($createdAt)
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'notifications' => $notificationsList
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
}

exit;

function getTimeAgo($datetime)
{
    global $lang;
    try {
        $now = new DateTime('now', new DateTimeZone('Europe/Berlin'));
        $diff = $now->diff($datetime);

        if ($diff->y > 0)
            return sprintf($lang['time_years_ago'] ?? '%s years ago', $diff->y);
        if ($diff->m > 0)
            return sprintf($lang['time_months_ago'] ?? '%s months ago', $diff->m);
        if ($diff->d > 0)
            return sprintf($lang['time_days_ago'] ?? '%s days ago', $diff->d);
        if ($diff->h > 0)
            return sprintf($lang['time_hours_ago'] ?? '%s hours ago', $diff->h);
        if ($diff->i > 0)
            return sprintf($lang['time_min_ago'] ?? '%s min ago', $diff->i);
        return $lang['time_just_now'] ?? 'just now';
    } catch (Exception $e) {
        return $lang['time_recently'] ?? 'recently';
    }
}