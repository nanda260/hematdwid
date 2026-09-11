<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$pdo = getConnection();
$userId = currentUserId();
$pageTitle = 'Pemasukan';
$activePage = 'pemasukan';

$settings = getSettings($pdo, $userId);

// ---------------------------------------------------------
// Proses tambah pemasukan
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_income') {
    if (!verifyCsrf()) {
        setFlash('error', 'Sesi tidak valid, silakan coba lagi.');
    } else {
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $amount = (float) ($_POST['amount'] ?? 0);
        $description = mb_convert_case(trim($_POST['description'] ?? ''), MB_CASE_TITLE, 'UTF-8');
        $incomeDate = $_POST['income_date'] ?? date('Y-m-d');

        if ($categoryId <= 0 || $amount <= 0) {
            setFlash('error', 'Kategori dan jumlah pemasukan wajib diisi dengan benar.');
        } else {
            $stmt = $pdo->prepare('SELECT id FROM categories WHERE id = ? AND user_id = ? AND type = "pemasukan"');
            $stmt->execute([$categoryId, $userId]);

            if (!$stmt->fetch()) {
                setFlash('error', 'Kategori tidak valid.');
            } else {
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare(
                        'INSERT INTO incomes (user_id, category_id, amount, description, income_date) VALUES (?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([$userId, $categoryId, $amount, $description ?: null, $incomeDate]);
                    $incomeId = (int) $pdo->lastInsertId();

                    $allocations = [
                        'utama'  => round($amount * ((float) $settings['persen_utama']) / 100, 2),
                        'nabung' => round($amount * ((float) $settings['persen_nabung']) / 100, 2),
                    ];
                    // sisa dibulatkan ke bebas agar total pasti sama persis dengan amount
                    $allocations['bebas'] = round($amount - $allocations['utama'] - $allocations['nabung'], 2);

                    $stmtAlloc = $pdo->prepare(
                        'INSERT INTO income_allocations (income_id, bucket, amount) VALUES (?, ?, ?)'
                    );
                    foreach ($allocations as $bucket => $bucketAmount) {
                        $stmtAlloc->execute([$incomeId, $bucket, $bucketAmount]);
                    }

                    $pdo->commit();
                    setFlash('success', 'Pemasukan berhasil dicatat dan dibagi ke 3 kantong.');
                } catch (Exception $e) {
                    $pdo->rollBack();
                    setFlash('error', 'Gagal menyimpan pemasukan.');
                }
            }
        }
    }
    header('Location: pemasukan.php');
    exit;
}

// ---------------------------------------------------------
// Proses edit pemasukan
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_income') {
    if (!verifyCsrf()) {
        setFlash('error', 'Sesi tidak valid, silakan coba lagi.');
    } else {
        $incomeId = (int) ($_POST['income_id'] ?? 0);
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $amount = (float) ($_POST['amount'] ?? 0);
        $description = mb_convert_case(trim($_POST['description'] ?? ''), MB_CASE_TITLE, 'UTF-8');
        $incomeDate = $_POST['income_date'] ?? date('Y-m-d');

        if ($incomeId <= 0 || $categoryId <= 0 || $amount <= 0) {
            setFlash('error', 'Semua kolom wajib diisi dengan benar.');
        } else {
            $stmt = $pdo->prepare('SELECT id FROM incomes WHERE id = ? AND user_id = ?');
            $stmt->execute([$incomeId, $userId]);

            if (!$stmt->fetch()) {
                setFlash('error', 'Data pemasukan tidak ditemukan.');
            } else {
                $stmt = $pdo->prepare('SELECT id FROM categories WHERE id = ? AND user_id = ? AND type = "pemasukan"');
                $stmt->execute([$categoryId, $userId]);

                if (!$stmt->fetch()) {
                    setFlash('error', 'Kategori tidak valid.');
                } else {
                    $pdo->beginTransaction();
                    try {
                        $stmt = $pdo->prepare(
                            'UPDATE incomes SET category_id = ?, amount = ?, description = ?, income_date = ?
                             WHERE id = ? AND user_id = ?'
                        );
                        $stmt->execute([$categoryId, $amount, $description ?: null, $incomeDate, $incomeId, $userId]);

                        $stmt = $pdo->prepare('DELETE FROM income_allocations WHERE income_id = ?');
                        $stmt->execute([$incomeId]);

                        $allocations = [
                            'utama'  => round($amount * ((float) $settings['persen_utama']) / 100, 2),
                            'nabung' => round($amount * ((float) $settings['persen_nabung']) / 100, 2),
                        ];
                        $allocations['bebas'] = round($amount - $allocations['utama'] - $allocations['nabung'], 2);

                        $stmtAlloc = $pdo->prepare(
                            'INSERT INTO income_allocations (income_id, bucket, amount) VALUES (?, ?, ?)'
                        );
                        foreach ($allocations as $bucket => $bucketAmount) {
                            $stmtAlloc->execute([$incomeId, $bucket, $bucketAmount]);
                        }

                        $pdo->commit();
                        setFlash('success', 'Pemasukan berhasil diperbarui dan pembagian kantong disesuaikan.');
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        setFlash('error', 'Gagal memperbarui pemasukan.');
                    }
                }
            }
        }
    }
    header('Location: pemasukan.php');
    exit;
}

// ---------------------------------------------------------
// Hapus pemasukan
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_income') {
    if (verifyCsrf()) {
        $incomeId = (int) ($_POST['income_id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM incomes WHERE id = ? AND user_id = ?');
        $stmt->execute([$incomeId, $userId]);
        setFlash('success', 'Pemasukan dihapus.');
    }
    header('Location: pemasukan.php');
    exit;
}

// ---------------------------------------------------------
// Data untuk tampilan
// ---------------------------------------------------------
$stmt = $pdo->prepare('SELECT id, name FROM categories WHERE user_id = ? AND type = "pemasukan" ORDER BY name');
$stmt->execute([$userId]);
$categories = $stmt->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Pemasukan</h1>
    <p>Setiap pemasukan otomatis terbagi ke 3 kantong sesuai presentase kamu.</p>
  </div>
</div>

<div class="form-grid">
  <div class="card">
    <h2 style="margin-bottom:16px;">Tambah Pemasukan</h2>

    <?php if (empty($categories)): ?>
      <p>Belum ada kategori pemasukan. <a href="kategori.php">Buat kategori dulu</a>.</p>
    <?php else: ?>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="add_income">

        <div class="field">
          <label for="category_id">Kategori</label>
          <select id="category_id" name="category_id" required>
            <option value="">Pilih kategori</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['id'] ?>"><?= sanitize($cat['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label for="incomeAmount">Jumlah (Rp)</label>
          <input type="text" inputmode="numeric" id="incomeAmount" name="amount" class="rupiah-input" required placeholder="500.000">
        </div>

        <div class="field">
          <label for="income_date">Tanggal</label>
          <input type="date" id="income_date" name="income_date" value="<?= date('Y-m-d') ?>" required>
        </div>

        <div class="field">
          <label for="description">Keterangan (opsional)</label>
          <textarea id="description" name="description" placeholder="Contoh: Kiriman orang tua bulan Oktober"></textarea>
        </div>

        <div id="splitPreview"
             data-utama="<?= $settings['persen_utama'] ?>"
             data-nabung="<?= $settings['persen_nabung'] ?>"
             data-bebas="<?= $settings['persen_bebas'] ?>"
             class="field">
          <label>Pratinjau Pembagian</label>
          <div class="stat-foot"><span>Kebutuhan Utama (<?= $settings['persen_utama'] ?>%)</span><strong data-out="utama">Rp0</strong></div>
          <div class="stat-foot"><span>Tabungan (<?= $settings['persen_nabung'] ?>%)</span><strong data-out="nabung">Rp0</strong></div>
          <div class="stat-foot"><span>Bebas Pakai (<?= $settings['persen_bebas'] ?>%)</span><strong data-out="bebas">Rp0</strong></div>
        </div>

        <button type="submit" class="btn btn-primary btn-block">Simpan Pemasukan</button>
      </form>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2 style="margin-bottom:16px;">Riwayat Pemasukan</h2>

    <div class="tx-filter-bar" data-riwayat data-endpoint="api/riwayat-pemasukan.php">
  <button type="button" class="btn btn-secondary btn-sm filter-toggle-btn" data-filter-toggle aria-expanded="false">
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
    Filter
  </button>
  <div class="filter-row">
    <div class="field filter-search">
      <label for="incSearch">Cari keterangan</label>
      <input type="text" id="incSearch" name="search" placeholder="Cari keterangan...">
    </div>
    <div class="field">
      <label for="incDateFrom">Dari tanggal</label>
      <input type="date" id="incDateFrom" name="date_from">
    </div>
    <div class="field">
      <label for="incDateTo">Sampai tanggal</label>
      <input type="date" id="incDateTo" name="date_to">
    </div>
    <div class="field">
      <label for="incFilterCategory">Kategori</label>
      <select id="incFilterCategory" name="category_id">
        <option value="">Semua kategori</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= $cat['id'] ?>"><?= sanitize($cat['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="button" class="btn btn-sm filter-reset-btn" data-riwayat-reset>Reset Filter</button>
  </div>
</div>

    <div class="table-wrap list-tx-table">
      <table>
        <thead>
          <tr><th>Tanggal</th><th>Kategori</th><th>Keterangan</th><th>Jumlah</th><th></th></tr>
        </thead>
        <tbody id="incomeTableBody" data-riwayat-table></tbody>
      </table>
    </div>

    <div class="transaction-list" id="incomeCardList" data-riwayat-cards></div>

    <div class="empty-state" data-riwayat-empty style="display:none;">Tidak ada pemasukan yang cocok dengan filter.</div>
    <div class="riwayat-loading" data-riwayat-loading style="display:none;">Memuat...</div>
    <div data-riwayat-sentinel style="height:1px;"></div>
  </div>
</div>

<div class="modal-overlay" id="editIncomeModal" data-modal>
  <div class="modal-box">
    <div class="modal-head">
      <h2>Edit Pemasukan</h2>
      <button type="button" class="modal-close" data-modal-close aria-label="Tutup">&times;</button>
    </div>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="action" value="edit_income">
      <input type="hidden" name="income_id" id="editIncomeId">

      <div class="field">
        <label for="editIncomeCategory">Kategori</label>
        <select id="editIncomeCategory" name="category_id" required>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>"><?= sanitize($cat['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label for="editIncomeAmount">Jumlah (Rp)</label>
        <input type="text" inputmode="numeric" id="editIncomeAmount" name="amount" class="rupiah-input" required>
      </div>

      <div class="field">
        <label for="editIncomeDate">Tanggal</label>
        <input type="date" id="editIncomeDate" name="income_date" required>
      </div>

      <div class="field">
        <label for="editIncomeDescription">Keterangan (opsional)</label>
        <textarea id="editIncomeDescription" name="description"></textarea>
      </div>

      <p class="field-hint" style="margin-bottom:16px;">Pembagian ke 3 kantong akan dihitung ulang otomatis sesuai presentase saat ini.</p>

      <div class="form-actions">
        <button type="button" class="btn btn-secondary btn-block" data-modal-close>Batal</button>
        <button type="submit" class="btn btn-primary btn-block">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
