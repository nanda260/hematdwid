<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/webauthn.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
requireLogin();

$pdo = getConnection();
$userId = currentUserId();

$challenge = webauthnGenerateChallenge('register');

// Ambil kredensial yang sudah didaftarkan agar tidak didaftarkan dua kali di perangkat yang sama
$stmt = $pdo->prepare('SELECT credential_id FROM webauthn_credentials WHERE user_id = ?');
$stmt->execute([$userId]);
$excludeCredentials = array_map(function ($row) {
    return [
        'type' => 'public-key',
        'id'   => base64url_encode($row['credential_id']),
    ];
}, $stmt->fetchAll());

echo json_encode([
    'challenge' => base64url_encode($challenge),
    'rp' => [
        'name' => WEBAUTHN_RP_NAME,
        'id'   => webauthnRpId(),
    ],
    'user' => [
        'id'          => base64url_encode((string)$userId),
        'name'        => currentUserName(),
        'displayName' => currentUserName(),
    ],
    'pubKeyCredParams' => [
        ['type' => 'public-key', 'alg' => -7], // ES256
    ],
    'authenticatorSelection' => [
        'authenticatorAttachment' => 'platform', // paksa sensor bawaan (sidik jari/Face ID)
        'userVerification'        => 'required',
        'residentKey'             => 'required',
        'requireResidentKey'      => true, // kompatibilitas WebAuthn L1
    ],
    'timeout' => 60000,
    'attestation' => 'none',
    'excludeCredentials' => $excludeCredentials,
]);