document.addEventListener('DOMContentLoaded', function () {
  initSidebarToggle();
  initFlashAutoHide();
  initRupiahInputs();
  initIncomeSplitPreview();
  initExpenseBucketAutofill();
  initPercentTotalCheck();
  initDeleteConfirm();
  initCategoryTypeToggle();
  initServiceWorker();
  initSidebarSwipe();
});
/* ---------------------------------------------------------
 * Registrasi service worker untuk PWA
 * ------------------------------------------------------- */
function initServiceWorker() {
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('sw.js').catch(function (err) {
        console.error('Registrasi service worker gagal:', err);
      });
    });
  }
}

/* ---------------------------------------------------------
 * Input harga bergaya rupiah (pemisah ribuan otomatis),
 * mengirim angka murni ke server saat form disubmit.
 * ------------------------------------------------------- */
function initRupiahInputs() {
  var inputs = document.querySelectorAll('.rupiah-input');
  if (!inputs.length) return;

  function toDigits(str) {
    return (str || '').replace(/\D/g, '');
  }

  function formatDigits(digits) {
    if (!digits) return '';
    return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  }

  inputs.forEach(function (input) {
    // set nilai awal (misal saat validasi gagal & value dikembalikan server)
    var initialDigits = toDigits(input.value);
    if (initialDigits) input.value = formatDigits(initialDigits);

    input.addEventListener('input', function () {
      var caretFromEnd = input.value.length - input.selectionStart;
      var digits = toDigits(input.value);
      input.value = formatDigits(digits);
      var pos = input.value.length - caretFromEnd;
      input.setSelectionRange(pos, pos);
    });

    var form = input.closest('form');
    if (form) {
      form.addEventListener('submit', function () {
        input.value = toDigits(input.value);
      });
    }
  });
}

/* ---------------------------------------------------------
 * Form kategori: sembunyikan pilihan kantong jika jenis = pemasukan
 * ------------------------------------------------------- */
function initCategoryTypeToggle() {
  var typeSelect = document.getElementById('type');
  var bucketField = document.getElementById('bucketField');
  if (!typeSelect || !bucketField) return;

  function sync() {
    bucketField.style.display = typeSelect.value === 'pengeluaran' ? 'block' : 'none';
  }

  typeSelect.addEventListener('change', sync);
  sync();
}

/* ---------------------------------------------------------
 * Sidebar toggle (mobile)
 * ------------------------------------------------------- */
function initSidebarToggle() {
  var toggle = document.getElementById('navToggle');
  var sidebar = document.getElementById('sidebar');
  if (!toggle || !sidebar) return;

  toggle.addEventListener('click', function () {
    var isOpen = sidebar.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  });

  document.addEventListener('click', function (e) {
    if (!sidebar.classList.contains('is-open')) return;
    if (sidebar.contains(e.target) || toggle.contains(e.target)) return;
    sidebar.classList.remove('is-open');
    toggle.setAttribute('aria-expanded', 'false');
  });
}

/* ---------------------------------------------------------
 * Swipe dari tepi kiri layar untuk membuka sidebar (mobile).
 * Hanya aktif dalam zona tepi layar agar tidak bentrok dengan
 * scroll horizontal pada tabel riwayat.
 * ------------------------------------------------------- */
function initSidebarSwipe() {
  var sidebar = document.getElementById('sidebar');
  var toggle = document.getElementById('navToggle');
  if (!sidebar || !toggle) return;

  var EDGE_ZONE_PX = 24;
  var SWIPE_THRESHOLD_PX = 60;
  var MOBILE_BREAKPOINT_PX = 780;

  var startX = 0;
  var startY = 0;
  var tracking = false;

  document.addEventListener('touchstart', function (e) {
    if (window.innerWidth > MOBILE_BREAKPOINT_PX) return;
    if (sidebar.classList.contains('is-open')) return;

    var touch = e.touches[0];
    if (touch.clientX > EDGE_ZONE_PX) return;

    startX = touch.clientX;
    startY = touch.clientY;
    tracking = true;
  }, { passive: true });

  document.addEventListener('touchend', function (e) {
    if (!tracking) return;
    tracking = false;

    var touch = e.changedTouches[0];
    var deltaX = touch.clientX - startX;
    var deltaY = Math.abs(touch.clientY - startY);

    if (deltaX >= SWIPE_THRESHOLD_PX && deltaX > deltaY) {
      sidebar.classList.add('is-open');
      toggle.setAttribute('aria-expanded', 'true');
    }
  }, { passive: true });
}

/* ---------------------------------------------------------
 * Flash message hilang otomatis setelah beberapa detik
 * ------------------------------------------------------- */
function initFlashAutoHide() {  var flash = document.querySelector('.flash');
  if (!flash) return;
  setTimeout(function () {
    flash.style.display = 'none';
  }, 5000);
}

/* ---------------------------------------------------------
 * Form tambah pemasukan: tampilkan pratinjau pembagian 3 kantong
 * secara langsung saat user mengetik nominal.
 * ------------------------------------------------------- */
function initIncomeSplitPreview() {
  var input = document.getElementById('incomeAmount');
  var preview = document.getElementById('splitPreview');
  if (!input || !preview) return;

  var persenUtama = parseFloat(preview.dataset.utama || '55');
  var persenNabung = parseFloat(preview.dataset.nabung || '25');
  var persenBebas = parseFloat(preview.dataset.bebas || '20');

  var elUtama = preview.querySelector('[data-out="utama"]');
  var elNabung = preview.querySelector('[data-out="nabung"]');
  var elBebas = preview.querySelector('[data-out="bebas"]');

  function formatRupiah(n) {
    if (isNaN(n) || n < 0) n = 0;
    return 'Rp' + Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  }

  function update() {
    var amount = parseFloat((input.value || '').replace(/\D/g, '')) || 0;
    elUtama.textContent = formatRupiah(amount * persenUtama / 100);
    elNabung.textContent = formatRupiah(amount * persenNabung / 100);
    elBebas.textContent = formatRupiah(amount * persenBebas / 100);
  }

  input.addEventListener('input', update);
  update();
}

/* ---------------------------------------------------------
 * Form tambah pengeluaran: saat kategori dipilih, otomatis
 * pilihkan kantong sumber dana sesuai kategori (tetap bisa diubah manual).
 * ------------------------------------------------------- */
function initExpenseBucketAutofill() {
  var categorySelect = document.getElementById('expenseCategory');
  var bucketSelect = document.getElementById('expenseBucket');
  if (!categorySelect || !bucketSelect) return;

  categorySelect.addEventListener('change', function () {
    var selected = categorySelect.options[categorySelect.selectedIndex];
    var defaultBucket = selected.getAttribute('data-bucket');
    if (defaultBucket) {
      bucketSelect.value = defaultBucket;
    }
  });
}

/* ---------------------------------------------------------
 * Halaman presentase: cek live total = 100%
 * ------------------------------------------------------- */
function initPercentTotalCheck() {
  var inputs = document.querySelectorAll('.percent-input');
  var totalEl = document.getElementById('percentTotalValue');
  var wrapEl = document.getElementById('percentTotalWrap');
  var submitBtn = document.getElementById('percentSubmit');
  if (!inputs.length || !totalEl) return;

  function recalc() {
    var total = 0;
    inputs.forEach(function (inp) {
      total += parseFloat(inp.value) || 0;
    });
    totalEl.textContent = total.toFixed(2) + '%';

    var isValid = Math.abs(total - 100) < 0.01;
    wrapEl.classList.toggle('is-invalid', !isValid);
    if (submitBtn) submitBtn.disabled = !isValid;
  }

  inputs.forEach(function (inp) {
    inp.addEventListener('input', recalc);
  });
  recalc();
}

/* ---------------------------------------------------------
 * Konfirmasi sebelum menghapus data
 * ------------------------------------------------------- */
function initDeleteConfirm() {
  document.querySelectorAll('[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      var message = form.getAttribute('data-confirm') || 'Yakin ingin menghapus data ini?';
      if (!confirm(message)) {
        e.preventDefault();
      }
    });
  });
}
