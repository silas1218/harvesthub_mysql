// customer.js — Community Gardener dashboard logic

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

async function postAction(action, params) {
  const res = await fetch('api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ action, ...params }),
  });
  return res.json();
}

// ---------- Plot ----------

async function loadPlot() {
  const res = await fetch('api.php?action=my_plot');
  const data = await res.json();
  const el = document.getElementById('plot-status');
  if (!data.ok) return;

  if (data.plots.length > 0) {
    const pendingUnassignment = data.pending_application?.RequestType === 'Unassign';
    el.innerHTML = `
      <p class="text-muted" style="font-size: 0.85rem; margin: 0 0 8px;">Your currently assigned plots:</p>
      <ul style="margin: 0; padding-left: 18px;">
        ${data.plots.map(plot => `
          <li style="margin-bottom: 10px;">
            <strong>${escapeHtml(plot.Label)}</strong>
            <button class="btn btn-ghost btn-sm unassign-plot-btn" data-id="${plot.PltID}" data-label="${escapeHtml(plot.Label)}" type="button" style="margin-left: 8px;" ${pendingUnassignment ? 'disabled' : ''}>${pendingUnassignment ? 'Unassignment pending' : 'Request unassignment'}</button>
          </li>
        `).join('')}
      </ul>
      <p class="text-muted" style="font-size: 0.85rem;">Log your crops and resource needs using the panels alongside this one.</p>
    `;
    if (data.pending_application?.RequestType === 'Unassign') {
      el.insertAdjacentHTML('beforeend', `<p class="form-alert" style="display:block; background:#f3e9d6; color:#8a5a1e;">Your request to unassign <strong>${escapeHtml(data.pending_application.Label)}</strong> is pending Coordinator approval.</p>`);
    }
    document.querySelectorAll('.unassign-plot-btn').forEach(button => {
      button.addEventListener('click', async () => {
        if (!window.confirm(`Request unassignment of "${button.dataset.label}"?`)) return;
        const result = await postAction('request_plot_unassignment', { plt_id: button.dataset.id });
        if (result.ok) {
          showToast('Unassignment request submitted.', 'success');
          loadPlot();
        } else {
          showToast(result.error || 'Could not submit unassignment request.', 'danger');
        }
      });
    });
    return;
  }

  if (data.pending_application) {
    el.innerHTML = `
      <p class="form-alert" style="display:block; background:#f3e9d6; color:#8a5a1e;">
        Application for <strong>${escapeHtml(data.pending_application.Label)}</strong> is pending Coordinator approval.
      </p>
    `;
    return;
  }

  if (data.available_plots.length === 0) {
    el.innerHTML = `<p class="text-muted" style="font-size: 0.9rem;">No plots available right now. Check back later.</p>`;
    return;
  }

  el.innerHTML = `
    <p class="text-muted" style="font-size: 0.9rem;">You don't have a plot yet. Apply for one below:</p>
    <div class="inline-form">
      <select id="plot-select" style="flex: 1;">
        ${data.available_plots.map(p => `<option value="${p.PltID}">${escapeHtml(p.Label)}</option>`).join('')}
      </select>
      <button class="btn btn-accent btn-sm" id="apply-plot-btn">Apply</button>
    </div>
  `;

  document.getElementById('apply-plot-btn').addEventListener('click', async () => {
    const pltId = document.getElementById('plot-select').value;
    const result = await postAction('apply_plot', { plt_id: pltId });
    if (result.ok) {
      showToast('Application submitted!', 'success');
      loadPlot();
    } else {
      showToast(result.error || 'Could not submit application.', 'danger');
    }
  });
}

// ---------- Crop Log ----------

async function loadCropLog() {
  const res = await fetch('api.php?action=my_croplog');
  const data = await res.json();
  const el = document.getElementById('croplog-list');
  if (!data.ok) return;

  if (data.logs.length === 0) {
    el.innerHTML = '<p class="text-muted" style="font-size: 0.88rem;">No entries yet.</p>';
    return;
  }

  el.innerHTML = data.logs.map(log => `
    <div style="border-bottom: 1px solid var(--line); padding: 10px 0;">
      <div style="display:flex; justify-content: space-between;">
        <strong style="font-size: 0.9rem;">${escapeHtml(log.CropName)}</strong>
        <span class="text-muted" style="font-size: 0.72rem;">${escapeHtml(log.LoggedAt)}</span>
      </div>
      ${log.MaintenanceNotes ? `<div class="text-muted" style="font-size: 0.85rem;">${escapeHtml(log.MaintenanceNotes)}</div>` : ''}
      ${log.HarvestYield ? `<div style="font-size: 0.85rem;">Yield: ${escapeHtml(log.HarvestYield)}</div>` : ''}
    </div>
  `).join('');
}

document.getElementById('croplog-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const alertEl = document.getElementById('croplog-alert');
  alertEl.hidden = true;

  const cropName = document.getElementById('crop-name').value.trim();
  if (cropName === '') {
    alertEl.textContent = 'Crop name is required.';
    alertEl.hidden = false;
    return;
  }

  const result = await postAction('croplog_create', {
    crop_name: cropName,
    notes: document.getElementById('crop-notes').value.trim(),
    yield: document.getElementById('crop-yield').value.trim(),
  });

  if (result.ok) {
    e.target.reset();
    loadCropLog();
  } else {
    alertEl.textContent = result.error || 'Could not save entry.';
    alertEl.hidden = false;
  }
});


const plotBtn = document.getElementById('request-plot-btn');
const plotAlert = document.getElementById('plot-request-alert');
const plotSuccess = document.getElementById('plot-request-success');
const availablePlotsEl = document.getElementById('available-plots');

function renderAvailablePlots(plots) {
  if (plots.length === 0) {
    availablePlotsEl.innerHTML = '<p class="text-muted" style="font-size: 0.85rem;">No plots are available right now. Check back later.</p>';
    return;
  }

  availablePlotsEl.innerHTML = `
    <p class="text-muted" style="font-size: 0.85rem; margin: 0 0 8px;">Choose an available plot to request:</p>
    <div class="inline-form">
      <select id="more-plot-select" class="field-select" style="flex: 1;">
        ${plots.map(plot => `<option value="${plot.PltID}">${escapeHtml(plot.Label)}</option>`).join('')}
      </select>
      <button type="button" class="btn btn-accent btn-sm" id="more-plot-apply">Request</button>
    </div>
  `;

  document.getElementById('more-plot-apply').addEventListener('click', async () => {
    const applyBtn = document.getElementById('more-plot-apply');
    applyBtn.disabled = true;
    plotAlert.hidden = true;
    plotSuccess.hidden = true;

    const result = await postAction('apply_plot', {
      plt_id: document.getElementById('more-plot-select').value,
    });

    if (result.ok) {
      plotSuccess.textContent = 'Plot request submitted for Coordinator approval.';
      plotSuccess.hidden = false;
      availablePlotsEl.hidden = true;
      plotBtn.textContent = 'Request for more plots';
    } else {
      plotAlert.textContent = result.error || 'Could not submit plot request.';
      plotAlert.hidden = false;
      applyBtn.disabled = false;
    }
  });
}

if (plotBtn) {
  plotBtn.addEventListener('click', async () => {
    if (!availablePlotsEl.hidden) {
      availablePlotsEl.hidden = true;
      plotBtn.textContent = 'Request for more plots';
      return;
    }

    plotAlert.hidden = true;
    plotSuccess.hidden = true;
    plotBtn.disabled = true;

    try {
      const res = await fetch('api.php?action=my_plot');
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error(data.error || 'Could not load available plots.');

      renderAvailablePlots(data.available_plots);
      availablePlotsEl.hidden = false;
      plotBtn.textContent = 'Hide available plots';
    } catch (err) {
      plotAlert.textContent = err.message;
      plotAlert.hidden = false;
    } finally {
      plotBtn.disabled = false;
    }
  });
}

document.addEventListener('DOMContentLoaded', () => {
  loadPlot();
  loadCropLog();
  loadResources();
  loadMyRequests();
  setInterval(loadPlot, 5000);
});
