(function () {
  function formatRupiahInput(value) {
    var number = Math.round(Number(value) || 0);
    return number.toLocaleString('id-ID');
  }

  function openModal(modal) {
    modal.classList.add('is-open');
  }

  function closeModal(modal) {
    modal.classList.remove('is-open');
  }

  document.addEventListener('click', function (e) {
    var editExpenseBtn = e.target.closest('[data-edit-expense]');
    if (editExpenseBtn) {
      var modal = document.getElementById('editExpenseModal');
      if (!modal) return;
      modal.querySelector('#editExpenseId').value = editExpenseBtn.dataset.id;
      modal.querySelector('#editExpenseCategory').value = editExpenseBtn.dataset.categoryId;
      modal.querySelector('#editExpenseBucket').value = editExpenseBtn.dataset.bucket;
      modal.querySelector('#editExpenseAmount').value = formatRupiahInput(editExpenseBtn.dataset.amount);
      modal.querySelector('#editExpenseDate').value = editExpenseBtn.dataset.date;
      modal.querySelector('#editExpenseDescription').value = editExpenseBtn.dataset.description || '';
      openModal(modal);
      return;
    }

    var editIncomeBtn = e.target.closest('[data-edit-income]');
    if (editIncomeBtn) {
      var modal2 = document.getElementById('editIncomeModal');
      if (!modal2) return;
      modal2.querySelector('#editIncomeId').value = editIncomeBtn.dataset.id;
      modal2.querySelector('#editIncomeCategory').value = editIncomeBtn.dataset.categoryId;
      modal2.querySelector('#editIncomeAmount').value = formatRupiahInput(editIncomeBtn.dataset.amount);
      modal2.querySelector('#editIncomeDate').value = editIncomeBtn.dataset.date;
      modal2.querySelector('#editIncomeDescription').value = editIncomeBtn.dataset.description || '';
      openModal(modal2);
      return;
    }

    var closeBtn = e.target.closest('[data-modal-close]');
    if (closeBtn) {
      var modalToClose = closeBtn.closest('[data-modal]');
      if (modalToClose) closeModal(modalToClose);
      return;
    }

    if (e.target.classList && e.target.classList.contains('modal-overlay')) {
      closeModal(e.target);
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      document.querySelectorAll('[data-modal].is-open').forEach(closeModal);
    }
  });
})();