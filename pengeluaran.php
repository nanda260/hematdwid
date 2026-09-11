<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$pdo = getConnection();
$userId = currentUserId();
$pageTitle = 'Pengeluaran';
$activePage = 'pengeluaran';

// ---------------------------------------------------------
// Proses tambah pengeluaran
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_expense') {
    if (!verifyCsrf()) {
        setFlash('error', 'Sesi tidak valid, silakan coba lagi.');
    } else {
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $bucket = $_POST['bucket'] ?? '';
        $amount = (float) ($_POST['amount'] ?? 0);
        $description = mb_convert_case(trim($_POST['description'] ?? ''), MB_CASE_TITLE, 'UTF-8');
        $expenseDate = $_POST['expense_date'] ?? date('Y-m-d');

        $validBuckets = ['utama', 'nabung', 'bebas'];

        if ($categoryId <= 0 || $amount <= 0 || !in_array($bucket, $validBuckets, true)) {
            setFlash('error', 'Semua kolom wajib diisi dengan benar.');
        } else {
            $stmt = $pdo->prepare('SELECT id FROM categories WHERE id = ? AND user_id = ? AND type = "pengeluaran"');
            $stmt->execute([$categoryId, $userId]);

            if (!$stmt->fetch()) {
                setFlash('error', 'Kategori tidak valid.');
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO expenses (user_id, category_id, bucket, amount, description, expense_date)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$userId, $categoryId, $bucket, $amount, $description ?: null, $expenseDate]);
                setFlash('success', 'Pengeluaran berhasil dicatat.');
            }
        }
    }
    header('Location: pengeluaran.php');
    exit;
}

// ---------------------------------------------------------
// Proses edit pengeluaran
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_expense') {
    if (!verifyCsrf()) {
        setFlash('error', 'Sesi tidak valid, silakan coba lagi.');
    } else {
        $expenseId = (int) ($_POST['expense_id'] ?? 0);
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $bucket = $_POST['bucket'] ?? '';
        $amount = (float) ($_POST['amount'] ?? 0);
        $description = mb_convert_case(trim($_POST['description'] ?? ''), MB_CASE_TITLE, 'UTF-8');
        $expenseDate = $_POST['expense_date'] ?? date('Y-m-d');

        $validBuckets = ['utama', 'nabung', 'bebas'];

        if ($expenseId <= 0 || $categoryId <= 0 || $amount <= 0 || !in_array($bucket, $validBuckets, true)) {
            setFlash('error', 'Semua kolom wajib diisi dengan benar.');
        } else {
            $stmt = $pdo->prepare('SELECT id FROM categories WHERE id = ? AND user_id = ? AND type = "pengeluaran"');
            $stmt->execute([$categoryId, $userId]);

            if (!$stmt->fetch()) {
                setFlash('error', 'Kategori tidak valid.');
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE expenses SET category_id = ?, bucket = ?, amount = ?, description = ?, expense_date = ?
                     WHERE id = ? AND user_id = ?'
                );
                $stmt->execute([$categoryId, $bucket, $amount, $description ?: null, $expenseDate, $expenseId, $userId]);
                setFlash('success', 'Pengeluaran berhasil diperbarui.');
            }
        }
    }
    header('Location: pengeluaran.php');
    exit;
}

// ---------------------------------------------------------
// Hapus pengeluaran
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_expense') {
    if (verifyCsrf()) {
        $expenseId = (int) ($_POST['expense_id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM expenses WHERE id = ? AND user_id = ?');
        $stmt->execute([$expenseId, $userId]);
        setFlash('success', 'Pengeluaran dihapus.');
    }
    header('Location: pengeluaran.php');
    exit;
}

// ---------------------------------------------------------
// Data untuk tampilan
// ---------------------------------------------------------
$stmt = $pdo->prepare('SELECT id, name, bucket FROM categories WHERE user_id = ? AND type = "pengeluaran" ORDER BY name');
$stmt->execute([$userId]);
$categories = $stmt->fetchAll();

$bucketBalances = getBucketBalances($pdo, $userId);

require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Pengeluaran</h1>
    <p>Setiap pengeluaran menarik saldo dari salah satu kantong.</p>
  </div>
</div>

<div class="form-grid">
  <div class="card">
    <h2 style="margin-bottom:16px;">Tambah Pengeluaran</h2>

    <?php if (empty($categories)): ?>
      <p>Belum ada kategori pengeluaran. <a href="kategori.php">Buat kategori dulu</a>.</p>
    <?php else: ?>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="add_expense">

        <div class="field">
          <label for="expenseCategory">Kategori</label>
          <select id="expenseCategory" name="category_id" required>
            <option value="">Pilih kategori</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['id'] ?>" data-bucket="<?= sanitize($cat['bucket'] ?? '') ?>">
                <?= sanitize($cat['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label for="expenseBucket">Sumber Kantong</label>
          <select id="expenseBucket" name="bucket" required>
            <option value="utama">Kebutuhan Utama (saldo: <?= formatRupiah($bucketBalances['utama']) ?>)</option>
            <option value="nabung">Tabungan (saldo: <?= formatRupiah($bucketBalances['nabung']) ?>)</option>
            <option value="bebas">Bebas Pakai (saldo: <?= formatRupiah($bucketBalances['bebas']) ?>)</option>
          </select>
          <span class="field-hint">Terisi otomatis sesuai kategori, bisa diubah manual.</span>
        </div>

        <div class="field">
          <label for="amount">Jumlah (Rp)</label>
          <input type="text" inputmode="numeric" id="amount" name="amount" class="rupiah-input" required placeholder="25.000">
        </div>

        <div class="field">
          <label for="expense_date">Tanggal</label>
          <input type="date" id="expense_date" name="expense_date" value="<?= date('Y-m-d') ?>" required>
        </div>

        <div class="field">
          <label for="description">Keterangan (opsional)</label>
          <textarea id="description" name="description" placeholder="Contoh: Makan siang warteg"></textarea>
        </div>

        <button type="submit" class="btn btn-primary btn-block">Simpan Pengeluaran</button>
      </form>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2 style="margin-bottom:16px;">Riwayat Pengeluaran</h2>

    <div class="tx-filter-bar" data-riwayat data-endpoint="api/riwayat-pengeluaran.php">
  <button type="button" class="btn btn-secondary btn-sm filter-toggle-btn" data-filter-toggle aria-expanded="false">
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
    Filter
  </button>
  <div class="filter-row">
    <div class="field filter-search">
      <label for="expSearch">Cari keterangan</label>
      <input type="text" id="expSearch" name="search" placeholder="Cari keterangan...">
    </div>
    <div class="field">
      <label for="expDateFrom">Dari tanggal</label>
      <input type="date" id="expDateFrom" name="date_from">
    </div>
    <div class="field">
      <label for="expDateTo">Sampai tanggal</label>
      <input type="date" id="expDateTo" name="date_to">
    </div>
    <div class="field">
      <label for="expFilterCategory">Kategori</label>
      <select id="expFilterCategory" name="category_id">
        <option value="">Semua kategori</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= $cat['id'] ?>"><?= sanitize($cat['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="expFilterBucket">Kantong</label>
      <select id="expFilterBucket" name="bucket">
        <option value="">Semua kantong</option>
        <option value="utama">Kebutuhan Utama</option>
        <option value="nabung">Tabungan</option>
        <option value="bebas">Bebas Pakai</option>
      </select>
    </div>
    <button type="button" class="btn btn-sm filter-reset-btn" data-riwayat-reset>Reset Filter</button>
  </div>
</div>

    <div class="table-wrap list-tx-table">
      <table>
        <thead>
          <tr><th>Tanggal</th><th>Kategori</th><th>Kantong</th><th>Keterangan</th><th>Jumlah</th><th></th></tr>
        </thead>
        <tbody id="expenseTableBody" data-riwayat-table></tbody>
      </table>
    </div>

    <div class="transaction-list" id="expenseCardList" data-riwayat-cards></div>

    <div class="empty-state" data-riwayat-empty style="display:none;">Tidak ada pengeluaran yang cocok dengan filter.</div>
    <div class="riwayat-loading" data-riwayat-loading style="display:none;">Memuat...</div>
    <div data-riwayat-sentinel style="height:1px;"></div>
  </div>
</div>

<div class="modal-overlay" id="editExpenseModal" data-modal>
  <div class="modal-box">
    <div class="modal-head">
      <h2>Edit Pengeluaran</h2>
      <button type="button" class="modal-close" data-modal-close aria-label="Tutup">&times;</button>
    </div>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="action" value="edit_expense">
      <input type="hidden" name="expense_id" id="editExpenseId">

      <div class="field">
        <label for="editExpenseCategory">Kategori</label>
        <select id="editExpenseCategory" name="category_id" required>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>" data-bucket="<?= sanitize($cat['bucket'] ?? '') ?>">
              <?= sanitize($cat['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label for="editExpenseBucket">Sumber Kantong</label>
        <select id="editExpenseBucket" name="bucket" required>
          <option value="utama">Kebutuhan Utama</option>
          <option value="nabung">Tabungan</option>
          <option value="bebas">Bebas Pakai</option>
        </select>
      </div>

      <div class="field">
        <label for="editExpenseAmount">Jumlah (Rp)</label>
        <input type="text" inputmode="numeric" id="editExpenseAmount" name="amount" class="rupiah-input" required>
      </div>

      <div class="field">
        <label for="editExpenseDate">Tanggal</label>
        <input type="date" id="editExpenseDate" name="expense_date" required>
      </div>

      <div class="field">
        <label for="editExpenseDescription">Keterangan (opsional)</label>
        <textarea id="editExpenseDescription" name="description"></textarea>
      </div>

      <div class="form-actions">
        <button type="button" class="btn btn-secondary btn-block" data-modal-close>Batal</button>
        <button type="submit" class="btn btn-primary btn-block">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
