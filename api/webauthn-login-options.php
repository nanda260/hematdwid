<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/webauthn.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

function webauthnLoginFail(string $message): void
{
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

// Usernameless: tidak perlu email, browser akan menampilkan akun via sensor
$challenge = webauthnGenerateChallenge('login');

echo json_encode([
    'challenge'        => base64url_encode($challenge),
    'rpId'             => webauthnRpId(),
    'timeout'          => 60000,
    'userVerification' => 'required',
]);