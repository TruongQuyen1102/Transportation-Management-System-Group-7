/* ═══════════════════════════════════════════════════════════════════════════
   app.js  —  LogiTrack Pro client-side interactions
   ═══════════════════════════════════════════════════════════════════════════ */

document.addEventListener('DOMContentLoaded', () => {

  // ─── Sidebar Toggle ──────────────────────────────────────────────────────
  const sidebar      = document.querySelector('.sidebar');
  const mainContent  = document.querySelector('.main-content');
  const toggleBtn    = document.getElementById('sidebarToggle');

  if (toggleBtn && sidebar) {
    toggleBtn.addEventListener('click', () => {
      sidebar.classList.toggle('collapsed');
      mainContent?.classList.toggle('sidebar-collapsed');
      localStorage.setItem('sidebar-collapsed', sidebar.classList.contains('collapsed'));
    });
    // Restore state
    if (localStorage.getItem('sidebar-collapsed') === 'true') {
      sidebar.classList.add('collapsed');
      mainContent?.classList.add('sidebar-collapsed');
    }
  }

  // ─── Avatar Dropdown ─────────────────────────────────────────────────────
  const avatarBtn = document.getElementById('avatarBtn');
  const avatarMenu = document.getElementById('avatarMenu');
  if (avatarBtn && avatarMenu) {
    avatarBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      avatarMenu.classList.toggle('open');
    });
    document.addEventListener('click', () => avatarMenu.classList.remove('open'));
  }

  // ─── Generic Dropdown Menus ───────────────────────────────────────────────
  document.querySelectorAll('[data-dropdown-toggle]').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const target = document.getElementById(btn.dataset.dropdownToggle);
      if (!target) return;
      const isOpen = target.classList.contains('open');
      document.querySelectorAll('.dropdown-menu.open, .action-dropdown.open').forEach(m => m.classList.remove('open'));
      if (!isOpen) target.classList.add('open');
    });
  });
  document.addEventListener('click', () => {
    document.querySelectorAll('.dropdown-menu.open, .action-dropdown.open').forEach(m => m.classList.remove('open'));
  });

  // ─── Modal System ─────────────────────────────────────────────────────────
  // Open: <button data-modal-open="modalId">
  document.querySelectorAll('[data-modal-open]').forEach(btn => {
    btn.addEventListener('click', () => {
      const modal = document.getElementById(btn.dataset.modalOpen);
      modal?.classList.add('open');
    });
  });
  // Close: .modal-close buttons, clicking overlay
  document.querySelectorAll('.modal-close, [data-modal-close]').forEach(btn => {
    btn.addEventListener('click', () => {
      btn.closest('.modal-overlay')?.classList.remove('open');
    });
  });
  document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) overlay.classList.remove('open');
    });
  });

  // ─── Tabs ─────────────────────────────────────────────────────────────────
  document.querySelectorAll('[data-tab]').forEach(btn => {
    btn.addEventListener('click', () => {
      const group = btn.closest('[data-tab-group]') || btn.closest('.tabs')?.parentElement;
      const target = btn.dataset.tab;
      // Deactivate all in this group
      group?.querySelectorAll('[data-tab]').forEach(b => b.classList.remove('active'));
      group?.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
      btn.classList.add('active');
      group?.querySelector('#' + target)?.classList.add('active');
    });
  });

  // ─── Toast Notifications ──────────────────────────────────────────────────
  window.showToast = (title, msg = '', type = 'default') => {
    const icons = { default: '📦', success: '✅', error: '❌', info: 'ℹ️' };
    const container = document.getElementById('toastContainer') || (() => {
      const c = document.createElement('div');
      c.id = 'toastContainer';
      c.className = 'toast-container';
      document.body.appendChild(c);
      return c;
    })();
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
      <span class="toast-icon">${icons[type] || icons.default}</span>
      <div class="toast-body">
        <div class="toast-title">${title}</div>
        ${msg ? `<div class="toast-msg">${msg}</div>` : ''}
      </div>
      <span class="toast-close" onclick="this.parentElement.remove()">✕</span>`;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
  };

  // ─── Form Submit Feedback ─────────────────────────────────────────────────
  document.querySelectorAll('form[data-feedback]').forEach(form => {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const msg = form.dataset.feedback || 'Changes saved successfully.';
      showToast('Saved', msg, 'success');
      form.closest('.modal-overlay')?.classList.remove('open');
    });
  });

  // ─── Confirm Delete ───────────────────────────────────────────────────────
  document.querySelectorAll('[data-confirm]').forEach(btn => {
    btn.addEventListener('click', (e) => {
      if (!confirm(btn.dataset.confirm || 'Are you sure?')) e.preventDefault();
    });
  });

  // ─── Upload Zone ──────────────────────────────────────────────────────────
  document.querySelectorAll('.upload-zone').forEach(zone => {
    zone.addEventListener('dragover', (e) => { e.preventDefault(); zone.classList.add('drag-over'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
    zone.addEventListener('drop', (e) => {
      e.preventDefault();
      zone.classList.remove('drag-over');
      const files = e.dataTransfer.files;
      if (files.length > 0) {
        zone.querySelector('.upload-title').textContent = `File selected: ${files[0].name}`;
        showToast('File Selected', files[0].name, 'info');
      }
    });
    zone.addEventListener('click', () => {
      const input = zone.querySelector('input[type=file]') || (() => {
        const i = document.createElement('input');
        i.type = 'file'; i.accept = '.csv,.xlsx'; i.style.display = 'none';
        zone.appendChild(i);
        i.addEventListener('change', () => {
          if (i.files.length > 0) {
            zone.querySelector('.upload-title').textContent = `File selected: ${i.files[0].name}`;
            showToast('File Selected', i.files[0].name, 'info');
          }
        });
        return i;
      })();
      input.click();
    });
  });

  // ─── Inline Search Table Filter ───────────────────────────────────────────
  document.querySelectorAll('[data-table-search]').forEach(input => {
    const tableId = input.dataset.tableSearch;
    const table   = document.getElementById(tableId);
    if (!table) return;
    input.addEventListener('input', () => {
      const q = input.value.toLowerCase();
      table.querySelectorAll('tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
      });
    });
  });

  // ─── Login Role Selector ──────────────────────────────────────────────────
  document.querySelectorAll('.role-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.role-btn').forEach(b => b.classList.remove('selected'));
      btn.classList.add('selected');
      const roleInput = document.getElementById('selectedRole');
      if (roleInput) roleInput.value = btn.dataset.role;
      // Pre-fill demo credentials
      const demos = {
        admin:       { user: 'admin',       pw: 'admin123' },
        manager:     { user: 'manager',     pw: 'manager123' },
        accountant:  { user: 'accountant',  pw: 'accountant123' },
        operations:  { user: 'ops',         pw: 'ops123' },
      };
      const d = demos[btn.dataset.role];
      if (d) {
        const uf = document.getElementById('username');
        const pf = document.getElementById('password');
        if (uf) uf.value = d.user;
        if (pf) pf.value = d.pw;
      }
    });
  });

  // ─── Password Toggle ──────────────────────────────────────────────────────
  document.querySelectorAll('.toggle-pw').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = btn.closest('.input-icon-wrap').querySelector('input');
      if (input.type === 'password') {
        input.type = 'text'; btn.textContent = '🙈';
      } else {
        input.type = 'password'; btn.textContent = '👁';
      }
    });
  });

  // ─── Switch Toggles ───────────────────────────────────────────────────────
  document.querySelectorAll('.switch input').forEach(sw => {
    sw.addEventListener('change', () => {
      const label = sw.closest('.switch')?.querySelector('.switch-label');
      if (label) label.textContent = sw.checked ? 'Active' : 'Inactive';
    });
  });

  // ─── Chart.js Defaults ───────────────────────────────────────────────────
  if (window.Chart) {
    Chart.defaults.font.family = "'Montserrat', sans-serif";
    Chart.defaults.font.size   = 12;
    Chart.defaults.color       = '#74787A';
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.padding = 16;
    Chart.defaults.plugins.tooltip.padding = 12;
    Chart.defaults.plugins.tooltip.cornerRadius = 8;
    Chart.defaults.plugins.tooltip.backgroundColor = '#081e30';
    Chart.defaults.plugins.tooltip.titleFont = { weight: '700', size: 13 };
    Chart.defaults.plugins.tooltip.bodyFont  = { size: 12 };
  }

});
