<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';

requireLogin();

$pdo = getConnection();
$userId = currentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_credential') {
    if (verifyCsrf()) {
        $credId = (int)($_POST['credential_id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM webauthn_credentials WHERE id = ? AND user_id = ?');
        $stmt->execute([$credId, $userId]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Sidik jari berhasil dihapus.'];
    }
    header('Location: keamanan.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id, label, created_at FROM webauthn_credentials WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$userId]);
$credentials = $stmt->fetchAll();

$pageTitle = 'Keamanan';
$activePage = 'keamanan';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Keamanan</h1>
    <p>Kelola metode masuk dengan sidik jari untuk akun Anda.</p>
  </div>
</div>

<div class="card">
  <h2>Login dengan Sidik Jari</h2>
  <p>Daftarkan sensor sidik jari atau Face ID perangkat ini agar bisa dipakai sebagai opsi masuk tanpa mengetik kata sandi.</p>

  <button type="button" id="btnRegisterFingerprint" class="btn btn-primary">
    Daftarkan Sidik Jari
  </button>
  <p id="webauthnRegisterStatus" class="field-hint"></p>
</div>

<div class="card" style="margin-top: 20px;">
  <h2>Perangkat Terdaftar</h2>

  <?php if (empty($credentials)): ?>
    <div class="empty-state">Belum ada sidik jari yang didaftarkan.</div>
  <?php else: ?>
    <div class="category-list">
      <?php foreach ($credentials as $cred): ?>
        <div class="category-row">
          <div class="cat-meta">
            <strong><?= sanitize($cred['label']) ?></strong>
            <span class="field-hint" style="margin-left: 8px;">
              Didaftarkan <?= date('d/m/Y H:i', strtotime($cred['created_at'])) ?>
            </span>
          </div>
          <form method="post" data-confirm="Hapus sidik jari ini?">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="delete_credential">
            <input type="hidden" name="credential_id" value="<?= (int)$cred['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script src="assets/js/webauthn.js" defer></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>