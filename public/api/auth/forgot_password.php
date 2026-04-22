<?php
 
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// This is the most important line! 
// It tells PHP where the Composer libraries are.
require_once __DIR__ . '/../../vendor/autoload.php';

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

use TrustLink\Config\Database;
use TrustLink\Utils\Response;

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');

if (!$email) {
    Response::badRequest('Email is required');
}

try {
    $db = Database::getInstance();

    // Check if user exists with this email
    $stmt = $db->prepare("SELECT id, name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        // For security, don't reveal that email doesn't exist
        Response::success(null, 'If the email exists, a password reset link has been sent');
        exit;
    }

    // Delete any existing tokens for this user
    $stmt = $db->prepare("DELETE FROM password_resets WHERE user_id = ?");
    $stmt->execute([$user['id']]);

    // Generate a secure token (64 random hex chars)
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Store token
    $stmt = $db->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$user['id'], $token, $expires]);

    // Build reset link
    $baseUrl = getenv('APP_URL') ?: 'http://localhost/trustfiles';
    $resetLink = rtrim($baseUrl, '/') . '/public/reset-password.php?token=' . $token;




    $mail = new PHPMailer(true);

try {
    // ========================
    // SMTP CONFIGURATION
    // ========================
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'nambafuchrispinus@gmail.com'; // 🔥 CHANGE THIS
    $mail->Password   = 'pxud rqfk chyp fgdb';    // 🔥 CHANGE THIS
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // ========================
    // EMAIL DETAILS
    // ========================
    $mail->setFrom('no-reply@trustlink.ke', 'TrustLink');
    $mail->addAddress($email, $user['name']);

    // ========================
    // CONTENT
    // ========================
    $mail->isHTML(false);
    $mail->Subject = 'Password Reset Request - TrustLink';

    $mail->Body = "Hello {$user['name']},\n\n"
        . "We received a request to reset your password.\n\n"
        . "Click the link below to set a new password:\n\n"
        . "$resetLink\n\n"
        . "This link expires in 1 hour.\n\n"
        . "If you did not request this, please ignore this email.\n\n"
        . "Regards,\nTrustLink Team";

    $mail->send();

    // ✅ SUCCESS RESPONSE
    Response::success(null, 'If the email exists, a password reset link has been sent');

} catch (Exception $e) {
    error_log("Mailer Error: {$mail->ErrorInfo}");
    Response::serverError('Failed to send reset email');
}
    // Send email (you must implement this with PHPMailer or mail())
    // For now, we'll just log the link and return it (remove in production)
   // error_log("Password reset link for {$user['name']}: " . $resetLink);

    // You can optionally send email using mail() or PHPMailer
    // Example with mail() (requires server mail configuration):
   // $subject = "Password Reset Request - TrustLink";
   // $message = "Hello {$user['name']},\n\n";
   // $message .= "We received a request to reset your password. Click the link below to set a new password:\n\n";
   // $message .= $resetLink . "\n\n";
   // $message .= "This link will expire in 1 hour.\n\n";
   // $message .= "If you did not request this, please ignore this email.\n\n";
   // $message .= "Regards,\nTrustLink Team";
   // $headers = "From: no-reply@trustlink.ke\r\n";
    // mail($email, $subject, $message, $headers); // Uncomment when ready

    Response::success(null, 'If the email exists, a password reset link has been sent');

} catch (\PDOException $e) {
    error_log("Forgot password error: " . $e->getMessage());
    Response::serverError('Failed to process request');
}