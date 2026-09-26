<?php
require_once __DIR__ . '/auth.php';
$user = requireRole('admin');
$navTitle = 'Manage Accounts';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HarvestHub — Manage Accounts</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=8">
</head>
<body>

<div class="app-layout">
  <?php include __DIR__ . '/admin_sidebar.php'; ?>

  <div class="main-content">
    <main class="wrap" id="top" style="max-width: 1200px; padding-top: 32px;">
      
      <!-- New: Create Admin Account Panel -->
      <div class="panel" style="margin-bottom: 24px;">
        <p class="panel-title">Provision New Administrator</p>
        <form id="create-admin-form" class="inline-form" style="display: flex; gap: 12px; flex-wrap: wrap;">
          <input type="text" id="new-admin-name" placeholder="Full Name" required style="flex: 1; min-width: 200px;">
          <input type="email" id="new-admin-email" placeholder="Email Address" required style="flex: 1; min-width: 200px;">
          <input type="password" id="new-admin-pass" placeholder="Temporary Password (min 8)" minlength="8" required style="flex: 1; min-width: 200px;">
          <button type="submit" class="btn btn-accent btn-sm">Create Admin</button>
        </form>
      </div>

      <!-- Pending Account Requests -->
      <div class="panel" style="margin-bottom: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; margin-bottom: 16px;">
          <p class="panel-title" style="margin: 0;">Pending Account Requests</p>
          <input type="search" id="search-pending-accounts" data-table-search="pending-signups-table" placeholder="Search requests..." aria-label="Search pending account requests" style="width: min(100%, 260px);">
        </div>
        <div class="pending-request-list" id="signups-list"></div>
        <p class="text-muted" id="signups-empty" hidden>No pending account requests.</p>
      </div>

      <div style="display: grid; grid-template-columns: 1fr; gap: 24px;">
        <!-- Gardeners -->
        <div class="panel">
          <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 16px;">
            <p class="panel-title" style="margin: 0;">Active Community Gardeners</p>
            <input type="search" id="search-gardeners" data-table-search="gardeners-table" placeholder="Search gardeners..." aria-label="Search active community gardeners">
          </div>
          <div class="table-wrap">
            <table class="data-table">
              <thead><tr><th>Name</th><th>Email</th><th>Location</th><th>Actions</th></tr></thead>
              <tbody id="gardeners-table"></tbody>
            </table>
          </div>
        </div>

        <!-- Coordinators -->
        <div class="panel">
          <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 16px;">
            <p class="panel-title" style="margin: 0;">Active Garden Coordinators</p>
            <input type="search" id="search-coordinators" data-table-search="coordinators-table" placeholder="Search coordinators..." aria-label="Search active garden coordinators">
          </div>
          <div class="table-wrap">
            <table class="data-table">
              <thead><tr><th>Name</th><th>Email</th><th>Shift</th><th>Location</th><th>Actions</th></tr></thead>
              <tbody id="coordinators-table"></tbody>
            </table>
          </div>
        </div>
      </div>

    </main>
  </div>
</div>

<!-- Archive Confirmation Modal -->
<div class="modal-overlay" id="delete-modal" hidden>
  <div class="modal" role="dialog" aria-modal="true">
    <h3 id="delete-modal-title">Archive this account?</h3>
    <p id="delete-modal-body">They will lose login access, but their logs and listings will remain intact.</p>
    <div class="modal-actions">
      <button type="button" class="btn btn-ghost" id="delete-cancel">Cancel</button>
      <button type="button" class="btn btn-accent" id="delete-confirm" style="background: var(--brown-600);">Archive</button>
    </div>
  </div>
</div>

<div class="toast-container" id="toast-container" aria-live="polite"></div>
<script src="assets/admin.js"></script>
</body>
</html>