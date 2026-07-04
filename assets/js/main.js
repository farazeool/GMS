// BrightBlaze shared JavaScript
document.addEventListener('DOMContentLoaded', function () {
  // Auto-dismiss flash alerts after 4 seconds
  document.querySelectorAll('.alert-dismissible').forEach(function (el) {
    setTimeout(function () {
      if (window.bootstrap) {
        bootstrap.Alert.getOrCreateInstance(el).close();
      }
    }, 4000);
  });

  // Confirm dangerous actions: add data-confirm="message" to links/buttons
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (event) {
      if (!window.confirm(el.getAttribute('data-confirm'))) {
        event.preventDefault();
      }
    });
  });

  // Mobile sidebar toggle
  var toggle = document.getElementById('bbSidebarToggle');
  if (toggle) {
    toggle.addEventListener('click', function () {
      var sidebar = document.querySelector('.bb-sidebar');
      if (sidebar) {
        sidebar.classList.toggle('bb-open');
      }
    });
  }
});
