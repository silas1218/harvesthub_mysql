<?php
// Get the current filename (e.g., 'customer_dashboard.php')
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
  <div class="sidebar-brand">
    <span class="sprout">🌱</span> HarvestHub
  </div>

  <div class="sidebar-user">
    <?= htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
  </div>
  
  <nav class="sidebar-nav">
    <a href="customer_dashboard.php" class="sidebar-link <?= $currentPage === 'customer_dashboard.php' ? 'active' : '' ?>">
      Dashboard
    </a>
    <a href="customer_plots.php" class="sidebar-link <?= $currentPage === 'customer_plots.php' ? 'active' : '' ?>">
      My Plots & Crops
    </a>
    <a href="customer_inventory.php" class="sidebar-link <?= $currentPage === 'customer_inventory.php' ? 'active' : '' ?>">
      Resource Inventory
    </a>
    <a href="customer_exchange.php" class="sidebar-link <?= $currentPage === 'customer_exchange.php' ? 'active' : '' ?>">
      Exchange Board
    </a>
  </nav>

  <div class="sidebar-footer">
    <a href="logout.php" class="sidebar-link" style="color: #fca5a5;">Log Out</a>
  </div>
</aside>