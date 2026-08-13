<?php
/**
 * Prestige ABA — contact form handler.
 * Emails every "Schedule a Consultation" submission straight to the owner.
 * No third party involved; runs on the site's own Hostinger PHP host.
 */

// ---- Destination: submissions go strictly to the owner's inbox ----
$TO   = 'kadi@prestigeaba.org';
$FROM = 'no-reply@prestigeaba.org'; // same domain, for SPF alignment

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

// Honeypot: real users never fill this hidden field. Pretend success for bots.
if (!empty($_POST['company'])) {
    echo json_encode(['ok' => true]);
    exit;
}

// Strip CR/LF to block header injection via any single-line field.
function oneLine($v) {
    return trim(preg_replace('/[\r\n]+/', ' ', (string)$v));
}

$name      = oneLine($_POST['name']      ?? '');
$phone     = oneLine($_POST['phone']     ?? '');
$email     = oneLine($_POST['email']     ?? '');
$childAge  = oneLine($_POST['child_age'] ?? '');
$insurance = oneLine($_POST['insurance'] ?? '');
$message   = trim((string)($_POST['message'] ?? ''));

// Validation
$bad = [];
if ($name === '')                                              $bad[] = 'name';
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $bad[] = 'email';
if ($bad) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'validation', 'fields' => $bad]);
    exit;
}

$subject = 'New consultation request — ' . ($name !== '' ? $name : 'website visitor');

$body = "New Schedule-a-Consultation request from prestigeaba.org\n"
      . str_repeat('-', 52) . "\n"
      . "Name:       " . ($name      !== '' ? $name      : '(not given)') . "\n"
      . "Phone:      " . ($phone     !== '' ? $phone     : '(not given)') . "\n"
      . "Email:      " . $email . "\n"
      . "Child age:  " . ($childAge  !== '' ? $childAge  : '(not given)') . "\n"
      . "Insurance:  " . ($insurance !== '' ? $insurance : '(not given)') . "\n\n"
      . "Message:\n"
      . ($message !== '' ? $message : '(none)') . "\n\n"
      . str_repeat('-', 52) . "\n"
      . "Sent " . date('Y-m-d H:i:s T') . " from the website contact form.\n"
      . "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n";

$headers  = 'From: Prestige ABA Website <' . $FROM . ">\r\n";
$headers .= 'Reply-To: ' . $email . "\r\n"; // replying goes to the family
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= 'X-Mailer: PHP/' . phpversion();

$sent = @mail($TO, $subject, $body, $headers, '-f ' . $FROM);

if ($sent) {
    echo json_encode(['ok' => true]);
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'send_failed']);
}
