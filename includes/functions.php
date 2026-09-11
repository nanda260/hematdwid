<?php
require_once __DIR__ . '/../config/database.php';

const ICON_EDIT = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4Z"></path></svg>';
const ICON_DELETE = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>';

/* ---------------------------------------------------------
 * Format & sanitasi
 * ------------------------------------------------------- */
function formatRupiah($amount): string
{
    return 'Rp' . number_format((float) $amount, 0, ',', '.');
}

function sanitize(string $value): string
{
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

/* ---------------------------------------------------------
 * CSRF
 * ------------------------------------------------------- */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): bool
{
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

/* ---------------------------------------------------------
 * Flash message (sekali tampil lalu hilang)
 * ------------------------------------------------------- */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/* ---------------------------------------------------------
 * Pengaturan persentase
 * ------------------------------------------------------- */
function getSettings(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT * FROM settings WHERE user_id = ?');
    $stmt->execute([$userId]);
    $settings = $stmt->fetch();

    if (!$settings) {
        // Bentuk fallback jika baris settings belum ada
        $stmt = $pdo->prepare(
            'INSERT INTO settings (user_id, persen_utama, persen_nabung, persen_bebas)
             VALUES (?, 55, 25, 20)'
        );
        $stmt->execute([$userId]);
        return [
            'persen_utama'  => 55,
            'persen_nabung' => 25,
            'persen_bebas'  => 20,
        ];
    }

    return $settings;
}

/* ---------------------------------------------------------
 * Kategori default untuk user baru
 * ------------------------------------------------------- */
function seedDefaultCategories(PDO $pdo, int $userId): void
{
    $pemasukan = ['Uang Saku', 'Gaji Part-time', 'Beasiswa', 'Lainnya'];
    $pengeluaran = [
        ['name' => 'Makan & Minum', 'bucket' => 'utama'],
        ['name' => 'Transportasi', 'bucket' => 'utama'],
        ['name' => 'Kos & Utilitas', 'bucket' => 'utama'],
        ['name' => 'Kebutuhan Kuliah', 'bucket' => 'utama'],
        ['name' => 'Tabungan Darurat', 'bucket' => 'nabung'],
        ['name' => 'Hiburan', 'bucket' => 'bebas'],
        ['name' => 'Jajan', 'bucket' => 'bebas'],
    ];

    $stmtIn = $pdo->prepare(
        'INSERT INTO categories (user_id, type, name, bucket) VALUES (?, "pemasukan", ?, NULL)'
    );
    foreach ($pemasukan as $name) {
        $stmtIn->execute([$userId, $name]);
    }

    $stmtOut = $pdo->prepare(
        'INSERT INTO categories (user_id, type, name, bucket) VALUES (?, "pengeluaran", ?, ?)'
    );
    foreach ($pengeluaran as $cat) {
        $stmtOut->execute([$userId, $cat['name'], $cat['bucket']]);
    }
}

/* ---------------------------------------------------------
 * Saldo per kantong (bucket): total masuk - total keluar
 * ------------------------------------------------------- */
function getBucketBalances(PDO $pdo, int $userId): array
{
    $buckets = ['utama' => 0.0, 'nabung' => 0.0, 'bebas' => 0.0];

    $stmt = $pdo->prepare(
        'SELECT ia.bucket, SUM(ia.amount) AS total
         FROM income_allocations ia
         INNER JOIN incomes i ON i.id = ia.income_id
         WHERE i.user_id = ?
         GROUP BY ia.bucket'
    );
    $stmt->execute([$userId]);
    foreach ($stmt->fetchAll() as $row) {
        $buckets[$row['bucket']] += (float) $row['total'];
    }

    $stmt = $pdo->prepare(
        'SELECT bucket, SUM(amount) AS total FROM expenses WHERE user_id = ? GROUP BY bucket'
    );
    $stmt->execute([$userId]);
    foreach ($stmt->fetchAll() as $row) {
        $buckets[$row['bucket']] -= (float) $row['total'];
    }

    $stmt = $pdo->prepare(
        'SELECT to_bucket, SUM(amount) AS total FROM transfers WHERE user_id = ? GROUP BY to_bucket'
    );
    $stmt->execute([$userId]);
    foreach ($stmt->fetchAll() as $row) {
        $buckets[$row['to_bucket']] += (float) $row['total'];
    }

    $stmt = $pdo->prepare(
        'SELECT from_bucket, SUM(amount) AS total FROM transfers WHERE user_id = ? GROUP BY from_bucket'
    );
    $stmt->execute([$userId]);
    foreach ($stmt->fetchAll() as $row) {
        $buckets[$row['from_bucket']] -= (float) $row['total'];
    }

    return $buckets;
}

/* ---------------------------------------------------------
 * Render baris riwayat transfer antar kantong
 * ------------------------------------------------------- */
function renderTransferRow(array $trf, string $csrf): string
{
    ob_start(); ?>
    <tr>
      <td><?= formatTanggalIndo($trf['transfer_date']) ?></td>
      <td>
        <span class="tag tag-<?= sanitize($trf['from_bucket']) ?>"><?= bucketLabel($trf['from_bucket']) ?></span>
        &rarr;
        <span class="tag tag-<?= sanitize($trf['to_bucket']) ?>"><?= bucketLabel($trf['to_bucket']) ?></span>
      </td>
      <td><?= sanitize($trf['description'] ?: '-') ?></td>
      <td><?= formatRupiah($trf['amount']) ?></td>
      <td>
        <form method="post" data-confirm="Hapus transfer ini? Saldo kantong akan dikembalikan.">
          <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
          <input type="hidden" name="action" value="delete_transfer">
          <input type="hidden" name="transfer_id" value="<?= (int) $trf['id'] ?>">
          <button type="submit" class="btn btn-secondary btn-sm btn-icon" title="Hapus" aria-label="Hapus"><?= ICON_DELETE ?></button>
        </form>
      </td>
    </tr>
    <?php
    return trim(ob_get_clean());
}

function renderTransferCard(array $trf, string $csrf): string
{
    ob_start(); ?>
    <div class="transaction-card">
      <div class="transaction-card-top">
        <span><?= formatTanggalIndo($trf['transfer_date']) ?></span>
        <span><?= formatRupiah($trf['amount']) ?></span>
      </div>
      <div class="transaction-card-mid">
        <span class="tag tag-<?= sanitize($trf['from_bucket']) ?>"><?= bucketLabel($trf['from_bucket']) ?></span>
        &rarr;
        <span class="tag tag-<?= sanitize($trf['to_bucket']) ?>"><?= bucketLabel($trf['to_bucket']) ?></span>
      </div>
      <?php if ($trf['description']): ?>
        <div class="transaction-card-desc"><?= sanitize($trf['description']) ?></div>
      <?php endif; ?>
      <div class="transaction-card-actions">
        <form method="post" data-confirm="Hapus transfer ini? Saldo kantong akan dikembalikan.">
          <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
          <input type="hidden" name="action" value="delete_transfer">
          <input type="hidden" name="transfer_id" value="<?= (int) $trf['id'] ?>">
          <button type="submit" class="btn btn-secondary btn-sm btn-icon" title="Hapus" aria-label="Hapus"><?= ICON_DELETE ?></button>
        </form>
      </div>
    </div>
    <?php
    return trim(ob_get_clean());
}

/* ---------------------------------------------------------
 * Total pemasukan & pengeluaran bulan berjalan
 * ------------------------------------------------------- */
function getMonthlySummary(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(amount),0) AS total FROM incomes
         WHERE user_id = ? AND DATE_FORMAT(income_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')"
    );
    $stmt->execute([$userId]);
    $totalIncome = (float) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(amount),0) AS total FROM expenses
         WHERE user_id = ? AND DATE_FORMAT(expense_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')"
    );
    $stmt->execute([$userId]);
    $totalExpense = (float) $stmt->fetchColumn();

    return ['income' => $totalIncome, 'expense' => $totalExpense];
}

/* ---------------------------------------------------------
 * Ringkasan pengeluaran per kategori bulan berjalan (untuk grafik)
 * ------------------------------------------------------- */
function getExpenseByCategory(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        "SELECT c.name, SUM(e.amount) AS total
         FROM expenses e
         INNER JOIN categories c ON c.id = e.category_id
         WHERE e.user_id = ? AND DATE_FORMAT(e.expense_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
         GROUP BY c.id, c.name
         ORDER BY total DESC"
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/* ---------------------------------------------------------
 * Alokasi & pemakaian per kantong untuk bulan berjalan
 * (dipakai untuk progress bar di dashboard)
 * ------------------------------------------------------- */
function getMonthlyBucketSummary(PDO $pdo, int $userId, ?string $periodStart = null): array
{
    // periodStart berupa datetime (Y-m-d H:i:s) agar transaksi di hari yang sama
    // SEBELUM waktu input uang saku tidak ikut terhitung ke periode baru.
    // Jika tidak diberikan, fallback ke awal bulan kalender berjalan.
    $periodStart = $periodStart ?: date('Y-m-01 00:00:00');

    $buckets = [
        'utama'  => ['allocated' => 0.0, 'spent' => 0.0],
        'nabung' => ['allocated' => 0.0, 'spent' => 0.0],
        'bebas'  => ['allocated' => 0.0, 'spent' => 0.0],
    ];

    // Saldo sisa dari SEBELUM periode ini (carry-over) ikut dihitung sebagai
    // basis alokasi, supaya progress bar tidak salah menganggap sisa saldo lama
    // sebagai "terpakai" begitu ada pemasukan baru.
    $stmt = $pdo->prepare(
        "SELECT ia.bucket, SUM(ia.amount) AS total
         FROM income_allocations ia
         INNER JOIN incomes i ON i.id = ia.income_id
         WHERE i.user_id = ? AND i.created_at < ?
         GROUP BY ia.bucket"
    );
    $stmt->execute([$userId, $periodStart]);
    foreach ($stmt->fetchAll() as $row) {
        $buckets[$row['bucket']]['allocated'] += (float) $row['total'];
    }

    $stmt = $pdo->prepare(
        "SELECT bucket, SUM(amount) AS total FROM expenses
         WHERE user_id = ? AND created_at < ?
         GROUP BY bucket"
    );
    $stmt->execute([$userId, $periodStart]);
    foreach ($stmt->fetchAll() as $row) {
        $buckets[$row['bucket']]['allocated'] -= (float) $row['total'];
    }

    $stmt = $pdo->prepare(
        "SELECT to_bucket, SUM(amount) AS total FROM transfers
         WHERE user_id = ? AND created_at < ?
         GROUP BY to_bucket"
    );
    $stmt->execute([$userId, $periodStart]);
    foreach ($stmt->fetchAll() as $row) {
        $buckets[$row['to_bucket']]['allocated'] += (float) $row['total'];
    }

    $stmt = $pdo->prepare(
        "SELECT from_bucket, SUM(amount) AS total FROM transfers
         WHERE user_id = ? AND created_at < ?
         GROUP BY from_bucket"
    );
    $stmt->execute([$userId, $periodStart]);
    foreach ($stmt->fetchAll() as $row) {
        $buckets[$row['from_bucket']]['allocated'] -= (float) $row['total'];
    }

    // Basis alokasi tidak boleh negatif (kalau kantong sudah minus sebelumnya)
    foreach ($buckets as $key => $val) {
        $buckets[$key]['allocated'] = max(0.0, $val['allocated']);
    }

    $stmt = $pdo->prepare(
        "SELECT ia.bucket, SUM(ia.amount) AS total
         FROM income_allocations ia
         INNER JOIN incomes i ON i.id = ia.income_id
         WHERE i.user_id = ? AND i.created_at >= ?
         GROUP BY ia.bucket"
    );
    $stmt->execute([$userId, $periodStart]);
    foreach ($stmt->fetchAll() as $row) {
        $buckets[$row['bucket']]['allocated'] += (float) $row['total'];
    }

    $stmt = $pdo->prepare(
        "SELECT bucket, SUM(amount) AS total FROM expenses
         WHERE user_id = ? AND created_at >= ?
         GROUP BY bucket"
    );
    $stmt->execute([$userId, $periodStart]);
    foreach ($stmt->fetchAll() as $row) {
        $buckets[$row['bucket']]['spent'] = (float) $row['total'];
    }

    // Transfer masuk menambah alokasi kantong tujuan pada periode ini
    $stmt = $pdo->prepare(
        "SELECT to_bucket, SUM(amount) AS total FROM transfers
         WHERE user_id = ? AND created_at >= ?
         GROUP BY to_bucket"
    );
    $stmt->execute([$userId, $periodStart]);
    foreach ($stmt->fetchAll() as $row) {
        $buckets[$row['to_bucket']]['allocated'] += (float) $row['total'];
    }

    // Transfer keluar dihitung sebagai "terpakai" pada kantong asal periode ini
    $stmt = $pdo->prepare(
        "SELECT from_bucket, SUM(amount) AS total FROM transfers
         WHERE user_id = ? AND created_at >= ?
         GROUP BY from_bucket"
    );
    $stmt->execute([$userId, $periodStart]);
    foreach ($stmt->fetchAll() as $row) {
        $buckets[$row['from_bucket']]['spent'] += (float) $row['total'];
    }

    return $buckets;
}

function bucketLabel(string $bucket): string
{
    return match ($bucket) {
        'utama'  => 'Kebutuhan Utama',
        'nabung' => 'Tabungan',
        'bebas'  => 'Bebas Pakai',
        default  => ucfirst($bucket),
    };
}

/**
 * Format tanggal dengan nama hari dalam Bahasa Indonesia.
 * Contoh output: "Senin, 10 Sep 2026"
 */
function formatTanggalIndo(string $dateString): string {
    if (empty($dateString)) {
        return '-';
    }

    $timestamp = strtotime($dateString);
    if (!$timestamp) {
        return $dateString;
    }

    $namaHari = [
        'Sun' => 'Minggu',
        'Mon' => 'Senin',
        'Tue' => 'Selasa',
        'Wed' => 'Rabu',
        'Thu' => 'Kamis',
        'Fri' => 'Jumat',
        'Sat' => 'Sabtu'
    ];

    $hariEn = date('D', $timestamp);
    $hariId = $namaHari[$hariEn] ?? '';

    return $hariId . ', ' . date('d M Y', $timestamp);
}

/**
 * Mendapatkan data pemasukan uang saku terbaru beserta jumlah hari yang telah berlalu.
 * Perhitungan inklusif: Hari transaksi dihitung sebagai hari ke-1.
 */
function getLastAllowanceInfo(PDO $pdo, int $userId): ?array {
    $stmt = $pdo->prepare("
        SELECT i.income_date, i.amount, i.created_at, c.name AS category_name
        FROM incomes i
        INNER JOIN categories c ON c.id = i.category_id
        WHERE i.user_id = ? 
          AND LOWER(c.name) LIKE '%saku%'
        ORDER BY i.income_date DESC, i.id DESC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $data = $stmt->fetch();

    if (!$data) {
        return null;
    }

    $incomeDate = new DateTime($data['income_date']);
    $today = new DateTime('today');

    // Jika tanggal pemasukan di masa depan (misal salah input), kembalikan 1 hari
    if ($incomeDate > $today) {
        $daysPassed = 1;
    } else {
        // Selisih hari + 1 agar hari transaksi dihitung sebagai hari ke-1
        $daysPassed = $today->diff($incomeDate)->days + 1;
    }

    return [
        'income_date' => $data['income_date'],
        'created_at'  => $data['created_at'],
        'amount'      => $data['amount'],
        'category'    => $data['category_name'],
        'days_passed' => $daysPassed
    ];
}

/* ---------------------------------------------------------
 * Render baris/kartu riwayat transaksi
 * (dipakai bersama oleh halaman & endpoint AJAX riwayat)
 * ------------------------------------------------------- */
function renderExpenseRow(array $exp, string $csrf): string
{
    ob_start(); ?>
    <tr>
      <td><?= formatTanggalIndo($exp['expense_date']) ?></td>
      <td><?= sanitize($exp['category_name']) ?></td>
      <td><span class="tag tag-<?= sanitize($exp['bucket']) ?>"><?= bucketLabel($exp['bucket']) ?></span></td>
      <td><?= sanitize($exp['description'] ?: '-') ?></td>
      <td class="amount-out">− <?= formatRupiah($exp['amount']) ?></td>
      <td>
        <div class="row-actions">
          <button type="button" class="btn btn-secondary btn-sm btn-icon" data-edit-expense
            title="Edit" aria-label="Edit"
            data-id="<?= (int) $exp['id'] ?>"
            data-category-id="<?= (int) $exp['category_id'] ?>"
            data-bucket="<?= sanitize($exp['bucket']) ?>"
            data-amount="<?= (float) $exp['amount'] ?>"
            data-date="<?= sanitize($exp['expense_date']) ?>"
            data-description="<?= sanitize($exp['description'] ?? '') ?>"><?= ICON_EDIT ?></button>
          <form method="post" data-confirm="Hapus pengeluaran ini?">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <input type="hidden" name="action" value="delete_expense">
            <input type="hidden" name="expense_id" value="<?= (int) $exp['id'] ?>">
            <button type="submit" class="btn btn-secondary btn-sm btn-icon" title="Hapus" aria-label="Hapus"><?= ICON_DELETE ?></button>
          </form>
        </div>
      </td>
    </tr>
    <?php
    return trim(ob_get_clean());
}

function renderExpenseCard(array $exp, string $csrf): string
{
    ob_start(); ?>
    <div class="transaction-card">
      <div class="transaction-card-top">
        <span><?= formatTanggalIndo($exp['expense_date']) ?></span>
        <span class="amount-out">− <?= formatRupiah($exp['amount']) ?></span>
      </div>
      <div class="transaction-card-mid">
        <span><?= sanitize($exp['category_name']) ?></span>
        <span class="tag tag-<?= sanitize($exp['bucket']) ?>"><?= bucketLabel($exp['bucket']) ?></span>
      </div>
      <?php if ($exp['description']): ?>
        <div class="transaction-card-desc"><?= sanitize($exp['description']) ?></div>
      <?php endif; ?>
      <div class="transaction-card-actions">
        <button type="button" class="btn btn-secondary btn-sm btn-icon" data-edit-expense
          title="Edit" aria-label="Edit"
          data-id="<?= (int) $exp['id'] ?>"
          data-category-id="<?= (int) $exp['category_id'] ?>"
          data-bucket="<?= sanitize($exp['bucket']) ?>"
          data-amount="<?= (float) $exp['amount'] ?>"
          data-date="<?= sanitize($exp['expense_date']) ?>"
          data-description="<?= sanitize($exp['description'] ?? '') ?>"><?= ICON_EDIT ?></button>
        <form method="post" data-confirm="Hapus pengeluaran ini?">
          <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
          <input type="hidden" name="action" value="delete_expense">
          <input type="hidden" name="expense_id" value="<?= (int) $exp['id'] ?>">
          <button type="submit" class="btn btn-secondary btn-sm btn-icon" title="Hapus" aria-label="Hapus"><?= ICON_DELETE ?></button>
        </form>
      </div>
    </div>
    <?php
    return trim(ob_get_clean());
}

function renderIncomeRow(array $inc, string $csrf): string
{
    ob_start(); ?>
    <tr>
      <td><?= formatTanggalIndo($inc['income_date']) ?></td>
      <td><?= sanitize($inc['category_name']) ?></td>
      <td><?= sanitize($inc['description'] ?: '-') ?></td>
      <td class="amount-in">+ <?= formatRupiah($inc['amount']) ?></td>
      <td>
        <div class="row-actions">
          <button type="button" class="btn btn-secondary btn-sm btn-icon" data-edit-income
            title="Edit" aria-label="Edit"
            data-id="<?= (int) $inc['id'] ?>"
            data-category-id="<?= (int) $inc['category_id'] ?>"
            data-amount="<?= (float) $inc['amount'] ?>"
            data-date="<?= sanitize($inc['income_date']) ?>"
            data-description="<?= sanitize($inc['description'] ?? '') ?>"><?= ICON_EDIT ?></button>
          <form method="post" data-confirm="Hapus pemasukan ini? Pembagian ke 3 kantong juga akan dihapus.">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <input type="hidden" name="action" value="delete_income">
            <input type="hidden" name="income_id" value="<?= (int) $inc['id'] ?>">
            <button type="submit" class="btn btn-secondary btn-sm btn-icon" title="Hapus" aria-label="Hapus"><?= ICON_DELETE ?></button>
          </form>
        </div>
      </td>
    </tr>
    <?php
    return trim(ob_get_clean());
}

function renderIncomeCard(array $inc, string $csrf): string
{
    ob_start(); ?>
    <div class="transaction-card">
      <div class="transaction-card-top">
        <span><?= formatTanggalIndo($inc['income_date']) ?></span>
        <span class="amount-in">+ <?= formatRupiah($inc['amount']) ?></span>
      </div>
      <div class="transaction-card-mid">
        <span><?= sanitize($inc['category_name']) ?></span>
      </div>
      <?php if ($inc['description']): ?>
        <div class="transaction-card-desc"><?= sanitize($inc['description']) ?></div>
      <?php endif; ?>
      <div class="transaction-card-actions">
        <button type="button" class="btn btn-secondary btn-sm btn-icon" data-edit-income
          title="Edit" aria-label="Edit"
          data-id="<?= (int) $inc['id'] ?>"
          data-category-id="<?= (int) $inc['category_id'] ?>"
          data-amount="<?= (float) $inc['amount'] ?>"
          data-date="<?= sanitize($inc['income_date']) ?>"
          data-description="<?= sanitize($inc['description'] ?? '') ?>"><?= ICON_EDIT ?></button>
        <form method="post" data-confirm="Hapus pemasukan ini? Pembagian ke 3 kantong juga akan dihapus.">
          <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
          <input type="hidden" name="action" value="delete_income">
          <input type="hidden" name="income_id" value="<?= (int) $inc['id'] ?>">
          <button type="submit" class="btn btn-secondary btn-sm btn-icon" title="Hapus" aria-label="Hapus"><?= ICON_DELETE ?></button>
        </form>
      </div>
    </div>
    <?php
    return trim(ob_get_clean());
}