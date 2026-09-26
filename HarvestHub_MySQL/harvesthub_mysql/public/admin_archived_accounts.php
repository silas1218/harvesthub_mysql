<?php
require_once __DIR__ . '/auth.php';
$user = requireRole('admin');
$navTitle = 'Archived Accounts';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HarvestHub — Archived Accounts</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=8">
</head>
<body>

<div class="app-layout">
  <?php include __DIR__ . '/admin_sidebar.php'; ?>
  <div class="main-content">
    <?php include __DIR__ . '/nav_partial.php'; ?>
    
    <main class="wrap" id="top" style="max-width: 1200px; padding-top: 32px;">
      <div class="panel">
        <p class="panel-title">Archived Accounts</p>
        <p class="text-muted" style="margin-top: -10px; margin-bottom: 20px;">These accounts are disabled and cannot log in. Unarchive them to restore access.</p>
        
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Location</th><th>Shift</th><th>Actions</th></tr></thead>
            <tbody id="archived-table">
               <tr><td colspan="6" class="text-muted">Loading archives...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>
</div>

<div class="toast-container" id="toast-container" aria-live="polite"></div>
<script src="assets/admin.js"></script>
</body>
</html>