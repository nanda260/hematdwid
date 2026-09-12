<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$pdo = getConnection();
$userId = currentUserId();
$pageTitle = 'Hapus Data';
$activePage = 'hapus-data';

const KATA_KONFIRMASI = 'HAPUS';

// ---------------------------------------------------------
// Proses hapus data
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_data') {
    if (!verifyCsrf()) {
        setFlash('error', 'Sesi tidak valid, silakan coba lagi.');
        header('Location: hapus-data.php');
        exit;
    }

    $mode = $_POST['mode'] ?? '';
    $targets = $_POST['targets'] ?? [];
    $dateFrom = $_POST['date_from'] ?? '';
    $dateTo = $_POST['date_to'] ?? '';
    $confirmText = trim($_POST['confirm_text'] ?? '');

    $validTargets = ['pemasukan', 'pengeluaran', 'transfer'];
    $targets = array_values(array_intersect($targets, $validTargets));

    $isRangeValid = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) && $dateFrom <= $dateTo;

    if ($confirmText !== KATA_KONFIRMASI) {
        setFlash('error', 'Ketik "' . KATA_KONFIRMASI . '" untuk konfirmasi penghapusan.');
    } elseif (empty($targets)) {
        setFlash('error', 'Pilih minimal satu jenis data yang ingin dihapus.');
    } elseif ($mode === 'range' && !$isRangeValid) {
        setFlash('error', 'Rentang tanggal tidak valid.');
    } elseif (!in_array($mode, ['all', 'range'], true)) {
        setFlash('error', 'Mode penghapusan tidak valid.');
    } else {
        $pdo->beginTransaction();
        try {
            // Klausa WHERE dibangun dinamis: sama untuk mode "semua" dan "rentang",
            // hanya beda ada-tidaknya syarat tanggal.
            if (in_array('pemasukan', $targets, true)) {
                $whereIncome = 'user_id = ?';
                $paramsIncome = [$userId];
                if ($mode === 'range') {
                    $whereIncome .= ' AND income_date BETWEEN ? AND ?';
                    $paramsIncome[] = $dateFrom;
                    $paramsIncome[] = $dateTo;
                }
                $pdo->prepare("DELETE FROM income_allocations WHERE income_id IN (SELECT id FROM incomes WHERE $whereIncome)")
                    ->execute($paramsIncome);
                $pdo->prepare("DELETE FROM incomes WHERE $whereIncome")->execute($paramsIncome);
            }

            if (in_array('pengeluaran', $targets, true)) {
                $whereExpense = 'user_id = ?';
                $paramsExpense = [$userId];
                if ($mode === 'range') {
                    $whereExpense .= ' AND expense_date BETWEEN ? AND ?';
                    $paramsExpense[] = $dateFrom;
                    $paramsExpense[] = $dateTo;
                }
                $pdo->prepare("DELETE FROM expenses WHERE $whereExpense")->execute($paramsExpense);
            }

            if (in_array('transfer', $targets, true)) {
                $whereTransfer = 'user_id = ?';
                $paramsTransfer = [$userId];
                if ($mode === 'range') {
                    $whereTransfer .= ' AND transfer_date BETWEEN ? AND ?';
                    $paramsTransfer[] = $dateFrom;
                    $paramsTransfer[] = $dateTo;
                }
                $pdo->prepare("DELETE FROM transfers WHERE $whereTransfer")->execute($paramsTransfer);
            }

            $pdo->commit();
            setFlash('success', 'Data berhasil dihapus.');
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('error', 'Gagal menghapus data.');
        }
    }

    header('Location: hapus-data.php');
    exit;
}

// ---------------------------------------------------------
// Ringkasan jumlah data tersimpan (untuk ditampilkan sebelum hapus)
// ---------------------------------------------------------
$stmt = $pdo->prepare('SELECT COUNT(*) FROM incomes WHERE user_id = ?');
$stmt->execute([$userId]);
$countIncome = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM expenses WHERE user_id = ?');
$stmt->execute([$userId]);
$countExpense = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM transfers WHERE user_id = ?');
$stmt->execute([$userId]);
$countTransfer = (int) $stmt->fetchColumn();

require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Hapus Data</h1>
    <p>Hapus riwayat transaksi secara permanen. Kategori dan pengaturan presentase tidak terpengaruh.</p>
  </div>
</div>

<div class="card danger-zone">
  <h2 style="margin-bottom:16px;">Data Tersimpan Saat Ini</h2>
  <div class="data-summary">
    <div class="data-summary-item">
      <span>Pemasukan</span>
      <strong><?= $countIncome ?> data</strong>
    </div>
    <div class="data-summary-item">
      <span>Pengeluaran</span>
      <strong><?= $countExpense ?> data</strong>
    </div>
    <div class="data-summary-item">
      <span>Transfer</span>
      <strong><?= $countTransfer ?> data</strong>
    </div>
  </div>
</div>

<div class="card danger-zone" style="margin-top:20px;">
  <h2 style="margin-bottom:16px;">Hapus Riwayat Transaksi</h2>
  <div class="form-error">Tindakan ini tidak bisa dibatalkan. Pastikan kamu sudah yakin sebelum melanjutkan.</div>

  <form method="post" id="hapusDataForm">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <input type="hidden" name="action" value="delete_data">

    <div class="field">
      <label>Jenis Data</label>
      <div class="choice-group">
        <label class="choice-item"><input type="checkbox" name="targets[]" value="pemasukan" checked> Pemasukan</label>
        <label class="choice-item"><input type="checkbox" name="targets[]" value="pengeluaran" checked> Pengeluaran</label>
        <label class="choice-item"><input type="checkbox" name="targets[]" value="transfer" checked> Transfer Kantong</label>
      </div>
    </div>

    <div class="field">
      <label>Cakupan Penghapusan</label>
      <div class="choice-group">
        <label class="choice-item"><input type="radio" name="mode" value="all" checked id="modeAll"> Hapus Semua Data</label>
        <label class="choice-item"><input type="radio" name="mode" value="range" id="modeRange"> Hapus Berdasarkan Rentang Tanggal</label>
      </div>
    </div>

    <div class="date-range-fields" id="rangeFields" style="display:none;">
      <div class="field">
        <label for="date_from">Dari Tanggal</label>
        <input type="date" id="date_from" name="date_from">
      </div>
      <div class="field">
        <label for="date_to">Sampai Tanggal</label>
        <input type="date" id="date_to" name="date_to">
      </div>
    </div>

    <div class="field">
      <label for="confirm_text">Ketik "<?= KATA_KONFIRMASI ?>" untuk konfirmasi</label>
      <input type="text" id="confirm_text" name="confirm_text" autocomplete="off" required placeholder="<?= KATA_KONFIRMASI ?>">
    </div>

    <button type="submit" class="btn btn-danger btn-block">Hapus Data Permanen</button>
  </form>
</div>

<script>
  (function () {
    var modeAll = document.getElementById('modeAll');
    var modeRange = document.getElementById('modeRange');
    var rangeFields = document.getElementById('rangeFields');
    var dateFrom = document.getElementById('date_from');
    var dateTo = document.getElementById('date_to');

    function syncRangeVisibility() {
      var isRange = modeRange.checked;
      rangeFields.style.display = isRange ? 'grid' : 'none';
      dateFrom.required = isRange;
      dateTo.required = isRange;
    }

    modeAll.addEventListener('change', syncRangeVisibility);
    modeRange.addEventListener('change', syncRangeVisibility);
    syncRangeVisibility();

    document.getElementById('hapusDataForm').addEventListener('submit', function (e) {
      if (!confirm('Yakin ingin menghapus data ini secara permanen?')) {
        e.preventDefault();
      }
    });
  })();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>