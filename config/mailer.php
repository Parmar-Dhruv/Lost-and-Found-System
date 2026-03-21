<?php
// config/mailer.php
// Returns a configured PHPMailer instance ready to send.
// Never call this file directly — always require it from action files.

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function getMailer(): PHPMailer
{
    $mail = new PHPMailer(true); // true = exceptions enabled

    $mail->isSMTP();
    $mail->Host       = $_ENV['MAIL_HOST']     ?? '';
    $mail->SMTPAuth   = true;
    $mail->Username   = $_ENV['MAIL_USERNAME'] ?? '';
    $mail->Password   = $_ENV['MAIL_PASSWORD'] ?? '';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = (int)($_ENV['MAIL_PORT'] ?? 587);

    $mail->setFrom(
        $_ENV['MAIL_FROM']      ?? '',
        $_ENV['MAIL_FROM_NAME'] ?? 'Lost and Found'
    );

    $mail->isHTML(true);

    return $mail;
}

/**
 * Send an email silently — never throws, never crashes the app.
 * Returns true on success, false on failure.
 */
function sendMail(string $toEmail, string $toName, string $subject, string $htmlBody): bool
{
    try {
        $mail = getMailer();
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody); // plain text fallback
        $mail->send();
        return true;
    } catch (Exception $e) {
        // Silent failure — log to PHP error log but never crash the app
        error_log('Mailer error: ' . $e->getMessage());
        return false;
    }
}