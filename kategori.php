<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$pdo = getConnection();
$userId = currentUserId();
$pageTitle = 'Kategori';
$activePage = 'kategori';

// ---------------------------------------------------------
// Tambah kategori
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_category') {
    if (!verifyCsrf()) {
        setFlash('error', 'Sesi tidak valid, silakan coba lagi.');
    } else {
        $type = $_POST['type'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $bucket = $_POST['bucket'] ?? null;

        if (!in_array($type, ['pemasukan', 'pengeluaran'], true) || $name === '') {
            setFlash('error', 'Nama kategori dan jenis wajib diisi.');
        } elseif ($type === 'pengeluaran' && !in_array($bucket, ['utama', 'nabung', 'bebas'], true)) {
            setFlash('error', 'Kategori pengeluaran wajib punya kantong sumber default.');
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO categories (user_id, type, name, bucket) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$userId, $type, $name, $type === 'pengeluaran' ? $bucket : null]);
            setFlash('success', 'Kategori berhasil ditambahkan.');
        }
    }
    header('Location: kategori.php');
    exit;
}

// ---------------------------------------------------------
// Hapus kategori
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_category') {
    if (verifyCsrf()) {
        $categoryId = (int) ($_POST['category_id'] ?? 0);

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM incomes WHERE category_id = ?');
        $stmt->execute([$categoryId]);
        $usedInIncome = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM expenses WHERE category_id = ?');
        $stmt->execute([$categoryId]);
        $usedInExpense = (int) $stmt->fetchColumn();

        if ($usedInIncome > 0 || $usedInExpense > 0) {
            setFlash('error', 'Kategori tidak bisa dihapus karena masih dipakai transaksi.');
        } else {
            $stmt = $pdo->prepare('DELETE FROM categories WHERE id = ? AND user_id = ?');
            $stmt->execute([$categoryId, $userId]);
            setFlash('success', 'Kategori dihapus.');
        }
    }
    header('Location: kategori.php');
    exit;
}

// ---------------------------------------------------------
// Data
// ---------------------------------------------------------
$stmt = $pdo->prepare('SELECT * FROM categories WHERE user_id = ? AND type = "pemasukan" ORDER BY name');
$stmt->execute([$userId]);
$incomeCategories = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM categories WHERE user_id = ? AND type = "pengeluaran" ORDER BY name');
$stmt->execute([$userId]);
$expenseCategories = $stmt->fetchAll();

$expenseCategoriesByBucket = ['utama' => [], 'nabung' => [], 'bebas' => []];
foreach ($expenseCategories as $cat) {
    $expenseCategoriesByBucket[$cat['bucket']][] = $cat;
}

require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Kelola Kategori</h1>
    <p>Atur kategori pemasukan dan pengeluaran sesuai kebiasaanmu.</p>
  </div>
</div>

<div class="form-grid">
  <div class="card">
    <h2 style="margin-bottom:16px;">Tambah Kategori</h2>
    <form method="post" id="categoryForm">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="action" value="add_category">

      <div class="field">
        <label for="type">Jenis</label>
        <select id="type" name="type" required>
          <option value="pemasukan">Pemasukan</option>
          <option value="pengeluaran">Pengeluaran</option>
        </select>
      </div>

      <div class="field">
        <label for="name">Nama Kategori</label>
        <input type="text" id="name" name="name" required placeholder="Contoh: Fotokopi & Print">
      </div>

      <div class="field" id="bucketField">
        <label for="bucket">Kantong Sumber Default</label>
        <select id="bucket" name="bucket">
          <option value="utama">Kebutuhan Utama</option>
          <option value="nabung">Tabungan</option>
          <option value="bebas">Bebas Pakai</option>
        </select>
        <span class="field-hint">Dipakai sebagai saran otomatis saat mencatat pengeluaran.</span>
      </div>

      <button type="submit" class="btn btn-primary btn-block">Tambah Kategori</button>
    </form>
  </div>

  <div>
    <div class="card" style="margin-bottom:20px;">
      <h2 style="margin-bottom:14px;">Kategori Pemasukan</h2>
      <?php if (empty($incomeCategories)): ?>
        <div class="empty-state">Belum ada kategori pemasukan.</div>
      <?php else: ?>
        <div class="category-list">
          <?php foreach ($incomeCategories as $cat): ?>
            <div class="category-row">
              <div class="cat-meta"><?= sanitize($cat['name']) ?></div>
              <form method="post" data-confirm="Hapus kategori '<?= sanitize($cat['name']) ?>'?">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="delete_category">
                <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                <button type="submit" class="btn btn-secondary btn-sm">Hapus</button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2 style="margin-bottom:14px;">Kategori Pengeluaran</h2>
      <?php if (empty($expenseCategories)): ?>
        <div class="empty-state">Belum ada kategori pengeluaran.</div>
      <?php else: ?>
        <?php foreach ($expenseCategoriesByBucket as $bucketKey => $bucketCats): ?>
          <?php if (empty($bucketCats)) continue; ?>
          <div class="bucket-group">
            <span class="tag tag-<?= sanitize($bucketKey) ?> bucket-group-label"><?= bucketLabel($bucketKey) ?></span>
            <div class="category-list">
              <?php foreach ($bucketCats as $cat): ?>
                <div class="category-row">
                  <div class="cat-meta">
                    <span class="cat-name"><?= sanitize($cat['name']) ?></span>
                  </div>
                  <form method="post" data-confirm="Hapus kategori '<?= sanitize($cat['name']) ?>'?">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="action" value="delete_category">
                    <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                    <button type="submit" class="btn btn-secondary btn-sm">Hapus</button>
                  </form>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
