<?php
// Use PHPMailer directly (standalone)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if PHPMailer is available in different locations
$phpmailerPaths = [
    '../backend/vendor/autoload.php',
    '../vendor/autoload.php',
    'vendor/autoload.php'
];

$phpmailerLoaded = false;
foreach ($phpmailerPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $phpmailerLoaded = true;
        break;
    }
}

// Fallback to basic mail if PHPMailer not available
function sendBasicMail($to, $subject, $body, $replyToEmail = '') {
    $headers = "From: noreply@entriks.com\r\n";
    if ($replyToEmail) {
        $headers .= "Reply-To: $replyToEmail\r\n";
    }
    $headers .= "Return-Path: noreply@entriks.com\r\n";  // Return path for bounces
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $headers .= "X-Priority: 1 (Highest)\r\n";
    return mail($to, $subject, $body, $headers);
}

// Capture all form fields
$company = isset($_POST['company']) ? strip_tags(trim($_POST['company'])) : "";
$name = isset($_POST['name']) ? strip_tags(trim($_POST['name'])) : "";
$email = isset($_POST['email']) ? filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL) : "";
$phone = isset($_POST['phone']) ? strip_tags(trim($_POST['phone'])) : "";
$service = isset($_POST['service']) ? strip_tags(trim($_POST['service'])) : "";
$message = isset($_POST['message']) ? strip_tags(trim($_POST['message'])) : "";
$privacy = isset($_POST['privacy']) ? strip_tags(trim($_POST['privacy'])) : "";

// Validate required fields
if ($company && $name && $email && $service && $privacy) {
    // Build HTML email body with professional template
    $emailBody = "
<!DOCTYPE html>
<html lang='de'>
<head>
<meta charset='UTF-8'>
<meta name='viewport' content='width=device-width, initial-scale=1.0'>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@700;800;900&family=Inter:wght@400;500;600;700&display=swap');
</style>
</head>
<body style='margin:0;padding:40px 0;background-color:#ffffff;font-family:\"Inter\",Arial,sans-serif;color:#1a1a1a;'>

  <table width='100%' cellpadding='0' cellspacing='0'>
    <tr><td align='center'>
      <table width='580' cellpadding='0' cellspacing='0' style='max-width:580px;width:100%;'>

        <!-- LOGO + TALENT HUB -->
        <tr>
          <td align='center' style='padding-bottom:6px;'>
            <img src='https://entriks.com/assets/img/logo.png' alt='ENTRIKS' width='130' style='display:block;height:auto;margin:0 auto;' />
          </td>
        </tr>
        <tr>
          <td align='center' style='padding-bottom:36px;'>
            <span style='font-family:\"Orbitron\",\"Inter\",sans-serif;font-size:10px;font-weight:700;color:#888888;letter-spacing:4px;text-transform:uppercase;'>TALENT HUB</span>
          </td>
        </tr>

        <!-- EINLEITUNG -->
        <tr>
          <td style='padding-bottom:20px;'>
            <p style='margin:0;font-size:15px;color:#1a1a1a;line-height:1.7;font-family:\"Inter\",Arial,sans-serif;'>Eine neue Anfrage wurde über das <strong>ENTRIKS Talent Hub</strong> Kontaktformular eingereicht.</p>
          </td>
        </tr>

        <!-- DETAILS TABELLE -->
        <tr>
          <td style='padding-bottom:28px;'>
            <table width='100%' cellpadding='0' cellspacing='0'>

              <!-- Unternehmen -->
              <tr>
                <td style='padding:11px 0;border-bottom:1px solid #eeeeee;'>
                  <table width='100%' cellpadding='0' cellspacing='0'><tr>
                    <td width='36%' style='font-size:11px;color:#999999;font-weight:600;text-transform:uppercase;letter-spacing:1px;font-family:\"Inter\",Arial,sans-serif;'>Unternehmen</td>
                    <td width='64%' style='font-size:14px;color:#1a1a1a;font-weight:600;font-family:\"Inter\",Arial,sans-serif;'>$company</td>
                  </tr></table>
                </td>
              </tr>

              <!-- Name -->
              <tr>
                <td style='padding:11px 0;border-bottom:1px solid #eeeeee;'>
                  <table width='100%' cellpadding='0' cellspacing='0'><tr>
                    <td width='36%' style='font-size:11px;color:#999999;font-weight:600;text-transform:uppercase;letter-spacing:1px;font-family:\"Inter\",Arial,sans-serif;'>Name</td>
                    <td width='64%' style='font-size:14px;color:#1a1a1a;font-weight:600;font-family:\"Inter\",Arial,sans-serif;'>$name</td>
                  </tr></table>
                </td>
              </tr>

              <!-- E-Mail -->
              <tr>
                <td style='padding:11px 0;border-bottom:1px solid #eeeeee;'>
                  <table width='100%' cellpadding='0' cellspacing='0'><tr>
                    <td width='36%' style='font-size:11px;color:#999999;font-weight:600;text-transform:uppercase;letter-spacing:1px;font-family:\"Inter\",Arial,sans-serif;'>E-Mail</td>
                    <td width='64%'><a href='mailto:$email' style='font-size:14px;color:#c9a227;font-weight:600;text-decoration:none;font-family:\"Inter\",Arial,sans-serif;'>$email</a></td>
                  </tr></table>
                </td>
              </tr>

              <!-- Telefon -->
              <tr>
                <td style='padding:11px 0;border-bottom:1px solid #eeeeee;'>
                  <table width='100%' cellpadding='0' cellspacing='0'><tr>
                    <td width='36%' style='font-size:11px;color:#999999;font-weight:600;text-transform:uppercase;letter-spacing:1px;font-family:\"Inter\",Arial,sans-serif;'>Telefon</td>
                    <td width='64%' style='font-size:14px;color:#1a1a1a;font-family:\"Inter\",Arial,sans-serif;'>$phone</td>
                  </tr></table>
                </td>
              </tr>

              <!-- Interesse an -->
              <tr>
                <td style='padding:11px 0;border-bottom:1px solid #eeeeee;'>
                  <table width='100%' cellpadding='0' cellspacing='0'><tr>
                    <td width='36%' style='font-size:11px;color:#999999;font-weight:600;text-transform:uppercase;letter-spacing:1px;font-family:\"Inter\",Arial,sans-serif;'>Interesse an</td>
                    <td width='64%' style='font-size:14px;color:#1a1a1a;font-family:\"Inter\",Arial,sans-serif;'>$service</td>
                  </tr></table>
                </td>
              </tr>

              <!-- Nachricht -->
              <tr>
                <td style='padding:11px 0;'>
                  <table width='100%' cellpadding='0' cellspacing='0'><tr>
                    <td width='36%' style='font-size:11px;color:#999999;font-weight:600;text-transform:uppercase;letter-spacing:1px;font-family:\"Inter\",Arial,sans-serif;vertical-align:top;padding-top:2px;'>Nachricht</td>
                    <td width='64%' style='font-size:14px;color:#1a1a1a;line-height:1.65;font-family:\"Inter\",Arial,sans-serif;'>$message</td>
                  </tr></table>
                </td>
              </tr>

            </table>
          </td>
        </tr>

        <!-- SCHALTFLÄCHE (zentriert) -->
        <tr>
          <td align='center' style='padding-bottom:32px;'>
            <a href='mailto:$email' style='display:inline-block;background-color:#c9a227;color:#ffffff;font-size:14px;font-weight:700;padding:13px 32px;border-radius:8px;text-decoration:none;font-family:\"Inter\",Arial,sans-serif;'>Kunde antworten</a>
          </td>
        </tr>

        <!-- ABSCHLUSS -->
        <tr>
          <td style='padding-bottom:32px;'>
            <p style='margin:0;font-size:14px;color:#1a1a1a;line-height:1.7;font-family:\"Inter\",Arial,sans-serif;'>
              Mit freundlichen Grüßen,<br>
              <strong>Das ENTRIKS System</strong>
            </p>
          </td>
        </tr>

        <!-- FUSSZEILE -->
        <tr>
          <td style='border-top:1px solid #eeeeee;padding-top:24px;text-align:center;'>
            <p style='margin:0 0 4px 0;font-size:11px;color:#bbbbbb;line-height:1.6;font-family:\"Inter\",Arial,sans-serif;'>Diese Benachrichtigung wurde automatisch über das ENTRIKS Talent Hub Kontaktformular generiert.</p>
            <p style='margin:0 0 10px 0;font-size:11px;color:#bbbbbb;font-family:\"Inter\",Arial,sans-serif;'>© 2026 ENTRIKS. Alle Rechte vorbehalten.</p>
            <p style='margin:0 0 2px 0;font-size:12px;color:#bbbbbb;font-family:\"Orbitron\",\"Inter\",Arial,sans-serif;font-weight:700;letter-spacing:2px;'>ENTRIKS</p>
            <p style='margin:0;font-size:11px;color:#bbbbbb;font-family:\"Inter\",Arial,sans-serif;'>Lot Vaku L2.1, 10000, Pristina</p>
          </td>
        </tr>

      </table>
    </td></tr>
  </table>

</body>
</html>";
    
    // Try PHPMailer first, fallback to basic mail
    $success = false;
    
    if ($phpmailerLoaded) {
        try {
            // Try to get SMTP config from backend if available
            $smtpConfig = [];
            if (file_exists('../backend/config.php')) {
                include '../backend/config.php';
                global $smtpHost, $smtpPort, $smtpUsername, $smtpPassword, $smtpEncryption, $smtpFromEmail, $smtpFromName;
            }
            
            $mail = new PHPMailer(true);
            if (!empty($smtpHost)) {
                // Use SMTP if configured
                $mail->isSMTP();
                $mail->Host = $smtpHost;
                $mail->SMTPAuth = true;
                $mail->Username = $smtpUsername;
                $mail->Password = $smtpPassword;
                $mail->SMTPSecure = $smtpEncryption;
                $mail->Port = $smtpPort;
            }
            $mail->setFrom($smtpFromEmail ?? 'noreply@entriks.com', $smtpFromName ?? 'ENTRIKS');
            $mail->addAddress('info@entriks.com');
            $mail->addReplyTo($email, $name);  // Add reply-to with user's email
            $mail->isHTML(true);
            $mail->Subject = 'TalentHub Neue Anfrage';
            $mail->Body = $emailBody;
            $mail->send();
            $success = true;
        } catch (Exception $e) {
            error_log('PHPMailer Error: ' . $e->getMessage());
            // Fallback to basic mail
            $success = sendBasicMail('info@entriks.com', 'TalentHub Neue Anfrage', $emailBody, $email);
        }
    } else {
        // Use basic mail if PHPMailer not available
        $success = sendBasicMail('info@entriks.com', 'TalentHub Neue Anfrage', $emailBody, $email);
    }
    
    if ($success) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Vielen Dank! Ihre Anfrage wurde erfolgreich gesendet.']);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Fehler: Ihre Anfrage konnte nicht gesendet werden.']);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Bitte füllen Sie alle Pflichtfelder aus.']);
}
?>
