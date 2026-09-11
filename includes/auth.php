<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Durasi maksimal inaktivitas sesi (1 jam = 3600 detik).
 */
define('SESSION_MAX_LIFETIME', 3600);

/**
 * Memeriksa dan memperbarui waktu aktivitas sesi pengguna.
 */
function checkSessionTimeout(): void
{
    if (isset($_SESSION['user_id'])) {
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_MAX_LIFETIME)) {
            session_unset();
            session_destroy();

            session_start();
            $_SESSION['flash'] = [
                'type' => 'error',
                'message' => 'Sesi Anda telah berakhir karena tidak ada aktivitas selama 1 jam. Silakan masuk kembali.'
            ];

            header('Location: login.php');
            exit;
        }

        $_SESSION['last_activity'] = time();
    }
}

function isLoggedIn(): bool
{
    checkSessionTimeout();
    return isset($_SESSION['user_id']);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function currentUserId(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

function currentUserName(): string
{
    return $_SESSION['user_name'] ?? '';
}