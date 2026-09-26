function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

function showToast(message, type = 'success') {
  const toastEl = document.createElement('div');
  toastEl.className = `toast${type === 'danger' ? ' toast-danger' : ''}`;
  toastEl.textContent = message;
  document.getElementById('toast-container').appendChild(toastEl);
  setTimeout(() => toastEl.remove(), 3500);
}

function filterTableByName(inputId, tableId) {
  const query = document.getElementById(inputId).value.trim().toLowerCase();
  const table = document.getElementById(tableId);
  if (!table) return;

  table.querySelectorAll('tr[data-name]').forEach(row => {
    const name = row.dataset.name.toLowerCase();
    const location = (row.dataset.location || '').toLowerCase();
    row.hidden = query !== '' && !name.includes(query) && !location.includes(query);
  });
}

async function loadStats() {
  const statsRow = document.getElementById('stats-row');
  if (!statsRow) return; 

  const res = await fetch('api.php?action=stats');
  const data = await res.json();
  if (!data.ok) return;

  const cards = [
    ['Gardeners', data.stats.gardeners],
    ['Coordinators', data.stats.coordinators],
    ['Plots Occupied', data.stats.plots_occupied],
    ['Plots Available', data.stats.plots_available],
    ['Pending Applications', data.stats.pending_applications],
    ['Pending Resource Requests', data.stats.pending_resource_txns],
    ['Pending Signups', data.stats.pending_signups || 0],
    ['Active Listings', data.stats.active_listings],
    ['Completed Trades', data.stats.completed_trades],
  ];

  statsRow.innerHTML = cards.map(([label, value]) => `
    <div class="stat-card">
      <div class="stat-value">${value}</div>
      <div class="stat-label">${label}</div>
    </div>
  `).join('');
}

async function loadAccounts() {
  const gardenersTable = document.getElementById('gardeners-table');
  if (!gardenersTable) return;

  const res = await fetch('api.php?action=accounts');
  const data = await res.json();
  if (!data.ok) return;

  gardenersTable.innerHTML = data.gardeners.map(g => `
    <tr data-name="${escapeHtml(g.Name)}" data-location="${escapeHtml(g.Location || '')}">
      <td>${escapeHtml(g.Name)}</td>
      <td>${escapeHtml(g.Email)}</td>
      <td>${escapeHtml(g.Location || 'Not provided')}</td>
      <td class="text-right">
        <button type="button" class="btn btn-ghost btn-sm delete-btn" data-table="gardener" data-id="${g.id}" data-name="${escapeHtml(g.Name)}">Archive</button>
      </td>
    </tr>
  `).join('') || '<tr><td colspan="4" class="text-muted">No gardeners yet.</td></tr>';

  document.getElementById('coordinators-table').innerHTML = data.coordinators.map(c => `
    <tr data-name="${escapeHtml(c.Name)}" data-location="${escapeHtml(c.Location || '')}">
      <td>${escapeHtml(c.Name)}</td>
      <td>${escapeHtml(c.Email)}</td>
      <td>${escapeHtml(c.Shift)}</td>
      <td>${escapeHtml(c.Location || 'Not provided')}</td>
      <td class="text-right">
        <button type="button" class="btn btn-ghost btn-sm delete-btn" data-table="coordinator" data-id="${c.id}" data-name="${escapeHtml(c.Name)}">Archive</button>
      </td>
    </tr>
  `).join('') || '<tr><td colspan="5" class="text-muted">No coordinators yet.</td></tr>';

  document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.onclick = () => {
      openDeleteModal(btn.dataset.table, btn.dataset.id, btn.dataset.name);
    };
  });
}

// ---------- Pending Account Requests ----------

async function loadSignupRequests() {
  const tbody = document.getElementById('signups-list');
  if (!tbody) return;

  const res = await fetch('api.php?action=pending_signups');
  const data = await res.json();
  if (!data.ok) return;

  const emptyEl = document.getElementById('signups-empty');

  if (data.requests.length === 0) {
    tbody.innerHTML = '';
    emptyEl.hidden = false;
    return;
  }
  emptyEl.hidden = true;

  tbody.innerHTML = `
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Age</th><th>Location</th><th>Shift</th><th></th></tr></thead>
        <tbody id="pending-signups-table">
          ${data.requests.map(r => `
            <tr data-name="${escapeHtml(r.FirstName + ' ' + r.LastName)}" data-location="${escapeHtml(r.Location || '')}">
              <td>${escapeHtml(r.FirstName + ' ' + r.LastName)}</td>
              <td>${escapeHtml(r.Email)}</td>
              <td>${r.Role === 'staff' ? 'Coordinator' : 'Gardener'}</td>
              <td>${escapeHtml(String(r.Age))}</td>
              <td>${escapeHtml(r.Location)}</td>
              <td>${r.Role === 'staff' ? escapeHtml(r.Shift || 'Morning') : '<span class="text-muted">—</span>'}</td>
              <td class="text-right" style="white-space: nowrap;">
                <button class="btn btn-sm approve-signup" style="background: var(--green-700); color: var(--white);" data-id="${r.RequestID}">Approve</button>
                <button class="btn btn-sm reject-signup" style="background: var(--danger); color: var(--white);" data-id="${r.RequestID}">Reject</button>
              </td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    </div>
  `;

  tbody.querySelectorAll('.approve-signup').forEach(btn => {
    btn.addEventListener('click', () => processSignup(btn.dataset.id, 'approve'));
  });
  tbody.querySelectorAll('.reject-signup').forEach(btn => {
    btn.addEventListener('click', () => processSignup(btn.dataset.id, 'reject'));
  });
}

async function processSignup(requestId, decision) {
  const res = await fetch('api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ action: 'process_signup', request_id: requestId, decision }),
  });
  const data = await res.json();
  if (data.ok) {
    showToast(`Account request ${decision === 'approve' ? 'approved & created' : 'rejected'}.`, 'success');
    loadSignupRequests();
    loadAccounts();
    loadStats();
  } else {
    showToast(data.error || 'Could not process request.', 'danger');
  }
}

// ---------- Delete confirmation modal ----------

const deleteModal = document.getElementById('delete-modal');
const deleteModalBody = document.getElementById('delete-modal-body');
const deleteCancelBtn = document.getElementById('delete-cancel');
const deleteConfirmBtn = document.getElementById('delete-confirm');
let pendingDelete = null;

function openDeleteModal(table, id, name) {
  if (!deleteModal) return; // Safety check
  pendingDelete = { table, id };
  deleteModalBody.textContent = `Archive ${name}'s account? This cannot be undone.`;
  deleteModal.hidden = false;
  deleteConfirmBtn.focus();
}

function closeDeleteModal() {
  if (!deleteModal) return; // Safety check
  deleteModal.hidden = true;
  pendingDelete = null;
}

if (deleteCancelBtn) deleteCancelBtn.addEventListener('click', closeDeleteModal);
if (deleteModal) {
    deleteModal.addEventListener('click', (e) => { if (e.target === deleteModal) closeDeleteModal(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !deleteModal.hidden) closeDeleteModal(); });
}

if (deleteConfirmBtn) {
    deleteConfirmBtn.addEventListener('click', async () => {
      if (!pendingDelete) return;
      const { table, id } = pendingDelete;
      closeDeleteModal();

      const res = await fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'archive_account', table, id }),
      });
      const data = await res.json();
      if (data.ok) {
        showToast('Account archived.', 'success');
        loadAccounts();
      } else {
        showToast(data.error || 'Could not archive account.', 'danger');
      }
    });
}

// ---------- Archived Accounts Logic ----------
async function loadArchivedAccounts() {
  const table = document.getElementById('archived-table');
  if (!table) return; // Only run on the archived page

  const res = await fetch('api.php?action=archived_accounts');
  const data = await res.json();
  
  if (!data.ok || data.accounts.length === 0) {
    table.innerHTML = '<tr><td colspan="6" class="text-muted">No archived accounts found.</td></tr>';
    return;
  }

  table.innerHTML = data.accounts.map(a => `
    <tr>
      <td>${escapeHtml(a.Name)}</td>
      <td>${escapeHtml(a.Email)}</td>
      <td><span class="badge badge-neutral">${escapeHtml(a.Role)}</span></td>
      <td>${escapeHtml(a.Location)}</td>
      <td>${escapeHtml(a.Shift)}</td>
      <td class="text-right">
        <button type="button" class="btn btn-accent btn-sm unarchive-btn" data-role="${a.Role}" data-id="${a.id}">Unarchive</button>
      </td>
    </tr>
  `).join('');

  document.querySelectorAll('.unarchive-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
      btn.disabled = true;
      const res = await fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'unarchive_account', role: btn.dataset.role, id: btn.dataset.id })
      });
      const result = await res.json();
      if (result.ok) {
        showToast('Account successfully unarchived and restored.', 'success');
        loadArchivedAccounts();
      } else {
        showToast(result.error || 'Failed to unarchive.', 'danger');
        btn.disabled = false;
      }
    });
  });
}

// ---------- Chart.js Graph & Report Export Logic ----------
async function renderActivityGraph() {
  const ctx = document.getElementById('activityChart');
  if (!ctx) return; // Only run on the dashboard page

  const res = await fetch('api.php?action=activity_data');
  const data = await res.json();
  if (!data.ok) return;

  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: data.labels,
      datasets: [{
        label: 'Total Platform Actions',
        data: data.values,
        backgroundColor: '#a8562e',
        borderRadius: 4
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
    }
  });
}


// ---------- SINGLE INITIALIZATION BLOCK ----------
document.addEventListener('DOMContentLoaded', () => {
  
  // 1. Fire all loaders. The safety checks (!table, !ctx, etc.) will prevent them from crashing on the wrong pages.
  loadStats();
  loadAccounts();
  loadSignupRequests();
  loadArchivedAccounts();
  renderActivityGraph();

  // 2. Search functionality
  document.querySelectorAll('[data-search-target]').forEach(button => {
    const input = document.getElementById(button.dataset.searchTarget);
    if (!input) return; 
    
    const filter = () => filterTableByName(button.dataset.searchTarget, button.dataset.tableTarget);
    button.addEventListener('click', filter);
    input.addEventListener('keydown', event => {
      if (event.key === 'Enter') filter();
    });
  });

  // 3. Create Admin Form Logic
  const createAdminForm = document.getElementById('create-admin-form');
  if (createAdminForm) {
    createAdminForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      
      const submitBtn = createAdminForm.querySelector('button[type="submit"]');
      submitBtn.disabled = true;

      const res = await fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action: 'create_admin',
          name: document.getElementById('new-admin-name').value,
          email: document.getElementById('new-admin-email').value,
          password: document.getElementById('new-admin-pass').value
        })
      });
      const data = await res.json();
      if (data.ok) {
        showToast('Administrator account created successfully!', 'success');
        createAdminForm.reset();
      } else {
        showToast(data.error || 'Failed to create account.', 'danger');
      }
      submitBtn.disabled = false;
    });
  }

  // 4. Export Report Button
  const exportBtn = document.getElementById('export-report-btn');
  if (exportBtn) {
    exportBtn.addEventListener('click', async () => {
      const res = await fetch('api.php?action=stats');
      const data = await res.json();
      if (!data.ok) return;

      let csvContent = "data:text/csv;charset=utf-8,Metric,Value\n";
      for (const [key, value] of Object.entries(data.stats)) {
        csvContent += `${key.replace('_', ' ').toUpperCase()},${value}\n`;
      }
      
      const encodedUri = encodeURI(csvContent);
      const link = document.createElement("a");
      link.setAttribute("href", encodedUri);
      link.setAttribute("download", `HarvestHub_Report_${new Date().toISOString().split('T')[0]}.csv`);
      document.body.appendChild(link);
      link.click();
      link.remove();
    });
  }
});