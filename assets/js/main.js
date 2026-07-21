// BrightBlaze shared JavaScript
document.addEventListener('DOMContentLoaded', function () {
  // Auto-dismiss transient flash alerts after 5 seconds.
  document.querySelectorAll('.alert-dismissible').forEach(function (el) {
    setTimeout(function () {
      if (window.bootstrap) {
        bootstrap.Alert.getOrCreateInstance(el).close();
      }
    }, 5000);
  });

  // Confirm dangerous actions: add data-confirm="message" to links/buttons.
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (event) {
      if (!window.confirm(el.getAttribute('data-confirm'))) {
        event.preventDefault();
      }
    });
  });

  // Mobile sidebar (off-canvas) toggle with backdrop + focus handling.
  var toggle = document.getElementById('bbSidebarToggle');
  var sidebar = document.getElementById('bbSidebar');
  var backdrop = document.getElementById('bbBackdrop');

  function openSidebar() {
    if (!sidebar) return;
    sidebar.classList.add('bb-open');
    if (backdrop) backdrop.hidden = false;
    if (toggle) toggle.setAttribute('aria-expanded', 'true');
  }

  function closeSidebar() {
    if (!sidebar) return;
    sidebar.classList.remove('bb-open');
    if (backdrop) backdrop.hidden = true;
    if (toggle) {
      toggle.setAttribute('aria-expanded', 'false');
      toggle.focus();
    }
  }

  if (toggle && sidebar) {
    toggle.addEventListener('click', function () {
      if (sidebar.classList.contains('bb-open')) {
        closeSidebar();
      } else {
        openSidebar();
      }
    });
  }

  if (backdrop) {
    backdrop.addEventListener('click', closeSidebar);
  }

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && sidebar && sidebar.classList.contains('bb-open')) {
      closeSidebar();
    }
  });
});
