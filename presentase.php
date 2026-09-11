<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$pdo = getConnection();
$userId = currentUserId();
$pageTitle = 'Presentase';
$activePage = 'presentase';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        setFlash('error', 'Sesi tidak valid, silakan coba lagi.');
    } else {
        $utama = (float) ($_POST['persen_utama'] ?? 0);
        $nabung = (float) ($_POST['persen_nabung'] ?? 0);
        $bebas = (float) ($_POST['persen_bebas'] ?? 0);
        $total = $utama + $nabung + $bebas;

        if ($utama < 0 || $nabung < 0 || $bebas < 0) {
            setFlash('error', 'Presentase tidak boleh bernilai negatif.');
        } elseif (abs($total - 100) > 0.01) {
            setFlash('error', 'Total presentase harus tepat 100%. Saat ini: ' . number_format($total, 2) . '%.');
        } else {
            $stmt = $pdo->prepare(
                'UPDATE settings SET persen_utama = ?, persen_nabung = ?, persen_bebas = ? WHERE user_id = ?'
            );
            $stmt->execute([$utama, $nabung, $bebas, $userId]);
            setFlash('success', 'Presentase pembagian berhasil diperbarui.');
        }
    }
    header('Location: presentase.php');
    exit;
}

$settings = getSettings($pdo, $userId);

require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Presentase Pembagian</h1>
    <p>Atur porsi setiap pemasukan baru dibagi ke 3 kantong. Total harus 100%.</p>
  </div>
</div>

<div class="form-grid">
  <div class="card">
    <h2 style="margin-bottom:16px;">Ubah Presentase</h2>

    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

      <div class="percent-inputs">
        <div class="field" style="margin-bottom:0;">
          <label for="persen_utama">Kebutuhan Utama (%)</label>
          <input type="number" id="persen_utama" name="persen_utama" class="percent-input"
                 min="0" max="100" step="0.01" required value="<?= $settings['persen_utama'] ?>">
        </div>
        <div class="field" style="margin-bottom:0;">
          <label for="persen_nabung">Tabungan (%)</label>
          <input type="number" id="persen_nabung" name="persen_nabung" class="percent-input"
                 min="0" max="100" step="0.01" required value="<?= $settings['persen_nabung'] ?>">
        </div>
        <div class="field" style="margin-bottom:0;">
          <label for="persen_bebas">Bebas Pakai (%)</label>
          <input type="number" id="persen_bebas" name="persen_bebas" class="percent-input"
                 min="0" max="100" step="0.01" required value="<?= $settings['persen_bebas'] ?>">
        </div>
      </div>

      <p class="percent-total" id="percentTotalWrap">
        Total saat ini: <strong id="percentTotalValue">100%</strong> - harus tepat 100% agar bisa disimpan.
      </p>

      <button type="submit" class="btn btn-primary" id="percentSubmit">Simpan Presentase</button>
    </form>
  </div>

  <div class="card">
    <h2 style="margin-bottom:10px;">Kenapa harus 100%?</h2>
    <p>Presentase ini menentukan bagaimana setiap pemasukan baru langsung dipecah ke tiga kantong: <strong>Kebutuhan Utama</strong> untuk kos, makan, dan kuliah; <strong>Tabungan</strong>; dan <strong>Bebas Pakai</strong> untuk kebutuhan fleksibel.</p>
    <p>Perubahan presentase hanya berlaku untuk pemasukan yang dicatat setelah perubahan disimpan - transaksi lama tidak dihitung ulang.</p>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
