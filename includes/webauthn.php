<?php
/**
 * Helper WebAuthn native (tanpa library) — mendukung ES256 (EC P-256),
 * yang merupakan algoritma default pada sensor sidik jari platform
 * (Touch ID, Windows Hello, Android BiometricPrompt).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ==========================================================
 * KONFIGURASI RP (Relying Party)
 * ========================================================== */
function webauthnRpId(): string
{
    // RP ID harus berupa domain (tanpa skema/port), harus sama persis
    // dengan domain yang diakses browser.
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return explode(':', $host)[0];
}

function webauthnOrigin(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

define('WEBAUTHN_RP_NAME', 'HematDwid');

/* ==========================================================
 * BASE64URL
 * ========================================================== */
function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string
{
    $data = strtr($data, '-_', '+/');
    $pad = strlen($data) % 4;
    if ($pad) {
        $data .= str_repeat('=', 4 - $pad);
    }
    return base64_decode($data);
}

/* ==========================================================
 * CHALLENGE (disimpan di session, punya masa berlaku)
 * ========================================================== */
function webauthnGenerateChallenge(string $purpose): string
{
    $challenge = random_bytes(32);
    $_SESSION['webauthn_challenge_' . $purpose] = [
        'value'   => $challenge,
        'expires' => time() + 300, // 5 menit
    ];
    return $challenge;
}

function webauthnConsumeChallenge(string $purpose): ?string
{
    $key = 'webauthn_challenge_' . $purpose;
    $stored = $_SESSION[$key] ?? null;
    unset($_SESSION[$key]);

    if (!$stored || $stored['expires'] < time()) {
        return null;
    }
    return $stored['value'];
}

/* ==========================================================
 * CBOR DECODER (minimal — cukup untuk attestationObject & COSE key)
 * ========================================================== */
function cborDecodeItem(string $data, int $offset): array
{
    $byte = ord($data[$offset]);
    $majorType = $byte >> 5;
    $additionalInfo = $byte & 0x1F;
    $offset++;

    [$length, $offset] = cborReadLength($data, $offset, $additionalInfo);

    switch ($majorType) {
        case 0: // unsigned int
            return [$length, $offset];
        case 1: // negative int
            return [-1 - $length, $offset];
        case 2: // byte string
        case 3: // text string
            $val = substr($data, $offset, $length);
            return [$val, $offset + $length];
        case 4: // array
            $arr = [];
            for ($i = 0; $i < $length; $i++) {
                [$item, $offset] = cborDecodeItem($data, $offset);
                $arr[] = $item;
            }
            return [$arr, $offset];
        case 5: // map
            $map = [];
            for ($i = 0; $i < $length; $i++) {
                [$key, $offset] = cborDecodeItem($data, $offset);
                [$val, $offset] = cborDecodeItem($data, $offset);
                $map[$key] = $val;
            }
            return [$map, $offset];
        default:
            throw new Exception('Tipe CBOR tidak didukung: ' . $majorType);
    }
}

function cborReadLength(string $data, int $offset, int $additionalInfo): array
{
    if ($additionalInfo < 24) {
        return [$additionalInfo, $offset];
    } elseif ($additionalInfo === 24) {
        return [ord($data[$offset]), $offset + 1];
    } elseif ($additionalInfo === 25) {
        return [unpack('n', substr($data, $offset, 2))[1], $offset + 2];
    } elseif ($additionalInfo === 26) {
        return [unpack('N', substr($data, $offset, 4))[1], $offset + 4];
    } elseif ($additionalInfo === 27) {
        $hi = unpack('N', substr($data, $offset, 4))[1];
        $lo = unpack('N', substr($data, $offset + 4, 4))[1];
        return [($hi << 32) | $lo, $offset + 8];
    }
    throw new Exception('Panjang CBOR indefinite tidak didukung.');
}

/* ==========================================================
 * PARSING authenticatorData
 * ========================================================== */
function webauthnParseAuthData(string $authData): array
{
    $rpIdHash = substr($authData, 0, 32);
    $flags = ord($authData[32]);
    $counter = unpack('N', substr($authData, 33, 4))[1];
    $offset = 37;

    $result = [
        'rpIdHash'     => $rpIdHash,
        'flags'        => $flags,
        'counter'      => $counter,
        'userPresent'  => (bool)($flags & 0x01),
        'userVerified' => (bool)($flags & 0x04),
        'credentialId' => null,
        'publicKeyPem' => null,
    ];

    if ($flags & 0x40) { // AT: attested credential data hadir
        $offset += 16; // lewati AAGUID
        $credIdLen = unpack('n', substr($authData, $offset, 2))[1];
        $offset += 2;
        $credentialId = substr($authData, $offset, $credIdLen);
        $offset += $credIdLen;

        [$coseKey, ] = cborDecodeItem($authData, $offset);

        $result['credentialId'] = $credentialId;
        $result['publicKeyPem'] = webauthnCoseKeyToPem($coseKey);
    }

    return $result;
}

/* ==========================================================
 * KONVERSI COSE KEY (EC2/ES256) -> PEM
 * ========================================================== */
function webauthnCoseKeyToPem(array $coseKey): string
{
    $kty = $coseKey[1] ?? null;
    $crv = $coseKey[-1] ?? null;

    if ($kty !== 2 || $crv !== 1) {
        throw new Exception('Hanya kunci EC2/P-256 (ES256) yang didukung.');
    }

    $x = $coseKey[-2];
    $y = $coseKey[-3];

    // Header DER SPKI baku untuk EC P-256 (fixed karena X,Y selalu 32 byte)
    $prefix = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200');
    $der = $prefix . "\x04" . $x . $y;

    $b64 = chunk_split(base64_encode($der), 64, "\n");
    return "-----BEGIN PUBLIC KEY-----\n" . $b64 . "-----END PUBLIC KEY-----\n";
}

/* ==========================================================
 * PENYIMPANAN KREDENSIAL
 * ========================================================== */
function webauthnSaveCredential(PDO $pdo, int $userId, string $credentialId, string $publicKeyPem, int $signCount, string $label = ''): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO webauthn_credentials (user_id, credential_id, public_key, sign_count, label) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $credentialId, $publicKeyPem, $signCount, $label]);
}

function webauthnFindCredential(PDO $pdo, string $credentialId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM webauthn_credentials WHERE credential_id = ?');
    $stmt->execute([$credentialId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function webauthnUpdateSignCount(PDO $pdo, int $credRowId, int $newCount): void
{
    $stmt = $pdo->prepare('UPDATE webauthn_credentials SET sign_count = ? WHERE id = ?');
    $stmt->execute([$newCount, $credRowId]);
}