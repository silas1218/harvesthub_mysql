// admin.js — Admin dashboard logic

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

  document.getElementById('stats-row').innerHTML = cards.map(([label, value]) => `
    <div class="stat-card">
      <div class="stat-value">${value}</div>
      <div class="stat-label">${label}</div>
    </div>
  `).join('');
}

async function loadAccounts() {
  const res = await fetch('api.php?action=accounts');
  const data = await res.json();
  if (!data.ok) return;

  document.getElementById('gardeners-table').innerHTML = data.gardeners.map(g => `
    <tr data-name="${escapeHtml(g.Name)}" data-location="${escapeHtml(g.Location || '')}">
      <td>${escapeHtml(g.Name)}</td>
      <td>${escapeHtml(g.Email)}</td>
      <td>${escapeHtml(g.Location || 'Not provided')}</td>
      <td class="text-right">
        <button type="button" class="btn btn-accent btn-sm delete-btn" data-table="gardener" data-id="${g.id}" data-name="${escapeHtml(g.Name)}">Remove</button>
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
        <button type="button" class="btn btn-accent btn-sm delete-btn" data-table="coordinator" data-id="${c.id}" data-name="${escapeHtml(c.Name)}">Remove</button>
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
  const res = await fetch('api.php?action=pending_signups');
  const data = await res.json();
  if (!data.ok) return;

  const tbody = document.getElementById('signups-list');
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
  pendingDelete = { table, id };
  deleteModalBody.textContent = `Remove ${name}'s account? This cannot be undone.`;
  deleteModal.hidden = false;
  deleteConfirmBtn.focus();
}
function closeDeleteModal() {
  deleteModal.hidden = true;
  pendingDelete = null;
}
deleteCancelBtn.addEventListener('click', closeDeleteModal);
deleteModal.addEventListener('click', (e) => { if (e.target === deleteModal) closeDeleteModal(); });
document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !deleteModal.hidden) closeDeleteModal(); });

deleteConfirmBtn.addEventListener('click', async () => {
  if (!pendingDelete) return;
  const { table, id } = pendingDelete;
  closeDeleteModal();

  const res = await fetch('api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ action: 'delete_account', table, id }),
  });
  const data = await res.json();
  if (data.ok) {
    showToast('Account removed.', 'success');
    loadAccounts();
    loadStats();
  } else {
    showToast(data.error || 'Could not remove account.', 'danger');
  }
});

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-search-target]').forEach(button => {
    const input = document.getElementById(button.dataset.searchTarget);
    const filter = () => filterTableByName(button.dataset.searchTarget, button.dataset.tableTarget);
    button.addEventListener('click', filter);
    input.addEventListener('keydown', event => {
      if (event.key === 'Enter') filter();
    });
  });

  loadStats();
  loadAccounts();
  loadSignupRequests();
});