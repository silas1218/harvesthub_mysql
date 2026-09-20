<?php
require_once __DIR__ . '/auth.php';
$user = requireRole('customer');
$navTitle = 'Community Gardener Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HarvestHub — My Garden</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include __DIR__ . '/nav_partial.php'; ?>

<main class="wrap page-wrap" id="top">

  

  <div class="grid grid-3">

  <div class="panel">
      <p class="panel-title">Current Plots</p>
    <div id="plot-status"></div>

    <button type="button" class="btn btn-ghost btn-block" id="request-plot-btn" style="margin-top: 16px;">
    Request for more plots
    </button>
    <div id="available-plots" hidden style="margin-top: 12px;"></div>
    <p class="form-alert" id="plot-request-alert" hidden></p>
    <p class="form-success" id="plot-request-success" hidden></p>
  </div>

    <div class="panel">
      <p class="panel-title">Crop Lifecycle Log</p>
      <form id="croplog-form" novalidate style="margin-bottom: 16px;">
        <div class="field">
          <input type="text" id="crop-name" placeholder="Crop name" maxlength="60" required>
        </div>
        <div class="field">
          <input type="text" id="crop-notes" placeholder="Maintenance notes" maxlength="300">
        </div>
        <div class="field">
          <input type="text" id="crop-yield" placeholder="Harvest yield (e.g. 5 kg)" maxlength="60">
        </div>
        <button type="submit" class="btn btn-ghost btn-block">Add Log Entry</button>
        <p class="form-alert" id="croplog-alert" hidden></p>
      </form>
      <div id="croplog-list" class="scroll-y"></div>
    </div>

    <div class="panel">
      <p class="panel-title">Resource Sharing Hub</p>
      <form id="resource-form" class="inline-form" style="margin-bottom: 14px;">
        <select id="resource-select" class="field-select" style="flex: 1;"></select>
        <input type="number" id="resource-qty" min="1" value="1" class="field-qty" style="width: 64px;">
        <button type="submit" class="btn btn-ghost btn-sm">Request</button>
      </form>
      <p class="form-alert" id="resource-alert" hidden></p>
      <p class="text-muted" style="font-size: 0.85rem; margin: 14px 0 6px;">My Requests</p>
      <div id="my-requests-list" class="scroll-y" style="max-height: 160px;"></div>
    </div>

  </div>
    <h2 class="panel-title" style="margin: 32px 0 16px;">Produce Exchange Board</h2>

  <div class="app-row">

    <div class="post-panel" id="post">
      <h2>Post surplus produce</h2>
      <p class="panel-hint">Fields marked with an asterisk are required.</p>

      <form id="listing-form" novalidate>
        <div class="field">
          <label for="crop">Crop *</label>
          <input type="text" id="crop" name="crop" maxlength="60" autocomplete="off" required>
          <p class="field-error" id="crop-error" hidden>Enter a crop name using letters, spaces, or hyphens (max 60 characters).</p>
        </div>

        <div class="field">
          <label for="qty">Quantity *</label>
          <input type="number" id="qty" name="qty" min="1" max="1000" required>
          <p class="field-error" id="qty-error" hidden>Enter a whole number between 1 and 1000.</p>
        </div>

        <div class="field">
          <label for="notes">Notes <span class="field-optional">(optional)</span></label>
          <textarea id="notes" name="notes" maxlength="200" rows="3"></textarea>
          <p class="field-hint"><span id="notes-count">200</span> characters left</p>
        </div>

        <button type="submit" class="btn btn-accent btn-block">Post listing</button>

        <p class="form-alert" id="form-alert" role="alert" hidden></p>
        <p class="form-success" id="form-success" role="status" hidden></p>
      </form>
    </div>

    <div class="board-panel">
      <div class="board-head">
        <h2>Available listings</h2>
        <p class="board-count"><span id="results-count">0</span> results</p>
      </div>

      <div class="board-controls">
        <input type="text" id="search" placeholder="Search by crop, e.g. tomato" aria-label="Search by crop">
        <input type="number" id="min-qty" min="0" placeholder="Min qty" aria-label="Minimum quantity">
        <select id="sort" aria-label="Sort listings">
          <option value="newest">Newest first</option>
          <option value="oldest">Oldest first</option>
          <option value="qty_high">Quantity: high to low</option>
          <option value="qty_low">Quantity: low to high</option>
        </select>
      </div>

      <ul class="listings" id="listings" aria-live="polite"></ul>
      <p class="empty-state" id="empty-state" hidden>No listings match your search yet — try widening it, or be the first to post.</p>
    </div>

  </div>

</main>

<footer class="site-footer">
  <div class="wrap footer-row">
    <div>
      <p class="wordmark wordmark-light">HarvestHub</p>
      <p class="footer-tagline">A produce exchange board for gardeners who'd rather share than waste it.</p>
    </div>
    <div class="footer-meta">
      <p>Phase 3 prototype — Produce Exchange Board module</p>
      <p>Built with PHP, MySQL, and vanilla JavaScript</p>
    </div>
  </div>
</footer>

<!-- Claim confirmation modal -->
<div class="modal-overlay" id="claim-modal" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="claim-modal-title">
    <h3 id="claim-modal-title">Claim this listing?</h3>
    <p id="claim-modal-body"></p>
    <div class="modal-actions">
      <button type="button" class="btn btn-ghost" id="claim-cancel">Cancel</button>
      <button type="button" class="btn btn-accent" id="claim-confirm">Claim it</button>
    </div>
  </div>
</div>

<div class="toast-container" id="toast-container" aria-live="polite"></div>
<script src="assets/app.js"></script>
<script src="assets/customer.js?v=2"></script>
</body>
</html>
