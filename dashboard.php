<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$pdo = getConnection();
$userId = currentUserId();

$pageTitle = 'Dashboard';
$activePage = 'dashboard';

$monthly = getMonthlySummary($pdo, $userId);
$bucketBalances = getBucketBalances($pdo, $userId);
$settings = getSettings($pdo, $userId);
$expenseByCategory = getExpenseByCategory($pdo, $userId);
$allowanceInfo = getLastAllowanceInfo($pdo, $userId);

// Progress kantong dihitung sejak WAKTU (bukan sekadar tanggal) Uang Saku
// terakhir dicatat, sehingga transaksi di hari yang sama SEBELUM input
// uang saku baru tidak ikut masuk ke periode baru. Otomatis reset saat
// ada input uang saku baru.
$periodStart = $allowanceInfo['created_at'] ?? date('Y-m-01 00:00:00');
$bucketMonthly = getMonthlyBucketSummary($pdo, $userId, $periodStart);

// Pengeluaran hari ini
$stmt = $pdo->prepare(
    "SELECT e.amount, e.description, e.expense_date AS tgl, c.name AS kategori, e.bucket
     FROM expenses e INNER JOIN categories c ON c.id = e.category_id
     WHERE e.user_id = ? AND e.expense_date = CURDATE()
     ORDER BY e.id DESC"
);
$stmt->execute([$userId]);
$recentTransactions = $stmt->fetchAll();

$todayExpenseTotal = 0;
foreach ($recentTransactions as $t) {
    $todayExpenseTotal += (float) $t['amount'];
}

$maxExpenseCategory = 0;
foreach ($expenseByCategory as $row) {
    $maxExpenseCategory = max($maxExpenseCategory, (float) $row['total']);
}

require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Dashboard</h1>
    <p>Ringkasan keuangan bulan ini.</p>
  </div>
  <div class="form-actions">
    <a href="pemasukan.php" class="btn btn-secondary btn-sm">+ Pemasukan</a>
    <a href="pengeluaran.php" class="btn btn-primary btn-sm">+ Pengeluaran</a>
  </div>
</div>

<div class="total-balance-card">
  <p class="stat-label">Total Semua Kantong</p>
  <p class="stat-value"><?= formatRupiah($bucketBalances['utama'] + $bucketBalances['nabung'] + $bucketBalances['bebas']) ?></p>
</div>

<?php if ($allowanceInfo): ?>
  <div class="card" style="margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
      <div>
        <p class="stat-label" style="margin-bottom: 2px;">
          Pemasukan <?= sanitize($allowanceInfo['category']) ?> Terakhir
        </p>
        <p style="font-size: 0.9rem; color: var(--text-muted, #666); margin: 0;">
          <?= formatTanggalIndo($allowanceInfo['income_date']) ?> · <?= formatRupiah($allowanceInfo['amount']) ?>
        </p>
      </div>
      <div style="text-align: right;">
        <span class="tag tag-utama" style="font-size: 0.95rem; padding: 6px 12px; font-weight: 600;">
          Sudah <?= $allowanceInfo['days_passed'] ?> Hari
        </span>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="stat-grid">
  <?php
  $bucketConfig = [
      'utama'  => ['label' => 'Kebutuhan Utama', 'class' => ''],
      'nabung' => ['label' => 'Tabungan', 'class' => 'bucket-nabung'],
      'bebas'  => ['label' => 'Bebas Pakai', 'class' => 'bucket-bebas'],
  ];
  foreach ($bucketConfig as $key => $cfg):
      $allocated = $bucketMonthly[$key]['allocated'];
      $spent = $bucketMonthly[$key]['spent'];
      $percentUsed = $allocated > 0 ? min(100, ($spent / $allocated) * 100) : 0;
  ?>
    <div class="stat-card <?= $cfg['class'] ?>">
      <p class="stat-label"><?= $cfg['label'] ?> · Saldo</p>
      <p class="stat-value <?= $bucketBalances[$key] < 0 ? 'amount-out' : '' ?>"><?= formatRupiah($bucketBalances[$key]) ?></p>
      <div class="progress-track">
        <div class="progress-fill" style="width: <?= $percentUsed ?>%"></div>
      </div>
      <div class="stat-foot">
        <span>Terpakai periode ini: <?= formatRupiah($spent) ?></span>
        <span><?= number_format($percentUsed, 0) ?>%</span>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="two-col">
  <div class="card">
    <div class="section-title">
      <h2>Pengeluaran Hari Ini</h2>
      <a href="pengeluaran.php">Lihat pengeluaran</a>
    </div>

    <?php if (empty($recentTransactions)): ?>
      <div class="empty-state">Belum ada pengeluaran hari ini.</div>
    <?php else: ?>
      <div class="table-wrap dashboard-tx-table">
        <table>
          <thead>
            <tr><th>Kategori</th><th>Kantong</th><th>Keterangan</th><th>Jumlah</th></tr>
          </thead>
          <tbody>
            <?php foreach ($recentTransactions as $t): ?>
              <tr>
                <td><?= sanitize($t['kategori']) ?></td>
                <td><span class="tag tag-<?= sanitize($t['bucket']) ?>"><?= bucketLabel($t['bucket']) ?></span></td>
                <td><?= sanitize($t['description'] ?: '-') ?></td>
                <td class="amount-out">− <?= formatRupiah($t['amount']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="3" style="text-align:right; font-weight:600;">Total</td>
              <td class="amount-out">− <?= formatRupiah($todayExpenseTotal) ?></td>
            </tr>
          </tfoot>
        </table>
      </div>

      <div class="transaction-list">
        <?php foreach ($recentTransactions as $t): ?>
          <div class="transaction-card">
            <div class="transaction-card-top">
              <span><?= sanitize($t['kategori']) ?></span>
              <span class="amount-out">− <?= formatRupiah($t['amount']) ?></span>
            </div>
            <div class="transaction-card-mid">
              <span class="tag tag-<?= sanitize($t['bucket']) ?>"><?= bucketLabel($t['bucket']) ?></span>
            </div>
            <?php if ($t['description']): ?>
              <div class="transaction-card-desc"><?= sanitize($t['description']) ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <div class="transaction-card" style="font-weight:600;">
          <div class="transaction-card-top" style="margin-bottom:0;">
            <span>Total</span>
            <span class="amount-out">− <?= formatRupiah($todayExpenseTotal) ?></span>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="section-title">
      <h2>Pengeluaran per Kategori</h2>
    </div>

    <?php if (empty($expenseByCategory)): ?>
      <div class="empty-state">Belum ada pengeluaran bulan ini.</div>
    <?php else: ?>
      <?php foreach ($expenseByCategory as $row):
          $width = $maxExpenseCategory > 0 ? ((float) $row['total'] / $maxExpenseCategory) * 100 : 0;
      ?>
        <div style="margin-bottom: 14px;">
          <div class="stat-foot" style="margin-bottom: 4px;">
            <span><?= sanitize($row['name']) ?></span>
            <span><?= formatRupiah($row['total']) ?></span>
          </div>
          <div class="progress-track">
            <div class="progress-fill" style="width: <?= $width ?>%"></div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
