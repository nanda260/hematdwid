<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$pdo = getConnection();
$userId = currentUserId();
$pageTitle = 'Transfer Kantong';
$activePage = 'transfer';

$validBuckets = ['utama', 'nabung', 'bebas'];

// ---------------------------------------------------------
// Proses tambah transfer
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_transfer') {
    if (!verifyCsrf()) {
        setFlash('error', 'Sesi tidak valid, silakan coba lagi.');
    } else {
        $fromBucket = $_POST['from_bucket'] ?? '';
        $toBucket = $_POST['to_bucket'] ?? '';
        $amount = (float) ($_POST['amount'] ?? 0);
        $description = mb_convert_case(trim($_POST['description'] ?? ''), MB_CASE_TITLE, 'UTF-8');
        $transferDate = $_POST['transfer_date'] ?? date('Y-m-d');

        if (!in_array($fromBucket, $validBuckets, true) || !in_array($toBucket, $validBuckets, true)) {
            setFlash('error', 'Kantong asal/tujuan tidak valid.');
        } elseif ($fromBucket === $toBucket) {
            setFlash('error', 'Kantong asal dan tujuan tidak boleh sama.');
        } elseif ($amount <= 0) {
            setFlash('error', 'Jumlah transfer harus lebih dari 0.');
        } else {
            $balances = getBucketBalances($pdo, $userId);
            if ($balances[$fromBucket] < $amount) {
                setFlash('error', 'Saldo kantong asal tidak mencukupi.');
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO transfers (user_id, from_bucket, to_bucket, amount, description, transfer_date)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$userId, $fromBucket, $toBucket, $amount, $description ?: null, $transferDate]);
                setFlash('success', 'Transfer antar kantong berhasil dicatat.');
            }
        }
    }
    header('Location: transfer.php');
    exit;
}

// ---------------------------------------------------------
// Hapus transfer
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_transfer') {
    if (verifyCsrf()) {
        $transferId = (int) ($_POST['transfer_id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM transfers WHERE id = ? AND user_id = ?');
        $stmt->execute([$transferId, $userId]);
        setFlash('success', 'Transfer dihapus, saldo kantong dikembalikan.');
    }
    header('Location: transfer.php');
    exit;
}

// ---------------------------------------------------------
// Data untuk tampilan
// ---------------------------------------------------------
$bucketBalances = getBucketBalances($pdo, $userId);
$csrf = csrfToken();

$stmt = $pdo->prepare(
    'SELECT * FROM transfers WHERE user_id = ? ORDER BY transfer_date DESC, id DESC LIMIT 50'
);
$stmt->execute([$userId]);
$transfers = $stmt->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Transfer Kantong</h1>
    <p>Pindahkan saldo antar kantong tanpa mencatatnya sebagai pengeluaran.</p>
  </div>
</div>

<div class="form-grid">
  <div class="card">
    <h2 style="margin-bottom:16px;">Buat Transfer</h2>

    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
      <input type="hidden" name="action" value="add_transfer">

      <div class="field">
        <label for="from_bucket">Dari Kantong</label>
        <select id="from_bucket" name="from_bucket" required>
          <option value="utama">Kebutuhan Utama (saldo: <?= formatRupiah($bucketBalances['utama']) ?>)</option>
          <option value="nabung">Tabungan (saldo: <?= formatRupiah($bucketBalances['nabung']) ?>)</option>
          <option value="bebas">Bebas Pakai (saldo: <?= formatRupiah($bucketBalances['bebas']) ?>)</option>
        </select>
      </div>

      <div class="field">
        <label for="to_bucket">Ke Kantong</label>
        <select id="to_bucket" name="to_bucket" required>
          <option value="nabung">Tabungan</option>
          <option value="utama">Kebutuhan Utama</option>
          <option value="bebas">Bebas Pakai</option>
        </select>
      </div>

      <div class="field">
        <label for="transferAmount">Jumlah (Rp)</label>
        <input type="text" inputmode="numeric" id="transferAmount" name="amount" class="rupiah-input" required placeholder="50.000">
      </div>

      <div class="field">
        <label for="transfer_date">Tanggal</label>
        <input type="date" id="transfer_date" name="transfer_date" value="<?= date('Y-m-d') ?>" required>
      </div>

      <div class="field">
        <label for="description">Keterangan (opsional)</label>
        <textarea id="description" name="description" placeholder="Contoh: Pindah sisa uang bebas ke tabungan"></textarea>
      </div>

      <button type="submit" class="btn btn-primary btn-block">Proses Transfer</button>
    </form>
  </div>

  <div class="card">
    <h2 style="margin-bottom:16px;">Riwayat Transfer</h2>

    <?php if (empty($transfers)): ?>
      <div class="empty-state">Belum ada transfer antar kantong.</div>
    <?php else: ?>
      <div class="table-wrap list-tx-table">
        <table>
          <thead>
            <tr><th>Tanggal</th><th>Kantong</th><th>Keterangan</th><th>Jumlah</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($transfers as $trf): ?>
              <?= renderTransferRow($trf, $csrf) ?>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="transaction-list">
        <?php foreach ($transfers as $trf): ?>
          <?= renderTransferCard($trf, $csrf) ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
  // Pastikan kantong tujuan tidak bisa sama dengan kantong asal
  (function () {
    var fromSel = document.getElementById('from_bucket');
    var toSel = document.getElementById('to_bucket');

    function syncOptions() {
      Array.from(toSel.options).forEach(function (opt) {
        opt.disabled = (opt.value === fromSel.value);
      });
      if (toSel.value === fromSel.value) {
        var alt = Array.from(toSel.options).find(function (opt) { return opt.value !== fromSel.value; });
        if (alt) toSel.value = alt.value;
      }
    }
    fromSel.addEventListener('change', syncOptions);
    syncOptions();
  })();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>