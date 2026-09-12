<?php
/**
 * Koneksi database menggunakan PDO.
 * Sesuaikan konstanta di bawah dengan konfigurasi MySQL lokal Anda.
 */

date_default_timezone_set('Asia/Jakarta'); // WIB

define('DB_HOST', 'localhost');
define('DB_NAME', 'keuangan_mhs');
define('DB_USER', 'root');
define('DB_PASS', '');

function getConnection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            $pdo->exec("SET time_zone = '+07:00'");
        } catch (PDOException $e) {
            http_response_code(500);
            die('Koneksi database gagal. Periksa konfigurasi di config/database.php');
        }
    }

    return $pdo;
}
