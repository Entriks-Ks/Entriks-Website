<?php

// request_password_reset.php

require_once 'database.php';

require_once 'config.php';

require_once 'resend_helper.php';



header('Content-Type: application/json');



$data = json_decode(file_get_contents('php://input'), true);

$email = trim($data['email'] ?? '');



if (!$email || $email === null) {

    echo json_encode(['success' => false, 'error' => 'Email required.']);

    exit;

}



$user = $db->admins->findOne(['email' => $email]);

if (!$user) {

    // For security, don't reveal if the email exists or not

    echo json_encode(['success' => true]);

    exit;

}



// Check if there's already an active (non-expired) reset token for this email

$existingReset = $db->password_resets->findOne([

    'email' => $email,

    'expires' => ['$gte' => time()]

]);

if ($existingReset) {

    $remainingSeconds = $existingReset['expires'] - time();

    $remainingMinutes = ceil($remainingSeconds / 60);

    echo json_encode([

        'success' => false,

        'error' => "A reset link was already sent. Please check your inbox or wait {$remainingMinutes} minute(s) before requesting again.",

        'cooldown' => $remainingSeconds

    ]);

    exit;

}



// Invalidate any old expired tokens for this email

$db->password_resets->deleteMany(['email' => $email]);



$token = bin2hex(random_bytes(32));

$expires = time() + 3600; // 1 hour



$db->password_resets->insertOne([

    'email' => $email,

    'token' => $token,

    'expires' => $expires,

    'created_at' => new MongoDB\BSON\UTCDateTime()

]);



// Build reset link dynamically (same approach as invite_editor.php)

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';

$host = $_SERVER['HTTP_HOST'];

$dashboardFolder = dirname($_SERVER['PHP_SELF']);

$resetLink = "{$protocol}://{$host}{$dashboardFolder}/reset_password.php?token={$token}";



// Template variables

$product_name = $siteConfig['site_name'] ?? 'Entriks';

$company_name = $siteConfig['site_name'] ?? 'Entriks';

$logo_url = $siteConfig['logo_url'] ?? 'assets/img/logo.png';

if ($logo_url && strpos($logo_url, 'http') !== 0) {

    $logo_url = "{$protocol}://{$host}{$dashboardFolder}/" . ltrim($logo_url, '/');

}



$userName = $user['display_name'] ?? $user['name'] ?? 'there';



// Parse Address

$address_raw = $siteConfig['contact_address'] ?? '';

$address_parts = explode(',', $address_raw);

$company_street = trim($address_parts[0] ?? '');

$city_country = trim($address_parts[1] ?? '');

$city_parts = explode(' ', $city_country);

$company_country = array_pop($city_parts);

$company_city = implode(' ', $city_parts);



$current_year = date('Y');



$subject = 'Password Reset Request - ' . $product_name;



// Outlook-ready table-based template (same style as invitation email)

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

                    

                    <!-- GREETING -->

                    <tr>

                        <td align='left' style='padding-bottom: 24px; color: #1f2937; font-size: 16px; line-height: 24px;'>

                            <p style='margin: 0; font-weight: 600;'>Hi {$userName},</p>

                        </td>

                    </tr>

                    <tr>

                        <td align='left' style='padding-bottom: 20px; color: #4b5563; font-size: 16px; line-height: 24px;'>

                            <p style='margin: 0;'>We received a request to reset your password for your <strong>{$product_name}</strong> dashboard account.</p>

                        </td>

                    </tr>

                    <tr>

                        <td align='left' style='padding-bottom: 32px; color: #4b5563; font-size: 16px; line-height: 24px;'>

                            <p style='margin: 0;'>Click the button below to set a new password:</p>

                        </td>

                    </tr>

                    

                    <!-- BUTTON -->

                    <tr>

                        <td align='center' style='padding-bottom: 40px;'>

                            <table border='0' cellspacing='0' cellpadding='0'>

                                <tr>

                                    <td align='center' bgcolor='#d225d7' style='border-radius: 8px;'>

                                        <a href='{$resetLink}' target='_blank' style='display: inline-block; padding: 14px 32px; font-family: Arial, sans-serif; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 8px; border: 1px solid #d225d7;'>Reset Password</a>

                                    </td>

                                </tr>

                            </table>

                        </td>

                    </tr>

                    

                    <!-- EXTRA INFO -->

                    <tr>

                        <td align='left' style='padding-bottom: 12px; color: #4b5563; font-size: 15px; line-height: 22px;'>

                            <p style='margin: 0;'>This link will expire in <strong>1 hour</strong>. If you did not request a password reset, you can safely ignore this email.</p>

                        </td>

                    </tr>

                    <tr>

                        <td align='left' style='padding-bottom: 32px; color: #4b5563; font-size: 15px; line-height: 22px;'>

                            <p style='margin: 0;'>If you need help, contact our support team anytime — we reply fast.</p>

                        </td>

                    </tr>

                    

                    <!-- SIGNATURE -->

                    <tr>

                        <td align='left' style='padding-bottom: 40px; color: #4b5563; font-size: 16px; line-height: 24px;'>

                            <p style='margin: 0;'>Best regards,<br><strong>The {$product_name} Team</strong></p>

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

                            <p style='margin: 0;'>If the button above doesn't work, copy and paste this link into your browser:<br>

                            <a href='{$resetLink}' style='color: #d225d7; text-decoration: underline;'>{$resetLink}</a></p>

                        </td>

                    </tr>

                    

                    <!-- COMPANY INFO -->

                    <tr>

                        <td align='center' style='padding-bottom: 20px; color: #9ca3af; font-size: 12px; line-height: 18px;'>

                            <p style='margin: 0;'>&copy; {$current_year} {$product_name}. All rights reserved.</p>

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



$result = sendResendEmail($email, $subject, $html);

if ($result === true || (is_array($result) && ($result['success'] ?? false))) {

    echo json_encode(['success' => true]);

} else {

    // Email failed - delete the token so user can try again

    $db->password_resets->deleteMany(['email' => $email, 'token' => $token]);

    $errorDetail = is_array($result) ? ($result['error'] ?? 'Unknown error') : 'Failed to send email';

    error_log('Password reset email failed for ' . $email . ': ' . $errorDetail);

    echo json_encode(['success' => false, 'error' => 'Failed to send email: ' . $errorDetail]);

}

