(function () {
  'use strict';

  var form = document.getElementById('admin-batch-form');
  var selectAll = document.getElementById('admin-select-all');
  var approveBtn = document.getElementById('admin-batch-approve');
  var deleteBtn = document.getElementById('admin-batch-delete');
  if (!form || !selectAll || !approveBtn || !deleteBtn) return;

  function itemCheckboxes() {
    return Array.prototype.slice.call(document.querySelectorAll('.admin-item-checkbox'));
  }

  function selectedCount() {
    return itemCheckboxes().filter(function (box) {
      return box.checked;
    }).length;
  }

  function syncToolbar() {
    var boxes = itemCheckboxes();
    var selected = selectedCount();
    var total = boxes.length;
    approveBtn.disabled = selected === 0;
    deleteBtn.disabled = selected === 0;
    selectAll.checked = total > 0 && selected === total;
    selectAll.indeterminate = selected > 0 && selected < total;
  }

  selectAll.addEventListener('change', function () {
    var on = selectAll.checked;
    itemCheckboxes().forEach(function (box) {
      box.checked = on;
    });
    syncToolbar();
  });

  document.addEventListener('change', function (e) {
    if (e.target && e.target.classList && e.target.classList.contains('admin-item-checkbox')) {
      syncToolbar();
    }
  });

  form.addEventListener('submit', function (e) {
    if (selectedCount() === 0) {
      e.preventDefault();
      return;
    }
    var submitter = e.submitter;
    if (submitter && submitter.getAttribute('data-confirm')) {
      if (!window.confirm(submitter.getAttribute('data-confirm'))) {
        e.preventDefault();
      }
    }
  });

  syncToolbar();
})();
