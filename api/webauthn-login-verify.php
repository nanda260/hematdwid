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

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    webauthnLoginFail('Data tidak valid.');
}

$expectedChallenge = webauthnConsumeChallenge('login');

if ($expectedChallenge === null) {
    webauthnLoginFail('Sesi login kedaluwarsa. Silakan coba lagi.');
}

try {
    $credentialId = base64url_decode($input['id'] ?? '');
    $clientDataJSON = base64url_decode($input['response']['clientDataJSON'] ?? '');
    $authenticatorData = base64url_decode($input['response']['authenticatorData'] ?? '');
    $signature = base64url_decode($input['response']['signature'] ?? '');

    $clientData = json_decode($clientDataJSON, true);

    if (($clientData['type'] ?? '') !== 'webauthn.get') {
        webauthnLoginFail('Tipe operasi tidak sesuai.');
    }
    if (base64url_decode($clientData['challenge'] ?? '') !== $expectedChallenge) {
        webauthnLoginFail('Challenge tidak cocok.');
    }
    if (rtrim($clientData['origin'] ?? '', '/') !== rtrim(webauthnOrigin(), '/')) {
        webauthnLoginFail('Origin tidak dikenali.');
    }

    $pdo = getConnection();
    $cred = webauthnFindCredential($pdo, $credentialId);

    if (!$cred) {
        webauthnLoginFail('Kredensial tidak dikenali.');
    }

    // Identitas user diambil dari kredensial yang cocok, bukan input pengguna
    $userId = (int)$cred['user_id'];

    $rpIdHash = substr($authenticatorData, 0, 32);
    if (hash('sha256', webauthnRpId(), true) !== $rpIdHash) {
        webauthnLoginFail('RP ID tidak cocok.');
    }

    $flags = ord($authenticatorData[32]);
    if (!($flags & 0x01) || !($flags & 0x04)) {
        webauthnLoginFail('Verifikasi Biometrik tidak terdeteksi.');
    }

    $counter = unpack('N', substr($authenticatorData, 33, 4))[1];
    if ($counter !== 0 && $counter <= (int)$cred['sign_count']) {
        webauthnLoginFail('Kemungkinan kredensial diduplikasi (replay terdeteksi).');
    }

    $clientDataHash = hash('sha256', $clientDataJSON, true);
    $signedData = $authenticatorData . $clientDataHash;

    $publicKey = openssl_pkey_get_public($cred['public_key']);
    if (!$publicKey) {
        webauthnLoginFail('Kunci publik tidak valid.');
    }

    $valid = openssl_verify($signedData, $signature, $publicKey, OPENSSL_ALGO_SHA256);
    if ($valid !== 1) {
        webauthnLoginFail('Verifikasi Biometrik gagal.');
    }

    webauthnUpdateSignCount($pdo, (int)$cred['id'], $counter);

    $stmt = $pdo->prepare('SELECT id, name FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['last_activity'] = time();

    echo json_encode(['success' => true, 'redirect' => 'dashboard.php']);
} catch (Exception $e) {
    webauthnLoginFail('Gagal memproses: ' . $e->getMessage());
}