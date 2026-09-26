<?php
// Get the current filename to highlight the active tab
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
  <div class="sidebar-brand">
    <span class="sprout">🌱</span> HarvestHub
  </div>
  
  <nav class="sidebar-nav">
    <a href="admin_dashboard.php" class="sidebar-link <?= $currentPage === 'admin_dashboard.php' ? 'active' : '' ?>">
      Dashboard
    </a>
    <a href="admin_manage_accounts.php" class="sidebar-link <?= $currentPage === 'admin_manage_accounts.php' ? 'active' : '' ?>">
      Manage Accounts
    </a>
    <a href="admin_archived_accounts.php" class="sidebar-link <?= $currentPage === 'admin_archived_accounts.php' ? 'active' : '' ?>">
      Archived Accounts
    </a>
  </nav>

  <div class="sidebar-footer">
    <a href="logout.php" class="sidebar-link" style="color: #fca5a5;">Log Out</a>
  </div>
</aside>