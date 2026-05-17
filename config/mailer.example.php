<?php
// ─────────────────────────────────────────────
//  CampusSync — Mailer Configuration
//  Uses PHPMailer + Gmail SMTP
// ─────────────────────────────────────────────
//  SETUP:
//  1. Enable 2-Step Verification on Gmail
//     → https://myaccount.google.com/security
//  2. Generate App Password → paste below
// ─────────────────────────────────────────────

define('MAIL_HOST',      'smtp.gmail.com');
define('MAIL_PORT',      587);
define('MAIL_USERNAME',  '');  // ← YOUR Gmail
define('MAIL_PASSWORD',  '');   // ← 16-char App Password
define('MAIL_FROM',      '');  // ← Same Gmail
define('MAIL_FROM_NAME', 'CampusSync');

// Load PHPMailer at file level so 'use' statements work correctly
$_autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($_autoload)) {
    require_once $_autoload;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * sendOTPEmail()
 * Sends a 6-digit OTP to the given email address.
 */
function sendOTPEmail(string $toEmail, string $toName, string $otp): array {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        return [
            'success' => false,
            'error'   => 'PHPMailer not installed. Run: composer require phpmailer/phpmailer inside the campussync/ folder.'
        ];
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = 'Your CampusSync Verification Code';
        $mail->Body    = getOTPEmailTemplate($toName, $otp);
        $mail->AltBody = "Hi $toName,\n\nYour CampusSync verification code is: $otp\n\nThis code expires in 10 minutes.\n\nDo not share this code with anyone.";

        $mail->send();
        return ['success' => true, 'error' => ''];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}

/**
 * generateOTP()
 * Returns a cryptographically secure 6-digit OTP.
 */
function generateOTP(): string {
    return str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * getOTPEmailTemplate()
 * Returns the HTML email body for OTP verification.
 */
function getOTPEmailTemplate(string $name, string $otp): string {
    return <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <style>
    body { margin:0; padding:0; background:#0f0f11; font-family:'Segoe UI',Arial,sans-serif; }
    .wrap { max-width:520px; margin:40px auto; background:#17171c; border:1px solid #2a2a35; border-radius:16px; overflow:hidden; }
    .header { background:#17171c; border-bottom:1px solid #2a2a35; padding:28px 36px; }
    .brand { font-size:20px; font-weight:800; color:#e8e8f0; letter-spacing:-0.5px; }
    .brand span { color:#f5a623; }
    .body { padding:36px; }
    .greeting { font-size:15px; color:#9090a8; margin-bottom:20px; }
    .greeting strong { color:#e8e8f0; }
    .otp-box { background:#1e1e25; border:1px solid #2a2a35; border-left:4px solid #f5a623; border-radius:10px; padding:28px; text-align:center; margin:24px 0; }
    .otp-label { font-size:11px; color:#6b6b80; text-transform:uppercase; letter-spacing:2px; margin-bottom:12px; }
    .otp-code { font-size:42px; font-weight:800; color:#f5a623; letter-spacing:10px; font-family:'Courier New',monospace; }
    .expiry { font-size:13px; color:#6b6b80; margin-top:12px; }
    .warning { font-size:12px; color:#6b6b80; background:#1e1e25; border-radius:8px; padding:14px 18px; margin-top:20px; }
    .warning strong { color:#e05252; }
    .footer { border-top:1px solid #2a2a35; padding:20px 36px; text-align:center; font-size:12px; color:#6b6b80; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="header">
      <div class="brand"><span>⬡</span> CampusSync</div>
    </div>
    <div class="body">
      <p class="greeting">Hi <strong>{$name}</strong>,</p>
      <p class="greeting">Use the verification code below to confirm your email address and activate your CampusSync account.</p>
      <div class="otp-box">
        <div class="otp-label">Your Verification Code</div>
        <div class="otp-code">{$otp}</div>
        <div class="expiry">⏱ Expires in <strong style="color:#e8e8f0">10 minutes</strong></div>
      </div>
      <div class="warning">
        <strong>Do not share this code</strong> with anyone. CampusSync will never ask for your OTP via chat or phone.
      </div>
    </div>
    <div class="footer">
      If you didn't create a CampusSync account, you can safely ignore this email.
    </div>
  </div>
</body>
</html>
HTML;
}
