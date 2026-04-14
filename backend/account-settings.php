<?php
require_once __DIR__ . '/session_config.php';
require_once 'database.php';
/** @var \MongoDB\Database $db */
require_once 'config.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit();
}

$isAdmin = ($_SESSION['admin']['role'] ?? 'admin') === 'admin';

require_once 'gridfs.php';

function getDisplayFilename($url)
{
    if (!$url)
        return '';
    if (strpos($url, 'backend/image.php?id=') === 0) {
        $id = str_replace('backend/image.php?id=', '', $url);
        try {
            $metadata = getImageMetadataFromGridFS($id);
            return $metadata['filename'] ?? $url;
        } catch (Exception $e) {
            return $url;
        }
    }
    return basename($url);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        error_log('Account Settings POST: ' . print_r($_POST, true));

        $settingsData = [
            'type' => 'global_config',
            'display_name' => $_POST['display_name'] ?? 'Admin',
            'autosave_interval' => (int) ($_POST['autosave'] ?? 5),
            'meta_description_de' => $_POST['meta_description_de'] ?? '',
            'meta_description_en' => $_POST['meta_description_en'] ?? '',
            'site_name' => $_POST['site_name'] ?? 'ENTRIKS',
            'logo_url' => $_POST['logo_url'] ?? '',
            'favicon_url' => $_POST['favicon_url'] ?? '',
            'footer_logo_url' => $_POST['footer_logo_url'] ?? '',
            'footer_text_de' => $_POST['footer_text_de'] ?? '',
            'footer_text_en' => $_POST['footer_text_en'] ?? '',
            'contact_email' => $_POST['contact_email'] ?? '',
            'contact_phone' => $_POST['contact_phone'] ?? '',
            'contact_address' => $_POST['contact_address'] ?? '',
            'social_linkedin' => $_POST['social_linkedin'] ?? '',
            'social_facebook' => $_POST['social_facebook'] ?? '',
            'social_instagram' => $_POST['social_instagram'] ?? '',
            'posts_per_page' => (int) ($_POST['posts_per_page'] ?? 10),
            'comments_enabled' => isset($_POST['comments_enabled']) ? true : false,
            'blog_show_recent_posts' => isset($_POST['blog_show_recent_posts']) ? true : false,
            'blog_show_categories' => isset($_POST['blog_show_categories']) ? true : false,
            'blog_show_tags' => isset($_POST['blog_show_tags']) ? true : false,
            'default_language' => $_POST['default_language'] ?? 'de',
            'smtp_host' => $_POST['smtp_host'] ?? '',
            'smtp_port' => (int) ($_POST['smtp_port'] ?? 587),
            'smtp_username' => $_POST['smtp_username'] ?? '',
            'smtp_password' => $_POST['smtp_password'] ?? '',
            'smtp_encryption' => $_POST['smtp_encryption'] ?? 'tls',
            'smtp_from_email' => $_POST['smtp_from_email'] ?? '',
            'smtp_from_name' => $_POST['smtp_from_name'] ?? 'ENTRIKS',
            'cookie_consent_enabled' => isset($_POST['cookie_consent_enabled']) ? true : false,
            'back_to_top_enabled' => isset($_POST['back_to_top_enabled']) ? true : false,
            'back_to_top_mobile_enabled' => isset($_POST['back_to_top_mobile_enabled']) ? true : false,
            'dashboard_search_enabled' => isset($_POST['dashboard_search_enabled']) ? true : false,
            'updated_at' => new MongoDB\BSON\UTCDateTime()
        ];

        $db->admins->updateOne(
            ['_id' => new ObjectId($_SESSION['admin']['id'])],
            ['$set' => [
                'display_name' => $_POST['display_name'] ?? $_SESSION['admin']['name'],
                'preferred_language' => $_POST['default_language'] ?? 'de'
            ]]
        );

        $_SESSION['admin']['name'] = $_POST['display_name'] ?? $_SESSION['admin']['name'];
        $_SESSION['admin']['preferred_language'] = $_POST['default_language'] ?? 'de';

        if ($isAdmin) {
            $db->settings->updateOne(
                ['type' => 'global_config'],
                ['$set' => $settingsData],
                ['upsert' => true]
            );
        }

        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }

        $_SESSION['toast_message'] = $lang['msg_settings_saved'];
        $_SESSION['toast_type'] = 'success';

        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    } catch (Exception $e) {
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
        $_SESSION['toast_message'] = sprintf($lang['msg_error_saving_settings'] ?? 'Error saving settings: %s', $e->getMessage());
        $_SESSION['toast_type'] = 'error';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
}

$currentSettings = [];
try {
    $doc = $db->settings->findOne(['type' => 'global_config']);
    if ($doc) {
        $currentSettings = (array) $doc;
    }
} catch (Exception $e) {
}

$dispName = $_SESSION['admin']['name'] ?? $currentSettings['display_name'] ?? 'Admin';
$metaDe = $currentSettings['meta_description_de'] ?? 'ENTRIKS - Ihr Partner für Nearshoring & BPO';
$metaEn = $currentSettings['meta_description_en'] ?? 'ENTRIKS - Your partner for Nearshoring & BPO';
$siteName = $currentSettings['site_name'] ?? 'ENTRIKS';
$logoUrl = !empty($currentSettings['logo_url']) ? $currentSettings['logo_url'] : 'assets/img/logo.png';
$faviconUrl = !empty($currentSettings['favicon_url']) ? $currentSettings['favicon_url'] : 'assets/img/favicon.png';
$footerLogoUrl = !empty($currentSettings['footer_logo_url']) ? $currentSettings['footer_logo_url'] : 'assets/img/logo.png';
$footerTextDe = !empty($currentSettings['footer_text_de']) ? $currentSettings['footer_text_de'] : 'ENTRIKS Talent Hub verbindet DACH-Unternehmen mit hochqualifizierten Fachkräften aus dem Kosovo – durch Nearshoring und Active Sourcing.';
$footerTextEn = !empty($currentSettings['footer_text_en']) ? $currentSettings['footer_text_en'] : 'ENTRIKS Talent Hub connects DACH companies with highly qualified professionals from Kosovo through Nearshoring and Active Sourcing.';
$contactEmail = $currentSettings['contact_email'] ?? '';
$contactPhone = $currentSettings['contact_phone'] ?? '';
$contactAddress = $currentSettings['contact_address'] ?? '';
$socialLinkedin = $currentSettings['social_linkedin'] ?? '';
$socialFacebook = $currentSettings['social_facebook'] ?? '';
$socialInstagram = $currentSettings['social_instagram'] ?? '';
$smtpHost = $currentSettings['smtp_host'] ?? '';
$smtpPort = $currentSettings['smtp_port'] ?? 587;
$smtpUsername = $currentSettings['smtp_username'] ?? '';
$smtpPassword = $currentSettings['smtp_password'] ?? '';
$smtpEncryption = $currentSettings['smtp_encryption'] ?? 'tls';
$smtpFromEmail = $currentSettings['smtp_from_email'] ?? '';
$smtpFromName = $currentSettings['smtp_from_name'] ?? 'ENTRIKS';

$postsPerPage = $currentSettings['posts_per_page'] ?? 10;
$commentsEnabled = $currentSettings['comments_enabled'] ?? true;
$blogShowRecent = $currentSettings['blog_show_recent_posts'] ?? true;
$blogShowCategories = $currentSettings['blog_show_categories'] ?? true;
$blogShowTags = $currentSettings['blog_show_tags'] ?? true;

$defaultLanguage = $_SESSION['admin']['preferred_language'] ?? $currentSettings['default_language'] ?? 'de';
$cookieConsentEnabled = $currentSettings['cookie_consent_enabled'] ?? true;
$backToTopEnabled = $currentSettings['back_to_top_enabled'] ?? true;
$backToTopMobileEnabled = $currentSettings['back_to_top_mobile_enabled'] ?? true;

$infoIconNode = '
<div class="hint-icon" onclick="event.preventDefault(); event.stopPropagation(); toggleHint(this)">
    <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
        <path d="M13 10V2L5 14H11V22L19 10H13Z" />
    </svg>
</div>
';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $lang['settings_title'] ?></title>
    <link rel="shortcut icon" href="<?= htmlspecialchars($siteFaviconUrl) ?>" type="image/x-icon">
        <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= time() ?>">
        <script src="assets/js/global-search.js?v=<?= time() ?>" defer></script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-1: #20c1f5;
            --color-2: #49b9f2;
            --color-3: #7675ec;
            --color-4: #a04ee1;
            --color-5: #d225d7;
            --color-6: #f009d5;
            --primary-color: #7675ec;
            --secondary-color: #49b9f2;
            --accent-purple: #d225d7;
            --accent-green: #10b981;
            --accent-orange: #f59e0b;
            --accent-red: #ef4444;
            --bg-dark: #0a0a0a;
            --panel-bg: #1a1a1a;
            --panel-bg-hover: #262525;
            --text-main: #ffffff;
            --text-muted: #9ca3af;
            --text-dim: #6b7280;
            --border-color: rgba(255, 255, 255, 0.08);
            --border-color-strong: rgba(255, 255, 255, 0.12);
        }

        body {
            background: linear-gradient(135deg, #0a0a0a 0%, #1a0a1a 100%);
            color: var(--text-main);
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        .layout {
            display: flex;
            min-height: 100vh;
        }

        .content {
            flex-grow: 1;
            padding: 48px 56px;
            background-color: transparent;
        }
        
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .topbar-left h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            color: #fff;
        }

        .topbar-right a {
            display: flex;
            align-items: center;
            text-decoration: none;
        }

        .topbar-right svg {
            fill: #fff;
            width: 20px;
            height: 20px;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 48px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .settings-nav {
            position: sticky;
            top: 40px;
            height: fit-content;
        }

        .nav-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--text-dim);
            margin-bottom: 20px;
            font-weight: 700;
            padding-left: 16px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 10px 14px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 15px;
            font-weight: 500;
            border-radius: 8px;
            transition: background-color 0.15s ease, color 0.15s ease;
            margin-bottom: 8px;
            border: 1px solid transparent;
            position: relative;
            overflow: visible;
            width: 100%;
            box-sizing: border-box;
        }

        .nav-item:hover {
            background-color: rgba(255, 255, 255, 0.06);
            color: #fff;
            transform: translateX(4px);
        }

        .nav-item.active {
            background: linear-gradient(135deg, rgba(118, 117, 236, 0.15), rgba(73, 185, 242, 0.1));
            color: #fff;
            border: 1px solid rgba(118, 117, 236, 0.3);
        }

        .nav-item.active::before {
            width: 4px;
        }

        .nav-item svg {
            width: 22px;
            height: 22px;
            margin-right: 14px;
            opacity: 0.7;
            transition: all 0.3s ease;
        }

        .nav-item:hover svg {
            opacity: 0.9;
        }

        .nav-item.active svg {
            opacity: 1;
            color: var(--primary-color);
        }

        .nav-item--highlight {
            color: var(--text-muted);
            font-weight: 700;
            border-radius: 12px;
            padding: 10px 14px;
            display: flex;
            align-items: center;
        }

        .nav-item--highlight svg {
            opacity: 0.9;
            margin-right: 12px;
        }

        .nav-item--highlight:hover,
        .nav-item--highlight.active {
            background-color: rgba(255, 255, 255, 0.06);
            color: #fff !important;
            transform: translateX(4px);
            border: 1px solid rgba(118, 117, 236, 0.3);
        }

        .nav-item--highlight:hover::before,
        .nav-item--highlight.active::before {
            width: 4px;
        }

        .settings-nav .nav-label + .nav-item {
            margin-top: 14px;
        }

        .nav-item + .nav-label {
            margin-top: 24px;
            display: block;
        }

        .settings-card {
            background: var(--panel-bg);
            border-radius: 12px;
            padding: 48px;
            border: 1px solid var(--border-color-strong);
            margin-bottom: 32px;
            display: none;
            animation: fadeIn 0.5s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            overflow: hidden;
        }

        .settings-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(118, 117, 236, 0.5), transparent);
        }

        .settings-card.active {
            display: block;
        }

        @keyframes fadeIn {
            from { 
                opacity: 0; 
                transform: translateY(20px);
            }
            to { 
                opacity: 1; 
                transform: translateY(0);
            }
        }

        .card-header {
            margin-bottom: 40px;
            padding-bottom: 24px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.06);
            position: relative;
        }

        .card-header::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 80px;
            height: 2px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            border-radius: 2px;
        }

        .card-header h2 {
            font-size: 24px;
            color: #fff;
            margin: 0 0 10px 0;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .card-header p {
            font-size: 14px;
            color: var(--text-muted);
            margin: 0;
            line-height: 1.6;
        }

        .form-group {
            margin-bottom: 28px;
        }

        .form-label {
            display: block;
            margin-bottom: 12px;
            font-weight: 600;
            color: #e5e7eb;
            font-size: 14px;
            letter-spacing: 0.2px;
        }

        .form-control {
            width: 100%;
            padding: 16px 18px;
            background: rgba(0, 0, 0, 0.4);
            border: 1.5px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: #fff;
            font-size: 15px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-sizing: border-box;
            font-family: inherit;
        }

        .form-control:hover {
            border-color: rgba(255, 255, 255, 0.15);
            background: rgba(0, 0, 0, 0.5);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            background: rgba(0, 0, 0, 0.6);
        }

        .form-control:disabled {
            background: rgba(255, 255, 255, 0.03);
            color: #666;
            cursor: not-allowed;
            border-color: rgba(255, 255, 255, 0.05);
        }

        .form-hint {
            font-size: 13px;
            color: var(--text-dim);
            margin-top: 8px;
            line-height: 1.5;
            display: none; /* Hidden by default */
            padding: 14px 16px;
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            animation: slideDown 0.2s ease-out;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Analytics Style Info Box - Tooltip */
        .ana-info-box {
            background: #1a1a1c;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 14px 16px;
            font-size: 0.85rem;
            line-height: 1.5;
            color: #d1d5db;
            z-index: 9999;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.6);
            display: none;
            position: fixed;
            min-width: 260px;
            max-width: 340px;
            pointer-events: auto;
            animation: anaSlideDown 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .ana-info-box::before {
            content: '';
            position: absolute;
            top: -6px;
            left: 20px;
            width: 12px;
            height: 12px;
            background: #1a1a1c;
            border-left: 1px solid rgba(255, 255, 255, 0.1);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            transform: rotate(45deg);
        }

        @keyframes anaSlideDown {
            from { opacity: 0; transform: translateY(-6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .ana-info-box.active {
            display: block;
        }

        .hint-icon {
            width: 18px;
            height: 18px;
            color: #d225d7;
            cursor: pointer;
            transition: all 0.2s;
            opacity: 0.8;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            vertical-align: middle;
            margin-left: 8px;
        }

        .hint-icon:hover {
            opacity: 1;
            transform: scale(1.1);
            filter: drop-shadow(0 0 8px rgba(99, 102, 241, 0.5));
        }

        .form-label {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
            font-weight: 600;
            color: #e5e7eb;
            font-size: 14px;
            letter-spacing: 0.2px;
        }

        code {
            background: rgba(118, 117, 236, 0.15);
            padding: 3px 8px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            color: #e5e7eb;
            border: 1px solid rgba(118, 117, 236, 0.2);
        }

        .side-by-side-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        @media (max-width: 768px) {
            .side-by-side-row {
                grid-template-columns: 1fr;
            }
        }

        .premium-file-row {
            display: flex;
            align-items: center;
            background: rgba(0, 0, 0, 0.4);
            border: 1.5px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 8px 12px;
            gap: 12px;
            transition: all 0.3s ease;
        }

        .premium-file-row:focus-within {
            background: rgba(0, 0, 0, 0.5);
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(32, 193, 245, 0.1);
        }

        .premium-file-thumb {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            background: #2a2a2a;
            background-image: linear-gradient(45deg, #333 25%, transparent 25%), 
                              linear-gradient(-45deg, #333 25%, transparent 25%), 
                              linear-gradient(45deg, transparent 75%, #333 75%), 
                              linear-gradient(-45deg, transparent 75%, #333 75%);
            background-size: 10px 10px;
            background-position: 0 0, 0 5px, 5px -5px, -5px 0px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
            border: 1px solid rgba(255, 255, 255, 0.1);
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .premium-file-thumb img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .premium-file-info {
            flex-grow: 1;
            min-width: 0;
        }

        .premium-file-input {
            width: 100%;
            background: none;
            border: none;
            color: #fff;
            font-size: 14px;
            font-weight: 500;
            padding: 8px 0;
            outline: none;
        }

        .premium-file-input::placeholder {
            color: rgba(255, 255, 255, 0.3);
        }

        .premium-file-upload-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            padding: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .premium-file-upload-btn svg {
            width: 20px;
            height: 20px;
        }

        .floating-save {
            position: fixed;
            bottom: 40px;
            right: 40px;
            z-index: 100;
            transform: translateY(200%);
            transition: transform 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .floating-save.active {
            transform: translateY(0);
        }

        .btn-primary-large {
            background: linear-gradient(135deg, #7675ec 0%, #d225d7 100%);
            color: #fff;
            border: none;
            padding: 16px 40px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
            letter-spacing: 0.3px;
            box-shadow: 0 4px 15px rgba(118, 117, 236, 0.3);
        }

        .btn-primary-large:hover {
            box-shadow: 0 8px 25px rgba(118, 117, 236, 0.4);
        }

        .btn-primary-large svg {
            width: 20px;
            height: 20px;
        }

        .local-toast-container {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 1000;
        }

        .toggle-switch {
            display: flex;
            align-items: center;
            gap: 16px;
            cursor: pointer;
            user-select: none;
            padding: 12px 0;
        }

        .toggle-switch input {
            display: none;
        }

        .toggle-slider {
            position: relative;
            width: 56px;
            height: 30px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 30px;
            transition: all 0.3s ease;
            flex-shrink: 0;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .toggle-slider::before {
            content: '';
            position: absolute;
            top: 3px;
            left: 3px;
            width: 22px;
            height: 22px;
            background: #fff;
            border-radius: 50%;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .toggle-switch input:checked + .toggle-slider {
            background: linear-gradient(135deg, var(--accent-purple), var(--primary-color));
            border-color: transparent;
        }

        .toggle-switch input:checked + .toggle-slider::before {
            transform: translateX(26px);
        }

        .toggle-label {
            color: #fff;
            font-size: 15px;
            font-weight: 500;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: flex-start !important;
            gap: 12px;
            color: #fff;
            font-size: 17px;
            font-weight: 700;
            margin: 40px 0 24px 0;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            letter-spacing: 0.3px;
        }

        .section-header:first-of-type {
            margin-top: 0;
        }

        .section-accent {
            width: 4px;
            height: 20px;
            border-radius: 2px;
            display: inline-block;
        }

        @media (max-width: 1024px) {
            .settings-grid {
                grid-template-columns: 1fr;
                gap: 24px;
            }

            .settings-nav {
                position: static;
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
                grid-auto-rows: 48px;
            }

            .nav-label {
                display: none !important;
            }

            .nav-item {
                display: flex;
                margin-right: 0;
                margin-bottom: 0 !important;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                padding: 12px 14px;
                font-size: 13px;
                flex-shrink: 0;
                justify-content: flex-start;
                width: 100%;
                height: 48px;
                align-items: center;
            }

            .nav-item svg {
                width: 18px;
                height: 18px;
                margin-right: 10px;
                flex-shrink: 0;
            }
        }

        @media (max-width: 768px) {
            .content {
                padding: 20px;
            }

            .settings-topbar {
                margin-bottom: 24px;
                padding-bottom: 16px;
            }

            .settings-topbar h1 {
                font-size: 22px;
            }

            .topbar-avatar {
                width: 40px;
                height: 40px;
            }

            .settings-card {
                padding: 24px;
                border-radius: 16px;
                margin-bottom: 20px;
            }

            .card-header {
                margin-bottom: 24px;
                padding-bottom: 16px;
            }

            .card-header h2 {
                font-size: 18px;
            }

            .card-header p {
                font-size: 13px;
            }

            .form-group {
                margin-bottom: 20px;
            }

            .form-label {
                font-size: 13px;
                margin-bottom: 8px;
            }

            .form-control {
                padding: 12px 14px;
                font-size: 14px;
            }

            .form-hint {
                font-size: 12px;
            }

            .floating-save {
                bottom: 16px;
                right: 16px;
                left: 16px;
                width: auto;
            }

            .btn-primary-large {
                width: 100%;
                justify-content: center;
                padding: 14px 24px;
                font-size: 15px;
            }

            .toggle-switch {
                gap: 12px;
            }

            .toggle-slider {
                width: 48px;
                height: 26px;
            }

            .toggle-slider::before {
                width: 20px;
                height: 20px;
            }

            .toggle-switch input:checked + .toggle-slider::before {
                transform: translateX(22px);
            }

            .toggle-label {
                font-size: 14px;
            }
        }

        @media (max-width: 480px) {
            .content {
                padding: 16px;
            }

            .settings-topbar h1 {
                font-size: 20px;
            }

            .settings-card {
                padding: 20px;
            }

            .card-header h2 {
                font-size: 16px;
            }

            .nav-item {
                padding: 8px 12px;
                font-size: 13px;
            }

            .form-control {
                padding: 10px 12px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="preloader" id="preloader">
        <div class="preloader-spinner"></div>
    </div>

    <div class="layout">
        <?php $sidebarVariant = 'dashboard';
        $activeMenu = 'settings';
        include __DIR__ . '/partials/sidebar.php'; ?>

        <main class="content">
            <!-- Blur Background Theme -->
            <div class="blur-bg-theme bottom-right"></div>
            
            <?php
            $pageTitle = $lang['settings_title'];
            include __DIR__ . '/partials/topbar.php';
            ?>

            <form method="POST" action="">
                <div class="settings-grid">
                    
                    <div class="settings-nav" id="tour-settings-nav">
                        <a href="#profile" class="nav-item active" onclick="switchTab('profile', this); return false;">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                <path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM3.751 20.105a8.25 8.25 0 0 1 16.498 0 .75.75 0 0 1-.437.695A18.683 18.683 0 0 1 12 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 0 1-.437-.695Z" clip-rule="evenodd" />
                            </svg>
                            Personal Profile
                        </a>
                        <?php if ($isAdmin): ?>
                        <a href="#website" class="nav-item" onclick="switchTab('website', this); return false;">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M21.721 12.752a9.711 9.711 0 0 0-.945-5.003 12.754 12.754 0 0 1-4.339 2.708 18.991 18.991 0 0 1-.214 4.772 17.165 17.165 0 0 0 5.498-2.477ZM14.634 15.55a17.324 17.324 0 0 0 .332-4.647c-.952.227-1.945.347-2.966.347-1.021 0-2.014-.12-2.966-.347a17.515 17.515 0 0 0 .332 4.647 17.385 17.385 0 0 0 5.268 0ZM9.772 17.119a18.963 18.963 0 0 0 4.456 0A17.182 17.182 0 0 1 12 21.724a17.18 17.18 0 0 1-2.228-4.605ZM7.777 15.23a18.87 18.87 0 0 1-.214-4.774 12.753 12.753 0 0 1-4.34-2.708 9.711 9.711 0 0 0-.944 5.004 17.165 17.165 0 0 0 5.498 2.477ZM21.356 14.752a9.765 9.765 0 0 1-7.478 6.817 18.64 18.64 0 0 0 1.988-4.718 18.627 18.627 0 0 0 5.49-2.098ZM2.644 14.752c1.682.971 3.53 1.688 5.49 2.099a18.64 18.64 0 0 0 1.988 4.718 9.765 9.765 0 0 1-7.478-6.816ZM13.878 2.43a9.755 9.755 0 0 1 6.116 3.986 11.267 11.267 0 0 1-3.746 2.504 18.63 18.63 0 0 0-2.37-6.49ZM12 2.276a17.152 17.152 0 0 1 2.805 7.121c-.897.23-1.837.353-2.805.353-.968 0-1.908-.122-2.805-.353A17.151 17.151 0 0 1 12 2.276ZM10.122 2.43a18.629 18.629 0 0 0-2.37 6.49 11.266 11.266 0 0 1-3.746-2.504 9.754 9.754 0 0 1 6.116-3.985Z" />
                            </svg>
                            Website Configuration
                        </a>
                        <a href="#blog" class="nav-item" onclick="switchTab('blog', this); return false;">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                <path fill-rule="evenodd" d="M4.125 3C3.089 3 2.25 3.84 2.25 4.875V18a3 3 0 0 0 3 3h15a3 3 0 0 1-3-3V4.875C17.25 3.839 16.41 3 15.375 3H4.125ZM12 9.75a.75.75 0 0 0 0 1.5h1.5a.75.75 0 0 0 0-1.5H12Zm-.75-2.25a.75.75 0 0 1 .75-.75h1.5a.75.75 0 0 1 0 1.5H12a.75.75 0 0 1-.75-.75ZM6 12.75a.75.75 0 0 0 0 1.5h7.5a.75.75 0 0 0 0-1.5H6Zm-.75 3.75a.75.75 0 0 1 .75-.75h7.5a.75.75 0 0 1 0 1.5H6a.75.75 0 0 1-.75-.75ZM6 6.75a.75.75 0 0 0-.75.75v3c0 .414.336.75.75.75h3a.75.75 0 0 0 .75-.75v-3A.75.75 0 0 0 9 6.75H6Z" clip-rule="evenodd" />
                                <path d="M18.75 6.75h1.875c.621 0 1.125.504 1.125 1.125V18a1.5 1.5 0 0 1-3 0V6.75Z" />
                            </svg>
                            Blog
                        </a>
                        <?php endif; ?>
                        <a href="#dashboard" class="nav-item" onclick="switchTab('dashboard', this); return false;">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                <path fill-rule="evenodd" d="M11.078 2.25c-.917 0-1.699.663-1.85 1.567L9.05 4.889c-.02.12-.115.26-.297.348a7.493 7.493 0 0 0-.986.57c-.166.115-.334.126-.45.083L6.3 5.508a1.875 1.875 0 0 0-2.282.819l-.922 1.597a1.875 1.875 0 0 0 .432 2.385l.84.692c.095.078.17.229.154.43a7.598 7.598 0 0 0 0 1.139c.015.2-.059.352-.153.43l-.841.692a1.875 1.875 0 0 0-.432 2.385l.922 1.597a1.875 1.875 0 0 0 2.282.818l1.019-.382c.115-.043.283-.031.45.082.312.214.641.405.985.57.182.088.277.228.297.35l.178 1.071c.151.904.933 1.567 1.85 1.567h1.844c.916 0 1.699-.663 1.85-1.567l.178-1.072c.02-.12.114-.26.297-.349.344-.165.673-.356.985-.57.167-.114.335-.125.45-.082l1.02.382a1.875 1.875 0 0 0 2.28-.819l.923-1.597a1.875 1.875 0 0 0-.432-2.385l-.84-.692c-.095-.078-.17-.229-.154-.43a7.614 7.614 0 0 0 0-1.139c-.016-.2.059-.352.153-.43l.84-.692c.708-.582.891-1.59.433-2.385l-.922-1.597a1.875 1.875 0 0 0-2.282-.818l-1.02.382c-.114.043-.282.031-.449-.083a7.49 7.49 0 0 0-.985-.57c-.183-.087-.277-.227-.297-.348l-.179-1.072a1.875 1.875 0 0 0-1.85-1.567h-1.843ZM12 15.75a3.75 3.75 0 1 0 0-7.5 3.75 3.75 0 0 0 0 7.5Z" clip-rule="evenodd" />
                            </svg>
                            Dashboard
                        </a>
                    </div>

                    <div class="settings-content">
                        
                        <div id="profile" class="settings-card active">
                            <div class="card-header">
                                <h2><?= $lang['profile_title'] ?></h2>
                                <p><?= $lang['profile_desc'] ?></p>
                            </div>

                            <div class="section-header" style="margin-top:0;">
                                <span class="section-accent" style="color:#20c1f5; background:#20c1f5;"></span>
                                <?= $lang['settings_account_details'] ?>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr; gap: 24px;">
                                <div class="form-group" style="margin-bottom: 0px !important;">
                                    <label class="form-label"><?= $lang['label_display_name'] ?> <?= $infoIconNode ?></label>
                                    <input type="text" class="form-control" name="display_name" id="display_name" value="<?= htmlspecialchars($dispName) ?>" placeholder="e.g. John Doe">
                                    <div class="ana-info-box">
                                        <div style="opacity: 0.8; font-size: 13px;"><?= $lang['hint_display_name'] ?></div>
                                    </div>
                                </div>
                                <div class="form-group" style="margin-bottom: 0px !important;">
                                    <label class="form-label"><?= $lang['label_email'] ?> <?= $infoIconNode ?></label>
                                    <input type="email" class="form-control" value="<?= htmlspecialchars($_SESSION['admin']['email'] ?? '') ?>" disabled>
                                    <div class="ana-info-box">
                                        <div style="opacity: 0.8; font-size: 13px;"><?= $lang['hint_email'] ?></div>
                                    </div>
                                </div>
                                   <!-- Password Display Section (New Design) -->
                                   <div class="form-group" style="margin-bottom: 0px !important;">
                                       <label class="form-label" style="margin-bottom:8px; font-size:15px; font-weight:600; color:#e5e7eb;"><?= $lang['password_label'] ?> <?= $infoIconNode ?></label>
                                       <div style="display:flex; align-items:center; gap:18px;">
                                           <input type="password" value="••••••••••••••" disabled style="flex:1; background:rgba(255, 255, 255, 0.03); border-radius:12px; border:1.5px solid rgba(118,117,236,0.15); padding:16px 18px; color:#fff; font-size:20px; letter-spacing:0.25em; font-family:inherit; font-weight:500;">
                                           <button type="button" onclick="openChangePasswordModal()" style="background:linear-gradient(135deg, var(--accent-purple), var(--primary-color)); color:#fff; border:none; border-radius:10px; font-size:16px; font-weight:700; padding:14px 28px; box-shadow:0 2px 12px rgba(32,193,245,0.12); cursor:pointer; transition:background 0.2s;"><?= $lang['password_change_btn'] ?></button>
                                       </div>
                                       <div style="margin-top:10px; display:flex; justify-content:space-between; align-items:center; color:#9ca3af; font-size:14px;">
                                           <div><?= $lang['password_last_updated'] ?> <span id="last-password-update"><?= $lang['password_never_changed'] ?></span></div>
                                       </div>
                                       <div class="ana-info-box">
                                           <div style="opacity: 0.8; font-size: 13px;"><?= $lang['password_hint'] ?></div>
                                       </div>
                                   </div>
                            <script>
                            function openChangePasswordModal() {
                                document.getElementById('change-password-modal').style.display = 'flex';
                            }
                            function closeChangePasswordModal() {
                                document.getElementById('change-password-modal').style.display = 'none';
                                document.getElementById('change-password-form').reset();
                                document.getElementById('change-password-form').style.display = 'flex';
                                document.getElementById('change-password-error').style.display = 'none';
                                document.getElementById('change-password-success').style.display = 'none';
                            }
                            function showChangePasswordError(msg) {
                                const errorDiv = document.getElementById('change-password-error');
                                document.getElementById('change-password-error-text').textContent = msg;
                                errorDiv.style.display = 'flex';
                            }
                            function submitChangePassword(e) {
                                e.preventDefault();
                                const form = e.target;
                                const current_password = form.current_password.value;
                                const new_password = form.new_password.value;
                                const confirm_password = form.confirm_password.value;
                                const errorDiv = document.getElementById('change-password-error');
                                const successDiv = document.getElementById('change-password-success');
                                errorDiv.style.display = 'none';
                                successDiv.style.display = 'none';
                                if (new_password !== confirm_password) {
                                    showChangePasswordError('<?= addslashes($lang['password_no_match']) ?>');
                                    return;
                                }
                                fetch('change_password.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({ current_password, new_password })
                                })
                                .then(res => res.json())
                                .then(data => {
                                    if (data.success) {
                                        closeChangePasswordModal();
                                        showToast('<?= addslashes($lang['password_changed_toast']) ?>', 'success');
                                        updateLastPasswordDate();
                                    } else {
                                        showChangePasswordError(data.error || '<?= addslashes($lang['password_change_failed']) ?>');
                                    }
                                })
                                .catch(() => {
                                    showChangePasswordError('<?= addslashes($lang['password_change_failed']) ?>');
                                });
                            }
                            function forgotPasswordRequest() {
                                const email = "<?= htmlspecialchars($_SESSION['admin']['email'] ?? '') ?>";
                                if (!email) {
                                    showToast('<?= addslashes($lang['password_no_email']) ?>', 'error');
                                    return;
                                }
                                const resetLink = document.getElementById('reset-password-link');
                                const modalLink = document.getElementById('modal-reset-link');
                                if (modalLink && modalLink.classList.contains('disabled')) return;
                                if (resetLink && resetLink.classList.contains('disabled')) return;

                                fetch('request_password_reset.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({ email })
                                })
                                .then(res => res.json())
                                .then(data => {
                                    if (data.success) {
                                        closeChangePasswordModal();
                                        showToast('<?= addslashes($lang['password_reset_sent']) ?>', 'success');
                                        if (resetLink) startResetCooldown(resetLink, 3600);
                                        if (modalLink) startModalResetCooldown(modalLink, 3600);
                                    } else {
                                        if (data.cooldown) {
                                            if (resetLink) startResetCooldown(resetLink, data.cooldown);
                                            if (modalLink) startModalResetCooldown(modalLink, data.cooldown);
                                        }
                                        showToast(data.error || '<?= addslashes($lang['password_reset_failed']) ?>', 'error');
                                    }
                                })
                                .catch(() => showToast('<?= addslashes($lang['password_reset_failed']) ?>', 'error'));
                            }

                            function startModalResetCooldown(linkEl, seconds) {
                                linkEl.classList.add('disabled');
                                linkEl.style.pointerEvents = 'none';
                                linkEl.style.opacity = '0.5';
                                const origText = linkEl.textContent;

                                function updateTimer() {
                                    if (seconds <= 0) {
                                        linkEl.classList.remove('disabled');
                                        linkEl.style.pointerEvents = '';
                                        linkEl.style.opacity = '';
                                        linkEl.textContent = origText;
                                        return;
                                    }
                                    const m = Math.floor(seconds / 60);
                                    const s = seconds % 60;
                                    linkEl.textContent = 'sent (' + m + ':' + String(s).padStart(2, '0') + ')';
                                    seconds--;
                                    setTimeout(updateTimer, 1000);
                                }
                                updateTimer();
                            }

                            function startResetCooldown(linkEl, seconds) {
                                linkEl.classList.add('disabled');
                                linkEl.style.pointerEvents = 'none';
                                linkEl.style.opacity = '0.5';
                                const origText = linkEl.textContent;

                                function updateTimer() {
                                    if (seconds <= 0) {
                                        linkEl.classList.remove('disabled');
                                        linkEl.style.pointerEvents = '';
                                        linkEl.style.opacity = '';
                                        linkEl.textContent = origText;
                                        return;
                                    }
                                    const m = Math.floor(seconds / 60);
                                    const s = seconds % 60;
                                    const timeStr = m + ':' + String(s).padStart(2, '0');
                                    linkEl.textContent = '(' + timeStr + ')';
                                    seconds--;
                                    setTimeout(updateTimer, 1000);
                                }
                                updateTimer();
                            }

                            function updateLastPasswordDate() {
                                fetch('get_last_password_update.php')
                                    .then(res => res.json())
                                    .then(data => {
                                        if (data.last_update) {
                                            document.getElementById('last-password-update').textContent = data.last_update;
                                        } else {
                                            document.getElementById('last-password-update').textContent = '<?= addslashes($lang['password_never_changed'] ?? 'Never changed') ?>';
                                        }
                                    });
                            }
                            document.addEventListener('DOMContentLoaded', updateLastPasswordDate);
                            </script>
                            </div>
                        </div>

                        <?php if ($isAdmin): ?>
                        <div id="website" class="settings-card">
                            <div class="card-header">
                                <h2><?= $lang['website_title'] ?></h2>
                                <p><?= $lang['website_desc'] ?></p>
                            </div>
                            
                            <div class="section-header" style="margin-top:0;">
                                <span class="section-accent" style="color:#20c1f5; background:#20c1f5;"></span>
                                <?= $lang['section_seo'] ?>
                            </div>

                            <div class="form-group">
                                <div class="lang-tab-header">
                                    <label class="form-label" style="margin:0;"><?= $lang['label_meta_de'] ?? 'Meta Description' ?> <?= $infoIconNode ?></label>
                                    <div class="lang-tab-switcher" data-target="meta_desc">
                                        <button type="button" class="lang-tab-btn active" data-lang="de" onclick="switchLangTab('meta_desc','de')">DE</button>
                                        <button type="button" class="lang-tab-btn" data-lang="en" onclick="switchLangTab('meta_desc','en')">EN</button>
                                    </div>
                                </div>
                                
                                <div class="lang-tab-pane" id="meta_desc_de">
                                    <textarea name="meta_description_de" id="meta_description_de" class="form-control" rows="4"><?= htmlspecialchars($metaDe) ?></textarea>
                                </div>
                                <div class="lang-tab-pane" id="meta_desc_en" style="display:none;">
                                    <textarea name="meta_description_en" id="meta_description_en" class="form-control" rows="4"><?= htmlspecialchars($metaEn) ?></textarea>
                                </div>

                                <div class="ana-info-box">
                                    <div class="meta_desc-hint de" style="opacity: 0.8; font-size: 13px;"><?= $lang['hint_meta_de'] ?? 'German meta description for SEO.' ?></div>
                                    <div class="meta_desc-hint en" style="display:none; opacity: 0.8; font-size: 13px;"><?= $lang['hint_meta_en'] ?? 'English meta description for SEO.' ?></div>
                                </div>
                            </div>

                            <!-- Site Identity Section -->
                            <div class="section-header">
                                <span class="section-accent" style="color:#7675ec; background:#7675ec;"></span>
                                <?= $lang['section_identity'] ?>
                            </div>

                            <div class="form-group">
                                <label class="form-label"><?= $lang['label_site_name'] ?> <?= $infoIconNode ?></label>
                                <input type="text" name="site_name" id="site_name" class="form-control" value="<?= htmlspecialchars($siteName) ?>" placeholder="e.g. ENTRIKS">
                                <div class="ana-info-box">
                                    <div style="opacity: 0.8; font-size: 13px;"><?= $lang['hint_site_name'] ?></div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label"><?= $lang['label_logo_url'] ?> <?= $infoIconNode ?></label>
                                <div class="premium-file-row">
                                    <div class="premium-file-thumb" onclick="document.getElementById('upload_logo').click()" title="Click to upload">
                                        <?php
                                        $logoPreview = $logoUrl;
                                        if ($logoPreview && strpos($logoPreview, 'assets/') === 0)
                                            $logoPreview = '../' . $logoPreview;
                                        if ($logoPreview && strpos($logoPreview, 'backend/') === 0)
                                            $logoPreview = str_replace('backend/', '', $logoPreview);
                                        ?>
                                        <img src="<?= htmlspecialchars($logoPreview ?: '../assets/img/placeholder.png') ?>" alt="Logo Preview" id="preview_logo_url">
                                    </div>
                                    <div class="premium-file-info">
                                        <input type="text" id="logo_url_display" class="premium-file-input" value="<?= htmlspecialchars(getDisplayFilename($logoUrl)) ?>" placeholder="Enter URL or upload file..." oninput="syncDisplayToHidden(this, 'logo_url', 'preview_logo_url')">
                                        <input type="hidden" name="logo_url" id="logo_url" value="<?= htmlspecialchars($logoUrl) ?>">
                                    </div>
                                    <button type="button" class="premium-file-upload-btn" onclick="document.getElementById('upload_logo').click()" title="Upload Image">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                            <path fill-rule="evenodd" d="M11.47 2.47a.75.75 0 0 1 1.06 0l4.5 4.5a.75.75 0 0 1-1.06 1.06l-3.22-3.22V16.5a.75.75 0 0 1-1.5 0V4.81L8.03 8.03a.75.75 0 0 1-1.06-1.06l4.5-4.5ZM3 15.75a.75.75 0 0 1 .75.75v2.25a1.5 1.5 0 0 0 1.5 1.5h13.5a1.5 1.5 0 0 0 1.5-1.5V16.5a.75.75 0 0 1 1.5 0v2.25a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3V16.5a.75.75 0 0 1 .75-.75Z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                    <input type="file" id="upload_logo" style="display:none" onchange="handleSystemImageUpload(this, 'logo_url')" accept="image/*">
                                </div>
                                <div class="ana-info-box">
                                    <div style="opacity: 0.8; font-size: 13px;"><?= $lang['hint_logo_url'] ?></div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label"><?= $lang['label_favicon_url'] ?> <?= $infoIconNode ?></label>
                                <div class="premium-file-row">
                                    <div class="premium-file-thumb" onclick="document.getElementById('upload_favicon').click()" title="Click to upload">
                                        <?php
                                        $faviconPreview = $faviconUrl;
                                        if ($faviconPreview && strpos($faviconPreview, 'assets/') === 0)
                                            $faviconPreview = '../' . $faviconPreview;
                                        if ($faviconPreview && strpos($faviconPreview, 'backend/') === 0)
                                            $faviconPreview = str_replace('backend/', '', $faviconPreview);
                                        ?>
                                        <img src="<?= htmlspecialchars($faviconPreview ?: '../assets/img/placeholder.png') ?>" alt="Favicon Preview" id="preview_favicon_url">
                                    </div>
                                    <div class="premium-file-info">
                                        <input type="text" id="favicon_url_display" class="premium-file-input" value="<?= htmlspecialchars(getDisplayFilename($faviconUrl)) ?>" placeholder="Enter URL or upload file..." oninput="syncDisplayToHidden(this, 'favicon_url', 'preview_favicon_url')">
                                        <input type="hidden" name="favicon_url" id="favicon_url" value="<?= htmlspecialchars($faviconUrl) ?>">
                                    </div>
                                    <button type="button" class="premium-file-upload-btn" onclick="document.getElementById('upload_favicon').click()" title="Upload Image">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                            <path fill-rule="evenodd" d="M11.47 2.47a.75.75 0 0 1 1.06 0l4.5 4.5a.75.75 0 0 1-1.06 1.06l-3.22-3.22V16.5a.75.75 0 0 1-1.5 0V4.81L8.03 8.03a.75.75 0 0 1-1.06-1.06l4.5-4.5ZM3 15.75a.75.75 0 0 1 .75.75v2.25a1.5 1.5 0 0 0 1.5 1.5h13.5a1.5 1.5 0 0 0 1.5-1.5V16.5a.75.75 0 0 1 1.5 0v2.25a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3V16.5a.75.75 0 0 1 .75-.75Z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                    <input type="file" id="upload_favicon" style="display:none" onchange="handleSystemImageUpload(this, 'favicon_url')" accept="image/*">
                                </div>
                                <div class="ana-info-box">
                                    <div style="opacity: 0.8; font-size: 13px;"><?= $lang['hint_favicon_url'] ?></div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label"><?= $lang['label_footer_logo_url'] ?> <?= $infoIconNode ?></label>
                                <div class="premium-file-row">
                                    <div class="premium-file-thumb" onclick="document.getElementById('upload_footer_logo').click()" title="Click to upload">
                                        <?php
                                        $footerLogoPreview = $footerLogoUrl;
                                        if ($footerLogoPreview && strpos($footerLogoPreview, 'assets/') === 0)
                                            $footerLogoPreview = '../' . $footerLogoPreview;
                                        if ($footerLogoPreview && strpos($footerLogoPreview, 'backend/') === 0)
                                            $footerLogoPreview = str_replace('backend/', '', $footerLogoPreview);
                                        ?>
                                        <img src="<?= htmlspecialchars($footerLogoPreview ?: '../assets/img/placeholder.png') ?>" alt="Footer Logo Preview" id="preview_footer_logo_url">
                                    </div>
                                    <div class="premium-file-info">
                                        <input type="text" id="footer_logo_url_display" class="premium-file-input" value="<?= htmlspecialchars(getDisplayFilename($footerLogoUrl)) ?>" placeholder="Enter URL or upload file..." oninput="syncDisplayToHidden(this, 'footer_logo_url', 'preview_footer_logo_url')">
                                        <input type="hidden" name="footer_logo_url" id="footer_logo_url" value="<?= htmlspecialchars($footerLogoUrl) ?>">
                                    </div>
                                    <button type="button" class="premium-file-upload-btn" onclick="document.getElementById('upload_footer_logo').click()" title="Upload Image">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                            <path fill-rule="evenodd" d="M11.47 2.47a.75.75 0 0 1 1.06 0l4.5 4.5a.75.75 0 0 1-1.06 1.06l-3.22-3.22V16.5a.75.75 0 0 1-1.5 0V4.81L8.03 8.03a.75.75 0 0 1-1.06-1.06l4.5-4.5ZM3 15.75a.75.75 0 0 1 .75.75v2.25a1.5 1.5 0 0 0 1.5 1.5h13.5a1.5 1.5 0 0 0 1.5-1.5V16.5a.75.75 0 0 1 1.5 0v2.25a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3V16.5a.75.75 0 0 1 .75-.75Z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                    <input type="file" id="upload_footer_logo" style="display:none" onchange="handleSystemImageUpload(this, 'footer_logo_url')" accept="image/*">
                                </div>
                                <div class="ana-info-box">
                                    <div style="opacity: 0.8; font-size: 13px;"><?= $lang['hint_footer_logo_url'] ?></div>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="lang-tab-header">
                                    <label class="form-label" style="margin:0;"><?= $lang['label_footer_de'] ?? 'Footer-Text' ?> <?= $infoIconNode ?></label>
                                    <div class="lang-tab-switcher" data-target="footer_txt">
                                        <button type="button" class="lang-tab-btn active" data-lang="de" onclick="switchLangTab('footer_txt','de')">DE</button>
                                        <button type="button" class="lang-tab-btn" data-lang="en" onclick="switchLangTab('footer_txt','en')">EN</button>
                                    </div>
                                </div>
                                
                                <div class="lang-tab-pane" id="footer_txt_de">
                                    <textarea name="footer_text_de" id="footer_text_de" class="form-control" rows="3"><?= htmlspecialchars($footerTextDe) ?></textarea>
                                </div>
                                <div class="lang-tab-pane" id="footer_txt_en" style="display:none;">
                                    <textarea name="footer_text_en" id="footer_text_en" class="form-control" rows="3"><?= htmlspecialchars($footerTextEn) ?></textarea>
                                </div>

                                <div class="ana-info-box">
                                    <div class="footer_txt-hint de" style="opacity: 0.8; font-size: 13px;"><?= $lang['hint_footer_de'] ?? 'German footer text.' ?></div>
                                    <div class="footer_txt-hint en" style="display:none; opacity: 0.8; font-size: 13px;"><?= $lang['hint_footer_en'] ?? 'English footer text.' ?></div>
                                </div>
                            </div>

                            <!-- Contact Info Section -->
                            <div class="section-header">
                                <span class="section-accent" style="color:#a04ee1; background:#a04ee1;"></span>
                                <?= $lang['section_contact'] ?>
                            </div>

                            <div class="form-group">
                                <label class="form-label"><?= $lang['label_contact_email'] ?> <?= $infoIconNode ?></label>
                                <input type="email" name="contact_email" id="contact_email" class="form-control" value="<?= htmlspecialchars($contactEmail) ?>" placeholder="info@entriks.com">
                                <div class="ana-info-box">
                                    <div style="opacity: 0.8; font-size: 13px;"><?= $lang['hint_contact_email'] ?></div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label"><?= $lang['label_phone'] ?> <?= $infoIconNode ?></label>
                                <input type="text" name="contact_phone" id="contact_phone" class="form-control" value="<?= htmlspecialchars($contactPhone) ?>" placeholder="+383 43 889 344">
                                <div class="ana-info-box">
                                    <div style="opacity: 0.8; font-size: 13px;"><?= $lang['hint_phone'] ?></div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label"><?= $lang['label_address'] ?> <?= $infoIconNode ?></label>
                                <textarea name="contact_address" id="contact_address" class="form-control" rows="3" placeholder="Lot Vaku L2.1, 10000 Pristina, Kosovo"><?= htmlspecialchars($contactAddress) ?></textarea>
                                <div class="ana-info-box">
                                    <div style="opacity: 0.8; font-size: 13px;"><?= $lang['hint_address'] ?></div>
                                </div>
                            </div>

                            <!-- Social Media Section -->
                            <div class="section-header">
                                <span class="section-accent" style="color:#d225d7; background:#d225d7;"></span>
                                <?= $lang['section_social'] ?>
                            </div>

                            <div class="form-group">
                                <label class="form-label"><?= $lang['label_fb'] ?> <?= $infoIconNode ?></label>
                                <input type="url" name="social_facebook" id="social_facebook" class="form-control" value="<?= htmlspecialchars($socialFacebook) ?>" placeholder="https://facebook.com/entriks">
                                <div class="ana-info-box">
                                    <div style="opacity: 0.8; font-size: 13px;"><?= $lang['hint_fb'] ?></div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label"><?= $lang['label_insta'] ?> <?= $infoIconNode ?></label>
                                <input type="url" name="social_instagram" id="social_instagram" class="form-control" value="<?= htmlspecialchars($socialInstagram) ?>" placeholder="https://instagram.com/entriks">
                                <div class="ana-info-box">
                                    <div style="opacity: 0.8; font-size: 13px;"><?= $lang['hint_insta'] ?></div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label"><?= $lang['label_linkedin'] ?> <?= $infoIconNode ?></label>
                                <input type="url" name="social_linkedin" id="social_linkedin" class="form-control" value="<?= htmlspecialchars($socialLinkedin) ?>" placeholder="https://linkedin.com/company/entriks">
                                <div class="ana-info-box">
                                    <div style="opacity: 0.8; font-size: 13px;"><?= $lang['hint_linkedin'] ?></div>
                                </div>
                            </div>
                            <!-- Feature Flags Section (Moved from Dashboard) -->
                            <div class="section-header">
                                <span class="section-accent" style="color:#f009d5; background:#f009d5;"></span>
                                <?= $lang['section_features'] ?>
                            </div>

                            <div class="form-group">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="cookie_consent_enabled" id="cookie_consent_enabled" <?= $cookieConsentEnabled ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                    <span class="toggle-label"><?= $lang['label_cookie_consent'] ?> <?= $infoIconNode ?></span>
                                </label>
                                <div class="ana-info-box">
                                    <div style="opacity: 0.8; font-size: 13px;"><?= $lang['hint_cookie_consent'] ?></div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="back_to_top_enabled" id="back_to_top_enabled" <?= $backToTopEnabled ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                    <span class="toggle-label"><?= $lang['label_back_to_top'] ?> <?= $infoIconNode ?></span>
                                </label>
                                <div class="ana-info-box">
                                    <div style="opacity: 0.8; font-size: 13px;"><?= $lang['hint_back_to_top'] ?></div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="back_to_top_mobile_enabled" id="back_to_top_mobile_enabled" <?= $backToTopMobileEnabled ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                    <span class="toggle-label">Back To Top Button - Mobile <?= $infoIconNode ?></span>
                                </label>
                                <div class="ana-info-box">
                                    <div style="opacity: 0.8; font-size: 13px;"><?= $lang['hint_back_to_top_mobile'] ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Blog Configuration Card -->
                        <div id="blog" class="settings-card">
                            <div class="card-header">
                                <h2><?= $lang['blog_config_title'] ?></h2>
                                <p><?= $lang['blog_config_desc'] ?></p>
                            </div>

                            <div class="section-header">
                                <span class="section-accent" style="color:#20c1f5; background:#20c1f5;"></span>
                                <?= $lang['section_blog'] ?>
                            </div>

                            <div class="form-group">
                                <label class="form-label"><?= $lang['label_posts_per_page'] ?> <?= $infoIconNode ?></label>
                                <select name="posts_per_page" id="posts_per_page" class="form-control">
                                    <option value="1" <?= $postsPerPage == 1 ? 'selected' : '' ?>>1 <?= $lang['settings_posts_per_page_suffix'] ?></option>
                                    <option value="2" <?= $postsPerPage == 2 ? 'selected' : '' ?>>2 <?= $lang['settings_posts_per_page_suffix'] ?></option>
                                    <option value="3" <?= $postsPerPage == 3 ? 'selected' : '' ?>>3 <?= $lang['settings_posts_per_page_suffix'] ?></option>
                                    <option value="4" <?= $postsPerPage == 4 ? 'selected' : '' ?>>4 <?= $lang['settings_posts_per_page_suffix'] ?></option>
                                </select>
                                <div class="ana-info-box">
                                    <div style="opacity: 0.8; font-size: 13px;"><?= $lang['hint_posts_per_page'] ?></div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="comments_enabled" id="comments_enabled" <?= $commentsEnabled ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                    <span class="toggle-label"><?= $lang['label_enable_comments'] ?> <?= $infoIconNode ?></span>
                                </label>
                                <div class="ana-info-box">
                                    <div style="opacity: 0.8; font-size: 13px;"><?= $lang['hint_enable_comments'] ?></div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="blog_show_recent_posts" id="blog_show_recent_posts" <?= $blogShowRecent ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                    <span class="toggle-label"><?= $lang['label_show_recent'] ?> <?= $infoIconNode ?></span>
                                </label>
                                <div class="ana-info-box">
                                    <div style="opacity: 0.8; font-size: 13px;"><?= $lang['hint_show_recent'] ?></div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="blog_show_categories" id="blog_show_categories" <?= $blogShowCategories ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                    <span class="toggle-label"><?= $lang['label_show_categories'] ?> <?= $infoIconNode ?></span>
                                </label>
                                <div class="ana-info-box">
                                    <div style="opacity: 0.8; font-size: 13px;"><?= $lang['hint_show_categories'] ?></div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="blog_show_tags" id="blog_show_tags" <?= $blogShowTags ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                    <span class="toggle-label"><?= $lang['label_show_tags'] ?> <?= $infoIconNode ?></span>
                                </label>
                                <div class="ana-info-box">
                                    <div style="opacity: 0.8; font-size: 13px;"><?= $lang['hint_show_tags'] ?></div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Dashboard Configuration Card -->
                        <div id="dashboard" class="settings-card">
                            <div class="card-header">
                                <h2><?= $lang['dashboard_config_title'] ?></h2>
                                <p><?= $lang['dashboard_config_desc'] ?></p>
                            </div>

                            <div class="section-header" style="margin-top:0;">
                                <span class="section-accent" style="color:#49b9f2; background:#49b9f2;"></span>
                                <?= $lang['section_regional'] ?>
                            </div>

                            <div class="form-group">
                                <label class="form-label"><?= $lang['label_default_lang'] ?> <?= $infoIconNode ?></label>
                                <select name="default_language" id="default_language" class="form-control">
                                    <option value="de" <?= $defaultLanguage === 'de' ? 'selected' : '' ?>><?= $lang['lang_de'] ?></option>
                                    <option value="en" <?= $defaultLanguage === 'en' ? 'selected' : '' ?>><?= $lang['lang_en'] ?></option>
                                </select>
                                <div class="ana-info-box">
                                    <div style="opacity: 0.8; font-size: 13px;"><?= $lang['hint_default_lang'] ?></div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="dashboard_search_enabled" id="dashboard_search_enabled" <?= ($currentSettings['dashboard_search_enabled'] ?? true) ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                    <span class="toggle-label">Dashboard Search Bar <?= $infoIconNode ?></span>
                                </label>
                                <div class="ana-info-box">
                                    <div style="opacity: 0.8; font-size: 13px;">Enable or disable the search bar in the dashboard topbar.</div>
                                </div>
                            </div>
                        </div>
                        </div>

                    </div>
                </div>

                <!-- Floating Save Button -->
                <div class="floating-save" id="floating-save-bar">
                    <button type="submit" class="btn-primary-large">
                        <span><?= $lang['action_save_changes'] ?></span>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </button>
                </div>
            </form>

    <!-- Change Password Modal (outside main form to avoid nested form issues) -->
    <div id="change-password-modal" style="z-index: 2000; display: none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:#000000; align-items:center; justify-content:center;">
        <!-- Blur backgrounds like reset page -->
        <div style="position:absolute; border-radius:50%; filter:blur(120px); pointer-events:none; z-index:0; left:-50px; top:-50px; width:400px; height:400px; background:linear-gradient(90deg,#20c1f5,#49b9f2,#7675ec,#a04ee1,#d225d7,#f009d5); opacity:0.3;"></div>
        <div style="position:absolute; border-radius:50%; filter:blur(120px); pointer-events:none; z-index:0; right:-100px; bottom:-100px; width:450px; height:450px; background:linear-gradient(90deg,#20c1f5,#49b9f2,#7675ec,#a04ee1,#d225d7,#f009d5); opacity:0.35;"></div>

        <div style="position:relative; z-index:1; width:100%; max-width:480px; background:#262525; border-radius:16px; box-shadow:0 4px 24px rgba(0,0,0,0.2); border:1px solid #404040; overflow:hidden;">
            <div style="padding:48px 40px; display:flex; flex-direction:column;">
                <!-- Close button -->
                <button onclick="closeChangePasswordModal()" style="position:absolute; top:20px; right:20px; background:none; border:none; color:rgba(255,255,255,0.4); cursor:pointer; z-index:2; padding:6px; border-radius:8px; transition:color 0.2s;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>

                <!-- Header with title instead of logo -->
                <div style="margin-bottom:32px; text-align:center;">
                    <h2 style="font-size:28px; font-weight:700; color:#ffffff; margin:0; font-family:'Manrope',sans-serif;"><?= $lang['password_change_title'] ?></h2>
                </div>

                <!-- Error message (same style as reset page) -->
                <div id="change-password-error" style="display:none; align-items:center; gap:12px; background:rgba(220,38,38,0.15); border:1px solid rgba(220,38,38,0.3); color:#fca5a5; padding:14px 16px; border-radius:8px; margin-bottom:24px; font-size:14px;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#dc2626" style="width:20px; height:20px; flex-shrink:0;">
                        <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zM12 8.25a.75.75 0 01.75.75v3.75a.75.75 0 01-1.5 0V9a.75.75 0 01.75-.75zm0 8.25a.75.75 0 100-1.5.75.75 0 000 1.5z" clip-rule="evenodd" />
                    </svg>
                    <span id="change-password-error-text"></span>
                </div>

                <!-- Success message -->
                <div id="change-password-success" style="display:none; text-align:center; padding:20px 0;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#10b981" style="width:48px;height:48px;margin-bottom:16px;">
                        <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                    </svg>
                    <h2 style="color:#fff; margin-bottom:12px; font-size:22px; font-family:'Manrope',sans-serif;"><?= $lang['password_changed_success'] ?></h2>
                    <p style="color:#9ca3af; margin-bottom:0; font-size:15px;"><?= $lang['password_changed_desc'] ?></p>
                </div>

                <form id="change-password-form" onsubmit="submitChangePassword(event)" style="display:flex; flex-direction:column; gap:20px;">
                    <div style="display:flex; flex-direction:column; gap:8px;">
                        <label style="font-size:14px; font-weight:600; color:#ffffff; padding-left:2px; margin-bottom:8px; font-family:'Manrope',sans-serif;"><?= $lang['password_current_label'] ?></label>
                        <div style="position:relative; display:flex; align-items:center; width:100%;">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="position:absolute; left:16px; width:20px; height:20px; color:rgba(255,255,255,0.4); pointer-events:none; z-index:1;">
                                <path fill-rule="evenodd" d="M12 1.5a5.25 5.25 0 00-5.25 5.25v3a3 3 0 00-3 3v6.75a3 3 0 003 3h10.5a3 3 0 003-3v-6.75a3 3 0 00-3-3v-3c0-2.9-2.35-5.25-5.25-5.25zm3.75 8.25v-3a3.75 3.75 0 10-7.5 0v3h7.5z" clip-rule="evenodd" />
                            </svg>
                            <input type="password" name="current_password" required placeholder="<?= htmlspecialchars($lang['password_current_placeholder']) ?>" style="width:100%; padding:14px 48px; background:#1a1a1a; border:1px solid #404040; border-radius:8px; font-size:15px; color:#ffffff; outline:none; transition:border-color 0.2s, box-shadow 0.2s; font-family:'Roboto',sans-serif;">
                        </div>
                    </div>

                    <div style="display:flex; flex-direction:column; gap:8px;">
                        <label style="font-size:14px; font-weight:600; color:#ffffff; padding-left:2px; margin-bottom:8px; font-family:'Manrope',sans-serif;"><?= $lang['password_new_label'] ?></label>
                        <div style="position:relative; display:flex; align-items:center; width:100%;">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="position:absolute; left:16px; width:20px; height:20px; color:rgba(255,255,255,0.4); pointer-events:none; z-index:1;">
                                <path fill-rule="evenodd" d="M12 1.5a5.25 5.25 0 00-5.25 5.25v3a3 3 0 00-3 3v6.75a3 3 0 003 3h10.5a3 3 0 003-3v-6.75a3 3 0 00-3-3v-3c0-2.9-2.35-5.25-5.25-5.25zm3.75 8.25v-3a3.75 3.75 0 10-7.5 0v3h7.5z" clip-rule="evenodd" />
                            </svg>
                            <input type="password" name="new_password" required placeholder="<?= htmlspecialchars($lang['password_new_placeholder']) ?>" style="width:100%; padding:14px 48px; background:#1a1a1a; border:1px solid #404040; border-radius:8px; font-size:15px; color:#ffffff; outline:none; transition:border-color 0.2s, box-shadow 0.2s; font-family:'Roboto',sans-serif;">
                        </div>
                    </div>

                    <div style="display:flex; flex-direction:column; gap:8px;">
                        <label style="font-size:14px; font-weight:600; color:#ffffff; padding-left:2px; margin-bottom:8px; font-family:'Manrope',sans-serif;"><?= $lang['password_confirm_label'] ?></label>
                        <div style="position:relative; display:flex; align-items:center; width:100%;">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="position:absolute; left:16px; width:20px; height:20px; color:rgba(255,255,255,0.4); pointer-events:none; z-index:1;">
                                <path fill-rule="evenodd" d="M12 1.5a5.25 5.25 0 00-5.25 5.25v3a3 3 0 00-3 3v6.75a3 3 0 003 3h10.5a3 3 0 003-3v-6.75a3 3 0 00-3-3v-3c0-2.9-2.35-5.25-5.25-5.25zm3.75 8.25v-3a3.75 3.75 0 10-7.5 0v3h7.5z" clip-rule="evenodd" />
                            </svg>
                            <input type="password" name="confirm_password" required placeholder="<?= htmlspecialchars($lang['password_confirm_placeholder']) ?>" style="width:100%; padding:14px 48px; background:#1a1a1a; border:1px solid #404040; border-radius:8px; font-size:15px; color:#ffffff; outline:none; transition:border-color 0.2s, box-shadow 0.2s; font-family:'Roboto',sans-serif;">
                        </div>
                    </div>

                    <button type="submit" style="width:100%; padding:14px; background-color:#d225d7; border:1px solid #d225d7; color:#fff; border-radius:8px; font-size:16px; font-weight:600; cursor:pointer; font-family:'Manrope',sans-serif; transition:opacity 0.2s; margin-top:4px;"><?= $lang['password_change_submit'] ?></button>

                    <p style="text-align:center; margin-top:16px; font-size:14px; color:#9ca3af; font-family:'Manrope',sans-serif;"><?= $lang['password_forgot_text'] ?> <a href="#" id="modal-reset-link" onclick="forgotPasswordRequest(); return false;" style="color:#d225d7; text-decoration:underline; font-weight:600; transition:opacity 0.3s;"><?= $lang['password_forgot_link'] ?></a><?= $lang['password_forgot_end'] ?></p>
                </form>
            </div>
        </div>
    </div>

        </main>
    </div>

    <!-- Hidden toast data -->
    <?php if (isset($_SESSION['toast_message'])): ?>
    <div id="toast-data" 
         data-message="<?= htmlspecialchars($_SESSION['toast_message']) ?>" 
         data-type="<?= htmlspecialchars($_SESSION['toast_type'] ?? 'success') ?>" 
         style="display:none;"></div>
    <?php
    unset($_SESSION['toast_message']);
    unset($_SESSION['toast_type']);
endif;
?>

    <div id="toast-container" class="local-toast-container"></div>

    <?php if ($isAdmin): ?>
    <!-- User Modal -->
    <div id="user-modal" class="sidebar-overlay" style="z-index: 2000; align-items: center; justify-content: center; display: none;">
        <div style="background: var(--panel-bg); padding: 40px; border-radius: 24px; width: 100%; max-width: 500px; border: 1px solid var(--border-color-strong); box-shadow: 0 20px 60px rgba(0,0,0,0.5); position:relative;">
            <button onclick="closeUserModal()" style="position:absolute; top:20px; right:20px; background:none; border:none; color:var(--text-muted); cursor:pointer;">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
            <h2 id="modal-title" style="margin-top:0; color:#fff; margin-bottom:24px;">Add New User</h2>
            <form id="add-user-form" onsubmit="submitUser(event)">
                <input type="hidden" name="action" id="user-action" value="create">
                
                <div class="form-group" id="display-name-group">
                    <label class="form-label">Display Name <?= $infoIconNode ?></label>
                    <input type="text" name="display_name" class="form-control" placeholder="Jane Doe">
                    <div class="ana-info-box">
                        <div style="opacity: 0.8; font-size: 13px;"><?= $lang['hint_modal_user_name'] ?></div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address <?= $infoIconNode ?></label>
                    <input type="email" name="email" class="form-control" required placeholder="jane@entriks.com">
                    <div class="ana-info-box">
                        <div style="opacity: 0.8; font-size: 13px;"><?= $lang['hint_modal_user_email'] ?></div>
                    </div>
                </div>
                <div class="form-group" id="password-group">
                    <label class="form-label">Password <?= $infoIconNode ?></label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••">
                    <div class="ana-info-box">
                        <div style="opacity: 0.8; font-size: 13px;"><?= $lang['hint_modal_user_password'] ?></div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Role <?= $infoIconNode ?></label>
                    <select name="role" class="form-control" onchange="const hint = document.getElementById('role-hint-text'); if(this.value === 'admin') hint.textContent = '<?= addslashes($lang['role_admin_desc']) ?>'; else hint.textContent = '<?= addslashes($lang['role_editor_desc']) ?>';">
                        <option value="editor">Editor</option>
                        <option value="admin">Admin</option>
                    </select>
                    <div class="ana-info-box active" style="display:block;">
                        <div id="role-hint-text" style="opacity: 0.8; font-size: 13px;"><?= $lang['role_editor_desc'] ?></div>
                    </div>
                </div>
                <button type="submit" id="modal-submit-btn" class="btn-primary-large" style="width:100%; justify-content:center;">Create User</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function switchTab(tabId, element) {
            document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
            if (element) {
                element.classList.add('active');
            } else {
                const navItem = document.querySelector(`.nav-item[href="#${tabId}"]`);
                if (navItem) navItem.classList.add('active');
            }

            document.querySelectorAll('.settings-card').forEach(el => el.classList.remove('active'));
            const card = document.getElementById(tabId);
            if (card) card.classList.add('active');
            window.location.hash = tabId;
        }

        function handleHash() {
            const hash = window.location.hash.replace('#', '');
            if (!hash) return;

            // 1. Direct Tab Match
            const navItem = document.querySelector(`.nav-item[href="#${hash}"]`);
            if (navItem) {
                switchTab(hash, navItem);
                return;
            }

            // 2. Child Element Match (e.g. #meta_description_de)
            const targetEl = document.getElementById(hash);
            if (targetEl) {
                // Find parent tab container (closest .settings-card)
                const parentTab = targetEl.closest('.settings-card');
                if (parentTab) {
                    const tabId = parentTab.id;
                    const tabNav = document.querySelector(`.nav-item[href="#${tabId}"]`);
                    
                    // Switch to that tab if not active
                    if (tabNav && !parentTab.classList.contains('active')) {
                        switchTab(tabId, tabNav);
                    }

                    // Determine visible target for highlight
                    let highlightTarget = targetEl;
                    
                    // If target is hidden (like file inputs), find the visible container
                    if (targetEl.type === 'hidden' || window.getComputedStyle(targetEl).display === 'none') {
                        const fileRow = targetEl.closest('.premium-file-row');
                        if (fileRow) {
                            highlightTarget = fileRow;
                        } else {
                            // Try finding a visible sibling input
                            const visibleSibling = targetEl.parentElement ? targetEl.parentElement.querySelector('input:not([type="hidden"]), textarea, select') : null;
                            if (visibleSibling) highlightTarget = visibleSibling;
                        }
                    }

                    // Scroll and Focus
                    setTimeout(() => {
                        highlightTarget.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        if (highlightTarget.tagName === 'INPUT' || highlightTarget.tagName === 'TEXTAREA') {
                            highlightTarget.focus();
                        }
                        
                        // Flash effect
                        highlightTarget.style.transition = 'box-shadow 0.3s ease, border-color 0.3s ease';
                        
                        // Store original styles to revert later
                        const originalBorder = highlightTarget.style.borderColor;
                        const originalShadow = highlightTarget.style.boxShadow;
                        const originalRadius = highlightTarget.style.borderRadius;

                        // Apply highlight
                        highlightTarget.style.borderColor = 'var(--primary-color)';
                        highlightTarget.style.boxShadow = '0 0 0 4px rgba(118, 117, 236, 0.2)';
                        // Ensure border radius is preserved or set if missing (for rows)
                        if (!originalRadius && highlightTarget.classList.contains('premium-file-row')) {
                           highlightTarget.style.borderRadius = '12px';
                        }
                        
                        setTimeout(() => {
                            highlightTarget.style.borderColor = originalBorder;
                            highlightTarget.style.boxShadow = originalShadow;
                            if (!originalRadius && highlightTarget.classList.contains('premium-file-row')) {
                                highlightTarget.style.borderRadius = ''; 
                            }
                        }, 2000);
                    }, 300); 
                }
            }
        }

        window.addEventListener('load', handleHash);
        window.addEventListener('hashchange', handleHash);

        document.getElementById('display_name').addEventListener('input', function(e) {
            const val = e.target.value.trim();
            const initial = val.length > 0 ? val.charAt(0).toUpperCase() : '?';
            const topHeader = document.querySelector('.topbar-right span') || document.querySelector('.topbar-avatar');
            if(topHeader) topHeader.textContent = initial;
        });

        function toggleHint(btn) {
            const group = btn.closest('.form-group') || btn.closest('.toggle-switch')?.closest('.form-group');
            if (!group) return;
            const info = group.querySelector('.ana-info-box');
            if (!info) return;

            // Close all other info boxes
            document.querySelectorAll('.ana-info-box.active').forEach(box => {
                if (box !== info) box.classList.remove('active');
            });

            info.classList.toggle('active');

            if (info.classList.contains('active')) {
                // Position tooltip right below the icon
                const rect = btn.getBoundingClientRect();
                const tooltipWidth = 300;
                let left = rect.left + (rect.width / 2) - 20; // align arrow with icon center
                // Keep within viewport
                if (left + tooltipWidth > window.innerWidth - 16) {
                    left = window.innerWidth - tooltipWidth - 16;
                }
                if (left < 16) left = 16;
                info.style.top = (rect.bottom + 8) + 'px';
                info.style.left = left + 'px';
            }
        }

        // Close on click outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.hint-icon') && !e.target.closest('.ana-info-box')) {
                document.querySelectorAll('.ana-info-box.active').forEach(box => {
                    box.classList.remove('active');
                });
            }
        });

        // Close tooltips on scroll / resize so they don't drift
        window.addEventListener('scroll', () => {
            document.querySelectorAll('.ana-info-box.active').forEach(box => box.classList.remove('active'));
        }, true);
        window.addEventListener('resize', () => {
            document.querySelectorAll('.ana-info-box.active').forEach(box => box.classList.remove('active'));
        });

        document.addEventListener('DOMContentLoaded', () => {
            const toastData = document.getElementById('toast-data');
            if (toastData) {
                setTimeout(() => showToast(toastData.dataset.message, toastData.dataset.type), 100);
            }

            const form = document.querySelector('form');
            const saveBar = document.getElementById('floating-save-bar');
            
            const initialValues = {};
            const elements = form.elements;

            for (let i = 0; i < elements.length; i++) {
                const el = elements[i];
                if (el.name && !el.disabled) {
                    if (el.type === 'checkbox') {
                        initialValues[el.name] = el.checked;
                    } else {
                        initialValues[el.name] = el.value;
                    }
                }
            }

            function checkForChanges() {
                let isDirty = false;
                for (let i = 0; i < elements.length; i++) {
                    const el = elements[i];
                    if (el.name && !el.disabled) {
                        if (el.type === 'checkbox') {
                            if (el.checked !== initialValues[el.name]) {
                                isDirty = true;
                                break;
                            }
                        } else {
                            if (el.value !== initialValues[el.name]) {
                                isDirty = true;
                                break;
                            }
                        }
                    }
                }

                if (isDirty) {
                    saveBar.classList.add('active');
                } else {
                    saveBar.classList.remove('active');
                }
            }

            function autoSave() {
                const formData = new FormData(form);
                formData.append('ajax', '1');

                const saveBtn = document.querySelector('.floating-save button');
                const originalText = saveBtn.innerText;
                saveBtn.innerText = 'Saving...';
                saveBtn.disabled = true;

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const languageWas = initialValues['default_language'];
                        const languageIs = form.elements['default_language']?.value;
                        const langChanged = languageIs && languageWas !== languageIs;

                        showToast('<?= $lang['msg_settings_saved'] ?>', 'success');
                        for (let i = 0; i < elements.length; i++) {
                            const el = elements[i];
                            if (el.name) {
                                if(el.type === 'checkbox') {
                                    initialValues[el.name] = el.checked;
                                } else {
                                    initialValues[el.name] = el.value;
                                }
                            }
                        }
                        saveBar.classList.remove('active');

                        if (langChanged) {
                            setTimeout(() => window.location.reload(), 1000);
                        }
                    } else {
                        showToast('Error saving: ' + (data.error || 'Unknown'), 'error');
                    }
                })
                .catch(err => {
                    showToast('Connection error', 'error');
                })
                .finally(() => {
                    saveBtn.innerText = originalText;
                    saveBtn.disabled = false;
                });
            }


            form.addEventListener('change', () => {
                checkForChanges();
            });

            form.addEventListener('submit', (e) => {
                e.preventDefault();
                autoSave();
            });

            form.addEventListener('input', checkForChanges);
        });

        <?php if ($isAdmin): ?>
        // System Image Upload Handling
        function handleSystemImageUpload(input, targetId) {
            const file = input.files[0];
            if (!file) return;

            const hiddenInput = document.getElementById(targetId);
            const displayInput = document.getElementById(targetId + '_display');
            const previewId = 'preview_' + targetId;
            const row = input.closest('.premium-file-row');

            // Simple validation
            if (file.size > 5 * 1024 * 1024) {
                showToast('File too large (max 5MB)', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('image', file);

            row.style.opacity = '0.6';
            row.style.pointerEvents = 'none';

            fetch('ajax_upload_system_image.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    hiddenInput.value = data.url;
                    if (displayInput) displayInput.value = data.filename || data.url;
                    
                    // Update Preview
                    updateFilePreview(hiddenInput, previewId);

                    hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                } else {
                    showToast(data.error || 'Upload failed', 'error');
                }
            })
            .catch(err => {
                console.error('Upload Error:', err);
                showToast('Network error during upload', 'error');
            })
            .finally(() => {
                row.style.opacity = '1';
                row.style.pointerEvents = 'all';
                input.value = '';
            });
        }

        function syncDisplayToHidden(displayInput, hiddenId, previewId) {
            const hiddenInput = document.getElementById(hiddenId);
            hiddenInput.value = displayInput.value;
            updateFilePreview(hiddenInput, previewId);
            hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
        }

        function updateFilePreview(input, previewId) {
            const previewImg = document.getElementById(previewId);
            if (!previewImg) return;
            
            let val = input.value.trim();
            if (!val) {
                previewImg.src = '../assets/img/placeholder.png';
                return;
            }
            
            let previewUrl = val;
            if (previewUrl.match(/^https?:\/\//)) {
                // External URL - use as is
            } else if (previewUrl.indexOf('backend/') === 0) {
                // Internal backend served image - from /backend/ context it's just the part after backend/
                previewUrl = previewUrl.replace('backend/', '');
            } else if (previewUrl.indexOf('../') !== 0 && previewUrl.indexOf('assets/') === 0) {
                // Local asset e.g. assets/img/... - from /backend/ context it needs ../
                previewUrl = '../' + previewUrl;
            }
            
            previewImg.src = previewUrl;
        }

        
        function loadUsers() {
            const list = document.getElementById('users-list');
            if (!list) return;

            fetch('api/users.php')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        if (data.users.length === 0) {
                            list.innerHTML = '<div style="text-align:center; padding:40px; color:var(--text-muted);">No users found.</div>';
                            return;
                        }

                        let html = '<table style="width:100%; border-collapse:collapse;">';
                        html += '<thead style="text-align:left; color:var(--text-dim); font-size:12px; text-transform:uppercase; letter-spacing:1px; border-bottom:1px solid var(--border-color);"><tr><th style="padding:12px;">User</th><th style="padding:12px;">Role</th><th style="padding:12px;">Created</th><th style="padding:12px; text-align:right;">Actions</th></tr></thead>';
                        html += '<tbody>';
                        
                        const currentUserId = "<?= $_SESSION['admin']['id'] ?>";

                        data.users.forEach(user => {
                            const isMe = user.id === currentUserId;
                            html += `<tr style="border-bottom:1px solid rgba(255,255,255,0.04);">
                                <td style="padding:16px 12px;">
                                    <div style="font-weight:600; color:#fff;">${escapeHtml(user.display_name || 'Unknown')}</div>
                                </td>
                                <td style="padding:16px 12px;">
                                    <span style="font-size:11px; padding:4px 8px; border-radius:6px; background:${user.role === 'admin' ? 'rgba(118, 117, 236, 0.15)' : 'rgba(32, 193, 245, 0.15)'}; color:${user.role === 'admin' ? '#7675ec' : '#20c1f5'}; border:1px solid ${user.role === 'admin' ? 'rgba(118, 117, 236, 0.3)' : 'rgba(32, 193, 245, 0.3)'};">${user.role.toUpperCase()}</span>
                                </td>
                                <td style="padding:16px 12px; color:var(--text-muted); font-size:14px;">
                                    ${user.created_at ? new Date(user.created_at).toLocaleDateString() : '-'}
                                </td>
                                <td style="padding:16px 12px; text-align:right;">
                                    ${!isMe ? 
                                        `<button onclick="deleteUser('${user.id}')" style="background:none; border:none; color:#ef4444; opacity:0.6; cursor:pointer; padding:6px;">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                        </button>` 
                                        : '<span style="color:var(--text-dim); font-size:12px;">(You)</span>'}
                                </td>
                            </tr>`;
                        });
                        html += '</tbody></table>';
                        list.innerHTML = html;
                    } else {
                        list.innerHTML = `<div style="color:#ef4444; text-align:center;">${data.error}</div>`;
                    }
                })
                .catch(err => {
                    list.innerHTML = `<div style="color:#ef4444; text-align:center;">Failed to load users.</div>`;
                });
        }

        function openUserModal(mode = 'create') {
            const modal = document.getElementById('user-modal');
            const title = document.getElementById('modal-title');
            const btn = document.getElementById('modal-submit-btn');
            const actionInput = document.getElementById('user-action');
            const nameGroup = document.getElementById('display-name-group');
            const passGroup = document.getElementById('password-group');
            const roleHint = document.getElementById('role-hint');

            document.getElementById('add-user-form').reset();

            if (mode === 'invite') {
                title.innerText = 'Invite New User';
                btn.innerText = 'Send Invitation';
                actionInput.value = 'invite';
                nameGroup.style.display = 'none';
                passGroup.style.display = 'none';
                roleHint.innerText = 'User will receive an email to set their password.';
                
                document.getElementsByName('display_name')[0].required = false;
                document.getElementsByName('password')[0].required = false;

            } else {
                title.innerText = 'Create New User';
                btn.innerText = 'Create User';
                actionInput.value = 'create';
                nameGroup.style.display = 'block';
                passGroup.style.display = 'block';
                roleHint.innerText = 'Editors can manage posts but cannot change site settings.';

                document.getElementsByName('display_name')[0].required = true;
                document.getElementsByName('password')[0].required = true;
            }

            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            setTimeout(() => modal.classList.add('active'), 10);
        }

        function closeUserModal() {
            const modal = document.getElementById('user-modal');
            modal.classList.remove('active');
            setTimeout(() => {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }, 300);
        }

        async function submitUser(e) {
            e.preventDefault();
            const form = e.target;
            const btn = form.querySelector('button[type="submit"]');
            
            const formData = {
                action: form.elements['action'].value,
                display_name: form.elements['display_name'].value,
                email: form.elements['email'].value,
                password: form.elements['password'].value,
                role: form.elements['role'].value
            };

            const originalText = btn.innerText;
            btn.innerText = formData.action === 'invite' ? 'Sending...' : 'Creating...';
            btn.disabled = true;

            if (formData.action === 'invite') {
                try {
                    const mainForm = document.querySelector('form[method="POST"]');
                    if (mainForm) {
                        const mainData = new FormData(mainForm);
                        mainData.append('ajax', '1');
                        await fetch('', { method: 'POST', body: mainData });
                    }
                } catch (err) {
                    console.error('SMTP auto-save failed:', err);
                }
            }

            fetch('api/users.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(formData)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    closeUserModal();
                    form.reset();
                    showToast(data.message || 'User created successfully', 'success');
                    loadUsers();
                } else {
                    showToast(data.error || 'Failed to create user', 'error');
                }
            })
            .catch(err => showToast('Connection error', 'error'))
            .finally(() => {
                btn.innerText = originalText;
                btn.disabled = false;
            });
        }

        function deleteUser(id) {
            if (!confirm('Are you sure you want to delete this user?')) return;
            
            fetch(`api/users.php?id=${id}`, {
                method: 'DELETE'
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('User deleted', 'success');
                    loadUsers();
                } else {
                    showToast(data.error || 'Failed to delete', 'error');
                }
            })
            .catch(err => showToast('Deletion error', 'error'));
        }

        function escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }

        const originalSwitchTab = switchTab;
        switchTab = function(tabId, element) {
            originalSwitchTab(tabId, element);
            if (tabId === 'users') {
                loadUsers();
            }
        };
        <?php endif; ?>

        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            if(!container) return;

            const toasts = container.children;
            if (toasts.length >= 3) {
                toasts[0].remove();
            }

            const toast = document.createElement('div');
            toast.className = `premium-toast premium-toast--${type} active`;
            toast.style.display = 'flex';
            toast.style.alignItems = 'center';
            toast.style.gap = '12px';
            toast.style.background = '#262525';
            toast.style.border = '1px solid rgba(255,255,255,0.1)';
            toast.style.padding = '16px 20px';
            toast.style.borderRadius = '12px';
            toast.style.boxShadow = '0 10px 40px rgba(0,0,0,0.3)';
            toast.style.minWidth = '300px';
            toast.style.animation = 'slideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1)';
            toast.style.marginBottom = '10px';
            toast.style.color = '#fff';
            
            const color = type === 'success' ? '#10b981' : '#ef4444';
            
            toast.innerHTML = `
                <div style="width:32px; height:32px; border-radius:50%; background:${color}20; display:flex; align-items:center; justify-content:center; color:${color}; flex-shrink:0;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:20px; height:20px;">
                        ${type === 'success' 
                            ? '<path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />' 
                            : '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 10-2 0 1 1 0 002 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />'}
                    </svg>
                </div>
                <div>
                    <div style="font-weight:600; font-size:14px; margin-bottom:2px;">${type === 'success' ? '<?= $lang['toast_success_title'] ?>' : '<?= $lang['toast_error_title'] ?>'}</div>
                    <div style="font-size:13px; color:#9ca3af;">${message}</div>
                </div>
                <button onclick="this.closest('.premium-toast').remove()" style="margin-left:auto; background:none; border:none; color:#ffffff; cursor:pointer; padding:4px; display:flex; align-items:center; opacity: 0.8; transition: opacity 0.2s;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:18px; height:18px;">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
            `;

            container.appendChild(toast);

            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(-10px)';
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }
    </script>
    <script>
        // Aggressive Preloader Removal
        (function() {
            function removePreloader() {
                const preloader = document.getElementById('preloader');
                if (preloader) {
                    preloader.style.opacity = '0';
                    preloader.style.pointerEvents = 'none';
                    setTimeout(() => preloader.remove(), 300);
                }
            }

            // Attempt removal at various stages to ensure it goes away
            document.addEventListener('DOMContentLoaded', removePreloader);
            window.addEventListener('load', removePreloader);
            setTimeout(removePreloader, 500); // Failsafe
            setTimeout(removePreloader, 2000); // Ultimate Failsafe
        })();
    </script>
    <script>
        function switchTab(tabId, el) {
            // Deactivate all nav items
            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.remove('active');
            });
            // Activate current nav item if passed
            if(el) {
                el.classList.add('active');
            } else {
                // If no element passed, try to find one that links to this tabId
                const nav = document.querySelector(`.nav-item[href="#${tabId}"]`);
                if (nav) nav.classList.add('active');
            }

            // Hide all cards
            document.querySelectorAll('.settings-card').forEach(card => {
                card.classList.remove('active');
                card.style.display = 'none';
            });

            // Show target card
            const target = document.getElementById(tabId);
            if(target) {
                 target.style.display = 'block';
                 setTimeout(() => target.classList.add('active'), 10);
            }
        }

        function highlightElement(el) {
            if (!el) return;
            // Scroll into view
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            // Flash effect
            el.style.transition = 'all 0.5s ease';
            const originalBorder = el.style.borderColor;
            const originalShadow = el.style.boxShadow;
            
            el.style.borderColor = '#d225d7'; // Accent color
            el.style.boxShadow = '0 0 0 4px rgba(210, 37, 215, 0.2)';
            
            setTimeout(() => {
                el.style.borderColor = originalBorder;
                el.style.boxShadow = originalShadow;
            }, 2000);

            // Focus if input
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName)) {
                el.focus();
            }
        }

        window.addEventListener('DOMContentLoaded', () => {
            const hash = window.location.hash.substring(1); // e.g. "favicon_url"
            if(!hash) return;

            // 1. Is it a Tab?
            const nav = document.querySelector(`.nav-item[href="#${hash}"]`);
            if(nav) { 
                switchTab(hash, nav);
                return;
            }

            // 2. Is it a Field inside a Tab?
            const targetEl = document.getElementById(hash);
            if (targetEl) {
                // Find parent tab (settings-card)
                const parentCard = targetEl.closest('.settings-card');
                if (parentCard) {
                    const tabId = parentCard.id;
                    switchTab(tabId); // Switch to the tab containing the element
                    
                    // Wait for tab animation/display
                    setTimeout(() => {
                        highlightElement(targetEl);
                    }, 300);
                }
            }
        });

        /* ── Language tab switcher ── */
        function switchLangTab(group, lang) {
            const dePane = document.getElementById(group + '_de');
            const enPane = document.getElementById(group + '_en');
            
            // Toggle pane visibility
            if(dePane) dePane.style.display = (lang === 'de') ? '' : 'none';
            if(enPane) enPane.style.display = (lang === 'en') ? '' : 'none';
            
            // Update hint spans if they exist
            const hints = document.querySelectorAll(`.${group}-hint`);
            hints.forEach(d => {
                d.style.display = d.classList.contains(lang) ? '' : 'none';
            });

            // Toggle active button
            document.querySelectorAll('.lang-tab-switcher[data-target="' + group + '"] .lang-tab-btn').forEach(function(btn) {
                btn.classList.toggle('active', btn.dataset.lang === lang);
            });
        }
    </script>
    <style>
        .lang-tab-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .lang-tab-switcher {
            display: flex;
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 8px;
            overflow: hidden;
        }
        .lang-tab-btn {
            background: transparent;
            border: none;
            color: rgba(255,255,255,0.5);
            padding: 4px 14px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            letter-spacing: 0.5px;
            transition: background 0.18s, color 0.18s;
        }
        .lang-tab-btn:first-child {
            border-right: 1px solid rgba(255,255,255,0.12);
        }
        .lang-tab-btn.active {
            background: rgba(32,193,245,0.18);
            color: #20c1f5;
        }
        .lang-tab-btn:hover:not(.active) {
            background: rgba(255,255,255,0.06);
            color: rgba(255,255,255,0.8);
        }
    </style>
</body>
</html>