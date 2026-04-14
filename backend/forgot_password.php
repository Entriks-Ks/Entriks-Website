<?php
require_once 'session_config.php';
require_once 'database.php';
require_once 'config.php';

// Redirect if already logged in
if (isset($_SESSION['admin'])) {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (!$email) {
        $error = 'Bitte geben Sie Ihre E-Mail-Adresse ein.';
    } elseif ($db) {
        $user = $db->admins->findOne(['email' => $email]);
        
        if ($user) {
            // Check for existing active reset
            $existingReset = $db->password_resets->findOne([
                'email' => $email,
                'expires' => ['$gte' => time()]
            ]);
            
            if (!$existingReset) {
                // Generate token
                $token = bin2hex(random_bytes(32));
                $expires = time() + 3600;
                
                // Save token
                $db->password_resets->deleteMany(['email' => $email]);
                $db->password_resets->insertOne([
                    'email' => $email,
                    'token' => $token,
                    'expires' => $expires,
                    'created_at' => new MongoDB\BSON\UTCDateTime()
                ]);
                
                // Send email via Resend
                require_once 'resend_helper.php';
                $resetUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/backend/reset_password.php?token=' . $token;
                
                $emailHtml = '<h2>Passwort zurücksetzen</h2>
                    <p>Sie haben eine Anfrage zum Zurücksetzen Ihres Passworts gestellt.</p>
                    <p><a href="' . $resetUrl . '" style="padding:12px 24px;background:#FF69B4;color:#fff;text-decoration:none;border-radius:8px;">Passwort zurücksetzen</a></p>
                    <p>Oder kopieren Sie diesen Link: ' . $resetUrl . '</p>
                    <p>Dieser Link ist 1 Stunde gültig.</p>';
                
                sendResendEmail($email, 'Passwort zurücksetzen - ENTRIKS', $emailHtml);
            }
        }
        
        // Always show success (security)
        $message = 'Wenn ein Konto mit dieser E-Mail existiert, wurde ein Link zum Zurücksetzen des Passworts gesendet.';
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Passwort zurücksetzen - ENTRIKS</title>
    <link rel="shortcut icon" href="<?= htmlspecialchars($siteFaviconUrl ?? 'assets/img/favicon.png') ?>" type="image/x-icon">
    <link rel="stylesheet" href="assets/css/login.css">
    <style>
        :root {
            --gold: #d225d7;
            --bg-dark: #0a0a0f;
            --surface: #15151f;
            --text: #f0f0f5;
            --text-secondary: #a0a0b0;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-dark);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        /* Blur gradient background elements like login page */
        body::before {
            content: '';
            position: absolute;
            top: -50px;
            left: -100px;
            width: 400px;
            height: 400px;
            background: linear-gradient(90deg, #20c1f5, #49b9f2, #7675ec, #a04ee1, #d225d7, #f009d5);
            opacity: 0.25;
            filter: blur(80px);
            border-radius: 50%;
            z-index: 0;
        }
        body::after {
            content: '';
            position: absolute;
            bottom: -100px;
            right: -100px;
            width: 450px;
            height: 450px;
            background: linear-gradient(90deg, #20c1f5, #49b9f2, #7675ec, #a04ee1, #d225d7, #f009d5);
            opacity: 0.3;
            filter: blur(80px);
            border-radius: 50%;
            z-index: 0;
        }
        .container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 420px;
            padding: 40px;
            background: var(--surface);
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .logo {
            text-align: center;
            margin-bottom: 32px;
        }
        .logo img {
            height: 40px;
        }
        h1 {
            font-size: 24px;
            margin-bottom: 8px;
            text-align: center;
        }
        p.subtitle {
            color: var(--text-secondary);
            text-align: center;
            margin-bottom: 32px;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 24px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            color: var(--text-secondary);
        }
        input[type="email"] {
            width: 100%;
            padding: 14px 16px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            color: var(--text);
            font-size: 15px;
            outline: none;
            transition: border-color 0.2s;
        }
        input[type="email"]:focus {
            outline: none;
            border-color: rgba(255,255,255,0.2);
        }
        button[type="submit"] {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #7675ec 0%, #a04ee1 50%, #d225d7 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 16px rgba(118, 117, 236, 0.3);
        }
        button[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(118, 117, 236, 0.4);
        }
        button[type="submit"]:active {
            transform: translateY(0);
        }
        .message {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 14px;
        }
        .message.success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid #22c55e;
            color: #22c55e;
        }
        .message.error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid #ef4444;
            color: #ef4444;
        }
        .back-link {
            text-align: center;
            margin-top: 24px;
        }
        .back-link a {
            color: #d225d7;
            text-decoration: none;
            font-size: 14px;
        }
        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <img src="assets/img/logo.png" alt="ENTRIKS">
        </div>
        
        <h1>Passwort vergessen?</h1>
        
        <?php if ($message): ?>
            <div class="message success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="email">E-Mail-Adresse</label>
                <input type="email" id="email" name="email" placeholder="ihre@email.de" required autofocus>
            </div>
            
            <button type="submit">Link senden</button>
        </form>
        
        <div class="back-link">
            <a href="login.php">← Zurück zum Login</a>
        </div>
    </div>
</body>
</html>
