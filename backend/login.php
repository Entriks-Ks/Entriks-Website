<?php

require_once 'session_config.php';

require 'database.php';



require 'config.php';



// Override language if user has a preferred language cookie

if (isset($_COOKIE['preferred_lang'])) {

    $preferredLang = $_COOKIE['preferred_lang'];

    $langFile = __DIR__ . '/languages/' . $preferredLang . '.php';

    if (file_exists($langFile)) {

        $lang = require $langFile;

    }

}



// Check for Remember Me cookie if session is not set

if (!isset($_SESSION['admin']) && isset($_COOKIE['remember_me'])) {

    if ($db) {

        $token = $_COOKIE['remember_me'];

        $tokenHash = hash('sha256', $token);



        $admin = $db->admins->findOne([

            'remember_token' => $tokenHash,

            'token_expires' => ['$gt' => new MongoDB\BSON\UTCDateTime()]

        ]);



        if ($admin) {

            $_SESSION['admin'] = [

                'id' => (string) $admin['_id'],

                'email' => $admin['email'],

                'role' => $admin['role'] ?? 'admin',

                'name' => $admin['display_name'] ?? 'Admin',

                'permissions' => $admin['permissions'] ?? [],

                'preferred_language' => $admin['preferred_language'] ?? null

            ];



            // Refresh token expiry (optional, but good practice - extending it another 30 days)

            $newExpiry = new MongoDB\BSON\UTCDateTime((time() + (30 * 24 * 60 * 60)) * 1000);

            $db->admins->updateOne(

                ['_id' => $admin['_id']],

                ['$set' => ['token_expires' => $newExpiry]]

            );

            // Reset cookie

            setcookie('remember_me', $token, time() + (30 * 24 * 60 * 60), '/', '', isset($_SERVER['HTTPS']), true);



            header('Location: dashboard.php');

            exit;

        }

    }

}



// Redirect to dashboard if already logged in

if (isset($_SESSION['admin'])) {

    header('Location: dashboard.php');

    exit;

}



$error = '';



if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $email = $_POST['email'] ?? '';

    $pass = $_POST['password'] ?? '';



    if (!$db) {

        $error = $lang['login_error_database'];

    } else {

        $admin = $db->admins->findOne(['email' => $email]);



        if ($admin && password_verify($pass, $admin['password'])) {

            // Standard session login

            $_SESSION['admin'] = [

                'id' => (string) $admin['_id'],

                'email' => $email,

                'role' => $admin['role'] ?? 'admin',  // Default to admin for existing users

                'name' => $admin['display_name'] ?? 'Admin',

                'permissions' => $admin['permissions'] ?? [],

                'preferred_language' => $admin['preferred_language'] ?? null

            ];



            header('Location: dashboard.php');

            exit;

        } else {

            $error = $lang['login_error_incorrect'];

        }

    }

}

?>

<!DOCTYPE html>

<html>

    <head>

        <meta charset="utf-8">

        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title><?= $lang['login_title'] ?></title>

        <link rel="shortcut icon" href="<?= htmlspecialchars($siteFaviconUrl) ?>" type="image/x-icon">

        <link rel="stylesheet" href="assets/css/login.css">

        <link rel="stylesheet" href="assets/css/login_extra.css">

    </head>

    <body>

    <!-- Standard Preloader -->

    <div id="preloader" class="preloader">

        <div class="preloader-spinner"></div>

    </div>



    <div class="login-wrapper">

        <!-- Blur Background Elements -->

        <div class="blur-bg-theme top-left"></div>

        <div class="blur-bg-theme bottom-right"></div> 

        <div class="login-container">





            <!-- Right side - Login form -->

            <div class="login-right">

                <div class="login-header">

                    <img src="assets/img/logo.png" alt="Logo" class="login-logo">

                </div>



                <?php if (isset($_GET['setup']) && $_GET['setup'] === 'success'): ?>

                    <div class="success-message" style="margin-bottom: 20px; padding: 15px; background: rgba(34, 197, 94, 0.1); border: 1px solid #22c55e; border-radius: 12px; color: #22c55e; display: flex; align-items: center; gap: 10px;">

                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;">

                            <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 00 1.14-.094l3.75-5.25z" clip-rule="evenodd" />

                        </svg>

                        <span>Password set successfully! Please log in.</span>

                    </div>

                <?php endif; ?>



                <?php if ($error): ?>

                    <div class="error-message">

                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">

                            <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zM12 8.25a.75.75 0 01.75.75v3.75a.75.75 0 01-1.5 0V9a.75.75 0 01.75-.75zm0 8.25a.75.75 0 100-1.5.75.75 0 000 1.5z" clip-rule="evenodd" />

                        </svg>

                        <span><?= htmlspecialchars($error) ?></span>

                    </div>

                <?php endif; ?>



                <form method="POST" class="login-form">

                    <div class="input-group">

                        <label for="email"><?= $lang['login_email_label'] ?></label>

                        <div class="input-wrapper">

                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="input-icon">

                                <path d="M1.5 8.67v8.58a3 3 0 003 3h15a3 3 0 003-3V8.67l-8.928 5.493a3 3 0 01-3.144 0L1.5 8.67z" />

                                <path d="M22.5 6.908V6.75a3 3 0 00-3-3h-15a3 3 0 00-3 3v.158l9.714 5.978a1.5 1.5 0 001.572 0L22.5 6.908z" />

                            </svg>

                            <input type="email" id="email" name="email" placeholder="<?= $lang['login_email_placeholder'] ?>" required>

                        </div>

                    </div>



                    <div class="input-group">

                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">

                            <label for="password" style="margin-bottom:0;"><?= $lang['login_password_label'] ?></label>

                        </div>

                        <div class="input-wrapper">

                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="input-icon">

                                <path fill-rule="evenodd" d="M12 1.5a5.25 5.25 0 00-5.25 5.25v3a3 3 0 00-3 3v6.75a3 3 0 003 3h10.5a3 3 0 003-3v-6.75a3 3 0 00-3-3v-3c0-2.9-2.35-5.25-5.25-5.25zm3.75 8.25v-3a3.75 3.75 0 10-7.5 0v3h7.5z" clip-rule="evenodd" />

                            </svg>

                            <input type="password" id="password" name="password" placeholder="<?= $lang['login_password_placeholder'] ?>" required>

                            <button type="button" class="password-toggle" onclick="togglePassword()">

                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="eye-icon" id="eyeShow">

                                    <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" />

                                    <path fill-rule="evenodd" d="M1.323 11.447C2.811 6.976 7.028 3.75 12.001 3.75c4.97 0 9.185 3.223 10.675 7.69.12.362.12.752 0 1.113-1.487 4.471-5.705 7.697-10.677 7.697-4.97 0-9.186-3.223-10.675-7.69a1.762 1.762 0 0 1 0-1.113ZM17.25 12a5.25 5.25 0 1 1-10.5 0 5.25 5.25 0 0 1 10.5 0Z" clip-rule="evenodd" />

                                </svg>

                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="eye-icon hidden" id="eyeHide">

                                    <path d="M3.53 2.47a.75.75 0 0 0-1.06 1.06l18 18a.75.75 0 1 0 1.06-1.06l-18-18ZM22.676 12.553a11.249 11.249 0 0 1-2.631 4.31l-3.099-3.099a5.25 5.25 0 0 0-6.71-6.71L7.759 4.577a11.217 11.217 0 0 1 4.242-.827c4.97 0 9.185 3.223 10.675 7.69.12.362.12.752 0 1.113Z" />

                                    <path d="M15.75 12c0 .18-.013.357-.037.53l-4.244-4.243A3.75 3.75 0 0 1 15.75 12ZM12.53 15.713l-4.243-4.244a3.75 3.75 0 0 0 4.244 4.243Z" />

                                    <path d="M6.75 12c0-.619.107-1.213.304-1.764l-3.1-3.1a11.25 11.25 0 0 0-2.63 4.31c-.12.362-.12.752 0 1.114 1.489 4.467 5.704 7.69 10.675 7.69 1.5 0 2.933-.294 4.242-.827l-2.477-2.477A5.25 5.25 0 0 1 6.75 12Z" />

                                </svg>

                            </button>

                        </div>

                    </div>

                    

                    <button type="submit" class="login-btn">

                        <!-- Icon for button if desired, e.g. login icon -->

                        <?= $lang['login_button'] ?>

                    </button>



                    <div style="margin-top: 24px; text-align: center; color: var(--text-secondary); font-size: 0.85rem; opacity: 0.7; display: flex; align-items: center; justify-content: center; gap: 8px;">

                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6" style="width: 16px; height: 16px;">

                            <path fill-rule="evenodd" d="M12 1.5a5.25 5.25 0 0 0-5.25 5.25v3a3 3 0 0 0-3 3v6.75a3 3 0 0 0 3 3h10.5a3 3 0 0 0 3-3v-6.75a3 3 0 0 0-3-3v-3c0-2.9-2.35-5.25-5.25-5.25Zm3.75 8.25v-3a3.75 3.75 0 1 0-7.5 0v3h7.5Z" clip-rule="evenodd" />

                        </svg>

                        <span><?= str_replace('🔒 ', '', isset($lang['login_security_message']) ? $lang['login_security_message'] : 'Administration Area. Access logged.') ?></span>

                    </div>

                </form>





            </div>

        </div>

    </div>



    <script>

    function togglePassword() {

        const passwordInput = document.getElementById('password');

        const eyeShow = document.getElementById('eyeShow');

        const eyeHide = document.getElementById('eyeHide');

        

        if (passwordInput.type === 'password') {

            passwordInput.type = 'text';

            eyeShow.classList.add('hidden');

            eyeHide.classList.remove('hidden');

        } else {

            passwordInput.type = 'password';

            eyeShow.classList.remove('hidden');

            eyeHide.classList.add('hidden');

        }

    }



    // Preloader Logic

    window.addEventListener('load', function() {

        const preloader = document.getElementById('preloader');

        setTimeout(() => {

            preloader.classList.add('fade-out');

        }, 100);

    });



    // Show preloader on form submit

    document.querySelector('.login-form').addEventListener('submit', function() {

        document.getElementById('preloader').classList.remove('fade-out');

    });

    </script>



    </body>

</html>