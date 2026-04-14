<?php

require_once 'database.php';

require_once 'config.php';



$token = $_GET['token'] ?? '';

$preview = isset($_GET['preview']) && $_GET['preview'] === 'true';

$error = '';

$email = '';



if ($preview) {

    $email = 'preview@example.com';

} elseif (empty($token)) {

    $error = 'Invalid invitation link.';

} else {

    $admin = $db->admins->findOne([

        'invitation_token' => $token,

        'status' => 'pending'

    ]);



    if (!$admin) {

        $error = 'This invitation has already been used or is invalid.';

    } else {

        // Check expiry

        $expiry = $admin['invitation_expires'];

        if ($expiry instanceof MongoDB\BSON\UTCDateTime) {

            $expiryTs = $expiry->toDateTime()->getTimestamp();

            if (time() > $expiryTs) {

                $error = 'This invitation has expired.';

            }

        }

        $email = $admin['email'];

    }

}

?>

<!DOCTYPE html>

<html>

<head>

    <meta charset="utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Set Password - <?= htmlspecialchars($siteConfig['site_name'] ?? 'Entriks') ?></title>

    <link rel="icon" type="image/png" href="<?= htmlspecialchars($siteConfig['favicon_url'] ?? 'assets/img/favicon.png') ?>">

    <link rel="stylesheet" href="assets/css/login.css">

    <link rel="stylesheet" href="assets/css/login_extra.css">

</head>

<body>

    <div class="login-wrapper">
        <div class="blur-bg-theme bottom-right"></div>



        <div class="login-container">

            <div class="login-right">

                <div class="login-header">

                    <img src="<?= htmlspecialchars($siteConfig['logo_url'] ?? 'assets/img/logo.png') ?>" alt="Logo" class="login-logo">

                </div>



                <?php if ($error): ?>

                    <div class="error-message">

                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;">

                            <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zM12 8.25a.75.75 0 01.75.75v3.75a.75.75 0 01-1.5 0V9a.75.75 0 01.75-.75zm0 8.25a.75.75 0 100-1.5.75.75 0 000 1.5z" clip-rule="evenodd" />

                        </svg>

                        <span><?= htmlspecialchars($error) ?></span>

                    </div>

                <?php else: ?>

                    <form action="onboarding.php" method="POST" class="login-form">

                        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                        

                        <div class="input-group">

                            <label for="password">Set your password</label>

                            <div class="input-wrapper">

                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="input-icon">

                                    <path fill-rule="evenodd" d="M12 1.5a5.25 5.25 0 00-5.25 5.25v3a3 3 0 00-3 3v6.75a3 3 0 003 3h10.5a3 3 0 003-3v-6.75a3 3 0 00-3-3v-3c0-2.9-2.35-5.25-5.25-5.25zm3.75 8.25v-3a3.75 3.75 0 10-7.5 0v3h7.5z" clip-rule="evenodd" />

                                </svg>

                                <input type="password" id="password" name="password" required placeholder="Min. 8 characters">

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



                        <button type="submit" class="login-btn" style="background-color: #d225d7; border-color: #d225d7;">Set Password</button>

                    </form>

                <?php endif; ?>

            </div>

        </div>

    </div>

</body>

</html>

