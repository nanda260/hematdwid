(function () {
  function debounce(fn, delay) {
    let timer;
    return function (...args) {
      clearTimeout(timer);
      timer = setTimeout(() => fn.apply(this, args), delay);
    };
  }

  function initRiwayat(root) {
    const endpoint = root.dataset.endpoint;
    const scope = root.parentElement;
    const tableBody = scope.querySelector('[data-riwayat-table]');
    const cardList = scope.querySelector('[data-riwayat-cards]');
    const emptyState = scope.querySelector('[data-riwayat-empty]');
    const loadingEl = scope.querySelector('[data-riwayat-loading]');
    const sentinel = scope.querySelector('[data-riwayat-sentinel]');
    const resetBtn = root.querySelector('[data-riwayat-reset]');
    const totalEl = scope.querySelector('[data-riwayat-total]');
    const filterInputs = root.querySelectorAll('input[name], select[name]');

    let offset = 0;
    let loading = false;
    let done = false;
    let total = 0;

    function buildQuery() {
      const params = new URLSearchParams();
      filterInputs.forEach((el) => {
        if (el.value) params.set(el.name, el.value);
      });
      params.set('offset', offset);
      return params.toString();
    }

    function reset() {
      offset = 0;
      done = false;
      total = 0;
      tableBody.innerHTML = '';
      cardList.innerHTML = '';
      emptyState.style.display = 'none';
      loadMore();
    }

    function loadMore() {
      if (loading || done) return;
      loading = true;
      loadingEl.style.display = 'block';

      fetch(endpoint + '?' + buildQuery())
        .then((res) => res.json())
        .then((data) => {
          if (!data.success) return;

          data.rows.forEach((row) => {
            tableBody.insertAdjacentHTML('beforeend', row.table_html);
            cardList.insertAdjacentHTML('beforeend', row.card_html);
          });

          total += data.rows.length;
          offset += data.rows.length;

          if (!data.has_more) done = true;
          if (total === 0) emptyState.style.display = 'block';

          // Total nominal dihitung di server (bukan dijumlah dari baris yang sudah
          // dimuat), supaya tetap akurat walau data masih dimuat bertahap (lazy load).
          if (totalEl && data.total_formatted !== undefined) {
            totalEl.textContent = data.total_formatted;
          }        })
        .catch(() => {})
        .finally(() => {
          loading = false;
          loadingEl.style.display = 'none';
        });
    }

    filterInputs.forEach((el) => {
      const isText = el.type === 'text';
      el.addEventListener(isText ? 'input' : 'change', isText ? debounce(reset, 400) : reset);
    });

    if (resetBtn) {
      resetBtn.addEventListener('click', () => {
        filterInputs.forEach((el) => { el.value = ''; });
        reset();
      });
    }

    const toggleBtn = root.querySelector('[data-filter-toggle]');
    if (toggleBtn) {
      toggleBtn.addEventListener('click', () => {
        const isOpen = root.classList.toggle('is-open');
        toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      });
    }

    if ('IntersectionObserver' in window) {
      const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) loadMore();
        });
      }, { rootMargin: '200px' });
      observer.observe(sentinel);
    }

    loadMore();
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-riwayat]').forEach(initRiwayat);
  });
})();