<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/webauthn.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
requireLogin();

function webauthnFail(string $message): void
{
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    webauthnFail('Data tidak valid.');
}

$expectedChallenge = webauthnConsumeChallenge('register');
if ($expectedChallenge === null) {
    webauthnFail('Sesi pendaftaran kedaluwarsa. Silakan coba lagi.');
}

try {
    $clientDataJSON = base64url_decode($input['response']['clientDataJSON'] ?? '');
    $attestationObject = base64url_decode($input['response']['attestationObject'] ?? '');
    $clientData = json_decode($clientDataJSON, true);

    if (($clientData['type'] ?? '') !== 'webauthn.create') {
        webauthnFail('Tipe operasi tidak sesuai.');
    }
    if (base64url_decode($clientData['challenge'] ?? '') !== $expectedChallenge) {
        webauthnFail('Challenge tidak cocok.');
    }
    if (rtrim($clientData['origin'] ?? '', '/') !== rtrim(webauthnOrigin(), '/')) {
        webauthnFail('Origin tidak dikenali.');
    }

    [$attestation, ] = cborDecodeItem($attestationObject, 0);
    $authData = webauthnParseAuthData($attestation['authData']);

    if (!$authData['userPresent'] || !$authData['userVerified']) {
        webauthnFail('Verifikasi sidik jari tidak terdeteksi.');
    }
    if (hash('sha256', webauthnRpId(), true) !== $authData['rpIdHash']) {
        webauthnFail('RP ID tidak cocok.');
    }
    if (!$authData['credentialId'] || !$authData['publicKeyPem']) {
        webauthnFail('Kredensial tidak lengkap.');
    }

    $label = trim($input['label'] ?? '') ?: 'Perangkat ' . date('d/m/Y H:i');

    $pdo = getConnection();
    webauthnSaveCredential(
        $pdo,
        currentUserId(),
        $authData['credentialId'],
        $authData['publicKeyPem'],
        $authData['counter'],
        $label
    );

    echo json_encode(['success' => true, 'message' => 'Sidik jari berhasil didaftarkan.']);
} catch (Exception $e) {
    webauthnFail('Gagal memproses: ' . $e->getMessage());
}