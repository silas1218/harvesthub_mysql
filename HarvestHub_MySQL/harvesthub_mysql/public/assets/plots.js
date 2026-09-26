document.addEventListener('DOMContentLoaded', () => {

    async function loadPlots() {
        try {
            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'get_my_plots' })
            });
            const data = await res.json();
            
            if (!data.ok) return;

            const listEl = document.getElementById('plots-list');

            if (data.plots.length === 0) {
                listEl.innerHTML = '<p class="empty-state">You have not logged any crops yet.</p>';
                return;
            }

            listEl.innerHTML = data.plots.map(p => {
                // Determine badge colors based on status
                let badgeClass = 'badge-neutral';
                if (p.Status === 'Growing') badgeClass = 'badge-brown';
                if (p.Status === 'Harvested') badgeClass = 'badge-brown'; // Update to a green class if you add one to CSS
                if (p.Status === 'Failed') badgeClass = 'badge-neutral';

                return `
                <div class="plot-item" data-search="${escapeHtml(p.CropName).toLowerCase()}" style="display: flex; justify-content: space-between; align-items: center; padding: 16px 0; border-bottom: 1px solid #e2e8f0;">
                    
                    <div style="flex: 1; padding-right: 16px;">
                        <div style="margin-bottom: 4px;">
                            <strong style="font-size: 1.1rem;">${escapeHtml(p.CropName)}</strong>
                            <span class="badge ${badgeClass}" style="margin-left: 8px; font-size: 0.75rem;">${escapeHtml(p.Status)}</span>
                        </div>
                        <div style="font-size: 0.85rem; color: #475569; margin-bottom: 8px;">
                            <strong>Planted:</strong> ${new Date(p.PlantedDate).toLocaleDateString()} &nbsp;|&nbsp; 
                            <strong>Est. Harvest:</strong> ${new Date(p.EstHarvestDate).toLocaleDateString()}
                        </div>
                        ${p.Notes ? `<p style="margin: 0; font-size: 0.85rem; color: #64748b; font-style: italic;">"${escapeHtml(p.Notes)}"</p>` : ''}
                    </div>
                    
                    <div>
                        <select class="status-dropdown" data-id="${p.PlotID}" style="padding: 6px; border: 1px solid #e2e8f0; border-radius: 4px; background: #fff;">
                            <option value="Planted" ${p.Status === 'Planted' ? 'selected' : ''}>Planted</option>
                            <option value="Growing" ${p.Status === 'Growing' ? 'selected' : ''}>Growing</option>
                            <option value="Harvested" ${p.Status === 'Harvested' ? 'selected' : ''}>Harvested</option>
                            <option value="Failed" ${p.Status === 'Failed' ? 'selected' : ''}>Failed</option>
                        </select>
                    </div>

                </div>
                `;
            }).join('');

            // Attach listeners to status dropdowns
            document.querySelectorAll('.status-dropdown').forEach(select => {
                select.addEventListener('change', async (e) => {
                    const plotId = e.target.getAttribute('data-id');
                    const newStatus = e.target.value;
                    
                    try {
                        const res = await fetch('api.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({ action: 'update_crop_status', plot_id: plotId, status: newStatus })
                        });
                        const result = await res.json();
                        
                        if (result.ok) {
                            if (typeof showToast === 'function') showToast(`Status updated to ${newStatus}`, 'success');
                            loadPlots(); 
                        } else {
                            if (typeof showToast === 'function') showToast(result.error || 'Failed to update status.', 'error');
                        }
                    } catch (err) {
                        console.error('Error updating status:', err);
                    }
                });
            });

            // Re-apply search filter
            const searchEl = document.getElementById('search-plots');
            if (searchEl && searchEl.value !== '') {
                const event = new Event('input');
                searchEl.dispatchEvent(event);
            }
            
        } catch (err) {
            console.error("Error loading plots:", err);
            document.getElementById('plots-list').innerHTML = '<p class="empty-state" style="color: #d9534f;">Failed to load crops.</p>';
        }
    }

    // Handle Add Crop Form Submission
    const addForm = document.getElementById('add-plot-form');
    const cropInput = document.getElementById('plot-crop-name');

    if (cropInput) {
        cropInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^A-Za-z\s]/g, '');
        });
    }

    if (addForm) {
        addForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = addForm.querySelector('button[type="submit"]');
            btn.disabled = true;

            const cropName = document.getElementById('plot-crop-name').value;
            const plantedDate = document.getElementById('plot-planted-date').value;
            const harvestDate = document.getElementById('plot-harvest-date').value;
            const notes = document.getElementById('plot-notes').value;

            try {
                const res = await fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ 
                        action: 'add_crop_log', 
                        crop_name: cropName, 
                        planted_date: plantedDate, 
                        est_harvest_date: harvestDate, 
                        notes: notes 
                    })
                });
                const result = await res.json();
                
                if (result.ok) {
                    if (typeof showToast === 'function') showToast('Crop logged successfully!', 'success');
                    addForm.reset();
                    loadPlots();
                } else {
                    if (typeof showToast === 'function') showToast(result.error || 'Failed to log crop.', 'error');
                }
            } catch (err) {
                console.error("Error logging crop:", err);
            } finally {
                btn.disabled = false;
            }
        });
    }

    // Search Filter Logic
    const searchEl = document.getElementById('search-plots');
    if (searchEl) {
        searchEl.addEventListener('input', (e) => {
            const term = e.target.value.toLowerCase();
            document.querySelectorAll('.plot-item').forEach(item => {
                const itemName = item.getAttribute('data-search');
                item.style.display = itemName.includes(term) ? 'flex' : 'none';
            });
        });
    }

    // -----------------------------------------
    // COMMUNITY MAP LOGIC
    // -----------------------------------------
    
    async function loadMap() {
        try {
            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'get_community_map' })
            });
            const data = await res.json();

            if (!data.ok) return;

            const gridEl = document.getElementById('garden-map-grid');
            if (!gridEl) return;

            gridEl.innerHTML = '';

            if (!Array.isArray(data.plots) || data.plots.length === 0) {
                gridEl.innerHTML = '<p class="text-muted" style="grid-column: span 4; text-align: center;">No community plots available right now.</p>';
                return;
            }

            data.plots.forEach(plot => {
                let bg, border, textColor, cursor, onClick;

                if (plot.Status === 'Available') {
                    bg = '#dcfce7'; border = '#22c55e'; textColor = '#166534'; cursor = 'pointer';
                    const safePlotName = escapeHtml(plot.PlotName ?? 'Plot');
                    onClick = `onclick="openPlotModal(${plot.PlotID}, '${safePlotName.replace(/'/g, "\\'") }')"`;
                } else if (plot.Status === 'Pending Approval') {
                    bg = '#fef08a'; border = '#eab308'; textColor = '#854d0e'; cursor = 'not-allowed';
                    onClick = '';
                } else {
                    bg = '#e2e8f0'; border = '#94a3b8'; textColor = '#475569'; cursor = 'not-allowed';
                    onClick = '';
                }

                gridEl.innerHTML += `
                    <button type="button" ${onClick} style="
                        height: 100px; 
                        border-radius: 8px; 
                        background: ${bg}; 
                        border: 2px solid ${border}; 
                        color: ${textColor}; 
                        font-weight: bold; 
                        font-size: 1.1rem; 
                        cursor: ${cursor}; 
                        display: flex; 
                        align-items: center; 
                        justify-content: center; 
                        transition: opacity 0.2s;">
                        ${escapeHtml(plot.PlotName ?? 'Plot')}
                    </button>
                `;
            });

        } catch (err) {
            console.error("Error loading map:", err);
            const gridEl = document.getElementById('garden-map-grid');
            if (gridEl) {
                gridEl.innerHTML = '<p class="text-muted" style="grid-column: span 4; text-align: center;">Failed to load community map.</p>';
            }
        }
    }

    // Modal Logic
    const plotModal = document.getElementById('plot-modal');
    
    // Attach function to window so the inline onclick="" can find it
    window.openPlotModal = function(id, name) {
        document.getElementById('modal-plot-id').value = id;
        document.getElementById('modal-plot-name').textContent = name;
        plotModal.style.display = 'flex';
    };

    document.getElementById('cancel-plot-btn')?.addEventListener('click', () => {
        plotModal.style.display = 'none';
    });

    document.getElementById('confirm-plot-btn')?.addEventListener('click', async (e) => {
        const btn = e.target;
        btn.disabled = true;
        btn.textContent = 'Sending...';

        const plotId = document.getElementById('modal-plot-id').value;

        try {
            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'request_garden_plot', plot_id: plotId })
            });
            const result = await res.json();
            
            if (result.ok) {
                if (typeof showToast === 'function') showToast('Plot request sent to coordinator!', 'success');
                plotModal.style.display = 'none';
                loadMap(); // Refresh map to show it turn yellow (Pending)
            } else {
                if (typeof showToast === 'function') showToast(result.error || 'Failed to request plot.', 'error');
            }
        } catch (err) {
            console.error("Error requesting plot:", err);
        } finally {
            btn.disabled = false;
            btn.textContent = 'Send Request';
        }
    });

    // Helper for fetch body
    function newSearchParams(params) {
        return new URLSearchParams(params);
    }

    // Initialize
    loadPlots();
    loadMap();
});