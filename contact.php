<?php
// Receives the contact form and emails it to the agency.
// Set RECIPIENT to the address that should receive enquiries.
const RECIPIENT = 'info@example.com';

header('Content-Type: application/json; charset=utf-8');

function fail(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Method not allowed.', 405);
if (!empty($_POST['website'])) { echo json_encode(['ok' => true]); exit; } // spam trap

$clean = static fn(string $k, int $max): string =>
    mb_substr(trim(preg_replace('/[\r\n]+/', ' ', (string)($_POST[$k] ?? ''))), 0, $max);

$who   = $clean('who', 20);
$name  = $clean('name', 120);
$email = $clean('email', 160);
$phone = $clean('phone', 40);
$msg   = mb_substr(trim((string)($_POST['message'] ?? '')), 0, 4000);

if ($name === '' || $msg === '') fail('Enter your name and a message.');
if ($email === '' && $phone === '') fail('Enter an email or phone number so we can reply.');
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Enter a valid email address.');

// Simple rate limit: one message per 30 seconds per visitor
session_start();
if (isset($_SESSION['last_contact']) && time() - $_SESSION['last_contact'] < 30) fail('Please wait a moment before sending another message.', 429);
$_SESSION['last_contact'] = time();

$body = "Type: $who\nName: $name\nEmail: $email\nPhone: $phone\n\n$msg\n";
$headers = ['Content-Type: text/plain; charset=utf-8'];
if ($email !== '') $headers[] = 'Reply-To: ' . $email;

if (!mail(RECIPIENT, '=?UTF-8?B?' . base64_encode("Website enquiry from $name") . '?=', $body, implode("\r\n", $headers))) {
    fail('The message could not be sent. Please call or email us.', 500);
}
echo json_encode(['ok' => true]);
