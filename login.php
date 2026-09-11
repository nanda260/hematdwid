<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Sesi tidak valid, silakan coba lagi.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            $error = 'Email dan kata sandi wajib diisi.';
        } else {
            $pdo = getConnection();
            $stmt = $pdo->prepare('SELECT id, name, password FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['last_activity'] = time();
                header('Location: dashboard.php');
                exit;
            }

            $error = 'Email atau kata sandi salah.';
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
<title>Masuk </title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="icon" type="image/png" sizes="512x512" href="assets/img/logo.png">
<link rel="apple-touch-icon" sizes="512x512" href="assets/img/logo.png">
<link rel="manifest" href="/manifest.json" crossorigin="use-credentials">
<meta name="theme-color" content="#091413">
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
      <h1>Setiap rupiah masuk, langsung tahu tempatnya.</h1>
      <p>Pemasukan otomatis terbagi ke tiga kantong: kebutuhan utama, tabungan, dan bebas pakai - jadi kamu selalu tahu batas amanmu.</p>
      <div class="split-preview">
        <div class="chip"><strong>55%</strong><span>Kebutuhan Utama</span></div>
        <div class="chip"><strong>25%</strong><span>Tabungan</span></div>
        <div class="chip"><strong>20%</strong><span>Bebas Pakai</span></div>
      </div>
    </div>
    <p class="foot-note">Presentase dapat disesuaikan sendiri.</p>
  </div>

  <div class="auth-form-wrap">
    <div class="auth-form">
      <h2>Masuk ke akun</h2>
      <p>Lanjutkan mencatat keuangan harianmu.</p>

      <?php if ($error): ?>
        <div class="form-error"><?= sanitize($error) ?></div>
      <?php endif; ?>

      <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <div class="field">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" required value="<?= sanitize($_POST['email'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="password">Kata Sandi</label>
          <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Masuk</button>
      </form>

      <div id="webauthnLoginWrap" style="margin-top: 12px;">
        <button type="button" id="btnLoginFingerprint" class="btn btn-secondary btn-block">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 11a2 2 0 0 0-2 2c0 1.1-.2 2.2-.6 3.2"></path>
            <path d="M15 9a5 5 0 0 0-9.5 2c0 2-.5 4.4-1.5 6"></path>
            <path d="M17.5 4.5A9.5 9.5 0 0 1 21.5 12c0 1.1-.1 2.2-.3 3.2"></path>
            <path d="M9 17.5c-.5 1-1.5 2-2.5 2.5"></path>
            <path d="M12.5 15.5a2.5 2.5 0 0 0 3-3.5"></path>
          </svg>
          Masuk dengan Sidik Jari
        </button>
        <p id="webauthnLoginStatus" class="field-hint"></p>
      </div>

      <!-- Tombol Install PWA -->
      <div id="pwaInstallWrap" style="display: none; margin-top: 12px;">
        <button type="button" id="btnInstallPwa" class="btn btn-secondary btn-block">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
            <polyline points="7 10 12 15 17 10"></polyline>
            <line x1="12" y1="15" x2="12" y2="3"></line>
          </svg>
          Instal Aplikasi HematDwid
        </button>
      </div>

      <p class="auth-switch">Belum punya akun? <a href="register.php">Daftar di sini</a></p>
    </div>
  </div>
</div>

<script>
let deferredPrompt;
const pwaInstallWrap = document.getElementById('pwaInstallWrap');
const btnInstallPwa = document.getElementById('btnInstallPwa');

window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault();
  deferredPrompt = e;
  pwaInstallWrap.style.display = 'block';
});

btnInstallPwa.addEventListener('click', async () => {
  if (!deferredPrompt) return;
  deferredPrompt.prompt();
  const { outcome } = await deferredPrompt.userChoice;
  if (outcome === 'accepted') {
    pwaInstallWrap.style.display = 'none';
  }
  deferredPrompt = null;
});

window.addEventListener('appinstalled', () => {
  pwaInstallWrap.style.display = 'none';
  deferredPrompt = null;
});
</script>
<script src="assets/js/webauthn.js" defer></script>
</body>
</html>