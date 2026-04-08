<?php

// reset_password.php

require_once 'database.php';

require_once 'config.php';



$token = $_GET['token'] ?? '';

$error = '';

$success = false;

$fatalError = false;



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['token'] ?? '';

    $newPassword = $_POST['new_password'] ?? '';

    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!$newPassword || !$confirmPassword) {

        $error = 'Please fill in all fields.';

    } elseif ($newPassword !== $confirmPassword) {

        $error = 'Passwords do not match.';

    } elseif (strlen($newPassword) < 6) {

        $error = 'Password must be at least 6 characters.';

    } else {

        $reset = $db->password_resets->findOne(['token' => $token, 'expires' => ['$gte' => time()]]);

        if (!$reset) {

            $error = 'Invalid or expired token.';

            $fatalError = true;

        } else {

            $email = $reset['email'];

            $admin = $db->admins->findOne(['email' => $email]);



            // Check if new password was used before (current + history)

            $usedBefore = false;

            if ($admin && isset($admin['password']) && password_verify($newPassword, $admin['password'])) {

                $usedBefore = true;

            }

            if (!$usedBefore) {

                $passwordHistory = isset($admin['password_history']) ? (array)$admin['password_history'] : [];

                foreach ($passwordHistory as $oldHash) {

                    if (password_verify($newPassword, (string)$oldHash)) {

                        $usedBefore = true;

                        break;

                    }

                }

            }



            if ($usedBefore) {

                $error = "Sorry, you can't use a password you've used before. Please try a different password.";

            } else {

                // Save current password to history before updating

                $updateOps = [

                    '$set' => [

                        'password' => password_hash($newPassword, PASSWORD_DEFAULT),

                        'password_updated_at' => new MongoDB\BSON\UTCDateTime()

                    ]

                ];

                if ($admin && isset($admin['password'])) {

                    $updateOps['$push'] = ['password_history' => $admin['password']];

                }

                $db->admins->updateOne(['email' => $email], $updateOps);

                $db->password_resets->deleteMany(['email' => $email]);

                $success = true;

            }

        }

    }

} else {

    // Validate token on GET

    if (empty($token)) {

        $error = 'Invalid reset link.';

        $fatalError = true;

    } else {

        $reset = $db->password_resets->findOne(['token' => $token, 'expires' => ['$gte' => time()]]);

        if (!$reset) {

            $error = 'This reset link has expired or is invalid.';

            $fatalError = true;

        }

    }

}

?>

<!DOCTYPE html>

<html>

<head>

    <meta charset="utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Reset Password - <?= htmlspecialchars($siteConfig['site_name'] ?? 'Entriks') ?></title>

    <link rel="icon" type="image/png" href="<?= htmlspecialchars($siteConfig['favicon_url'] ?? 'assets/img/favicon.png') ?>">

    <link rel="stylesheet" href="assets/css/login.css">

    <link rel="stylesheet" href="assets/css/login_extra.css">

</head>

<body>

    <div class="login-wrapper">

        <div class="blur-bg-theme top-left"></div>

        <div class="blur-bg-theme bottom-right"></div>



        <div class="login-container">

            <div class="login-right">

                <div class="login-header">

                    <img src="<?= htmlspecialchars($siteConfig['logo_url'] ?? 'assets/img/logo.png') ?>" alt="Logo" class="login-logo">

                </div>



                <?php if ($fatalError): ?>

                    <div class="error-message">

                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;">

                            <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zM12 8.25a.75.75 0 01.75.75v3.75a.75.75 0 01-1.5 0V9a.75.75 0 01.75-.75zm0 8.25a.75.75 0 100-1.5.75.75 0 000 1.5z" clip-rule="evenodd" />

                        </svg>

                        <span><?= htmlspecialchars($error) ?></span>

                    </div>

                    <a href="login.php" class="login-btn" style="display:block; text-align:center; text-decoration:none; background-color:#d225d7; border-color:#d225d7; padding:14px 32px; color:#fff; border-radius:8px; font-weight:600; font-size:16px; margin-top:24px;">Go to Login</a>

                <?php elseif ($success): ?>

                    <div style="text-align:center; padding: 20px 0;">

                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#10b981" style="width:48px;height:48px;margin-bottom:16px;">

                            <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />

                        </svg>

                        <h2 style="color:#fff; margin-bottom:12px; font-size:22px;">Password Reset Successfully</h2>

                        <p style="color:#9ca3af; margin-bottom:24px;">Your password has been updated. You can now log in with your new password.</p>

                        <a href="login.php" class="login-btn" style="display:inline-block; text-decoration:none; background-color: #d225d7; border-color: #d225d7; padding: 14px 32px; color:#fff; border-radius:8px; font-weight:600; font-size:16px;">Go to Login</a>

                    </div>

                <?php else: ?>

                    <?php if ($error): ?>

                        <div class="error-message" style="margin-bottom: 20px;">

                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;">

                                <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zM12 8.25a.75.75 0 01.75.75v3.75a.75.75 0 01-1.5 0V9a.75.75 0 01.75-.75zm0 8.25a.75.75 0 100-1.5.75.75 0 000 1.5z" clip-rule="evenodd" />

                            </svg>

                            <span><?= htmlspecialchars($error) ?></span>

                        </div>

                    <?php endif; ?>

                    <form action="reset_password.php" method="POST" class="login-form">

                        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                        

                        <div class="input-group">

                            <label for="new_password">New password</label>

                            <div class="input-wrapper">

                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="input-icon">

                                    <path fill-rule="evenodd" d="M12 1.5a5.25 5.25 0 00-5.25 5.25v3a3 3 0 00-3 3v6.75a3 3 0 003 3h10.5a3 3 0 003-3v-6.75a3 3 0 00-3-3v-3c0-2.9-2.35-5.25-5.25-5.25zm3.75 8.25v-3a3.75 3.75 0 10-7.5 0v3h7.5z" clip-rule="evenodd" />

                                </svg>

                                <input type="password" id="new_password" name="new_password" required placeholder="Min. 6 characters">

                            </div>

                        </div>



                        <div class="input-group">

                            <label for="confirm_password">Confirm password</label>

                            <div class="input-wrapper">

                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="input-icon">

                                    <path fill-rule="evenodd" d="M12 1.5a5.25 5.25 0 00-5.25 5.25v3a3 3 0 00-3 3v6.75a3 3 0 003 3h10.5a3 3 0 003-3v-6.75a3 3 0 00-3-3v-3c0-2.9-2.35-5.25-5.25-5.25zm3.75 8.25v-3a3.75 3.75 0 10-7.5 0v3h7.5z" clip-rule="evenodd" />

                                </svg>

                                <input type="password" id="confirm_password" name="confirm_password" required placeholder="Repeat password">

                            </div>

                        </div>



                        <button type="submit" class="login-btn" style="background-color: #d225d7; border-color: #d225d7;">Reset Password</button>

                    </form>

                <?php endif; ?>

            </div>

        </div>

    </div>

</body>

</html>

