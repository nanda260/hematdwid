<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$old = ['name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Sesi tidak valid, silakan coba lagi.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';
        $old = ['name' => $name, 'email' => $email];

        if ($name === '' || $email === '' || $password === '') {
            $error = 'Semua kolom wajib diisi.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format email tidak valid.';
        } elseif (strlen($password) < 6) {
            $error = 'Kata sandi minimal 6 karakter.';
        } elseif ($password !== $passwordConfirm) {
            $error = 'Konfirmasi kata sandi tidak cocok.';
        } else {
            $pdo = getConnection();
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);

            if ($stmt->fetch()) {
                $error = 'Email sudah terdaftar.';
            } else {
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)');
                    $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
                    $userId = (int) $pdo->lastInsertId();

                    $stmt = $pdo->prepare(
                        'INSERT INTO settings (user_id, persen_utama, persen_nabung, persen_bebas) VALUES (?, 55, 25, 20)'
                    );
                    $stmt->execute([$userId]);

                    seedDefaultCategories($pdo, $userId);

                    $pdo->commit();

                    $_SESSION['user_id'] = $userId;
                    $_SESSION['user_name'] = $name;
                    header('Location: dashboard.php');
                    exit;
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Pendaftaran gagal, silakan coba lagi.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<script>
  (function () {
    var saved = localStorage.getItem('theme');
    if (saved === 'dark') {
      document.documentElement.setAttribute('data-theme', 'dark');
    }
  })();
</script>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daftar</title>
<link rel="icon" type="image/png" sizes="512x512" href="assets/img/logo.png">
<link rel="apple-touch-icon" sizes="512x512" href="assets/img/logo.png">
<link rel="manifest" href="/manifest.json" crossorigin="use-credentials">
<meta name="theme-color" content="#091413">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<script src="assets/js/loading.js" defer></script>
<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js')
        .then(reg => console.log('SW terdaftar:', reg.scope))
        .catch(err => console.error('Gagal daftar SW:', err));
    });
  }
</script>
</head>
<body>
<div id="globalLoadingOverlay" aria-hidden="true">
  <div class="loading-spinner"></div>
</div>
<div class="auth-shell">
  <div class="auth-visual">
    <span class="mark"><img src="assets/img/logo.png" alt="HematDwid">HematDwid</span>
    <div class="pitch">
      <h1>Dibuat untuk ritme keuangan anak kos.</h1>
      <p>Catat pemasukan dan pengeluaran harian, lalu biarkan sistem menjaga porsi kebutuhan, tabungan, dan jajanmu tetap seimbang.</p>
      <div class="split-preview">
        <div class="chip"><strong>55%</strong><span>Kebutuhan Utama</span></div>
        <div class="chip"><strong>25%</strong><span>Tabungan</span></div>
        <div class="chip"><strong>20%</strong><span>Bebas Pakai</span></div>
      </div>
    </div>
    <p class="foot-note">Kategori awal otomatis dibuatkan setelah daftar.</p>
  </div>

  <div class="auth-form-wrap">
    <div class="auth-form">
      <h2>Buat akun baru</h2>
      <p>Gratis, tidak ada langkah rumit.</p>

      <?php if ($error): ?>
        <div class="form-error"><?= sanitize($error) ?></div>
      <?php endif; ?>

      <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <div class="field">
          <label for="name">Nama</label>
          <input type="text" id="name" name="name" required value="<?= sanitize($old['name']) ?>">
        </div>
        <div class="field">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" required value="<?= sanitize($old['email']) ?>">
        </div>
        <div class="field">
          <label for="password">Kata Sandi</label>
          <input type="password" id="password" name="password" required>
          <span class="field-hint">Minimal 6 karakter.</span>
        </div>
        <div class="field">
          <label for="password_confirm">Konfirmasi Kata Sandi</label>
          <input type="password" id="password_confirm" name="password_confirm" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Daftar</button>
      </form>

      <p class="auth-switch">Sudah punya akun? <a href="login.php">Masuk di sini</a></p>
    </div>
  </div>
</div>

</body>
</html>
