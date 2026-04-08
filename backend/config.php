<?php



/**

 * Site Configuration Loader

 * Fetches global settings from database for use across all pages

 */



// Prevent multiple includes

if (!defined('SITE_CONFIG_LOADED')) {

    define('SITE_CONFIG_LOADED', true);



    // Default values

    $siteConfig = [

        'site_name' => 'ENTRIKS',

        'logo_url' => 'assets/img/logo.png',

        'favicon_url' => 'assets/img/favicon.png',

        'contact_email' => 'info@entriks.com',

        'contact_phone' => '+383 43 889 344',

        'contact_address' => 'Lot Vaku L2.1, 10000 Pristina, Kosovo',

        'social_linkedin' => '',

        'social_youtube' => '',

        'social_instagram' => '',

        'default_timezone' => 'Europe/Amsterdam',

        'default_language' => 'de',

        'cookie_consent_enabled' => true,

        'posts_per_page' => 10,

        'comments_enabled' => true

    ];



    try {

        // Include database if not already included

        if (!isset($db)) {

            require_once __DIR__ . '/database.php';

        }



        if (isset($db)) {

            $settings = $db->settings->findOne(['type' => 'global_config']);

            if ($settings) {

                // Merge settings from database

                foreach ($siteConfig as $key => $default) {

                    if (isset($settings[$key])) {

                        // Handle boolean values properly

                        if (is_bool($default)) {

                            $siteConfig[$key] = (bool) $settings[$key];

                        } elseif (!empty($settings[$key])) {

                            $siteConfig[$key] = $settings[$key];

                        }

                    }

                }

            }

        }

    } catch (Exception $e) {

        // Use defaults on error

    }



    // Make individual variables available for convenience

    $siteName = $siteConfig['site_name'];

    $siteLogoUrl = $siteConfig['logo_url'];

    $siteFaviconUrl = $siteConfig['favicon_url'];

    // Fix paths for backend pages (add ../ prefix since we're in /backend/ folder)

    // For blog subfolder, add ../../ prefix

    $currentPath = $_SERVER['PHP_SELF'] ?? '';

    $isBlogPage = strpos($currentPath, '/backend/blog/') !== false;

    if (!empty($siteFaviconUrl) && strpos($siteFaviconUrl, 'http') !== 0 && strpos($siteFaviconUrl, '../') !== 0) {

        if ($isBlogPage) {

            $siteFaviconUrl = '../../' . $siteFaviconUrl;

        } else {

            $siteFaviconUrl = '../' . $siteFaviconUrl;

        }

    }

    if (!empty($siteLogoUrl) && strpos($siteLogoUrl, 'http') !== 0 && strpos($siteLogoUrl, '../') !== 0) {

        if ($isBlogPage) {

            $siteLogoUrl = '../../' . $siteLogoUrl;

        } else {

            $siteLogoUrl = '../' . $siteLogoUrl;

        }

    }

    $sitePostsPerPage = $siteConfig['posts_per_page'];

    $siteCommentsEnabled = $siteConfig['comments_enabled'];

    $defaultLanguage = $siteConfig['default_language'];



    // Override with per-user language preference if set

    if (isset($_SESSION['admin']['preferred_language']) && !empty($_SESSION['admin']['preferred_language'])) {

        $defaultLanguage = $_SESSION['admin']['preferred_language'];

    }



    // Load Language File

    $langFile = __DIR__ . '/languages/' . $defaultLanguage . '.php';

    if (!file_exists($langFile)) {

        $langFile = __DIR__ . '/languages/en.php';  // Fallback

    }

    $lang = require $langFile;

}

