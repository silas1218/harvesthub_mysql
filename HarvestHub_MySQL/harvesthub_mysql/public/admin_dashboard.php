<?php
require_once __DIR__ . '/auth.php';
$user = requireRole('admin');
$navTitle = 'System Administrator Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HarvestHub — Admin Dashboard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include __DIR__ . '/nav_partial.php'; ?>

<main class="wrap page-wrap" id="top">

  <!-- Wrap stats in a unified panel -->
  <div class="panel" style="margin-bottom: 24px;">
    <p class="panel-title">System Overview</p>
    <div class="stat-grid" id="stats-row" style="margin-bottom: 0;"></div>
  </div>

  <!-- Pending Account Requests Panel -->
  <div class="panel" style="margin-bottom: 24px;">
    <p class="panel-title">Pending Account Requests</p>
    <div class="table-search">
      <label class="sr-only" for="pending-signups-search">Search pending account requests by name or location</label>
      <input type="search" id="pending-signups-search" placeholder="Search by name or location">
      <button type="button" class="btn btn-ghost btn-sm" data-search-target="pending-signups-search" data-table-target="pending-signups-table">Search</button>
    </div>
    <div class="pending-request-list" id="signups-list"></div>
    <p class="text-muted" id="signups-empty" hidden>No pending account requests.</p>
  </div>

  <div class="panel" style="margin-bottom: 24px;">
    <p class="panel-title">Community Gardeners</p>
    <div class="table-search">
      <label class="sr-only" for="gardeners-search">Search community gardeners by name or location</label>
      <input type="search" id="gardeners-search" placeholder="Search by name or location">
      <button type="button" class="btn btn-ghost btn-sm" data-search-target="gardeners-search" data-table-target="gardeners-table">Search</button>
    </div>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Name</th><th>Email</th><th>Location</th><th></th></tr></thead>
        <tbody id="gardeners-table"></tbody>
      </table>
    </div>
  </div>

  <div class="panel">
    <p class="panel-title">Garden Coordinators</p>
    <div class="table-search">
      <label class="sr-only" for="coordinators-search">Search garden coordinators by name or location</label>
      <input type="search" id="coordinators-search" placeholder="Search by name or location">
      <button type="button" class="btn btn-ghost btn-sm" data-search-target="coordinators-search" data-table-target="coordinators-table">Search</button>
    </div>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Name</th><th>Email</th><th>Shift</th><th>Location</th><th></th></tr></thead>
        <tbody id="coordinators-table"></tbody>
      </table>
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


<!-- Delete confirmation modal -->
<div class="modal-overlay" id="delete-modal" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="delete-modal-title">
    <h3 id="delete-modal-title">Remove this account?</h3>
    <p id="delete-modal-body">This cannot be undone.</p>
    <div class="modal-actions">
      <button type="button" class="btn btn-ghost" id="delete-cancel">Cancel</button>
      <button type="button" class="btn btn-accent" id="delete-confirm">Remove</button>
    </div>
  </div>
</div>

<div class="toast-container" id="toast-container" aria-live="polite"></div>
<script src="assets/admin.js"></script>
</body>
</html>