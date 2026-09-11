(function () {
  var overlay = null;
  var pendingCount = 0;
  var showTimer = null;

  function getOverlay() {
    if (!overlay) overlay = document.getElementById('globalLoadingOverlay');
    return overlay;
  }

  function showLoading() {
    pendingCount++;
    if (pendingCount > 1) return;

    showTimer = setTimeout(function () {
      var el = getOverlay();
      if (el) el.classList.add('is-visible');
    }, 120);
  }

  function hideLoading() {
    pendingCount = Math.max(0, pendingCount - 1);
    if (pendingCount > 0) return;

    clearTimeout(showTimer);
    var el = getOverlay();
    if (el) el.classList.remove('is-visible');
  }

  window.showLoading = showLoading;
  window.hideLoading = hideLoading;

  /* Auto-loading untuk semua panggilan fetch() */
  var originalFetch = window.fetch;
  window.fetch = function () {
    showLoading();
    return originalFetch.apply(this, arguments).finally(hideLoading);
  };

  /* Auto-loading saat klik link navigasi internal */
  document.addEventListener('click', function (e) {
    var link = e.target.closest('a[href]');
    if (!link) return;
    if (link.hasAttribute('data-no-loading')) return;
    if (link.target === '_blank') return;
    if (link.hasAttribute('download')) return;

    var href = link.getAttribute('href');
    if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) return;

    try {
      var url = new URL(href, window.location.href);
      if (url.origin !== window.location.origin) return;
    } catch (err) {
      return;
    }

    showLoading();
  });

  /* Auto-loading saat submit form (kecuali diberi data-no-loading) */
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (form.hasAttribute('data-no-loading')) return;
    showLoading();
  });

  /* Reset overlay saat halaman dipulihkan dari BFCache (tombol back) */
  window.addEventListener('pageshow', function () {
    pendingCount = 0;
    clearTimeout(showTimer);
    var el = getOverlay();
    if (el) el.classList.remove('is-visible');
  });
})();