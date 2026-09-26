// staff.js — Garden Coordinator dashboard logic

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

let applications = [];
let resourceTransactions = [];
let plots = [];
let resources = [];

function matchesSearch(value, query) {
  return String(value || '').toLowerCase().includes(query);
}

function renderApplications() {
  const query = document.getElementById('applications-search').value.trim().toLowerCase();
  const listEl = document.getElementById('applications-list');
  const emptyEl = document.getElementById('applications-empty');
  const filtered = applications.filter(app =>
    matchesSearch(app.GardenerName, query) || matchesSearch(app.Label, query));

  listEl.innerHTML = filtered.map(app => `
    <div class="action-row">
      <div>
        <div class="action-row-title">${escapeHtml(app.GardenerName)}</div>
        <div class="action-row-sub">${app.RequestType === 'Unassign'
          ? `Requesting for plot "${escapeHtml(app.Label)}" to be unassigned`
          : `Requesting ${escapeHtml(app.Label)}`}
          <span class="text-muted"> • ${escapeHtml(app.PlotStatus || 'Pending')}</span>
        </div>
      </div>
      <div class="action-row-actions">
        <button class="btn btn-accent btn-sm approve-app" data-id="${app.AppID}">Approve</button>
        <button class="btn btn-ghost btn-sm reject-app" data-id="${app.AppID}">Reject</button>
      </div>
    </div>
  `).join('');
  emptyEl.textContent = applications.length && !filtered.length
    ? 'No applications match your search.'
    : 'No pending applications.';
  emptyEl.hidden = filtered.length > 0;

  listEl.querySelectorAll('.approve-app').forEach(btn =>
    btn.addEventListener('click', () => processApplication(btn.dataset.id, 'approve')));
  listEl.querySelectorAll('.reject-app').forEach(btn =>
    btn.addEventListener('click', () => processApplication(btn.dataset.id, 'reject')));
}

function renderResourceTransactions() {
  const query = document.getElementById('resource-search').value.trim().toLowerCase();
  const listEl = document.getElementById('resource-txns-list');
  const emptyEl = document.getElementById('resource-txns-empty');
  const filtered = resourceTransactions.filter(txn =>
    matchesSearch(txn.GardenerName, query) || matchesSearch(txn.ResourceName, query));

  listEl.innerHTML = filtered.map(txn => `
    <div class="action-row">
      <div>
        <div class="action-row-title">${escapeHtml(txn.GardenerName)}</div>
        <div class="action-row-sub">${escapeHtml(String(txn.Qty))}x ${escapeHtml(txn.ResourceName)}</div>
      </div>
      <div class="action-row-actions">
        <button class="btn btn-accent btn-sm approve-txn" data-id="${txn.TxnID}">Approve</button>
        <button class="btn btn-ghost btn-sm reject-txn" data-id="${txn.TxnID}">Reject</button>
      </div>
    </div>
  `).join('');
  emptyEl.textContent = resourceTransactions.length && !filtered.length
    ? 'No resource requests match your search.'
    : 'No pending resource requests.';
  emptyEl.hidden = filtered.length > 0;

  listEl.querySelectorAll('.approve-txn').forEach(btn =>
    btn.addEventListener('click', () => processResourceTxn(btn.dataset.id, 'approve')));
  listEl.querySelectorAll('.reject-txn').forEach(btn =>
    btn.addEventListener('click', () => processResourceTxn(btn.dataset.id, 'reject')));
}

async function postAction(action, params) {
  const res = await fetch('api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ action, ...params }),
  });
  return res.json();
}

async function loadApplications() {
  const res = await fetch('api.php?action=pending_applications');
  const data = await res.json();
  if (!data.ok) return;
  applications = data.applications;
  renderApplications();
}

async function processApplication(appId, decision) {
  const data = await postAction('process_application', { app_id: appId, decision });
  if (data.ok) {
    showToast(`Application ${decision === 'approve' ? 'approved' : 'rejected'}.`, 'success');
    loadApplications();
    loadPlots();
  } else {
    showToast(data.error || 'Could not process application.', 'danger');
  }
}

async function loadResourceTxns() {
  const res = await fetch('api.php?action=pending_resource_txns');
  const data = await res.json();
  if (!data.ok) return;
  resourceTransactions = data.transactions;
  renderResourceTransactions();
}

async function processResourceTxn(txnId, decision) {
  const data = await postAction('process_resource_txn', { txn_id: txnId, decision });
  if (data.ok) {
    showToast(`Request ${decision === 'approve' ? 'approved' : 'rejected'}.`, 'success');
    loadResourceTxns();
  } else {
    showToast(data.error || 'Could not process request.', 'danger');
  }
}

async function loadPlots() {
  const res = await fetch('api.php?action=all_plots');
  const data = await res.json();
  if (!data.ok) return;

  plots = data.plots;
  renderPlots();
}

function renderPlots(selectedFilter) {
  const filter = selectedFilter || document.getElementById('plot-status-filter').value;
  const filtered = plots.filter(plot => {
    const status = String(plot.Status || '').trim().toLowerCase();
    if (filter === 'available') return status === 'available';
    if (filter === 'unavailable') return status === 'occupied';
    return true;
  });

  const rows = filtered.map(p => `
    <tr>
      <td>${escapeHtml(p.Label)}</td>
      <td><span class="badge ${p.Status === 'Occupied' ? 'badge-green' : 'badge-neutral'}">${escapeHtml(p.Status)}</span></td>
      <td>${p.GardenerName ? escapeHtml(p.GardenerName) : '<span class="text-muted">—</span>'}</td>
      <td>${p.Status === 'Available' && !p.GardenerName
        ? `<button class="btn btn-ghost btn-sm delete-plot" data-id="${p.PltID}" data-label="${escapeHtml(p.Label)}" type="button">Delete</button>`
        : '<span class="text-muted">—</span>'}</td>
    </tr>
  `).join('');
  document.getElementById('plots-table').innerHTML = rows || '<tr><td colspan="4" class="text-muted">No plots match this search.</td></tr>';

  document.querySelectorAll('.delete-plot').forEach(button => {
    button.addEventListener('click', () => deletePlot(button.dataset.id, button.dataset.label));
  });
}

async function createPlot(label) {
  const data = await postAction('create_plot', { label });
  if (data.ok) {
    showToast('Plot added.', 'success');
    document.getElementById('new-plot-label').value = '';
    loadPlots();
  } else {
    showToast(data.error || 'Could not add plot.', 'danger');
  }
}

async function deletePlot(plotId, label) {
  if (!window.confirm(`Delete plot "${label}"? This cannot be undone.`)) return;
  const data = await postAction('delete_plot', { plot_id: plotId });
  if (data.ok) {
    showToast('Plot deleted.', 'success');
    loadPlots();
  } else {
    showToast(data.error || 'Could not delete plot.', 'danger');
  }
}

async function loadResources() {
  const res = await fetch('api.php?action=all_resources');
  const data = await res.json();
  if (!data.ok) return;

  resources = data.resources;
  renderResources();
}

function renderResources() {
  const query = document.getElementById('all-resources-search').value.trim().toLowerCase();
  const filtered = resources.filter(resource =>
    matchesSearch(resource.Name, query) || matchesSearch(resource.Borrowers, query));

  const rows = filtered.map(resource => `
    <tr>
      <td>${escapeHtml(resource.Name)}</td>
      <td>${escapeHtml(String(resource.TotalQty))}</td>
      <td>${escapeHtml(String(resource.AvailableQty))}</td>
      <td>${resource.Borrowers ? escapeHtml(resource.Borrowers) : '<span class="text-muted">None</span>'}</td>
    </tr>
  `).join('');
  document.getElementById('resources-table').innerHTML = rows || '<tr><td colspan="4" class="text-muted">No resources match your search.</td></tr>';
}

document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('create-plot-form').addEventListener('submit', event => {
    event.preventDefault();
    createPlot(document.getElementById('new-plot-label').value.trim());
  });
  document.getElementById('applications-search-form').addEventListener('submit', event => {
    event.preventDefault();
    renderApplications();
  });
  document.getElementById('resource-search-form').addEventListener('submit', event => {
    event.preventDefault();
    renderResourceTransactions();
  });
  document.getElementById('all-resources-search-form').addEventListener('submit', event => {
    event.preventDefault();
    renderResources();
  });
  document.getElementById('plot-status-filter').addEventListener('change', event => {
    renderPlots(event.target.value);
  });
  loadApplications();
  loadResourceTxns();
  loadPlots();
  loadResources();
});
