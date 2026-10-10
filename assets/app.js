// Zangarry POS — tiny enhancements: sidebar toggle + confirm dialogs.
document.addEventListener('DOMContentLoaded', function () {
  var btn = document.getElementById('menuBtn');
  if (btn) {
    btn.addEventListener('click', function () {
      document.body.classList.toggle('nav-open');
    });
  }
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('submit', function (ev) {
      if (!window.confirm(el.getAttribute('data-confirm'))) ev.preventDefault();
    });
    // data-confirm on a plain button (not in a form)
    if (el.tagName === 'BUTTON') {
      el.addEventListener('click', function (ev) {
        if (!window.confirm(el.getAttribute('data-confirm'))) ev.preventDefault();
      });
    }
  });
});
