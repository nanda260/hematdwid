<?php
/**
 * Dipakai di setiap halaman internal (setelah login).
 * Membutuhkan variabel $pageTitle dan $activePage sebelum di-include.
 */
$balances = getBucketBalances(getConnection(), currentUserId());
$flash = getFlash();
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
<title><?= sanitize($pageTitle ?? 'Dashboard') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="icon" type="image/png" sizes="512x512" href="assets/img/logo.png">
<link rel="apple-touch-icon" sizes="512x512" href="assets/img/logo.png">
<link rel="manifest" href="manifest.json" crossorigin="use-credentials">
<meta name="theme-color" content="#091413">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<script src="assets/js/loading.js" defer></script>
<script src="assets/js/riwayat.js" defer></script>
<script src="assets/js/transaksi-edit.js" defer></script>

<!-- Tambahkan skrip registrasi Service Worker di bawah ini -->
<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('sw.js')
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
<div class="app-shell">

  <button class="nav-toggle" id="navToggle" aria-label="Buka menu" aria-expanded="false" aria-controls="sidebar">
    <span></span><span></span><span></span>
  </button>

  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <span class="brand-mark"><img src="assets/img/logo.png" alt="HematDwid"></span>
      <div>
        <p class="brand-title">HematDwid</p>
        <p class="brand-sub"><?= sanitize(currentUserName()) ?></p>
      </div>
    </div>

    <nav class="sidebar-nav" aria-label="Navigasi utama">
      <a href="dashboard.php" class="<?= ($activePage ?? '') === 'dashboard' ? 'is-active' : '' ?>">Dashboard</a>
      <a href="pemasukan.php" class="<?= ($activePage ?? '') === 'pemasukan' ? 'is-active' : '' ?>">Pemasukan</a>
      <a href="pengeluaran.php" class="<?= ($activePage ?? '') === 'pengeluaran' ? 'is-active' : '' ?>">Pengeluaran</a>
      <a href="transfer.php" class="<?= ($activePage ?? '') === 'transfer' ? 'is-active' : '' ?>">Transfer Kantong</a>
      <a href="kategori.php" class="<?= ($activePage ?? '') === 'kategori' ? 'is-active' : '' ?>">Kategori</a>
      <a href="presentase.php" class="<?= ($activePage ?? '') === 'presentase' ? 'is-active' : '' ?>">Presentase</a>
      <a href="keamanan.php" class="<?= ($activePage ?? '') === 'keamanan' ? 'is-active' : '' ?>">Keamanan</a>
      <a href="hapus-data.php" class="<?= ($activePage ?? '') === 'hapus-data' ? 'is-active' : '' ?>">Hapus Data</a>
    </nav>
    
    <div class="sidebar-wallet">
      <p class="wallet-label">Saldo kantong</p>
      <div class="wallet-row">
        <span>Utama</span>
        <strong class="<?= $balances['utama'] < 0 ? 'is-negative' : '' ?>"><?= formatRupiah($balances['utama']) ?></strong>
      </div>
      <div class="wallet-row">
        <span>Nabung</span>
        <strong class="<?= $balances['nabung'] < 0 ? 'is-negative' : '' ?>"><?= formatRupiah($balances['nabung']) ?></strong>
      </div>
      <div class="wallet-row">
        <span>Bebas</span>
        <strong class="<?= $balances['bebas'] < 0 ? 'is-negative' : '' ?>"><?= formatRupiah($balances['bebas']) ?></strong>
      </div>
      <?php $totalBalance = $balances['utama'] + $balances['nabung'] + $balances['bebas']; ?>
      <div class="wallet-row wallet-total">
        <span>Total</span>
        <strong class="<?= $totalBalance < 0 ? 'is-negative' : '' ?>"><?= formatRupiah($totalBalance) ?></strong>
      </div>
    </div>

    <button type="button" class="theme-toggle" id="themeToggle" aria-label="Ganti tema" aria-pressed="false">
      <svg class="icon-sun" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"></circle><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"></path></svg>
      <svg class="icon-moon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
      <span class="theme-toggle-label">Mode Gelap</span>
    </button>

    <script>
      (function () {
        var btn = document.getElementById('themeToggle');
        var root = document.documentElement;
        function updateLabel() {
          var isDark = root.getAttribute('data-theme') === 'dark';
          btn.setAttribute('aria-pressed', isDark ? 'true' : 'false');
          btn.querySelector('.theme-toggle-label').textContent = isDark ? 'Mode Terang' : 'Mode Gelap';
        }
        updateLabel();
        btn.addEventListener('click', function () {
          var isDark = root.getAttribute('data-theme') === 'dark';
          var next = isDark ? 'light' : 'dark';
          root.setAttribute('data-theme', next);
          localStorage.setItem('theme', next);
          updateLabel();
        });
      })();
    </script>

    <a href="logout.php" class="sidebar-logout">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
        <polyline points="16 17 21 12 16 7"></polyline>
        <line x1="21" y1="12" x2="9" y2="12"></line>
      </svg>
      Keluar
    </a>
  </aside>

  <main class="main-content">
    <?php if ($flash): ?>
      <div class="flash flash-<?= sanitize($flash['type']) ?>" role="status"><?= sanitize($flash['message']) ?></div>
    <?php endif; ?>
