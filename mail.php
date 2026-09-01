<?php

declare(strict_types=1);

require '/opt/mailer-vendor/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json; charset=UTF-8');

function respond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['success' => false, 'error' => 'Método no permitido']);
}

// Honeypot: campo oculto que un humano nunca completa.
if (!empty($_POST['website'] ?? '')) {
    respond(200, ['success' => true]);
}

$name = trim((string) ($_POST['name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$subject = trim((string) ($_POST['subject'] ?? ''));
$message = trim((string) ($_POST['message'] ?? ''));

$errors = [];
if ($name === '' || mb_strlen($name) > 200) {
    $errors[] = 'Nombre inválido';
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email inválido';
}
if ($subject === '' || mb_strlen($subject) > 300) {
    $errors[] = 'Asunto inválido';
}
if ($message === '' || mb_strlen($message) > 5000) {
    $errors[] = 'Mensaje inválido';
}

if ($errors) {
    respond(422, ['success' => false, 'error' => implode('. ', $errors)]);
}

$smtpUser = getenv('SMTP_USER') ?: '';
$smtpPass = getenv('SMTP_PASS') ?: '';
$mailTo = getenv('MAIL_TO') ?: $smtpUser;

if ($smtpUser === '' || $smtpPass === '') {
    error_log('mail.php: faltan SMTP_USER/SMTP_PASS en el entorno');
    respond(500, ['success' => false, 'error' => 'Error de configuración del servidor']);
}

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = $smtpUser;
    $mail->Password = $smtpPass;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->CharSet = 'UTF-8';

    $mail->setFrom($smtpUser, 'Grupo JRT - Formulario web');
    $mail->addAddress($mailTo);
    $mail->addReplyTo($email, $name);

    $mail->isHTML(true);
    $mail->Subject = 'Contacto web: ' . $subject;
    $mail->Body = sprintf(
        '<p><strong>Nombre:</strong> %1$s</p>' .
        '<p><strong>Email:</strong> %2$s</p>' .
        '<p><strong>Asunto:</strong> %3$s</p>' .
        '<p><strong>Mensaje:</strong></p><p>%4$s</p>',
        htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($email, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($subject, ENT_QUOTES, 'UTF-8'),
        nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'))
    );
    $mail->AltBody = "Nombre: $name\nEmail: $email\nAsunto: $subject\n\n$message";

    $mail->send();

    respond(200, ['success' => true]);
} catch (Exception $e) {
    error_log('mail.php: fallo al enviar - ' . $mail->ErrorInfo);
    respond(500, ['success' => false, 'error' => 'No se pudo enviar el mensaje, intentá de nuevo más tarde']);
}
