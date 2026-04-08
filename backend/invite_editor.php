<?php

require_once 'session_config.php';

require_once 'database.php';

require_once 'resend_helper.php';



// Check if user is logged in as admin

if (!isset($_SESSION['admin']) || ($_SESSION['admin']['role'] ?? 'admin') !== 'admin') {

    http_response_code(403);

    echo json_encode(['success' => false, 'message' => 'Unauthorized']);

    exit;

}



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_once 'config.php';



    $email = $_POST['email'] ?? '';

    $inviteeName = $_POST['display_name'] ?? 'there';

    $position = $_POST['position'] ?? 'Editor';

    $permissions = $_POST['permissions'] ?? [];

    if (is_string($permissions)) {

        $permissions = explode(',', $permissions);

    }



    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        echo json_encode(['success' => false, 'message' => 'Invalid email address']);

        exit;

    }



    if (!$db) {

        echo json_encode(['success' => false, 'message' => 'Database connection failed']);

        exit;

    }



    // Check if user already exists

    $existingUser = $db->admins->findOne(['email' => $email]);

    if ($existingUser) {

        echo json_encode(['success' => false, 'message' => 'User already exists with this email']);

        exit;

    }



    // Generate secure invitation token

    $token = bin2hex(random_bytes(32));

    $expires = new MongoDB\BSON\UTCDateTime((time() + (48 * 3600)) * 1000);  // 48 hours expiry



    // Prepare invitation link

    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';

    $host = $_SERVER['HTTP_HOST'];

    $dashboardFolder = dirname($_SERVER['PHP_SELF']);

    $inviteLink = "{$protocol}://{$host}{$dashboardFolder}/accept-invitation.php?token={$token}";



    // Setup Template Variables

    $product_name = $siteConfig['site_name'] ?? 'Your Dashboard';

    $inviter_name = ($_SESSION['admin']['name'] ?? 'An Administrator');

    $company_name = $siteConfig['site_name'] ?? 'Entriks';

    $logo_url = $siteConfig['logo_url'] ?? 'assets/img/logo.png';

    if ($logo_url && strpos($logo_url, 'http') !== 0) {

        $logo_url = "{$protocol}://{$host}{$dashboardFolder}/" . ltrim($logo_url, '/');

    }



    // Parse Address

    $address_raw = $siteConfig['contact_address'] ?? '';

    $address_parts = explode(',', $address_raw);

    $company_street = trim($address_parts[0] ?? '');

    $city_country = trim($address_parts[1] ?? '');

    $city_parts = explode(' ', $city_country);

    $company_country = array_pop($city_parts);

    $company_city = implode(' ', $city_parts);



    $current_year = date('Y');



    // Send email

    $subject = sprintf($lang['email_invite_subject'], $product_name);



    // Outlook-ready table-based template

    $html = "

    <!DOCTYPE html>

    <html>

    <head>

        <meta charset='utf-8'>

        <meta name='viewport' content='width=device-width, initial-scale=1'>

        <meta http-equiv='X-UA-Compatible' content='IE=edge'>

        <!--[if mso]>

        <style type='text/css'>

            body, table, td, p, a, li, blockquote { font-family: Arial, Helvetica, sans-serif !important; }

        </style>

        <![endif]-->

    </head>

    <body style='margin: 0; padding: 0; background-color: #ffffff; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased;'>

        <table border='0' cellpadding='0' cellspacing='0' width='100%' style='table-layout: fixed;'>

            <tr>

                <td align='center' style='padding: 40px 20px;'>

                    <!--[if (gte mso 9)|(IE)]>

                    <table align='center' border='0' cellspacing='0' cellpadding='0' width='600'>

                    <tr>

                    <td align='center' valign='top' width='600'>

                    <![endif]-->

                    <table border='0' cellpadding='0' cellspacing='0' width='100%' style='max-width: 600px;'>

                        <!-- LOGO -->

                        <tr>

                            <td align='center' style='padding-bottom: 40px;'>

                                <img src='{$logo_url}' alt='{$product_name}' height='40' style='display: block; height: 40px; border: 0;'>

                            </td>

                        </tr>

                        

                        <!-- BODY -->

                        <tr>

                            <td align='left' style='padding-bottom: 24px; color: #1f2937; font-size: 16px; line-height: 24px;'>

                                <p style='margin: 0; font-weight: 600;'>" . sprintf($lang['email_hi'], $inviteeName) . "</p>

                            </td>

                        </tr>

                        <tr>

                            <td align='left' style='padding-bottom: 20px; color: #4b5563; font-size: 16px; line-height: 24px;'>

                                <p style='margin: 0;'>" . sprintf($lang['email_invite_text'], "<strong>{$inviter_name}</strong>", "<strong>{$product_name}</strong>", "<strong>{$position}</strong>") . "</p>

                            </td>

                        </tr>

                        <tr>

                            <td align='left' style='padding-bottom: 32px; color: #4b5563; font-size: 16px; line-height: 24px;'>

                                <p style='margin: 0;'>{$lang['email_cta_text']}</p>

                            </td>

                        </tr>

                        

                        <!-- BUTTON -->

                        <tr>

                            <td align='center' style='padding-bottom: 40px;'>

                                <table border='0' cellspacing='0' cellpadding='0'>

                                    <tr>

                                        <td align='center' bgcolor='#d225d7' style='border-radius: 8px;'>

                                            <a href='{$inviteLink}' target='_blank' style='display: inline-block; padding: 14px 32px; font-family: Arial, sans-serif; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 8px; border: 1px solid #d225d7;'>{$lang['email_button_label']}</a>

                                        </td>

                                    </tr>

                                </table>

                            </td>

                        </tr>

                        

                        <!-- EXTRA INFO -->

                        <tr>

                            <td align='left' style='padding-bottom: 12px; color: #4b5563; font-size: 15px; line-height: 22px;'>

                                <p style='margin: 0;'>" . sprintf($lang['email_questions'], $inviter_name) . "</p>

                            </td>

                        </tr>

                        <tr>

                            <td align='left' style='padding-bottom: 32px; color: #4b5563; font-size: 15px; line-height: 22px;'>

                                <p style='margin: 0;'>{$lang['email_support']}</p>

                            </td>

                        </tr>

                        

                        <!-- SIGNATURE -->

                        <tr>

                            <td align='left' style='padding-bottom: 40px; color: #4b5563; font-size: 16px; line-height: 24px;'>

                                <p style='margin: 0;'>{$lang['email_welcome']}<br><strong>" . sprintf($lang['email_team'], $product_name) . "</strong></p>

                            </td>

                        </tr>

                        

                        <!-- FOOTER HR -->

                        <tr>

                            <td style='padding-bottom: 32px;'>

                                <table border='0' cellpadding='0' cellspacing='0' width='100%'>

                                    <tr><td style='border-top: 1px solid #e5e7eb; line-height: 1px; font-size: 1px;'>&nbsp;</td></tr>

                                </table>

                            </td>

                        </tr>

                        

                        <!-- FALLBACK LINK -->

                        <tr>

                            <td align='center' style='padding-bottom: 32px; color: #9ca3af; font-size: 12px; line-height: 18px;'>

                                <p style='margin: 0;'>{$lang['email_fallback_text']}<br>

                                <a href='{$inviteLink}' style='color: #d225d7; text-decoration: underline;'>{$inviteLink}</a></p>

                            </td>

                        </tr>

                        

                        <!-- COMPANY INFO -->

                        <tr>

                            <td align='center' style='padding-bottom: 20px; color: #9ca3af; font-size: 12px; line-height: 18px;'>

                                <p style='margin: 0;'>" . sprintf($lang['email_footer_rights'], $current_year, $product_name) . "</p>

                                <p style='margin: 8px 0 0;'>

                                    <strong>{$company_name}</strong><br>

                                    {$company_street}, {$company_city}, {$company_country}

                                </p>

                            </td>

                        </tr>

                    </table>

                    <!--[if (gte mso 9)|(IE)]>

                    </td>

                    </tr>

                    </table>

                    <![endif]-->

                </td>

            </tr>

        </table>

    </body>

    </html>

    ";



    $emailResult = sendResendEmail($email, $subject, $html);

    if ($emailResult === true || (is_array($emailResult) && ($emailResult['success'] ?? false))) {

        // Save invitation to database ONLY if email succeeds

        $db->admins->insertOne([

            'email' => $email,

            'display_name' => $inviteeName,

            'role' => 'editor',

            'position' => $position,

            'permissions' => $permissions,

            'status' => 'pending',

            'invitation_token' => $token,

            'invitation_expires' => $expires,

            'created_at' => new MongoDB\BSON\UTCDateTime()

        ]);

        echo json_encode(['success' => true, 'message' => 'Invitation sent successfully']);

    } else {

        $errorDetail = is_array($emailResult) ? ($emailResult['error'] ?? '') : '';

        echo json_encode(['success' => false, 'message' => 'Failed to send invitation email. ' . ($errorDetail ?: 'If you are using a test account, ensure the email is verified in Resend.')]);

    }

} else {

    http_response_code(405);

    echo json_encode(['success' => false, 'message' => 'Method not allowed']);

}

