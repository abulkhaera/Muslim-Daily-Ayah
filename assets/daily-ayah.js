(function () {
  'use strict';

  function focusable(root) {
    return Array.prototype.slice.call(
      root.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])')
    ).filter(function (el) { return !el.disabled && el.offsetParent !== null; });
  }

  function openModal(card) {
    var modal = document.getElementById(card.getAttribute('aria-controls'));
    if (!modal) return;

    modal.hidden = false;
    modal.dataset.lastFocus = ''; // marker, actual element kept in closure below
    document.documentElement.style.overflow = 'hidden';

    var items = focusable(modal.querySelector('.mda__modal-panel'));
    if (items.length) items[0].focus();

    function trap(e) {
      if (e.key === 'Escape') { close(); return; }
      if (e.key !== 'Tab') return;
      var els = focusable(modal.querySelector('.mda__modal-panel'));
      if (!els.length) return;
      var first = els[0], last = els[els.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }

    function close() {
      modal.hidden = true;
      document.documentElement.style.overflow = '';
      modal.removeEventListener('keydown', trap);
      modal.querySelectorAll('[data-mda-close]').forEach(function (el) {
        el.removeEventListener('click', close);
      });
      card.focus();
    }

    modal.addEventListener('keydown', trap);
    modal.querySelectorAll('[data-mda-close]').forEach(function (el) {
      el.addEventListener('click', close);
    });
  }

  document.addEventListener('click', function (e) {
    var card = e.target.closest('[data-mda-open]');
    if (card) openModal(card);
  });
})();
