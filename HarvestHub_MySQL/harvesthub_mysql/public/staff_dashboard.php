<?php
require_once __DIR__ . '/auth.php';
$user = requireRole('staff');
$navTitle = 'Garden Coordinator Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HarvestHub — Coordinator Dashboard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="staff-page">
<?php include __DIR__ . '/nav_partial.php'; ?>

<main class="wrap page-wrap staff-page-wrap" id="top">
  <div class="staff-dashboard-grid">

    <div class="pending-request-panels">
      <div class="panel">
        <p class="panel-title">Pending Plot Applications</p>
        <form class="table-search" id="applications-search-form">
          <label class="sr-only" for="applications-search">Search applications</label>
          <input id="applications-search" type="search" placeholder="Search gardener or plot">
          <button class="btn btn-accent btn-sm" type="submit">Search</button>
        </form>
        <div class="pending-request-list" id="applications-list"></div>
        <p class="text-muted" id="applications-empty" hidden>No pending applications.</p>
      </div>

      <div class="panel">
        <p class="panel-title">Pending Resource Requests</p>
        <form class="table-search" id="resource-search-form">
          <label class="sr-only" for="resource-search">Search resource requests</label>
          <input id="resource-search" type="search" placeholder="Search gardener or resource">
          <button class="btn btn-accent btn-sm" type="submit">Search</button>
        </form>
        <div class="pending-request-list" id="resource-txns-list"></div>
        <p class="text-muted" id="resource-txns-empty" hidden>No pending resource requests.</p>
      </div>
    </div>

    <div class="panel">
      <p class="panel-title">All Plots</p>
      <form class="plot-management-form" id="create-plot-form">
        <label class="sr-only" for="new-plot-label">New plot name</label>
        <input id="new-plot-label" type="text" maxlength="80" placeholder="New plot name" required>
        <button class="btn btn-accent btn-sm" type="submit">Add Plot</button>
      </form>
      <div class="table-search">
        <label class="sr-only" for="plot-status-filter">Filter plots by status</label>
        <select id="plot-status-filter" aria-label="Filter plots by status">
          <option value="all">See all plots</option>
          <option value="available">Available</option>
          <option value="unavailable">Unavailable</option>
        </select>
      </div>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Plot</th><th>Status</th><th>Gardener</th><th>Actions</th></tr></thead>
          <tbody id="plots-table"></tbody>
        </table>
      </div>
    </div>

    <div class="panel">
      <p class="panel-title">All Resources</p>
      <form class="table-search" id="all-resources-search-form">
        <label class="sr-only" for="all-resources-search">Search resources or borrowers</label>
        <input id="all-resources-search" type="search" placeholder="Search resource or borrower">
        <button class="btn btn-accent btn-sm" type="submit">Search</button>
      </form>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Resource</th><th>Total</th><th>Available</th><th>Borrowed By</th></tr></thead>
          <tbody id="resources-table"></tbody>
        </table>
      </div>
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

<div class="toast-container" id="toast-container" aria-live="polite"></div>
<script src="assets/staff.js?v=2"></script>
</body>
</html>
